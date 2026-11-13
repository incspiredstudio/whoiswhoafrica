<?php
/**
 * Consent for a complimentary listing (REQ-175, REQ-233, REQ-058). A complimentary-flagged customer profile cannot be published
 * until its person has agreed on a single-use link page. The decision is stored against the profile with a timestamp and the
 * policy version; a person who declines is recorded and the profile stays unpublished. No email address is stored here and no
 * personal data goes into a link URL: the link carries only an unguessable token bound to one purpose and one profile.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Consent;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Settings\SettingsService;

/**
 * Listing consent.
 */
final class ListingConsent {

	public const TABLE           = 'wiw_listing_consents';
	public const PURPOSE_ACCEPT  = 'complimentary_listing';
	public const PURPOSE_DECLINE = 'complimentary_listing_decline';
	public const SUBJECT         = 'profile';
	public const GRANTED         = 'granted';
	public const DECLINED        = 'declined';
	public const ORIGIN          = 'complimentary';

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		profile_id bigint(20) unsigned NOT NULL,
		decision varchar(10) NOT NULL,
		policy_version varchar(40) NOT NULL,
		decided_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY profile (profile_id)';

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
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Register the publication gate with the state machine.
	 */
	public static function register_gate(): void {
		add_filter(
			'wiw_profile_transition_refusal',
			static function ( $refusal, $profile_id, $from, $to, $origin ) {
				unset( $from );
				if ( is_string( $refusal ) && '' !== $refusal ) {
					return $refusal;
				}
				if ( self::ORIGIN === $origin && PubState::is_public( (string) $to ) && ! ( new self() )->has_granted( (int) $profile_id ) ) {
					return 'A complimentary listing needs the person\'s consent before it can be published.';
				}
				return '';
			},
			10,
			5
		);
	}

	/**
	 * Register the two link purposes. A link grants or declines for the one profile it was issued for, once.
	 *
	 * @param SignedLinks $links Signed link service.
	 */
	public function register_links( SignedLinks $links ): void {
		$links->register(
			self::PURPOSE_ACCEPT,
			function ( array $link ) use ( $links ): string {
				$this->decide( (int) $link['subject_id'], self::GRANTED );
				$links->revoke_for( self::PURPOSE_DECLINE, self::SUBJECT, (string) $link['subject_id'] );
				return 'Thank you. Your consent is recorded.';
			}
		);
		$links->register(
			self::PURPOSE_DECLINE,
			function ( array $link ) use ( $links ): string {
				$this->decide( (int) $link['subject_id'], self::DECLINED );
				$links->revoke_for( self::PURPOSE_ACCEPT, self::SUBJECT, (string) $link['subject_id'] );
				return 'Your choice is recorded. The profile will not be published.';
			}
		);
	}

	/**
	 * Issue the pair of links for a profile: one to agree, one to decline. The address is kept only by the link service.
	 *
	 * @param SignedLinks $links      Signed link service.
	 * @param int         $profile_id Profile.
	 * @param string      $email      Where the link is sent.
	 * @return array{accept: string, decline: string} Tokens.
	 */
	public function issue_links( SignedLinks $links, int $profile_id, string $email ): array {
		return array(
			'accept'  => $links->reissue( self::PURPOSE_ACCEPT, self::SUBJECT, (string) $profile_id, $email ),
			'decline' => $links->reissue( self::PURPOSE_DECLINE, self::SUBJECT, (string) $profile_id, $email ),
		);
	}

	/**
	 * Whether the person agreed to this profile being published.
	 *
	 * @param int $profile_id Profile.
	 */
	public function has_granted( int $profile_id ): bool {
		$row = $this->find( $profile_id );
		return null !== $row && self::GRANTED === $row['decision'];
	}

	/**
	 * The stored decision, if any.
	 *
	 * @param int $profile_id Profile.
	 * @return array<string, mixed>|null
	 */
	public function find( int $profile_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE profile_id = %d', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Record a decision for a complimentary profile; the latest decision for a profile replaces the earlier one.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $decision   granted or declined.
	 * @throws \InvalidArgumentException For a profile that is not a complimentary listing.
	 */
	public function decide( int $profile_id, string $decision ): void {
		global $wpdb;
		$state = ( new StateRepository() )->get( $profile_id );
		if ( null === $state || self::ORIGIN !== $state['origin'] ) {
			throw new \InvalidArgumentException( 'Not a complimentary listing.' );
		}
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'INSERT INTO ' . self::table() . ' (profile_id, decision, policy_version, decided_at) VALUES (%d, %s, %s, %s) ON DUPLICATE KEY UPDATE decision = VALUES(decision), policy_version = VALUES(policy_version), decided_at = VALUES(decided_at)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
				$profile_id,
				self::GRANTED === $decision ? self::GRANTED : self::DECLINED,
				(string) $this->settings->get( SettingsService::POLICY_VERSION ),
				( $this->clock )()
			)
		);
	}
}
