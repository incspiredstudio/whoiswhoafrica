<?php
/**
 * The choke point for post status writes on guarded post types (REQ-210, REQ-208). Every route that saves a post (wp-admin,
 * quick edit, bulk edit, REST, XML-RPC, imports, other plugins) ends in wp_insert_post_data, so one filter covers them all. A
 * change of status that does not run inside StateChangeToken::run() is undone and logged: through the audit log when the core
 * plugin is active, always through the wiw_guard_status_refused action, which carries ids only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Status;

use WhoIsWho\Guard\Guard;

/**
 * Status write guard.
 */
final class StatusWriteGuard {

	/**
	 * Statuses WordPress sets itself that never publish anything.
	 */
	private const PASS = array( 'auto-draft', 'trash', 'inherit' );

	/**
	 * Attach the filter. It runs after the core plugin's own profile guard (priority 99) so that one keeps its richer message.
	 */
	public static function init(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'filter' ), 100, 2 );
	}

	/**
	 * Undo an unauthorised status change.
	 *
	 * @param array<string, mixed> $data    Slashed post data.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public static function filter( array $data, array $postarr ): array {
		$type = (string) ( $data['post_type'] ?? '' );
		if ( ! in_array( $type, Guard::guarded_post_types(), true ) || StateChangeToken::is_active() ) {
			return $data;
		}
		$attempted = (string) ( $data['post_status'] ?? '' );
		if ( in_array( $attempted, self::PASS, true ) ) {
			return $data;
		}
		$id  = (int) ( $postarr['ID'] ?? 0 );
		$old = $id > 0 ? (string) get_post_status( $id ) : '';
		if ( '' === $old || in_array( $old, self::PASS, true ) ) {
			$old = Guard::STATUS_HIDDEN;
		}
		if ( $attempted === $old ) {
			return $data;
		}
		$data['post_status'] = $old;
		self::log( $type, $id, $old, $attempted );
		return $data;
	}

	/**
	 * Record a refusal. Ids and status names only.
	 *
	 * @param string $type      Post type.
	 * @param int    $id        Post id (0 for a new post).
	 * @param string $kept      Status that stays.
	 * @param string $attempted Status that was refused.
	 */
	private static function log( string $type, int $id, string $kept, string $attempted ): void {
		$actor = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		/**
		 * Fires when a status write outside the state machine is refused. Carries ids and status names, never content.
		 *
		 * @param string $type      Post type.
		 * @param int    $id        Post id.
		 * @param string $kept      Status that stays.
		 * @param string $attempted Status that was refused.
		 * @param int    $actor     Acting user id, 0 when none.
		 */
		do_action( 'wiw_guard_status_refused', $type, $id, $kept, $attempted, $actor );

		$logger = '\WhoIsWho\Core\Platform\Audit\AuditLogger';
		if ( class_exists( $logger ) ) {
			global $wpdb;
			// A missing or busy audit table must never print an error into a public response.
			$previous = $wpdb->suppress_errors( true );
			try {
				$audit = call_user_func( array( $logger, 'for_site' ) );
				$audit->log(
					'guard.status_write_refused',
					array(
						'type' => $type,
						'id'   => $id,
					),
					array( 'post_status' => $kept ),
					array( 'post_status' => $attempted ),
					'Status written outside the state machine'
				);
			} catch ( \Throwable $e ) {
				unset( $e );
				// The refusal stands even when the log is busy.
			} finally {
				$wpdb->suppress_errors( $previous );
			}
		}//end if
	}
}
