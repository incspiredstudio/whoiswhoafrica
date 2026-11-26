<?php
/**
 * Applies one item of a translations file (REQ-276, REQ-053, REQ-125, REQ-188, REQ-143). The profile is found by its record id
 * (profile id only when the file has none). Each field is compared with the value the file expects: equal to the new value is
 * already done, equal to the old value is applied, anything else changed since the file was made and is left alone. Everything
 * applicable goes through ProfileEditor in one edit, so it gets a version row, an audit row and a purge; each changed field then
 * gets a provenance row with method ai. The original name is kept (private) the first time, a non-Latin slug becomes the English
 * one with the old address redirecting, and a profile that now has the same name, type and main country as another is flagged as
 * a possible duplicate for staff to decide. Values never go to the logs.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Translate;

use WhoIsWho\Core\Directory\Admin\TermFields;
use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\Edit\EditRefused;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Url\ProfileSlugs;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Review\Duplicates\DuplicateRepository;

/**
 * Translation applier.
 */
final class TranslationApplier {

	public const PROVENANCE_SOURCE = 'Translation into English';
	public const DUPLICATE_BASIS   = 'same name after translation';
	public const DUPLICATE_SCORE   = 90;

	/**
	 * Outcomes of an item.
	 */
	public const APPLIED   = 'applied';
	public const PARTLY    = 'partly';
	public const DONE      = 'done';
	public const CHANGED   = 'changed';
	public const NOT_FOUND = 'not_found';
	public const REFUSED   = 'refused';

	/**
	 * Editor.
	 *
	 * @var ProfileEditor
	 */
	private ProfileEditor $editor;

	/**
	 * Constructor.
	 *
	 * @param ProfileEditor|null $editor Editor.
	 */
	public function __construct( ?ProfileEditor $editor = null ) {
		$this->editor = $editor ?? new ProfileEditor();
	}

	/**
	 * What applying an item would do, without writing anything.
	 *
	 * @param array{record_id: string, profile_id: int, original_name: string|null, original_lang: string, fields: array<string, array{old: string, new: string}>} $item Item.
	 * @return array{outcome: string, profile_id: int, apply: string[], done: string[], changed: string[], duplicates: int, old_name: string, new_name: string}
	 */
	public function preview( array $item ): array {
		$id = $this->find( $item );
		if ( null === $id ) {
			return $this->outcome( self::NOT_FOUND, 0, array(), array(), array(), $item );
		}
		$plan = $this->plan( $id, $item );
		return $this->outcome( $this->word( $plan ), $id, $plan['apply'], $plan['done'], $plan['changed'], $item );
	}

	/**
	 * Apply an item.
	 *
	 * @param array{record_id: string, profile_id: int, original_name: string|null, original_lang: string, fields: array<string, array{old: string, new: string}>} $item Item.
	 * @param int                                                                                                                                                  $actor_id  Staff member applying the file.
	 * @param string                                                                                                                                               $file_name The file's name, for the reason.
	 * @return array{outcome: string, profile_id: int, apply: string[], done: string[], changed: string[], duplicates: int, old_name: string, new_name: string}
	 */
	public function apply( array $item, int $actor_id, string $file_name ): array {
		$id = $this->find( $item );
		if ( null === $id ) {
			return $this->outcome( self::NOT_FOUND, 0, array(), array(), array(), $item );
		}
		$plan = $this->plan( $id, $item );
		if ( array() === $plan['apply'] ) {
			return $this->outcome( $this->word( $plan ), $id, array(), $plan['done'], $plan['changed'], $item );
		}
		$changes = array();
		foreach ( $plan['apply'] as $key ) {
			$changes[ $key ] = $item['fields'][ $key ]['new'];
		}
		$renamed  = isset( $changes['name'] );
		$kept_now = false;
		if ( $renamed && null !== $item['original_name'] && null === OriginalName::get( $id ) ) {
			OriginalName::set( $id, $item['original_name'], $item['original_lang'] );
			$kept_now = true;
		}
		$lang   = '' !== $item['original_lang'] ? OriginalName::language_name( $item['original_lang'] ) : 'another language';
		$reason = sprintf( 'Translated into English from %s (issue 885, file %s)', $lang, '' !== $file_name ? $file_name : 'unnamed' );
		try {
			$this->editor->edit( $id, $changes, $reason, $actor_id );
		} catch ( EditRefused $e ) {
			unset( $e );
			if ( $kept_now ) {
				OriginalName::clear( $id );
			}
			return $this->outcome( self::REFUSED, $id, array(), $plan['done'], array_merge( $plan['changed'], $plan['apply'] ), $item );
		}
		$this->provenance( $id, $plan['apply'] );
		$duplicates = 0;
		if ( $renamed ) {
			$this->english_slug( $id, $changes['name'] );
			$duplicates = $this->flag_duplicates( $id, $changes['name'] );
		}
		( new SearchIndexer() )->upsert( $id );
		$word              = array() === $plan['changed'] ? self::APPLIED : self::PARTLY;
		$out               = $this->outcome( $word, $id, $plan['apply'], $plan['done'], $plan['changed'], $item );
		$out['duplicates'] = $duplicates;
		return $out;
	}

