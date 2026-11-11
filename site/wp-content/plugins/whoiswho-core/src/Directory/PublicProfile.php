<?php
/**
 * The public read model of a profile (REQ-208, REQ-139, REQ-144, REQ-060, REQ-247, REQ-216, REQ-006): the only way a template or
 * form reads a profile. It returns an object only when the state allows it (read from the database on every call), exposes only
 * allowlisted fields that carry a source and retrieval date, and produces the pending label, the noindex flag and the share
 * preview, so a template cannot forget them. It has no email, phone, cover or banner property: none exists to leak.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Url\ProfileSlugs;
use WhoIsWho\Core\Review\StatePolicy;

/**
 * Public profile.
 */
final class PublicProfile {

	/**
	 * Post meta holding the record id the importer assigned (links a person to an organisation).
	 */
	public const RECORD_ID_META = '_wiw_record_id';

	/**
	 * Post meta holding the attachment id of the portrait or logo.
	 */
	public const IMAGE_META = '_wiw_image_id';

	/**
	 * Profile post id.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Slug.
	 *
	 * @var string
	 */
	public string $slug;

	/**
	 * Display name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Entity type.
	 *
	 * @var string
	 */
	public string $entity_type;

	/**
	 * Publication state.
	 *
	 * @var string
	 */
	public string $state;

	/**
	 * Address of the profile page.
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * Facts: field key => array{value: mixed, source_url: string, retrieved_at: string, confidence: string}. Only facts with a source.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $facts = array();

	/**
	 * Send noindex (header and meta).
	 *
	 * @var bool
	 */
	public bool $noindex;

	/**
	 * The pending-review label, or null when none applies.
	 *
	 * @var string|null
	 */
	public ?string $label;

	/**
	 * When the profile was reviewed (UTC, "Y-m-d H:i:s"), for staff and the "Reviewed on" decision in the theme.
	 *
	 * @var string|null
	 */
	public ?string $reviewed_at = null;

	/**
	 * Whether the profile is claimed: used only to hide the "Claim this profile" button, never to show a badge.
	 *
	 * @var bool
	 */
	public bool $claimed = false;

	/**
	 * Related organisation, only when that organisation is itself public: array{name: string, url: string}.
	 *
	 * @var array<string, string>|null
	 */
	public ?array $organisation = null;

	/**
	 * Attachment id of the portrait or logo; null when there is none. There is no cover or banner image on a profile.
	 *
	 * @var int|null
	 */
	public ?int $image_id = null;

	/**
	 * The policy for the state (status, lists, search, sitemap, schema, share card).
	 *
	 * @var StatePolicy
	 */
	public StatePolicy $policy;

	/**
	 * A note for staff previewing a profile that is not public; null for a public caller.
	 *
	 * @var string|null
	 */
	public ?string $visibility_note = null;

	/**
	 * Share preview data: name, title, organisation, image_id, description, label, url.
	 *
	 * @var array<string, mixed>
	 */
	public array $share = array();

	/**
	 * Use find() or find_by_id().
	 *
	 * @param int         $id     Id.
	 * @param string      $slug   Slug.
	 * @param string      $name   Name.
	 * @param string      $type   Entity type.
	 * @param string      $state  State.
	 * @param StatePolicy $policy Policy.
	 */
	private function __construct( int $id, string $slug, string $name, string $type, string $state, StatePolicy $policy ) {
		$this->id          = $id;
		$this->slug        = $slug;
		$this->name        = $name;
		$this->entity_type = $type;
		$this->state       = $state;
		$this->policy      = $policy;
		$this->noindex     = $policy->noindex;
		$this->label       = $policy->label ? self::pending_label() : null;
		$this->url         = ProfileSlugs::url_for_slug( $slug );
	}

	/**
	 * The pending-review label text: one configuration value (REQ-216).
	 */
	public static function pending_label(): string {
		$label = apply_filters( 'wiw_pending_label', StatePolicy::PENDING_LABEL );
		return is_string( $label ) && '' !== $label ? $label : StatePolicy::PENDING_LABEL;
	}

	/**
	 * Find a public profile by its current slug. Null unless the profile is in a public state (or staff preview).
	 *
	 * @param string $slug          Slug.
	 * @param bool   $staff_preview Staff may see a profile in any state, with a visibility note.
	 */
	public static function find( string $slug, bool $staff_preview = false ): ?self {
		$id = ( new ProfileSlugs() )->find_current( sanitize_title( $slug ) );
		return null === $id ? null : self::find_by_id( $id, $staff_preview );
	}

