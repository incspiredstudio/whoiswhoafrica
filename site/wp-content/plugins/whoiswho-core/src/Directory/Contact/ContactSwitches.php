<?php
/**
 * Contact details of a profile and the switches that make one public (REQ-060). Every detail is private until a staff member
 * holding edit_wiw_profiles switches it on, on the owner's request; each change goes through the audit log with the reason. The
 * audit row names the kind and the switch, never the value, so no personal data reaches the log. Only PublicProfile reads
 * public_details(); nothing else on a public surface may read the stored details.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Contact;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Contact switches.
 */
final class ContactSwitches {

	public const CAPABILITY = 'edit_wiw_profiles';

	public const META = '_wiw_contact_details';

	public const KINDS = array( 'email', 'phone', 'address' );

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private ?AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger|null $audit Audit logger.
	 */
	public function __construct( ?AuditLogger $audit = null ) {
		$this->audit = $audit;
	}

	/**
	 * Every stored detail with its switch, for staff.
	 *
	 * @param int $profile_id Profile.
	 * @return array<string, array{value: string, public: bool}>
	 */
	public function all( int $profile_id ): array {
		$raw = json_decode( (string) get_post_meta( $profile_id, self::META, true ), true );
		$out = array();
		foreach ( self::KINDS as $kind ) {
			$entry        = is_array( $raw ) && isset( $raw[ $kind ] ) && is_array( $raw[ $kind ] ) ? $raw[ $kind ] : array();
			$out[ $kind ] = array(
				'value'  => isset( $entry['value'] ) ? (string) $entry['value'] : '',
				'public' => ! empty( $entry['public'] ),
			);
		}
		return $out;
	}

	/**
	 * The details staff switched on, kind => value. Empty by default.
	 *
	 * @param int $profile_id Profile.
	 * @return array<string, string>
	 */
	public function public_details( int $profile_id ): array {
		$out = array();
		foreach ( $this->all( $profile_id ) as $kind => $entry ) {
			if ( $entry['public'] && '' !== $entry['value'] ) {
				$out[ $kind ] = $entry['value'];
			}
		}
		return $out;
	}

	/**
	 * Store a detail. It stays private: switching it on is a separate, audited step.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $kind       One of KINDS.
	 * @param string $value      The detail; empty clears it.
	 * @throws \RuntimeException When refused.
	 */
	public function set_value( int $profile_id, string $kind, string $value ): void {
		$this->guard( $kind );
		$value = trim( wp_strip_all_tags( $value ) );
		if ( 'email' === $kind && '' !== $value && ! is_email( $value ) ) {
			throw new \RuntimeException( 'That is not a valid email address.' );
		}
		if ( strlen( $value ) > 300 ) {
			throw new \RuntimeException( 'That detail is too long.' );
		}
		$all                    = $this->all( $profile_id );
		$all[ $kind ]['value']  = $value;
		$all[ $kind ]['public'] = '' === $value ? false : $all[ $kind ]['public'];
		$this->store( $profile_id, $all );
	}

	/**
	 * Switch one detail public or private, with the owner's request as the reason; audited.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $kind       One of KINDS.
	 * @param bool   $show       Whether it shows on the public profile.
	 * @param string $reason     Required, for example the request reference.
	 * @throws \RuntimeException When refused.
	 */
	public function set_public( int $profile_id, string $kind, bool $show, string $reason ): void {
		$this->guard( $kind );
		$reason = trim( $reason );
		if ( '' === $reason ) {
			throw new \RuntimeException( 'A reason or request reference is required.' );
		}
		$all = $this->all( $profile_id );
		if ( $show && '' === $all[ $kind ]['value'] ) {
			throw new \RuntimeException( 'There is no value to show. Add the detail first.' );
		}
		if ( $all[ $kind ]['public'] === $show ) {
			return;
		}
		$all[ $kind ]['public'] = $show;
		$this->store( $profile_id, $all );
		( $this->audit ?? AuditLogger::for_site() )->log(
			'profile.contact_switch',
			array(
				'type' => 'profile',
				'id'   => $profile_id,
			),
			array(
				'kind'   => $kind,
				'public' => ! $show,
			),
			array(
				'kind'   => $kind,
				'public' => $show,
			),
			$reason
		);
	}

	/**
	 * The capability and kind checks.
	 *
	 * @param string $kind Kind.
	 * @throws \RuntimeException When refused.
	 */
	private function guard( string $kind ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
			throw new \RuntimeException( 'You are not allowed to change contact details.' );
		}
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			throw new \RuntimeException( 'Unknown contact detail.' );
		}
	}

	/**
	 * Save the whole map.
	 *
	 * @param int                                               $profile_id Profile.
	 * @param array<string, array{value: string, public: bool}> $all        Details.
	 */
	private function store( int $profile_id, array $all ): void {
		update_post_meta( $profile_id, self::META, wp_slash( (string) wp_json_encode( $all ) ) );
	}
}
