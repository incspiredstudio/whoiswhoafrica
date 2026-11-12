<?php
/**
 * Repository for wiw_support_items (REQ-089, REQ-158, REQ-270, REQ-271). Computes the reference and both due dates when an
 * item is created, stores the requester's IP only as a keyed hash, and answers whether an item is in breach.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

/**
 * Support items.
 */
class SupportItems {

	public const TABLE = 'wiw_support_items';

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		reference varchar(16) NOT NULL,
		item_type varchar(20) NOT NULL,
		profile_id bigint(20) unsigned NULL,
		requester_name varchar(190) NOT NULL DEFAULT \'\',
		requester_relationship varchar(60) NOT NULL DEFAULT \'\',
		requester_email varchar(190) NOT NULL DEFAULT \'\',
		message text NULL,
		status varchar(16) NOT NULL DEFAULT \'new\',
		owner_id bigint(20) unsigned NULL,
		ip_hash char(64) NULL,
		confirmed_at datetime NULL,
		acknowledged_at datetime NULL,
		ack_due_at datetime NOT NULL,
		due_at datetime NOT NULL,
		resolved_at datetime NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY reference (reference),
		KEY type_status (item_type, status),
		KEY due_at (due_at),
		KEY profile (profile_id),
		KEY owner (owner_id)';

	/**
	 * Due-date calculator.
	 *
	 * @var DueDates
	 */
	private DueDates $due_dates;

	/**
	 * Key for hashing IP addresses.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Constructor.
	 *
	 * @param DueDates|null $due_dates Calculator.
	 * @param string        $key       Secret for hashing; empty uses a WordPress salt.
	 */
	public function __construct( ?DueDates $due_dates = null, string $key = '' ) {
		$this->due_dates = $due_dates ?? new DueDates();
		$this->key       = '' !== $key ? $key : wp_salt( 'auth' );
	}

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Keyed hash of an IP address. The address itself is never stored.
	 *
	 * @param string $ip IP address.
	 */
	public function hash_ip( string $ip ): string {
		return hash_hmac( 'sha256', 'support-ip|' . $ip, $this->key );
	}

	/**
	 * Create an item. Input is already validated by the form validator.
	 *
	 * @param array<string, mixed>    $input    type, profile_id, name, relationship, email, message, ip.
	 * @param \DateTimeImmutable|null $received Arrival time; now when null.
	 * @return array<string, mixed> The stored row.
	 * @throws \InvalidArgumentException For an unknown type.
	 */
	public function create( array $input, ?\DateTimeImmutable $received = null ): array {
		global $wpdb;
		$type = (string) ( $input['type'] ?? '' );
		if ( ! ItemType::is_valid( $type ) ) {
			throw new \InvalidArgumentException( 'Unknown item type.' );
		}
		$received = $received ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$ip       = (string) ( $input['ip'] ?? '' );
		$row      = array(
			'reference'              => Reference::generate(),
			'item_type'              => $type,
			'profile_id'             => isset( $input['profile_id'] ) ? (int) $input['profile_id'] : null,
			'requester_name'         => (string) ( $input['name'] ?? '' ),
			'requester_relationship' => (string) ( $input['relationship'] ?? '' ),
			'requester_email'        => (string) ( $input['email'] ?? '' ),
			'message'                => (string) ( $input['message'] ?? '' ),
			'status'                 => ItemStatus::NEW,
			'ip_hash'                => '' === $ip ? null : $this->hash_ip( $ip ),
			'ack_due_at'             => $this->stamp( $this->due_dates->acknowledge_due( $received ) ),
			'due_at'                 => $this->stamp( $this->due_dates->resolve_due( $type, $received ) ),
			'created_at'             => $this->stamp( $received ),
			'updated_at'             => $this->stamp( $received ),
		);
		$wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row['id'] = (int) $wpdb->insert_id;
		return $row;
	}

	/**
	 * One item by id.
	 *
	 * @param int $id Item id.
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * One item by its public reference.
	 *
	 * @param string $reference Reference.
	 * @return array<string, mixed>|null
	 */
	public function find_by_reference( string $reference ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE reference = %s', $reference ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Items of some types, oldest due date first.
	 *
	 * @param string[] $types  Item types.
	 * @param string   $status A status, or empty for every status.
	 * @param int      $limit  Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_by_types( array $types, string $status = '', int $limit = 100 ): array {
		global $wpdb;
		$types = array_values( array_filter( $types, array( ItemType::class, 'is_valid' ) ) );
		if ( array() === $types ) {
			return array();
		}
		$marks = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$sql   = 'SELECT * FROM ' . self::table() . " WHERE item_type IN ({$marks})";
		$args  = $types;
		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}
		$sql   .= ' ORDER BY due_at ASC, id ASC LIMIT %d';
		$args[] = max( 1, $limit );
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table; placeholders built from a count.
	}

	/**
	 * Change an item's status. Closing stamps resolved_at; acknowledging stamps acknowledged_at.
	 *
	 * @param int                     $id     Item id.
	 * @param string                  $status New status.
	 * @param \DateTimeImmutable|null $at     Moment of the change.
	 * @throws \InvalidArgumentException For an unknown status.
	 */
	public function set_status( int $id, string $status, ?\DateTimeImmutable $at = null ): void {
		global $wpdb;
		if ( ! in_array( $status, ItemStatus::all(), true ) ) {
			throw new \InvalidArgumentException( 'Unknown status.' );
		}
		$stamp  = $this->stamp( $at ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
		$values = array(
			'status'     => $status,
			'updated_at' => $stamp,
		);
		if ( ItemStatus::is_closed( $status ) ) {
			$values['resolved_at'] = $stamp;
		}
		$item = $this->get( $id );
		if ( null !== $item && null === $item['acknowledged_at'] && ItemStatus::NEW !== $status ) {
			$values['acknowledged_at'] = $stamp;
		}
		$wpdb->update( self::table(), $values, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Give an item an owner.
	 *
	 * @param int $id    Item id.
	 * @param int $owner User id, or 0 to clear.
	 */
	public function assign( int $id, int $owner ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'owner_id'   => $owner > 0 ? $owner : null,
				'updated_at' => $this->stamp( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Record that the requester confirmed their email.
	 *
	 * @param int                     $id Item id.
	 * @param \DateTimeImmutable|null $at Moment.
	 */
	public function confirm_email( int $id, ?\DateTimeImmutable $at = null ): void {
		global $wpdb;
		$stamp = $this->stamp( $at ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'confirmed_at' => $stamp,
				'updated_at'   => $stamp,
			),
			array( 'id' => $id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Whether a stored item is in breach right now (computed, never stored).
	 *
	 * @param array<string, mixed>    $item Row.
	 * @param \DateTimeImmutable|null $now  Now.
	 */
	public function is_breach( array $item, ?\DateTimeImmutable $now = null ): bool {
		$utc = new \DateTimeZone( 'UTC' );
		return DueDates::is_breach(
			(string) $item['status'],
			$now ?? new \DateTimeImmutable( 'now', $utc ),
			new \DateTimeImmutable( (string) $item['due_at'], $utc ),
			new \DateTimeImmutable( (string) $item['ack_due_at'], $utc )
		);
	}

	/**
	 * Format a moment as a UTC database datetime.
	 *
	 * @param \DateTimeImmutable $moment Moment.
	 */
	private function stamp( \DateTimeImmutable $moment ): string {
		return $moment->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
