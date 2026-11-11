<?php
/**
 * Internal notes on profiles, customers and nominations (REQ-187, REQ-188). Notes are never public and never shown to anyone
 * scoring. Each module says which of its own capabilities is required when it adds or lists notes, and every note added is
 * audited (ids and length only: the body may hold personal data and stays in this table).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Notes;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Notes service.
 */
class NotesService {

	public const TABLE = 'wiw_notes';

	public const TYPES = array( 'profile', 'customer', 'nomination' );

	public const MAX_LENGTH = 5000;

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		notable_type varchar(20) NOT NULL,
		notable_id bigint(20) unsigned NOT NULL,
		body text NOT NULL,
		author_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY notable (notable_type, notable_id, id)';

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit Audit logger.
	 */
	public function __construct( AuditLogger $audit ) {
		$this->audit = $audit;
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( AuditLogger::for_site() );
	}

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Attach a note to a record.
	 *
	 * @param string $type       profile, customer or nomination.
	 * @param int    $id         Record id.
	 * @param string $body       The note (plain text).
	 * @param string $capability The calling module's capability the current user must hold.
	 * @return int Note id.
	 * @throws \InvalidArgumentException For an unknown type, a missing id, or an empty or too long note (a \DomainException when the user lacks the capability).
	 */
	public function add( string $type, int $id, string $body, string $capability ): int {
		global $wpdb;
		$this->require_capability( $capability );
		$body = trim( wp_strip_all_tags( $body ) );
		if ( ! in_array( $type, self::TYPES, true ) || $id < 1 ) {
			throw new \InvalidArgumentException( 'A note needs a known record type and an id.' );
		}
		if ( '' === $body || strlen( $body ) > self::MAX_LENGTH ) {
			throw new \InvalidArgumentException( 'A note must be between 1 and 5000 characters.' );
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'notable_type' => $type,
				'notable_id'   => $id,
				'body'         => $body,
				'author_id'    => (int) get_current_user_id(),
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$note_id = (int) $wpdb->insert_id;
		$this->audit->log(
			'note.add',
			array(
				'type' => $type,
				'id'   => $id,
			),
			null,
			array(
				'note'   => $note_id,
				'length' => strlen( $body ),
			)
		);
		return $note_id;
	}

	/**
	 * The notes on a record, oldest first.
	 *
	 * @param string $type       Record type.
	 * @param int    $id         Record id.
	 * @param string $capability The calling module's capability the current user must hold.
	 * @return array<int, array{id: int, body: string, author_id: int, created_at: string}>
	 * @throws \DomainException When the user lacks the capability.
	 */
	public function list( string $type, int $id, string $capability ): array {
		global $wpdb;
		$this->require_capability( $capability );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, body, author_id, created_at FROM ' . self::table() . ' WHERE notable_type = %s AND notable_id = %d ORDER BY id ASC', $type, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'         => (int) $row['id'],
				'body'       => (string) $row['body'],
				'author_id'  => (int) $row['author_id'],
				'created_at' => (string) $row['created_at'],
			);
		}
		return $out;
	}

	/**
	 * Require a named wiw_* capability: deny by default when none is given.
	 *
	 * @param string $capability Capability.
	 * @throws \DomainException When the user lacks it, or none was named.
	 */
	private function require_capability( string $capability ): void {
		if ( 0 !== strpos( $capability, 'wiw_' ) || ! current_user_can( $capability ) ) {
			throw new \DomainException( 'Not allowed to use internal notes.' );
		}
	}
}
