<?php
/**
 * Who agreed to what, with the policy version and the time (REQ-058, REQ-061, REQ-063). One row per person and purpose. Marketing
 * email is its own purpose: no other consent counts as marketing consent. Used by the claim, enquiry, data-rights, invitation and
 * newsletter forms.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Consent;

use WhoIsWho\Core\Platform\Settings\SettingsService;

/**
 * Consent recorder.
 */
final class ConsentRecorder {

	public const TABLE = 'wiw_consents';

	public const ENQUIRY            = 'enquiry';
	public const CLAIM              = 'claim';
	public const DATA_RIGHTS        = 'data_rights';
	public const INVITATION         = 'invitation';
	public const MARKETING_EMAIL    = 'marketing_email';
	public const STORIES_NEWSLETTER = 'stories_newsletter';

	public const PURPOSES = array(
		self::ENQUIRY,
		self::CLAIM,
		self::DATA_RIGHTS,
		self::INVITATION,
		self::MARKETING_EMAIL,
		self::STORIES_NEWSLETTER,
	);

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(190) NOT NULL,
		email_hash char(64) NOT NULL,
		purpose varchar(40) NOT NULL,
		policy_version varchar(40) NOT NULL,
		granted_at datetime NOT NULL,
		revoked_at datetime NULL,
		confirmed_at datetime NULL,
		token_hash char(64) NULL,
		source varchar(60) NOT NULL DEFAULT \'\',
		PRIMARY KEY  (id),
		UNIQUE KEY person_purpose (email_hash, purpose),
		KEY purpose (purpose)';

	/**
	 * Clock returning a UTC time string.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Settings (for the policy version).
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings;

	/**
	 * Constructor.
	 *
	 * @param callable|null        $clock    Returns "Y-m-d H:i:s" UTC.
	 * @param SettingsService|null $settings Settings service.
	 */
	public function __construct( ?callable $clock = null, ?SettingsService $settings = null ) {
		$this->clock    = $clock ?? static fn (): string => gmdate( 'Y-m-d H:i:s' );
		$this->settings = $settings ?? SettingsService::for_site();
	}

	/**
	 * Keyed hash of an address, used to find a person without scanning addresses.
	 *
	 * @param string $email Address.
	 */
	public static function hash_email( string $email ): string {
		return hash_hmac( 'sha256', self::normalise( $email ), wp_salt( 'auth' ) );
	}

	/**
	 * Record consent. A repeat grant for the same person and purpose keeps one row: it refreshes the time and policy version only
	 * when the earlier consent was withdrawn.
	 *
	 * @param string $email   Address.
	 * @param string $purpose One of PURPOSES.
	 * @param string $source  Where it was given, for example "newsletter form".
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException For an unknown purpose or an invalid address.
	 */
	public function grant( string $email, string $purpose, string $source = '' ): array {
		global $wpdb;
		$email = self::normalise( $email );
		$this->check( $email, $purpose );
		$existing = $this->find( $email, $purpose );
		$now      = ( $this->clock )();
		if ( null === $existing ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . self::TABLE,
				array(
					'email'          => $email,
					'email_hash'     => self::hash_email( $email ),
					'purpose'        => $purpose,
					'policy_version' => $this->policy_version(),
					'granted_at'     => $now,
					'source'         => substr( sanitize_text_field( $source ), 0, 60 ),
				)
			);
		} elseif ( null !== $existing['revoked_at'] ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . self::TABLE,
				array(
					'policy_version' => $this->policy_version(),
					'granted_at'     => $now,
					'revoked_at'     => null,
					'source'         => substr( sanitize_text_field( $source ), 0, 60 ),
				),
				array( 'id' => $existing['id'] )
			);
		}//end if
		$row = $this->find( $email, $purpose );
		return $row ?? array();
	}

	/**
	 * Withdraw consent.
	 *
	 * @param string $email   Address.
	 * @param string $purpose Purpose.
	 * @return bool True when an active consent was withdrawn.
	 */
	public function revoke( string $email, string $purpose ): bool {
		global $wpdb;
		$row = $this->find( $email, $purpose );
		if ( null === $row || null !== $row['revoked_at'] ) {
			return false;
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE,
			array( 'revoked_at' => ( $this->clock )() ),
			array( 'id' => $row['id'] )
		);
		return true;
	}

	/**
	 * Whether the person has an active consent for exactly this purpose.
	 *
	 * @param string $email   Address.
	 * @param string $purpose Purpose.
	 */
	public function has_consent( string $email, string $purpose ): bool {
		$row = $this->find( $email, $purpose );
		return null !== $row && null === $row['revoked_at'];
	}

	/**
	 * One consent row.
	 *
	 * @param string $email   Address.
	 * @param string $purpose Purpose.
	 * @return array<string, mixed>|null
	 */
	public function find( string $email, string $purpose ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email_hash = %s AND purpose = %s", self::hash_email( $email ), $purpose ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? $row : null;
	}

	/**
	 * All of a person's consents, for export, erase and retention.
	 *
	 * @param string $email Address.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_email( string $email ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email_hash = %s ORDER BY id", self::hash_email( $email ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * The current policy version, from the settings service.
	 */
	private function policy_version(): string {
		return (string) $this->settings->get( SettingsService::POLICY_VERSION );
	}

	/**
	 * Lower-case and trim.
	 *
	 * @param string $email Address.
	 */
	private static function normalise( string $email ): string {
		return strtolower( trim( $email ) );
	}

	/**
	 * Check inputs.
	 *
	 * @param string $email   Normalised address.
	 * @param string $purpose Purpose.
	 * @throws \InvalidArgumentException When invalid.
	 */
	private function check( string $email, string $purpose ): void {
		if ( ! in_array( $purpose, self::PURPOSES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown consent purpose.' );
		}
		if ( ! is_email( $email ) || strlen( $email ) > 190 ) {
			throw new \InvalidArgumentException( 'Invalid email address.' );
		}
	}
}
