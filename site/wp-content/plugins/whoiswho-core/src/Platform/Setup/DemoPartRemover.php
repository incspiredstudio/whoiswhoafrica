<?php
/**
 * Removes one part of the demo content (REQ-208, REQ-056): only the items marked as demo that belong to the part, a few at a time
 * so a shared host's time limit is never reached. An item a person changed after the import is kept, the demo marker is taken
 * off it so it counts as the site's own content from then on, and it is reported back. A person's change is: different text, a
 * later modification time, an edit in the WordPress editor, a profile edit or revision, or a reader's own comment on a demo
 * story. Settings the demo changed are put back only when nobody changed them since.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Content\Comments\CommentPolicy;
use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Platform\Cli\Sample\SampleMarker;
use WhoIsWho\Core\Platform\Settings\SettingsService;

/**
 * Demo part remover.
 */
final class DemoPartRemover {

	/**
	 * The shipped sample profiles (their import batch is forgotten with the Profiles part).
	 *
	 * @var SampleBundle
	 */
	private SampleBundle $bundle;

	/**
	 * Constructor.
	 *
	 * @param SampleBundle|null $bundle Shipped sample profiles.
	 */
	public function __construct( ?SampleBundle $bundle = null ) {
		$this->bundle = $bundle ?? new SampleBundle();
	}

	/**
	 * Remove a part's demo items until the time is up (at least one item per call, so every call makes progress).
	 *
	 * @param string     $part  One of DemoParts::PARTS.
	 * @param float|null $until A microtime(true) deadline, or null for no limit.
	 * @return array{done: bool, left: int, removed: int, kept: array<int, array{title: string, url: string}>}
	 * @throws \InvalidArgumentException For an unknown part.
	 */
	public function remove( string $part, ?float $until ): array {
		switch ( $part ) {
			case 'comments':
				return $this->comments( $until );
			case 'awards':
				return $this->awards();
			case 'curation':
				return $this->curation();
			case 'settings':
				return $this->settings();
			case 'profiles':
			case 'stories':
			case 'events':
			case 'pages':
				return $this->posts( $part, $until );
		}
		throw new \InvalidArgumentException( 'Unknown demo part.' );
	}

	/**
	 * A result.
	 *
	 * @param bool                                          $done    Whether the part is gone.
	 * @param int                                           $left    Items left.
	 * @param int                                           $removed Items removed in this call.
	 * @param array<int, array{title: string, url: string}> $kept    Items kept in this call.
	 * @return array{done: bool, left: int, removed: int, kept: array<int, array{title: string, url: string}>}
	 */
	private static function result( bool $done, int $left, int $removed, array $kept ): array {
		return array(
			'done'    => $done,
			'left'    => $left,
			'removed' => $removed,
			'kept'    => $kept,
		);
	}

	/**
	 * The demo posts of a part: the posts first, then their pictures.
	 *
	 * @param string     $part  Part.
	 * @param float|null $until Deadline.
	 * @return array{done: bool, left: int, removed: int, kept: array<int, array{title: string, url: string}>}
	 */
	private function posts( string $part, ?float $until ): array {
		$removed = 0;
		$kept    = array();
		foreach ( DemoParts::post_keys( $part ) as $id => $key ) {
			if ( str_starts_with( $key, 'image:' ) ) {
				if ( self::key_exists( substr( $key, 6 ) ) ) {
					// The post that uses this picture was kept, so the picture stays with it.
					SampleMarker::unmark_post( $id );
				} elseif ( false !== wp_delete_post( $id, true ) ) {
					++$removed;
				}
			} elseif ( $this->edited( $id ) ) {
				$kept[] = array(
					'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
					'url'   => (string) get_edit_post_link( $id, 'raw' ),
				);
				SampleMarker::unmark_post( $id );
			} else {
				$this->delete_post( $id );
				++$removed;
			}
			if ( null !== $until && microtime( true ) >= $until ) {
				break;
			}
		}//end foreach
		$left = count( DemoParts::post_keys( $part ) );
		if ( 0 === $left ) {
			$this->finish_posts( $part );
		}
		return self::result( 0 === $left, $left, $removed, $kept );
	}

	/**
	 * Delete one demo post, and a profile's own rows.
	 *
	 * @param int $id Post.
	 */
	private function delete_post( int $id ): void {
		if ( ProfileType::POST_TYPE === get_post_type( $id ) ) {
			global $wpdb;
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- clean-up of a demo profile's own row.
			( new RecordMap() )->unlink_profile( $id );
		}
		wp_delete_post( $id, true );
	}