	/**
	 * What renaming a term would do: done, applied (when $write) or changed since.
	 *
	 * @param array{taxonomy: string, term_id: int, old: string, new: string} $term     Term.
	 * @param bool                                                            $write    Whether to rename.
	 * @return string One of the outcome words.
	 */
	public function term( array $term, bool $write ): string {
		$current = get_term( $term['term_id'], $term['taxonomy'] );
		if ( ! $current instanceof \WP_Term ) {
			return self::NOT_FOUND;
		}
		$now = self::norm( $current->name );
		if ( self::norm( $term['new'] ) === $now ) {
			return self::DONE;
		}
		if ( self::norm( $term['old'] ) !== $now ) {
			return self::CHANGED;
		}
		if ( ! $write ) {
			return self::APPLIED;
		}
		$args     = array( 'name' => $term['new'] );
		$old_slug = $current->slug;
		if ( false !== strpos( $old_slug, '%' ) ) {
			$args['slug'] = sanitize_title( remove_accents( $term['new'] ) );
		}
		$result = wp_update_term( $term['term_id'], $term['taxonomy'], $args );
		if ( is_wp_error( $result ) ) {
			return self::REFUSED;
		}
		$after = get_term( $term['term_id'], $term['taxonomy'] );
		if ( $after instanceof \WP_Term && $after->slug !== $old_slug ) {
			$kept = array_map( 'strval', (array) get_term_meta( $term['term_id'], TermFields::META_OLD_SLUGS, false ) );
			if ( ! in_array( $old_slug, $kept, true ) ) {
				add_term_meta( $term['term_id'], TermFields::META_OLD_SLUGS, $old_slug );
			}
		}
		AuditLogger::for_site()->log(
			'term.translate',
			array(
				'type' => 'term',
				'id'   => $term['term_id'],
			),
			array(
				'name' => $current->name,
				'slug' => $old_slug,
			),
			array(
				'name' => $after instanceof \WP_Term ? $after->name : $term['new'],
				'slug' => $after instanceof \WP_Term ? $after->slug : $old_slug,
			),
			'Translated into English (issue 885)'
		);
		return self::APPLIED;
	}

	/**
	 * The profile an item names: by record id, else by profile id.
	 *
	 * @param array{record_id: string, profile_id: int} $item Item.
	 */
	private function find( array $item ): ?int {
		$id = '' !== $item['record_id'] ? ( new RecordMap() )->profile_for( $item['record_id'] ) : ( $item['profile_id'] > 0 ? $item['profile_id'] : null );
		if ( null === $id || ProfileType::POST_TYPE !== get_post_type( $id ) || null === ( new StateRepository() )->get( $id ) ) {
			return null;
		}
		return (int) $id;
	}

	/**
	 * Sort an item's fields into apply, done and changed since.
	 *
	 * @param int                                                           $id   Profile.
	 * @param array{fields: array<string, array{old: string, new: string}>} $item Item.
	 * @return array{apply: string[], done: string[], changed: string[]}
	 */
	private function plan( int $id, array $item ): array {
		$type  = (string) ( ( new StateRepository() )->get( $id )['entity_type'] ?? '' );
		$allow = new FieldAllowlist();
		$plan  = array(
			'apply'   => array(),
			'done'    => array(),
			'changed' => array(),
		);
		foreach ( $item['fields'] as $key => $change ) {
			$key = (string) $key;
			if ( ! $allow->is_allowed( $type, $key ) ) {
				$plan['changed'][] = $key;
				continue;
			}
			$now = self::norm( $this->raw( $id, $key ) );
			if ( self::norm( $change['new'] ) === $now ) {
				$plan['done'][] = $key;
			} elseif ( self::norm( $change['old'] ) === $now ) {
				$plan['apply'][] = $key;
			} else {
				$plan['changed'][] = $key;
			}
		}
		return $plan;
	}

	/**
	 * A field's stored value: the raw post title for the name, the meta value otherwise.
	 *
	 * @param int    $id  Profile.
	 * @param string $key Field.
	 */
	private function raw( int $id, string $key ): string {
		if ( 'name' === $key ) {
			return (string) get_post_field( 'post_title', $id, 'raw' );
		}
		return (string) get_post_meta( $id, (string) FieldAllowlist::meta_key( $key ), true );
	}

