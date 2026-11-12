<?php
/**
 * Stories newsletter subscribers, stored on the consent record (REQ-058, REQ-061, REQ-249). One row per address and purpose in
 * wiw_consents, kept apart from enquiries, customers and awards: nothing here joins to them. A sign-up is pending until the
 * address is confirmed with a single-use token (only its hash is stored); unsubscribing sets the revoked time. A repeat sign-up
 * never makes a second row. Marketing email is its own purpose, so this consent never counts for anything else.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Newsletter;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Platform\Settings\SettingsService;

/**
 * Subscriber repository.
 */
final class SubscriberRepository {

	public const PURPOSE = ConsentRecorder::STORIES_NEWSLETTER;

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
	 * Table name with the site prefix.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . ConsentRecorder::TABLE;
	}

	/**
	 * Hash of a confirmation token, the only form that is stored.
	 *
	 * @param string $token Token.
	 */
	public static function hash_token( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * Add a pending sign-up, or refresh an existing unconfirmed or unsubscribed one. A confirmed, active subscriber is left as
	 * they are and no token is made, so a repeat sign-up neither duplicates nor reconfirms.
	 *
	 * @param string $email  Address.
	 * @param string $source Where it was given.
	 * @return array{token: string|null, created: bool} The confirmation token to email (null when none is needed), and whether this made the row.
	 * @throws \InvalidArgumentException For an invalid address.
	 */
	public function add_pending( string $email, string $source = 'newsletter form' ): array {
		global $wpdb;
		$email = strtolower( trim( $email ) );
		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException( 'Invalid address.' );
		}
		$existing = $this->find_by_email_hash( ConsentRecorder::hash_email( $email ) );
		if ( null !== $existing && null !== $existing['confirmed_at'] && null === $existing['revoked_at'] ) {
			return array(
				'token'   => null,
				'created' => false,
			);
		}
		$token  = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$values = array(
			'policy_version' => (string) $this->settings->get( SettingsService::POLICY_VERSION ),
			'granted_at'     => ( $this->clock )(),
			'revoked_at'     => null,
			'confirmed_at'   => null,
			'token_hash'     => self::hash_token( $token ),
			'source'         => substr( sanitize_text_field( $source ), 0, 60 ),
		);
		if ( null === $existing ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table(),
				array_merge(
					$values,
					array(
						'email'      => $email,
						'email_hash' => ConsentRecorder::hash_email( $email ),
						'purpose'    => self::PURPOSE,
					)
				)
			);
			return array(
				'token'   => $token,
				'created' => true,
			);
		}
		$wpdb->update( self::table(), $values, array( 'id' => $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array(
			'token'   => $token,
			'created' => false,
		);
	}

	/**
	 * Confirm a sign-up with its token. The token works once.
	 *
	 * @param string $token Token from the email link.
	 * @return bool True when a pending sign-up was confirmed.
	 */
	public function confirm( string $token ): bool {
		global $wpdb;
		if ( '' === $token ) {
			return false;
		}
		$changed = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET confirmed_at = %s, token_hash = NULL WHERE token_hash = %s AND purpose = %s AND revoked_at IS NULL AND confirmed_at IS NULL', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
				( $this->clock )(),
				self::hash_token( $token ),
				self::PURPOSE
			)
		);
		return 1 === $changed;
	}

	/**
	 * Unsubscribe an address. The row stays, with its revoked time, as the record of the withdrawal.
	 *
	 * @param string $email Address.
	 * @return bool True when an active sign-up was withdrawn.
	 */
	public function unsubscribe( string $email ): bool {
		global $wpdb;
		$changed = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET revoked_at = %s, token_hash = NULL WHERE email_hash = %s AND purpose = %s AND revoked_at IS NULL', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
				( $this->clock )(),
				ConsentRecorder::hash_email( $email ),
				self::PURPOSE
			)
		);
		return 1 === $changed;
	}

	/**
	 * Whether an address is a confirmed, active subscriber.
	 *
	 * @param string $email Address.
	 */
	public function is_subscribed( string $email ): bool {
		$row = $this->find_by_email_hash( ConsentRecorder::hash_email( $email ) );
		return null !== $row && null !== $row['confirmed_at'] && null === $row['revoked_at'];
	}

	/**
	 * One row by the keyed hash of the address (how retention, export and erase find it).
	 *
	 * @param string $email_hash Hash from ConsentRecorder::hash_email().
	 * @return array<string, mixed>|null
	 */
	public function find_by_email_hash( string $email_hash ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE email_hash = %s AND purpose = %s', $email_hash, self::PURPOSE ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * The address of one sign-up row, for the job that emails it (the address is never kept in the job).
	 *
	 * @param int $id Row id.
	 * @return string Address, or an empty string when the row is gone.
	 */
	public function email_for_id( int $id ): string {
		global $wpdb;
		$email = $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ' . self::table() . ' WHERE id = %d AND purpose = %s', $id, self::PURPOSE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_string( $email ) ? $email : '';
	}

	/**
	 * Subscribers, newest first.
	 *
	 * @param string $state confirmed (active), pending or unsubscribed.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( string $state = 'confirmed', int $limit = 200 ): array {
		global $wpdb;
		switch ( $state ) {
			case 'pending':
				$where = 'confirmed_at IS NULL AND revoked_at IS NULL';
				break;
			case 'unsubscribed':
				$where = 'revoked_at IS NOT NULL';
				break;
			default:
				$where = 'confirmed_at IS NOT NULL AND revoked_at IS NULL';
		}
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE purpose = %s AND {$where} ORDER BY granted_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name and fixed clauses.
				self::PURPOSE,
				max( 1, $limit )
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