	/**
	 * After the last post of a part: forget the demo import batch, or put the front page back.
	 *
	 * @param string $part Part.
	 */
	private function finish_posts( string $part ): void {
		if ( 'profiles' === $part ) {
			$this->bundle->forget();
			return;
		}
		if ( 'pages' !== $part ) {
			return;
		}
		$registry = SampleMarker::registry();
		$prev     = (array) ( $registry['prev'] ?? array() );
		$front    = (int) get_option( 'page_on_front', 0 );
		if ( 0 === $front || ! get_post( $front ) instanceof \WP_Post ) {
			// The demo Home page is gone, so the front page setting goes back to what it was.
			foreach ( array( 'show_on_front', 'page_on_front' ) as $option ) {
				if ( array_key_exists( 'option:' . $option, $prev ) ) {
					null === $prev[ 'option:' . $option ] ? delete_option( $option ) : update_option( $option, $prev[ 'option:' . $option ] );
				}
			}
		}
		$privacy = (int) get_option( 'wp_page_for_privacy_policy', 0 );
		if ( array_key_exists( 'option:wp_page_for_privacy_policy', $prev ) && ( 0 === $privacy || ! get_post( $privacy ) instanceof \WP_Post ) ) {
			// The demo privacy page is gone, so WordPress's privacy policy page setting goes back to what it was.
			null === $prev['option:wp_page_for_privacy_policy'] ? delete_option( 'wp_page_for_privacy_policy' ) : update_option( 'wp_page_for_privacy_policy', $prev['option:wp_page_for_privacy_policy'] );
		}
		unset( $registry['prev']['option:show_on_front'], $registry['prev']['option:page_on_front'], $registry['prev']['option:wp_page_for_privacy_policy'] );
		SampleMarker::save_registry( $registry );
	}

	/**
	 * Whether a post with this demo key still exists.
	 *
	 * @param string $key Key.
	 */
	private static function key_exists( string $key ): bool {
		global $wpdb;
		return null !== $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", SampleMarker::KEY_META, $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a short read of the demo markers.
	}

	/**
	 * Whether a person changed a demo post after the import.
	 *
	 * @param int $id Post.
	 */
	public function edited( int $id ): bool {
		clean_post_cache( $id );
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		$hash  = (string) get_post_meta( $id, SampleMarker::HASH_META, true );
		$stamp = (string) get_post_meta( $id, SampleMarker::STAMP_META, true );
		if ( ( '' !== $hash && SampleMarker::fingerprint( $post ) !== $hash ) || ( '' !== $stamp && strcmp( $post->post_modified_gmt, $stamp ) > 0 ) ) {
			return true;
		}
		if ( '' !== (string) get_post_meta( $id, '_edit_last', true ) ) {
			return true;
		}
		if ( ProfileType::POST_TYPE === $post->post_type ) {
			foreach ( ( new VersionRepository() )->for_profile( $id ) as $version ) {
				if ( 'import' !== (string) $version['created_via'] ) {
					return true;
				}
			}
		}
		if ( CommentPolicy::STORY_TYPE === $post->post_type ) {
			$all  = (int) get_comments(
				array(
					'post_id' => $id,
					'status'  => 'all',
					'count'   => true,
				)
			);
			$demo = (int) get_comments(
				array(
					'post_id'    => $id,
					'status'     => 'all',
					'count'      => true,
					'meta_key'   => SampleMarker::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a one-off setup check.
					'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
				)
			);
			if ( $all > $demo ) {
				// A reader's own comment is on it: deleting the story would delete their comment too.
				return true;
			}
		}//end if
		return false;
	}

	/**
	 * The demo comments.
	 *
	 * @param float|null $until Deadline.
	 * @return array{done: bool, left: int, removed: int, kept: array<int, array{title: string, url: string}>}
	 */
	private function comments( ?float $until ): array {
		$removed = 0;
		$kept    = array();
		foreach ( array_keys( DemoParts::comment_keys() ) as $id ) {
			$comment = get_comment( $id );
			if ( $comment instanceof \WP_Comment && (string) get_comment_meta( $id, SampleMarker::HASH_META, true ) !== SampleMarker::comment_fingerprint( $comment ) ) {
				$kept[] = array(
					/* translators: %s: commenter name. */
					'title' => sprintf( __( 'Comment by %s', 'whoiswho' ), $comment->comment_author ),
					'url'   => admin_url( 'comment.php?action=editcomment&c=' . $id ),
				);
				SampleMarker::unmark_comment( $id );
			} elseif ( wp_delete_comment( $id, true ) ) {
				++$removed;
			}
			if ( null !== $until && microtime( true ) >= $until ) {
				break;
			}
		}
		$left = count( DemoParts::comment_keys() );
		return self::result( 0 === $left, $left, $removed, $kept );
	}