	/**
	 * A value for comparison: entities decoded and outer space trimmed.
	 *
	 * @param string $value Value.
	 */
	public static function norm( string $value ): string {
		return trim( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * The outcome word for a plan that applies nothing (or would apply something, in a preview).
	 *
	 * @param array{apply: string[], done: string[], changed: string[]} $plan Plan.
	 */
	private function word( array $plan ): string {
		if ( array() !== $plan['apply'] ) {
			return array() === $plan['changed'] ? self::APPLIED : self::PARTLY;
		}
		return array() === $plan['changed'] ? self::DONE : self::CHANGED;
	}

	/**
	 * Mark each translated field as AI-sourced. Later provenance rows win, so this one replaces the editor's staff-edit row.
	 *
	 * @param int      $id     Profile.
	 * @param string[] $fields Fields changed.
	 */
	private function provenance( int $id, array $fields ): void {
		$prov = new ProvenanceRepository();
		$now  = gmdate( 'Y-m-d H:i:s' );
		foreach ( $fields as $key ) {
			$prov->record(
				$id,
				array(
					'field'        => $key,
					'source_url'   => self::PROVENANCE_SOURCE,
					'retrieved_at' => $now,
					'confidence'   => 'medium',
					'method'       => 'ai',
				)
			);
		}
	}

	/**
	 * Replace a non-Latin (percent-encoded) slug with one made from the English name. ProfileSlugs keeps the old slug, so the old
	 * address answers 301 to the new one.
	 *
	 * @param int    $id   Profile.
	 * @param string $name English name.
	 */
	private function english_slug( int $id, string $name ): void {
		$old = (string) get_post_field( 'post_name', $id );
		if ( false === strpos( $old, '%' ) ) {
			return;
		}
		$slugs = new ProfileSlugs();
		$new   = $slugs->change( $id, $name );
		if ( $new === $old ) {
			return;
		}
		$known = array_map( 'strval', (array) get_post_meta( $id, ProfileSlugs::OLD_SLUG_META, false ) );
		if ( ! in_array( $old, $known, true ) ) {
			add_post_meta( $id, ProfileSlugs::OLD_SLUG_META, $old );
		}
		AuditLogger::for_site()->log(
			'profile.slug_change',
			array(
				'type' => 'profile',
				'id'   => $id,
			),
			$old,
			$new,
			'Translated into English (issue 885)'
		);
	}

	/**
	 * Flag every other profile with the same name (case and entities ignored), entity type and main country as a possible
	 * duplicate, unless the pair is already on file.
	 *
	 * @param int    $id   Profile.
	 * @param string $name English name.
	 * @return int Pairs newly flagged.
	 */
	private function flag_duplicates( int $id, string $name ): int {
		global $wpdb;
		$state   = ( new StateRepository() )->get( $id );
		$type    = (string) ( $state['entity_type'] ?? '' );
		$country = (string) get_post_meta( $id, Classification::PRIMARY_COUNTRY_META, true );
		$plain   = self::norm( $name );
		$forms   = array_values( array_unique( array( strtolower( $plain ), strtolower( esc_html( $plain ) ), strtolower( $name ) ) ) );
		$holders = implode( ', ', array_fill( 0, count( $forms ), '%s' ) );
		$others  = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- the replacements are one array.
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID <> %d AND post_status NOT IN ('trash', 'auto-draft') AND LOWER(post_title) IN ($holders)", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
				array_merge( array( ProfileType::POST_TYPE, $id ), $forms )
			)
		);
		$pairs   = new DuplicateRepository();
		$flagged = 0;
		foreach ( (array) $others as $other ) {
			$other = (int) $other;
			$them  = ( new StateRepository() )->get( $other );
			if ( null === $them || (string) $them['entity_type'] !== $type || (string) get_post_meta( $other, Classification::PRIMARY_COUNTRY_META, true ) !== $country ) {
				continue;
			}
			if ( null !== $pairs->get( $id, $other ) ) {
				continue;
			}
			if ( $pairs->flag( $id, $other, self::DUPLICATE_SCORE, self::DUPLICATE_BASIS ) ) {
				++$flagged;
			}
		}
		return $flagged;
	}

	/**
	 * An outcome row.
	 *
	 * @param string                                                        $word    Outcome.
	 * @param int                                                           $id      Profile, or 0.
	 * @param string[]                                                      $apply   Fields applied (or to apply).
	 * @param string[]                                                      $done    Fields already done.
	 * @param string[]                                                      $changed Fields changed since.
	 * @param array{fields: array<string, array{old: string, new: string}>} $item    Item.
	 * @return array{outcome: string, profile_id: int, apply: string[], done: string[], changed: string[], duplicates: int, old_name: string, new_name: string}
	 */
	private function outcome( string $word, int $id, array $apply, array $done, array $changed, array $item ): array {
		return array(
			'outcome'    => $word,
			'profile_id' => $id,
			'apply'      => $apply,
			'done'       => $done,
			'changed'    => $changed,
			'duplicates' => 0,
			'old_name'   => isset( $item['fields']['name'] ) ? self::norm( $item['fields']['name']['old'] ) : ( $id > 0 ? self::norm( (string) get_post_field( 'post_title', $id, 'raw' ) ) : '' ),
			'new_name'   => isset( $item['fields']['name'] ) ? self::norm( $item['fields']['name']['new'] ) : '',
		);
	}
}
