<?php
/**
 * Refuses any write to a profile's post status made outside the StateMachine (REQ-210): quick edit, the editor, REST, a plugin.
 * The status is put back to what the state row says (wiw_hidden when the profile has no state yet) and the refusal is logged.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Post status guard.
 */
final class StatusGuard {

	/**
	 * Statuses WordPress sets itself that never make a profile public.
	 */
	private const PASS = array( 'auto-draft', 'trash', 'inherit' );

	/**
	 * Hook the filter.
	 */
	public function register(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'filter' ), 99, 2 );
	}

	/**
	 * Filter for wp_insert_post_data.
	 *
	 * @param array<string, mixed> $data    Slashed post data.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public function filter( array $data, array $postarr ): array {
		if ( ProfileType::POST_TYPE !== ( $data['post_type'] ?? '' ) || StateMachine::is_writing() ) {
			return $data;
		}
		$attempted = (string) ( $data['post_status'] ?? '' );
		if ( in_array( $attempted, self::PASS, true ) ) {
			return $data;
		}
		$id      = (int) ( $postarr['ID'] ?? 0 );
		$state   = $id > 0 ? ( new StateRepository() )->pub_state( $id ) : null;
		$allowed = null === $state ? ProfileType::STATUS_HIDDEN : StateMachine::post_status_for( $state );
		if ( $attempted === $allowed ) {
			return $data;
		}
		$data['post_status'] = $allowed;
		try {
			AuditLogger::for_site()->log(
				'profile.status_write_refused',
				array(
					'type' => 'profile',
					'id'   => $id,
				),
				array( 'post_status' => $allowed ),
				array( 'post_status' => $attempted ),
				'Status written outside the StateMachine'
			);
		} catch ( \Throwable $e ) {
			unset( $e );
			// The refusal stands even if the log is busy.
		}
		return $data;
	}
}
