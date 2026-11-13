<?php
/**
 * Audit and purge for events (REQ-262, REQ-244, REQ-051). Every create, schedule, publish, unpublish and trash of an event goes
 * through AuditLogger, with the title length-free subject being the post id only. Saving a published event, or moving one into or
 * out of the published state, announces its public page, the Events list and the Home page on wiw_purge_urls so the cache drops
 * them at once. An unpublished event is no longer public: WordPress answers 404 and leaves it out of the sitemap.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Events\Admin;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Event lifecycle hooks.
 */
final class EventLifecycle {

	/**
	 * Hook registration.
	 */
	public static function hooks(): void {
		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 10, 3 );
		add_action( 'save_post_' . EventPostType::TYPE, array( self::class, 'on_save' ), 20, 2 );
	}

	/**
	 * The audit action for a status change, or null when none is logged.
	 *
	 * @param string $after Status after.
	 * @param string $before Status before.
	 */
	public static function action_for( string $after, string $before ): ?string {
		if ( $after === $before || 'inherit' === $after ) {
			return null;
		}
		if ( 'trash' === $after ) {
			return 'event.trash';
		}
		if ( 'publish' === $after ) {
			return 'event.publish';
		}
		if ( 'future' === $after ) {
			return 'event.schedule';
		}
		if ( 'publish' === $before || 'future' === $before ) {
			return 'event.unpublish';
		}
		return null;
	}

	/**
	 * Audit a status change of an event; a first save also writes event.create.
	 *
	 * @param string   $after  Status after.
	 * @param string   $before  Status before.
	 * @param \WP_Post $post Event.
	 */
	public static function on_transition( string $after, string $before, \WP_Post $post ): void {
		if ( EventPostType::TYPE !== $post->post_type || 'auto-draft' === $after ) {
			return;
		}
		$subject = array(
			'type' => 'event',
			'id'   => $post->ID,
		);
		if ( 'auto-draft' === $before || 'new' === $before ) {
			AuditLogger::for_site()->log( 'event.create', $subject, null, array( 'status' => $after ) );
		}
		$action = self::action_for( $after, $before );
		if ( null !== $action && ! ( 'event.unpublish' === $action && ( 'auto-draft' === $before || 'new' === $before ) ) ) {
			AuditLogger::for_site()->log( $action, $subject, array( 'status' => $before ), array( 'status' => $after ) );
		}
		if ( 'publish' === $after || 'publish' === $before ) {
			self::purge( $post );
		}
	}

	/**
	 * Purge after a save of a published event.
	 *
	 * @param int      $post_id Event id.
	 * @param \WP_Post $post    Event.
	 */
	public static function on_save( int $post_id, \WP_Post $post ): void {
		unset( $post_id );
		if ( 'publish' === $post->post_status ) {
			self::purge( $post );
		}
	}

	/**
	 * Announce the event page, the Events list and the Home page for purging.
	 *
	 * @param \WP_Post $post Event.
	 */
	public static function purge( \WP_Post $post ): void {
		$urls = array( home_url( '/events/' ), home_url( '/' ) );
		$link = get_permalink( $post );
		if ( is_string( $link ) && '' !== $link ) {
			array_unshift( $urls, $link );
		}
		/** This action is documented in Directory\Purge\PurgeQueue::run(). */
		do_action( 'wiw_purge_urls', $urls, false );
	}
}
