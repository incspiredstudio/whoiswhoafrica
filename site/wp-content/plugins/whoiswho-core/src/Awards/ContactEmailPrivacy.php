<?php
/**
 * Privacy handling of the nomination Contact email (REQ-268, REQ-064). The column is in the PII catalogue (erase: delete,
 * export: exclude). An erasure request for an address deletes the nominations that carry it; the retention job clears the
 * address from old nominations and keeps the rest of the row. Audit rows carry ids and counts only, never the address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Contact email privacy.
 */
final class ContactEmailPrivacy {

	/**
	 * Columns an export of a nomination may contain: the Contact email is never one of them.
	 */
	public const EXPORT_COLUMNS = array( 'id', 'category_id', 'profile_id', 'stage', 'status', 'source', 'created_at' );

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
	 * The columns an export may include.
	 *
	 * @return string[]
	 */
	public function export_columns(): array {
		return self::EXPORT_COLUMNS;
	}

	/**
	 * Erasure request: delete every nomination whose Contact email is this address.
	 *
	 * @param string $email Address (compared without regard to case).
	 * @return int Nominations deleted.
	 */
	public function erase_for_email( string $email ): int {
		global $wpdb;
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) {
			return 0;
		}
		$table = AwardsTables::table( AwardsTables::NOMINATIONS );
		$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $table . ' WHERE LOWER(contact_email) = %s', $email ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		foreach ( $ids as $id ) {
			$id = (int) $id;
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( AwardsTables::table( AwardsTables::CHANGES ), array( 'nomination_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->audit->log(
				'nomination.erased',
				array(
					'type' => 'nomination',
					'id'   => $id,
				),
				null,
				array( 'reason' => 'data_rights_erasure' )
			);
		}
		return count( $ids );
	}

	/**
	 * Retention: clear the Contact email from nominations created before the cut-off.
	 *
	 * @param \DateTimeImmutable $before Nominations created before this moment lose the address.
	 * @return int Nominations cleared.
	 */
	public function retain( \DateTimeImmutable $before ): int {
		global $wpdb;
		$table = AwardsTables::table( AwardsTables::NOMINATIONS );
		$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $table . ' WHERE contact_email IS NOT NULL AND created_at < %s', $before->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		foreach ( $ids as $id ) {
			$id = (int) $id;
			$wpdb->update( $table, array( 'contact_email' => null ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->audit->log(
				'nomination.contact_email_cleared',
				array(
					'type' => 'nomination',
					'id'   => $id,
				),
				null,
				array( 'reason' => 'retention' )
			);
		}
		return count( $ids );
	}
}
