<?php
/**
 * Page-cache purge and rollover for events (REQ-262, REQ-264). Publishing, unpublishing, trashing or editing a published event
 * purges its page, the Events list and the Home page. A recurring job purges the same URLs once an event's end (or start, when
 * there is no end) time has passed, so "Past event" shows and the registration button goes without anyone editing the event.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Events;

use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Event cache.
 */
final class EventCache {

	public const OPTION   = 'wiw_event_rollover_checked';
	public const JOB      = 'events.rollover';
	public const INTERVAL = 900;

	/**
	 * Hook the purge on status changes and edits, and the recurring rollover job.
	 */
	public static function hooks(): void {
		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 10, 3 );
		add_action( 'save_post_' . EventPostType::TYPE, array( self::class, 'on_save' ), 20, 2 );
	}

	/**
	 * Register the rollover job.
	 */
	public static function register_job(): void {
		JobRunner::for_site()->recurring(
			self::JOB,
			self::INTERVAL,
			static function (): void {
				self::rollover( current_time( 'Y-m-d H:i' ) );
			}
		);
	}

	/**
	 * A status change: purge when the event was or becomes public.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_transition( $new_status, $old_status, \WP_Post $post ): void {
		if ( EventPostType::TYPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			self::purge( array( (int) $post->ID ) );
		}
	}

	/**
	 * An edit of a published event.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public static function on_save( $post_id, \WP_Post $post ): void {
		if ( 'publish' === $post->post_status && ! wp_is_post_revision( (int) $post_id ) ) {
			self::purge( array( (int) $post_id ) );
		}
	}

	/**
	 * Purge the event pages, the Events list and Home.
	 *
	 * @param int[] $ids Event ids.
	 */
	public static function purge( array $ids ): void {
		$urls = array( home_url( '/events/' ), home_url( '/' ) );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			// Its public address, even when it has just been unpublished or trashed.
			$public              = clone $post;
			$public->post_status = 'publish';
			$link                = get_permalink( $public );
			if ( is_string( $link ) && '' !== $link ) {
				$urls[] = $link;
			}
		}
		do_action( 'wiw_purge_urls', array_values( array_unique( $urls ) ), false );
	}

	/**
	 * Purge the events whose end (or start) time passed since the last run.
	 *
	 * @param string $now Now, Y-m-d H:i in site time.
	 * @return int[] Events purged.
	 */
	public static function rollover( string $now ): array {
		$last = (string) get_option( self::OPTION, '' );
		update_option( self::OPTION, $now, false );
		if ( '' === $last ) {
			return array();
		}
		$due   = array();
		$posts = get_posts(
			array(
				'post_type'      => EventPostType::TYPE,
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $id ) {
			$id    = (int) $id;
			$end   = EventFields::value( $id, 'end' );
			$start = EventFields::value( $id, 'start' );
			$edge  = '' !== $end ? $end : $start;
			if ( '' !== $edge && strcmp( $edge, $last ) > 0 && strcmp( $edge, $now ) <= 0 ) {
				$due[] = $id;
			}
		}
		if ( array() !== $due ) {
			self::purge( $due );
		}
		return $due;
	}
}
