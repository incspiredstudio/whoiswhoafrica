<?php
/**
 * The per-profile enquiries switch (REQ-018). Staff holding wiw_support_inbox switch enquiries off or on for one profile, on the
 * owner's request; every change goes through the audit log with the reason. The visitor-facing handler reads the state through
 * PublicProfile::$enquiries_open.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Profile enquiry switch.
 */
final class ProfileEnquirySwitch {

	public const CAPABILITY = 'wiw_support_inbox';

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger|null $audit Audit logger.
	 */
	public function __construct( ?AuditLogger $audit = null ) {
		$this->audit = $audit ?? AuditLogger::for_site();
	}

	/**
	 * Switch enquiries on or off for a profile.
	 *
	 * @param int    $profile_id Profile post id.
	 * @param bool   $open       True to accept enquiries, false to switch them off.
	 * @param string $reason     Why (for example "Owner asked, ticket ENQ-0007"); required.
	 * @throws \RuntimeException When the user lacks the capability or gives no reason.
	 */
	public function set_open( int $profile_id, bool $open, string $reason ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			throw new \RuntimeException( 'You are not allowed to change enquiry settings.' );
		}
		$reason = trim( $reason );
		if ( '' === $reason ) {
			throw new \RuntimeException( 'A reason is required.' );
		}
		$was_open = '1' !== (string) get_post_meta( $profile_id, PublicProfile::ENQUIRIES_OFF_META, true );
		if ( $was_open === $open ) {
			return;
		}
		if ( $open ) {
			delete_post_meta( $profile_id, PublicProfile::ENQUIRIES_OFF_META );
		} else {
			update_post_meta( $profile_id, PublicProfile::ENQUIRIES_OFF_META, '1' );
		}
		$this->audit->log(
			'profile.enquiries_switch',
			array(
				'type' => 'profile',
				'id'   => $profile_id,
			),
			array( 'enquiries_open' => $was_open ),
			array( 'enquiries_open' => $open ),
			$reason
		);
	}
}
