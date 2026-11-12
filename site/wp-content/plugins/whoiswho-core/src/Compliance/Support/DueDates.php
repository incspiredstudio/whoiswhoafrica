<?php
/**
 * Published response targets as dates (REQ-158): acknowledge within one business day, resolve corrections within seven
 * days, hide a valid removal within two business days. Business days are Monday to Friday in West Africa Time; the
 * holiday list is one value (config/business-calendar.php) because the rules do not say whether Nigerian public holidays
 * count (confirm in M1-COR-18). All stored times are UTC.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

/**
 * Due-date calculator.
 */
final class DueDates {

	public const ZONE = 'Africa/Lagos';

	/**
	 * Holidays as Y-m-d dates in WAT.
	 *
	 * @var string[]
	 */
	private array $holidays;

	/**
	 * Constructor.
	 *
	 * @param string[]|null $holidays Holiday dates (Y-m-d); null reads config/business-calendar.php.
	 */
	public function __construct( ?array $holidays = null ) {
		if ( null === $holidays ) {
			$file     = dirname( __DIR__, 3 ) . '/config/business-calendar.php';
			$config   = is_file( $file ) ? require $file : array();
			$holidays = (array) ( $config['holidays'] ?? array() );
		}
		$this->holidays = array_map( 'strval', $holidays );
	}

	/**
	 * Add business days to a moment, keeping the time of day, counted in WAT.
	 *
	 * @param \DateTimeImmutable $from Start (any zone).
	 * @param int                $days Business days to add (0 or more).
	 */
	public function add_business_days( \DateTimeImmutable $from, int $days ): \DateTimeImmutable {
		$moment = $from->setTimezone( new \DateTimeZone( self::ZONE ) );
		while ( $days > 0 ) {
			$moment = $moment->modify( '+1 day' );
			if ( $this->is_business_day( $moment ) ) {
				--$days;
			}
		}
		return $moment;
	}

	/**
	 * Whether a WAT date is a business day.
	 *
	 * @param \DateTimeImmutable $day Day.
	 */
	public function is_business_day( \DateTimeImmutable $day ): bool {
		$local = $day->setTimezone( new \DateTimeZone( self::ZONE ) );
		return (int) $local->format( 'N' ) < 6 && ! in_array( $local->format( 'Y-m-d' ), $this->holidays, true );
	}

	/**
	 * When the requester must have been answered by.
	 *
	 * @param \DateTimeImmutable $received When the item arrived.
	 */
	public function acknowledge_due( \DateTimeImmutable $received ): \DateTimeImmutable {
		return $this->add_business_days( $received, 1 );
	}

	/**
	 * When the item must be resolved by: corrections seven days, a removal's hide two business days, everything else
	 * the one-business-day acknowledgement.
	 *
	 * @param string             $type     Item type.
	 * @param \DateTimeImmutable $received When the item arrived.
	 */
	public function resolve_due( string $type, \DateTimeImmutable $received ): \DateTimeImmutable {
		switch ( $type ) {
			case ItemType::CORRECTION:
				return $received->modify( '+7 days' );
			case ItemType::REMOVAL:
				return $this->add_business_days( $received, 2 );
			default:
				return $this->acknowledge_due( $received );
		}
	}

	/**
	 * Whether an item is in breach: still open past its resolve date, or not yet acknowledged past its acknowledge date.
	 *
	 * @param string                  $status          Item status.
	 * @param \DateTimeImmutable      $now             Now.
	 * @param \DateTimeImmutable|null $due        Resolve date; null when the item has none (enquiries).
	 * @param \DateTimeImmutable      $acknowledge_due Acknowledge date.
	 */
	public static function is_breach( string $status, \DateTimeImmutable $now, ?\DateTimeImmutable $due, \DateTimeImmutable $acknowledge_due ): bool {
		if ( ItemStatus::is_closed( $status ) ) {
			return false;
		}
		if ( null !== $due && $now > $due ) {
			return true;
		}
		return ItemStatus::NEW === $status && $now > $acknowledge_due;
	}
}
