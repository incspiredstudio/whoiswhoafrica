<?php
/**
 * Derives a deal's status, progress and counting result from its fields and placements (REQ-269, REQ-022, REQ-180). The status
 * is computed and never typed; nothing here reads or writes a database, so it is cheap to test and to call from a list.
 *
 * A deal counts toward the sprint target when it is agreed, not cancelled, and its payment record is complete: amount,
 * currency, method, reference, date received and a named confirming administrator.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

/**
 * Deal status.
 */
final class DealStatus {

	public const CANCELLED         = 'cancelled';
	public const LIVE              = 'live';
	public const SCHEDULED         = 'scheduled';
	public const ENDED             = 'ended';
	public const NEEDS_PAYMENT     = 'needs_payment';
	public const NEEDS_STORY       = 'needs_story';
	public const READY_TO_SCHEDULE = 'ready_to_schedule';

	public const STATUSES = array( self::CANCELLED, self::LIVE, self::SCHEDULED, self::ENDED, self::NEEDS_PAYMENT, self::NEEDS_STORY, self::READY_TO_SCHEDULE );

	/**
	 * The four steps, in order.
	 */
	public const STEPS = array( 'deal_agreed', 'payment_recorded', 'story_ready', 'placement' );

	/**
	 * Fields a complete payment record has, column => label.
	 */
	public const PAYMENT_FIELDS = array(
		'amount_minor' => 'Amount',
		'currency'     => 'Currency',
		'method'       => 'Method',
		'reference'    => 'Reference',
		'received_on'  => 'Date received',
		'confirmed_by' => 'Confirmed by',
	);

	/**
	 * Labels of the payment fields that are empty.
	 *
	 * @param array<string, mixed> $deal Deal row.
	 * @return array<string, string> column => label.
	 */
	public static function missing_payment_fields( array $deal ): array {
		$missing = array();
		foreach ( self::PAYMENT_FIELDS as $column => $label ) {
			$value = $deal[ $column ] ?? null;
			if ( null === $value || '' === trim( (string) $value ) || ( 'confirmed_by' === $column && 0 === (int) $value ) ) {
				$missing[ $column ] = $label;
			}
		}
		return $missing;
	}

	/**
	 * Whether the payment record is complete.
	 *
	 * @param array<string, mixed> $deal Deal row.
	 */
	public static function payment_complete( array $deal ): bool {
		return array() === self::missing_payment_fields( $deal );
	}

	/**
	 * Whether the deal counts toward the sprint target (REQ-022).
	 *
	 * @param array<string, mixed> $deal Deal row.
	 */
	public static function counts( array $deal ): bool {
		return ! empty( $deal['agreed'] ) && empty( $deal['cancelled'] ) && self::payment_complete( $deal );
	}

	/**
	 * Status of a deal.
	 *
	 * @param array<string, mixed>             $deal       Deal row.
	 * @param array<int, array<string, mixed>> $placements Placement rows of the deal.
	 * @param string                           $today      Date as YYYY-MM-DD.
	 */
	public static function of( array $deal, array $placements, string $today ): string {
		if ( ! empty( $deal['cancelled'] ) ) {
			return self::CANCELLED;
		}
		if ( array() !== $placements ) {
			$scheduled = false;
			foreach ( $placements as $placement ) {
				if ( ! empty( $placement['ended'] ) || (string) $placement['ends_on'] < $today ) {
					continue;
				}
				if ( (string) $placement['starts_on'] <= $today ) {
					return self::LIVE;
				}
				$scheduled = true;
			}
			return $scheduled ? self::SCHEDULED : self::ENDED;
		}
		if ( ! self::payment_complete( $deal ) ) {
			return self::NEEDS_PAYMENT;
		}
		return empty( $deal['story_id'] ) ? self::NEEDS_STORY : self::READY_TO_SCHEDULE;
	}

	/**
	 * The "Payment not recorded" flag: a scheduled or live deal without a complete payment record.
	 *
	 * @param array<string, mixed>             $deal       Deal row.
	 * @param array<int, array<string, mixed>> $placements Placements.
	 * @param string                           $today      Date.
	 */
	public static function payment_not_recorded( array $deal, array $placements, string $today ): bool {
		return in_array( self::of( $deal, $placements, $today ), array( self::LIVE, self::SCHEDULED ), true ) && ! self::payment_complete( $deal );
	}

	/**
	 * Four-step progress: each step done or not, and the next step.
	 *
	 * @param array<string, mixed>             $deal       Deal row.
	 * @param array<int, array<string, mixed>> $placements Placements.
	 * @return array{steps: array<string, bool>, next: string|null}
	 */
	public static function progress( array $deal, array $placements ): array {
		$steps = array(
			'deal_agreed'      => ! empty( $deal['agreed'] ),
			'payment_recorded' => self::payment_complete( $deal ),
			'story_ready'      => ! empty( $deal['story_id'] ),
			'placement'        => array() !== $placements,
		);
		$next  = null;
		foreach ( $steps as $step => $done ) {
			if ( ! $done ) {
				$next = $step;
				break;
			}
		}
		return array(
			'steps' => $steps,
			'next'  => $next,
		);
	}

	/**
	 * Number of deals that count.
	 *
	 * @param array<int, array<string, mixed>> $deals Deal rows.
	 */
	public static function counted_total( array $deals ): int {
		return count( array_filter( $deals, array( self::class, 'counts' ) ) );
	}

	/**
	 * Confirmed revenue in minor units of one currency: the sum over counted deals only.
	 *
	 * @param array<int, array<string, mixed>> $deals    Deal rows.
	 * @param string                           $currency Currency code.
	 */
	public static function confirmed_revenue( array $deals, string $currency = Money::DEFAULT_CURRENCY ): int {
		$sum = 0;
		foreach ( $deals as $deal ) {
			if ( self::counts( $deal ) && $currency === (string) $deal['currency'] ) {
				$sum += (int) $deal['amount_minor'];
			}
		}
		return $sum;
	}
}