	/**
	 * Find by profile id.
	 *
	 * @param int  $id            Profile post id.
	 * @param bool $staff_preview Staff preview.
	 */
	public static function find_by_id( int $id, bool $staff_preview = false ): ?self {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ProfileType::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$row = ( new StateRepository() )->get( $id );
		if ( null === $row ) {
			return null;
		}
		$state  = (string) $row['pub_state'];
		$policy = StatePolicy::for_state( $state );
		$public = $policy->is_visible() && 'publish' === $post->post_status;
		$staff  = $staff_preview && current_user_can( 'read_private_wiw_profiles' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
		if ( ! $public && ! $staff ) {
			return null;
		}
		$profile                  = new self( $id, $post->post_name, $post->post_title, (string) $row['entity_type'], $state, $policy );
		$profile->claimed         = (bool) (int) $row['claimed'];
		$profile->reviewed_at     = PubState::PUBLISHED_REVIEWED === $state && ! empty( $row['reviewed_at'] ) ? (string) $row['reviewed_at'] : null;
		$profile->visibility_note = $public ? null : 'Not public: ' . $state . '. Visitors see HTTP ' . $policy->status . '.';
		$profile->facts           = self::facts( $id, (string) $row['entity_type'], $state );
		$profile->organisation    = self::organisation( $profile );
		$image                    = (int) get_post_meta( $id, self::IMAGE_META, true );
		$profile->image_id        = $image > 0 ? $image : null;
		$profile->share           = self::share( $profile );
		return $profile;
	}

	/**
	 * Reviewed public profiles as id => name, for pickers (stories link only to reviewed profiles).
	 *
	 * @param int $limit Maximum entries.
	 * @return array<int, string>
	 */
	public static function public_choices( int $limit = 500 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID, p.post_title FROM {$wpdb->posts} p INNER JOIN " . StateRepository::table() . ' s ON s.profile_id = p.ID WHERE p.post_type = %s AND p.post_status = %s AND s.pub_state = %s ORDER BY p.post_title ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
				ProfileType::POST_TYPE,
				'publish',
				PubState::PUBLISHED_REVIEWED,
				max( 1, $limit )
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['ID'] ] = (string) $row['post_title'];
		}
		return $out;
	}

	/**
	 * Allowlisted facts that carry a source. A fact with no provenance row, or only low-confidence ones, is left out.
	 *
	 * @param int    $id    Profile.
	 * @param string $type  Entity type.
	 * @param string $state State (the AI summary shows only once reviewed).
	 * @return array<string, array<string, mixed>>
	 */
	private static function facts( int $id, string $type, string $state ): array {
		$allow = new FieldAllowlist();
		$prov  = array();
		foreach ( ( new ProvenanceRepository() )->for_profile( $id ) as $entry ) {
			if ( 'low' !== $entry['confidence'] ) {
				$prov[ (string) $entry['field'] ] = $entry;
				// Later rows win.
			}
		}
		$facts = array();
		foreach ( array_keys( $allow->for_type( $type ) ) as $key ) {
			$meta_key = FieldAllowlist::meta_key( $key );
			if ( null === $meta_key || ! isset( $prov[ $key ] ) || ( 'summary' === $key && PubState::PUBLISHED_REVIEWED !== $state ) ) {
				continue;
			}
			$raw = get_post_meta( $id, $meta_key, true );
			if ( '' === $raw || ! is_string( $raw ) ) {
				continue;
			}
			$value = $raw;
			if ( 'list' === ( $allow->for_type( $type )[ $key ]['type'] ?? '' ) ) {
				$decoded = json_decode( $raw, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- decoding our own stored JSON.
				$value   = is_array( $decoded ) ? array_values( array_filter( $decoded, 'is_string' ) ) : array();
			}
			$facts[ $key ] = array(
				'value'        => $value,
				'source_url'   => (string) $prov[ $key ]['source_url'],
				'retrieved_at' => (string) $prov[ $key ]['retrieved_at'],
				'confidence'   => (string) $prov[ $key ]['confidence'],
			);
		}//end foreach
		return $facts;
	}

	/**
	 * The related organisation of a person, resolved now and returned only if that organisation is public.
	 *
	 * @param self $profile The person.
	 * @return array<string, string>|null
	 */
	private static function organisation( self $profile ): ?array {
		global $wpdb;
		$ref = get_post_meta( $profile->id, FieldAllowlist::META_PREFIX . 'current_organisation_ref', true );
		if ( ! is_string( $ref ) || '' === $ref ) {
			return null;
		}
		$org_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", self::RECORD_ID_META, $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( null === $org_id ) {
			return null;
		}
		$org = self::find_by_id( (int) $org_id );
		return null === $org ? null : array(
			'name' => $org->name,
			'url'  => $org->url,
		);
	}

	/**
	 * Share preview data.
	 *
	 * @param self $profile Profile.
	 * @return array<string, mixed>
	 */
	private static function share( self $profile ): array {
		$title = $profile->facts['current_title']['value'] ?? ( $profile->facts['org_type']['value'] ?? '' );
		$org   = $profile->facts['current_organisation']['value'] ?? '';
		return array(
			'name'         => $profile->name,
			'title'        => is_string( $title ) ? $title : '',
			'organisation' => is_string( $org ) ? $org : '',
			'image_id'     => $profile->image_id,
			'label'        => $profile->label,
			'url'          => $profile->url,
			'enabled'      => $profile->policy->share_card,
		);
	}
}
