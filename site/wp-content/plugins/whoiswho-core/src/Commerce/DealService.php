<?php
/**
 * The deal service (REQ-269, REQ-179, REQ-226, REQ-086, REQ-239, REQ-183). Money fields (amount, currency, method, reference,
 * date received, confirmed by), creating a deal and cancelling one need the fixed capability wiw_manage_register, which only
 * an Administrator can hold; other deal fields need wiw_set_sponsored. A card-like number in the reference is refused. A
 * pending-review or hidden profile cannot be linked to a deal. Every money step writes an audit row of field names and
 * amounts, never contact details or the reference text.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Deal service.
 */
final class DealService {

	public const MONEY_CAP = 'wiw_manage_register';
	public const STORY_CAP = 'wiw_set_sponsored';

	/**
	 * Columns only the register capability may see or write.
	 */
	public const MONEY_FIELDS = array( 'amount_minor', 'currency', 'method', 'reference', 'received_on', 'confirmed_by' );

	/**
	 * Repository.
	 *
	 * @var ArrangementRepository
	 */
	private ArrangementRepository $deals;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Clock.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Profile state lookup.
	 *
	 * @var callable
	 */
	private $profile_state;

	/**
	 * Constructor.
	 *
	 * @param ArrangementRepository $deals         Repository.
	 * @param AuditLogger           $audit         Audit logger.
	 * @param callable|null         $clock         fn(): \DateTimeImmutable.
	 * @param callable|null         $profile_state fn(int $profile_id): ?string, the profile's publication state.
	 */
	public function __construct( ArrangementRepository $deals, AuditLogger $audit, ?callable $clock = null, ?callable $profile_state = null ) {
		$this->deals         = $deals;
		$this->audit         = $audit;
		$this->clock         = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$this->profile_state = $profile_state ?? static fn ( int $id ): ?string => ( new StateRepository() )->pub_state( $id );
	}

	/**
	 * Create a deal: it needs a customer and an agreed amount.
	 *
	 * @param array<string, mixed> $fields customer_id, amount_minor, currency and optionally the other fields.
	 * @return int Deal id.
	 * @throws \DomainException          When the user lacks the register capability or a rule is broken.
	 */
	public function create( array $fields ): int {
		$this->require_cap( self::MONEY_CAP );
		$fields['agreed'] = true;
		if ( empty( $fields['customer_id'] ) || ! isset( $fields['amount_minor'] ) || '' === $fields['amount_minor'] ) {
			throw new \DomainException( 'A deal needs a customer and an agreed amount.' );
		}
		$fields = $this->checked( $fields );
		$id     = $this->deals->insert( $fields, ( $this->clock )() );
		$this->log(
			'deal.create',
			$id,
			null,
			array(
				'amount_minor' => (int) $fields['amount_minor'],
				'currency'     => (string) ( $fields['currency'] ?? Money::DEFAULT_CURRENCY ),
			)
		);
		return $id;
	}

	/**
	 * Change a deal. Money fields need the register capability, the others the sponsored capability. A DomainException is thrown when the user lacks the capability for a field or a rule is broken.
	 *
	 * @param int                  $id     Deal id.
	 * @param array<string, mixed> $fields Values to change.
	 * @return array<int, string> Names of the fields that changed.
	 * @throws \InvalidArgumentException When the deal is unknown.
	 */
	public function update( int $id, array $fields ): array {
		$touches_money = array() !== array_intersect( array_keys( $fields ), self::MONEY_FIELDS );
		$this->require_cap( $touches_money ? self::MONEY_CAP : self::STORY_CAP );
		if ( array_key_exists( 'cancelled', $fields ) || array_key_exists( 'agreed', $fields ) ) {
			$this->require_cap( self::MONEY_CAP );
		}
		$fields = $this->checked( $fields );
		$before = $this->deals->get( $id );
		if ( null === $before ) {
			throw new \InvalidArgumentException( 'Unknown deal.' );
		}
		$changed = $this->deals->update( $id, $fields, ( $this->clock )() );
		if ( in_array( 'amount_minor', $changed, true ) || in_array( 'currency', $changed, true ) ) {
			$this->log(
				'deal.amount_change',
				$id,
				array(
					'amount_minor' => null === $before['amount_minor'] ? null : (int) $before['amount_minor'],
					'currency'     => (string) $before['currency'],
				),
				array(
					'amount_minor' => (int) ( $fields['amount_minor'] ?? $before['amount_minor'] ),
					'currency'     => (string) ( $fields['currency'] ?? $before['currency'] ),
				)
			);
		}
		$payment = array_values( array_intersect( $changed, array( 'method', 'reference', 'received_on', 'confirmed_by' ) ) );
		if ( array() !== $payment ) {
			$this->log( 'deal.payment', $id, null, array( 'fields' => $payment ) );
		}
		if ( in_array( 'cancelled', $changed, true ) ) {
			$this->log( 'deal.cancel', $id, null, array( 'cancelled' => ! empty( $fields['cancelled'] ) ) );
		}
		$other = array_values( array_diff( $changed, self::MONEY_FIELDS, array( 'cancelled' ) ) );
		if ( array() !== $other ) {
			$this->log( 'deal.update', $id, null, array( 'fields' => $other ) );
		}
		return $changed;
	}

