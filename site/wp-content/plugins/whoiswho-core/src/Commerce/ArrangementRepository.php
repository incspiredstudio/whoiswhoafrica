<?php
/**
 * Storage of sponsored story deals in wiw_arrangements (REQ-021, REQ-112, REQ-179, REQ-205). The only deal type is
 * sponsored_post. Amounts are integer minor units plus a currency code. There is no column for a profile, a nomination or a
 * winner: no relation links a deal to Awards (REQ-199). Which fields a role may write is the job of a later layer.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

/**
 * Arrangement (deal) repository.
 */
class ArrangementRepository {

	public const TABLE = 'wiw_arrangements';

	/**
	 * The one deal type that exists.
	 */
	public const TYPE = 'sponsored_post';

	public const TYPES = array( self::TYPE );

	/**
	 * Columns a caller may set (the type, external id and times are set here).
	 */
	public const WRITABLE = array( 'customer_id', 'story_id', 'amount_minor', 'currency', 'method', 'reference', 'received_on', 'confirmed_by', 'agreed', 'cancelled', 'notes' );

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		external_id char(36) NOT NULL,
		type varchar(20) NOT NULL DEFAULT \'sponsored_post\',
		customer_id bigint(20) unsigned NOT NULL,
		story_id bigint(20) unsigned NULL,
		amount_minor bigint(20) unsigned NULL,
		currency char(3) NOT NULL DEFAULT \'NGN\',
		method varchar(40) NOT NULL DEFAULT \'\',
		reference varchar(190) NOT NULL DEFAULT \'\',
		received_on date NULL,
		confirmed_by bigint(20) unsigned NULL,
		agreed tinyint(1) unsigned NOT NULL DEFAULT 0,
		cancelled tinyint(1) unsigned NOT NULL DEFAULT 0,
		notes text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY external_id (external_id),
		KEY customer_id (customer_id),
		KEY story_id (story_id),
		KEY agreed_cancelled (agreed, cancelled)';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Insert a deal. The type is always sponsored_post.
	 *
	 * @param array<string, mixed> $fields Values for the writable columns; customer_id is required.
	 * @param \DateTimeImmutable   $at     Moment.
	 * @return int New id.
	 * @throws \InvalidArgumentException When a value is invalid, or a type other than sponsored_post is passed.
	 * @throws \RuntimeException         When the row cannot be stored.
	 */
	public function insert( array $fields, \DateTimeImmutable $at ): int {
		global $wpdb;
		if ( isset( $fields['type'] ) && self::TYPE !== $fields['type'] ) {
			throw new \InvalidArgumentException( 'The only deal type is sponsored_post.' );
		}
		$clean = $this->clean( $fields );
		if ( empty( $clean['customer_id'] ) ) {
			throw new \InvalidArgumentException( 'A deal needs a customer.' );
		}
		$stamp = $at->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$row   = array_merge(
			array(
				'external_id' => wp_generate_uuid4(),
				'type'        => self::TYPE,
				'created_at'  => $stamp,
				'updated_at'  => $stamp,
			),
			$clean
		);
		if ( false === $wpdb->insert( self::table(), $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw new \RuntimeException( 'The deal could not be stored.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Change writable columns; the type, external id and created time never change.
	 *
	 * @param int                  $id     Deal id.
	 * @param array<string, mixed> $fields Values to change.
	 * @param \DateTimeImmutable   $at     Moment.
	 * @return array<int, string> Names of the columns that actually changed.
	 * @throws \InvalidArgumentException When the deal is unknown or a value is invalid.
	 */
	public function update( int $id, array $fields, \DateTimeImmutable $at ): array {
		global $wpdb;
		if ( isset( $fields['type'] ) && self::TYPE !== $fields['type'] ) {
			throw new \InvalidArgumentException( 'The only deal type is sponsored_post.' );
		}
		$current = $this->get( $id );
		if ( null === $current ) {
			throw new \InvalidArgumentException( 'Unknown deal.' );
		}
		$changed = array();
		foreach ( $this->clean( $fields ) as $column => $value ) {
			if ( ( null === $value ? null : (string) $value ) !== ( null === $current[ $column ] ? null : (string) $current[ $column ] ) ) {
				$changed[ $column ] = $value;
			}
		}
		if ( array() === $changed ) {
			return array();
		}
		$changed['updated_at'] = $at->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$wpdb->update( self::table(), $changed, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		unset( $changed['updated_at'] );
		return array_keys( $changed );
	}

	/**
	 * One deal.
	 *
	 * @param int $id Id.
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Every deal, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
	}

	/**
	 * The amount of a stored deal, if one is recorded.
	 *
	 * @param array<string, mixed> $row Deal row.
	 */
	public static function money( array $row ): ?Money {
		if ( ! isset( $row['amount_minor'] ) ) {
			return null;
		}
		return new Money( (int) $row['amount_minor'], (string) ( $row['currency'] ?? Money::DEFAULT_CURRENCY ) );
	}

	/**
	 * Keep only writable columns, with types applied.
	 *
	 * @param array<string, mixed> $fields Input.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException On a negative or non-whole amount, or a bad currency or date.
	 */
	private function clean( array $fields ): array {
		$out = array();
		foreach ( self::WRITABLE as $column ) {
			if ( ! array_key_exists( $column, $fields ) ) {
				continue;
			}
			$value = $fields[ $column ];
			switch ( $column ) {
				case 'customer_id':
				case 'story_id':
				case 'confirmed_by':
					$out[ $column ] = ( null === $value || '' === $value || 0 === (int) $value ) ? null : (int) $value;
					break;
				case 'amount_minor':
					if ( null === $value || '' === $value ) {
						$out[ $column ] = null;
						break;
					}
					if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
						throw new \InvalidArgumentException( 'An amount is a whole number of minor units.' );
					}
					$out[ $column ] = ( new Money( (int) $value ) )->minor();
					break;
				case 'currency':
					$out[ $column ] = ( new Money( 0, strtoupper( trim( (string) $value ) ) ) )->currency();
					break;
				case 'received_on':
					$text = trim( is_scalar( $value ) ? (string) $value : '' );
					if ( '' !== $text && 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $text ) ) {
						throw new \InvalidArgumentException( 'A date received is YYYY-MM-DD.' );
					}
					$out[ $column ] = '' === $text ? null : $text;
					break;
				case 'agreed':
				case 'cancelled':
					$out[ $column ] = $value ? 1 : 0;
					break;
				default:
					$out[ $column ] = trim( is_scalar( $value ) ? (string) $value : '' );
			}//end switch
		}//end foreach
		return $out;
	}
}