	/**
	 * The demo award nominations, and the demo categories nobody else's nomination uses.
	 *
	 * @return array{done: bool, left: int, removed: int, kept: array<int, array{title: string, url: string}>}
	 */
	private function awards(): array {
		global $wpdb;
		$registry = SampleMarker::registry();
		$removed  = 0;
		$kept     = array();
		foreach ( array_filter( array_map( 'intval', array_values( (array) ( $registry['nominations'] ?? array() ) ) ) ) as $id ) {
			$wpdb->delete( AwardsTables::table( AwardsTables::CHANGES ), array( 'nomination_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- clean-up of demo rows by id.
			if ( $wpdb->delete( AwardsTables::table( AwardsTables::NOMINATIONS ), array( 'id' => $id ), array( '%d' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- as above.
				++$removed;
			}
		}
		foreach ( (array) ( $registry['categories'] ?? array() ) as $title => $id ) {
			$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE category_id = %d', (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name; values prepared.
			if ( $used > 0 ) {
				$kept[] = array(
					'title' => sprintf(
						/* translators: %s: award category title. */
						__( 'Award category "%s" (it has nominations someone added)', 'whoiswho' ),
						(string) $title
					),
					'url'   => '',
				);
				continue;
			}
			if ( $wpdb->delete( AwardsTables::table( AwardsTables::CATEGORIES ), array( 'id' => (int) $id ), array( '%d' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- clean-up of demo rows by id.
				++$removed;
			}
		}
		unset( $registry['nominations'], $registry['categories'] );
		SampleMarker::save_registry( $registry );
		return self::result( true, 0, $removed, $kept );
	}

	/**
	 * The home page choices: put back what was there, unless a person changed them since.
	 *
	 * @return array{done: bool, left: int, removed: int, kept: array<int, array{title: string, url: string}>}
	 */
	private function curation(): array {
		$registry = SampleMarker::registry();
		$prev     = (array) ( $registry['prev'] ?? array() );
		$set      = (array) ( $registry['set'] ?? array() );
		$removed  = 0;
		$kept     = array();
		foreach ( CurationSettings::BLOCKS as $block ) {
			$key = 'curation:' . $block;
			if ( ! array_key_exists( $key, $prev ) ) {
				continue;
			}
			$name = CurationSettings::OPTION_PREFIX . $block;
			if ( array_key_exists( $key, $set ) && get_option( $name, null ) !== $set[ $key ] ) {
				$kept[] = array(
					'title' => __( 'Home page curation (changed since the demo set it)', 'whoiswho' ),
					'url'   => '',
				);
			} else {
				null === $prev[ $key ] ? delete_option( $name ) : update_option( $name, $prev[ $key ], false );
				++$removed;
			}
			unset( $registry['prev'][ $key ], $registry['set'][ $key ] );
		}
		SampleMarker::save_registry( $registry );
		return self::result( true, 0, $removed, array_slice( $kept, 0, 1 ) );
	}

	/**
	 * The Nominations switch: put back what it was, unless a person changed it since.
	 *
	 * @return array{done: bool, left: int, removed: int, kept: array<int, array{title: string, url: string}>}
	 */
	private function settings(): array {
		$registry = SampleMarker::registry();
		$prev     = (array) ( $registry['prev'] ?? array() );
		$removed  = 0;
		if ( array_key_exists( 'nominations', $prev ) ) {
			$settings = SettingsService::for_site();
			// The demo switched it on; when it is still on, it goes back to the earlier value.
			if ( $settings->nominations_enabled() && ! (bool) $prev['nominations'] ) {
				$settings->set( SettingsService::NOMINATIONS_ENABLED, false, 'Demo content removed' );
				++$removed;
			}
			unset( $registry['prev']['nominations'] );
			SampleMarker::save_registry( $registry );
		}
		return self::result( true, 0, $removed, array() );
	}
}