	/**
	 * Record the payment: method, reference, date received and the confirming administrator (the current user by default).
	 *
	 * @param int                  $id     Deal id.
	 * @param array<string, mixed> $fields method, reference, received_on and optionally amount_minor, currency, confirmed_by.
	 * @return array<int, string> Names of the fields that changed.
	 * @throws \DomainException When the user lacks the register capability.
	 */
	public function record_payment( int $id, array $fields ): array {
		$this->require_cap( self::MONEY_CAP );
		$allowed = array_intersect_key( $fields, array_flip( self::MONEY_FIELDS ) );
		if ( empty( $allowed['confirmed_by'] ) ) {
			$allowed['confirmed_by'] = get_current_user_id();
		}
		return $this->update( $id, $allowed );
	}

	/**
	 * Cancel a deal.
	 *
	 * @param int $id Deal id.
	 * @throws \DomainException When the user lacks the register capability.
	 */
	public function cancel( int $id ): void {
		$this->require_cap( self::MONEY_CAP );
		$this->update( $id, array( 'cancelled' => true ) );
	}

	/**
	 * A deal as the current user may see it: money fields only for the register capability.
	 *
	 * @param array<string, mixed> $deal Deal row.
	 * @return array<string, mixed>
	 */
	public function view( array $deal ): array {
		if ( current_user_can( self::MONEY_CAP ) ) {
			return $deal;
		}
		return array_diff_key( $deal, array_flip( self::MONEY_FIELDS ) );
	}

	/**
	 * Validate the values: no card-like reference, and no link to a profile that is not reviewed. The deal has no profile column,
	 * so a profile reference is checked and then dropped.
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, mixed>
	 * @throws \DomainException When a rule is broken.
	 */
	private function checked( array $fields ): array {
		if ( isset( $fields['reference'] ) && CardGuard::looks_like_card( (string) $fields['reference'] ) ) {
			throw new \DomainException( 'The reference looks like a card number. Do not enter card details.' );
		}
		if ( array_key_exists( 'profile_id', $fields ) ) {
			if ( PubState::PUBLISHED_REVIEWED !== ( $this->profile_state )( (int) $fields['profile_id'] ) ) {
				throw new \DomainException( 'A profile that is not reviewed cannot be linked to a deal.' );
			}
			unset( $fields['profile_id'] );
		}
		return $fields;
	}

	/**
	 * Audit row: ids, amounts and field names only.
	 *
	 * @param string                    $action Action.
	 * @param int                       $id     Deal.
	 * @param array<string, mixed>|null $before Before.
	 * @param array<string, mixed>      $after  After.
	 */
	private function log( string $action, int $id, ?array $before, array $after ): void {
		$this->audit->log(
			$action,
			array(
				'type' => 'deal',
				'id'   => $id,
			),
			$before,
			$after
		);
		$this->audit->log_use(
			self::MONEY_CAP,
			array(
				'type' => 'deal',
				'id'   => $id,
			)
		);
	}

	/**
	 * Refuse without the capability.
	 *
	 * @param string $cap Capability.
	 * @throws \DomainException When the user lacks it.
	 */
	private function require_cap( string $cap ): void {
		if ( ! current_user_can( $cap ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- one of two named wiw_* capabilities.
			throw new \DomainException( 'Not allowed to change this deal.' );
		}
	}
}
