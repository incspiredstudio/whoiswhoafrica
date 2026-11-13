<?php
/**
 * Storage of customers in wiw_customers (REQ-205, REQ-112, REQ-187). Each customer has a stable external id that never changes
 * (for a later link to an outside system) and created and changed timestamps. The contact columns and the internal notes are
 * personal data in the catalogue and are never read by a public template. No payment or card data lives here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

/**
 * Customer repository.
 */
class CustomerRepository {

	public const TABLE = 'wiw_customers';

	/**
	 * Columns a caller may set.
	 */
	public const WRITABLE = array( 'name', 'segment', 'contact_email', 'contact_phone', 'notes' );

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		external_id char(36) NOT NULL,
		name varchar(190) NOT NULL,
		segment varchar(60) NOT NULL DEFAULT \'\',
		contact_email varchar(190) NOT NULL DEFAULT \'\',
		contact_phone varchar(40) NOT NULL DEFAULT \'\',
		notes text NULL,
		enquiry_id bigint(20) unsigned NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY external_id (external_id),
		UNIQUE KEY enquiry_id (enquiry_id),
		KEY name (name)';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Insert a customer.
	 *
	 * @param array<string, string> $fields     Values for the writable columns.
	 * @param int|null              $enquiry_id The enquiry it came from, if any.
	 * @param \DateTimeImmutable    $at         Moment.
	 * @return int New id.
	 * @throws \InvalidArgumentException When the name is empty.
	 * @throws \RuntimeException         When the row cannot be stored.
	 */
	public function insert( array $fields, ?int $enquiry_id, \DateTimeImmutable $at ): int {
		global $wpdb;
		$clean = $this->clean( $fields );
		if ( '' === ( $clean['name'] ?? '' ) ) {
			throw new \InvalidArgumentException( 'A customer needs a name.' );
		}
		$stamp = $at->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$row   = array_merge(
			array(
				'external_id' => wp_generate_uuid4(),
				'enquiry_id'  => $enquiry_id,
				'created_at'  => $stamp,
				'updated_at'  => $stamp,
			),
			$clean
		);
		if ( false === $wpdb->insert( self::table(), $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw new \RuntimeException( 'The customer could not be stored.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Change writable columns. The external id and created time never change.
	 *
	 * @param int                   $id     Customer id.
	 * @param array<string, string> $fields Values to change.
	 * @param \DateTimeImmutable    $at     Moment.
	 * @return array<int, string> Names of the columns that actually changed.
	 * @throws \InvalidArgumentException When the customer is unknown or the name would be empty.
	 */
	public function update( int $id, array $fields, \DateTimeImmutable $at ): array {
		global $wpdb;
		$current = $this->get( $id );
		if ( null === $current ) {
			throw new \InvalidArgumentException( 'Unknown customer.' );
		}
		$clean   = $this->clean( $fields );
		$changed = array();
		foreach ( $clean as $column => $value ) {
			if ( (string) $current[ $column ] !== $value ) {
				$changed[ $column ] = $value;
			}
		}
		if ( isset( $changed['name'] ) && '' === $changed['name'] ) {
			throw new \InvalidArgumentException( 'A customer needs a name.' );
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
	 * One customer.
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
	 * The customer created from an enquiry, if there is one.
	 *
	 * @param int $enquiry_id Support item id.
	 * @return array<string, mixed>|null
	 */
	public function find_by_enquiry( int $enquiry_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE enquiry_id = %d', $enquiry_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Keep only writable columns, as trimmed strings.
	 *
	 * @param array<string, mixed> $fields Input.
	 * @return array<string, string>
	 */
	private function clean( array $fields ): array {
		$out = array();
		foreach ( self::WRITABLE as $column ) {
			if ( array_key_exists( $column, $fields ) ) {
				$out[ $column ] = trim( is_scalar( $fields[ $column ] ) ? (string) $fields[ $column ] : '' );
			}
		}
		return $out;
	}
}
