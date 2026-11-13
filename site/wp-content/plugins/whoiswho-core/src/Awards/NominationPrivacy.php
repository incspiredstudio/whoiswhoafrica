<?php
/**
 * Nomination personal data in data-request exports, erasure and retention (REQ-268, REQ-064, REQ-059, REQ-063). The columns are
 * listed in the PII catalogue: the nominator's name and email, the nominee's typed name and organisation, the relationship note
 * and the evidence are exported and anonymised; the staff-only Contact email is never exported and is deleted. This class
 * registers the WordPress personal data exporter and eraser, and a retention step the retention job runs through the
 * wiw_retention_run action. A person who nominated sees only their own submissions; a nominee found by the Contact email sees
 * the nomination about them, never who nominated them and never the Contact email itself. Nothing here logs a name or address:
 * the audit entry holds ids and counts only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;

/**
 * Nomination privacy.
 */
final class NominationPrivacy {

	public const GROUP          = 'whoiswho-nominations';
	public const TABLES         = array( 'wiw_nominations' );
	public const DEFAULT_DAYS   = 730;
	public const RETENTION_HOOK = 'wiw_retention_run';

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
	 * Attach hooks for this site.
	 */
	public static function hooks(): void {
		add_filter(
			'wp_privacy_personal_data_exporters',
			static function ( $exporters ): array {
				$exporters                = is_array( $exporters ) ? $exporters : array();
				$exporters[ self::GROUP ] = array(
					'exporter_friendly_name' => 'Award nominations',
					'callback'               => static fn ( string $email, int $page = 1 ): array => ( new self( AuditLogger::for_site() ) )->export( $email, $page ),
				);
				return $exporters;
			}
		);
		add_filter(
			'wp_privacy_personal_data_erasers',
			static function ( $erasers ): array {
				$erasers                = is_array( $erasers ) ? $erasers : array();
				$erasers[ self::GROUP ] = array(
					'eraser_friendly_name' => 'Award nominations',
					'callback'             => static fn ( string $email, int $page = 1 ): array => ( new self( AuditLogger::for_site() ) )->erase( $email, $page ),
				);
				return $erasers;
			}
		);
		add_action(
			self::RETENTION_HOOK,
			static function (): void {
				( new self( AuditLogger::for_site() ) )->retain( self::retention_days() );
			}
		);
	}

	/**
	 * How long nominations are kept, in days; the retention schedule can change it through the filter.
	 */
	public static function retention_days(): int {
		return max( 1, (int) apply_filters( 'wiw_retention_days_nominations', self::DEFAULT_DAYS ) );
	}

	/**
	 * Export what is held for an address: the nominations it sent, and the nominations about the person whose Contact email it
	 * is. The Contact email column is never part of the output.
	 *
	 * @param string $email Address from the data request.
	 * @param int    $page  Page (one page holds every row).
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		unset( $page );
		$items = array();
		foreach ( $this->rows_for( $email ) as $row ) {
			$sent   = 0 === strcasecmp( (string) $row['nominator_email'], $email );
			$fields = array(
				array(
					'name'  => 'Reference',
					'value' => (string) $row['reference'],
				),
				array(
					'name'  => 'Nominee',
					'value' => (string) $row['nominee_name'],
				),
				array(
					'name'  => 'Nominee organisation',
					'value' => (string) $row['nominee_org'],
				),
				array(
					'name'  => 'Evidence',
					'value' => (string) $row['evidence'],
				),
				array(
					'name'  => 'Submitted',
					'value' => (string) $row['created_at'],
				),
			);
			if ( $sent ) {
				$fields[] = array(
					'name'  => 'Nominator name',
					'value' => (string) $row['nominator_name'],
				);
				$fields[] = array(
					'name'  => 'Nominator email',
					'value' => (string) $row['nominator_email'],
				);
				$fields[] = array(
					'name'  => 'Relationship to the nominee',
					'value' => (string) $row['relationship_note'],
				);
			}
			$items[] = array(
				'group_id'    => self::GROUP,
				'group_label' => 'Award nominations',
				'item_id'     => 'nomination-' . (int) $row['id'],
				'data'        => $fields,
			);
		}//end foreach
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase what is held for an address. A nominator's own details are anonymised and the nomination stays. For the nominee the
	 * Contact email is deleted, the typed name, organisation and evidence are anonymised, and a Live nomination goes Not live.
	 *
	 * @param string $email Address from the data request.
	 * @param int    $page  Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		unset( $page );
		global $wpdb;
		$table   = AwardsTables::table( AwardsTables::NOMINATIONS );
		$removed = 0;
		foreach ( $this->rows_for( $email ) as $row ) {
			$values = array();
			if ( 0 === strcasecmp( (string) $row['nominator_email'], $email ) ) {
				$values['nominator_name']    = PiiCatalogue::REDACTED;
				$values['nominator_email']   = null;
				$values['relationship_note'] = null === $row['relationship_note'] ? null : PiiCatalogue::REDACTED;
			}
			if ( 0 === strcasecmp( (string) $row['contact_email'], $email ) ) {
				$values['contact_email'] = null;
				$values['nominee_name']  = null === $row['nominee_name'] ? null : PiiCatalogue::REDACTED;
				$values['nominee_org']   = null === $row['nominee_org'] ? null : PiiCatalogue::REDACTED;
				$values['evidence']      = null === $row['evidence'] ? null : PiiCatalogue::REDACTED;
				$values['status']        = 'not_live';
			}
			if ( array() === $values ) {
				continue;
			}
			$values['notes']      = null === $row['notes'] ? null : PiiCatalogue::REDACTED;
			$values['updated_at'] = gmdate( 'Y-m-d H:i:s' );
			$wpdb->update( $table, $values, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->audit->log( 'nomination.erase', $this->subject( (int) $row['id'] ), null, array( 'erased' => 'data request' ) );
			++$removed;
		}//end foreach
		if ( $removed > 0 ) {
			do_action( 'wiw_purge_urls', array( home_url( '/awards/nominees/' ), home_url( '/awards/winners/' ), home_url( '/awards/' ) ), false );
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Retention: anonymise the personal data of nominations older than the period and delete their Contact emails. Idempotent;
	 * a nomination already cleared is skipped. Only the count is logged.
	 *
	 * @param int  $days    Keep for this many days.
	 * @param bool $dry_run Count only; change nothing.
	 * @return int How many nominations were (or would be) cleared.
	 */
	public function retain( int $days, bool $dry_run = false ): int {
		global $wpdb;
		$table  = AwardsTables::table( AwardsTables::NOMINATIONS );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$ids    = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $table . ' WHERE created_at < %s AND ( contact_email IS NOT NULL OR nominator_email IS NOT NULL OR ( nominator_name IS NOT NULL AND nominator_name <> %s ) OR ( nominee_name IS NOT NULL AND nominee_name <> %s ) )', $cutoff, PiiCatalogue::REDACTED, PiiCatalogue::REDACTED ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		if ( $dry_run || array() === $ids ) {
			return count( $ids );
		}
		foreach ( $ids as $id ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'UPDATE ' . $table . ' SET contact_email = NULL, nominator_email = NULL, nominator_name = IF(nominator_name IS NULL, NULL, %s), nominee_name = IF(nominee_name IS NULL, NULL, %s), nominee_org = IF(nominee_org IS NULL, NULL, %s), relationship_note = IF(relationship_note IS NULL, NULL, %s), evidence = IF(evidence IS NULL, NULL, %s), notes = IF(notes IS NULL, NULL, %s), updated_at = %s WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
					PiiCatalogue::REDACTED,
					PiiCatalogue::REDACTED,
					PiiCatalogue::REDACTED,
					PiiCatalogue::REDACTED,
					PiiCatalogue::REDACTED,
					PiiCatalogue::REDACTED,
					gmdate( 'Y-m-d H:i:s' ),
					(int) $id
				)
			);
		}
		$this->audit->log(
			'nomination.retention',
			array(
				'type' => 'nomination',
				'id'   => 'batch',
			),
			null,
			array( 'cleared' => count( $ids ) )
		);
		return count( $ids );
	}

	/**
	 * Rows where the address is the nominator or the Contact email.
	 *
	 * @param string $email Address.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows_for( string $email ): array {
		global $wpdb;
		if ( '' === trim( $email ) ) {
			return array();
		}
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, reference, nominator_name, nominator_email, contact_email, nominee_name, nominee_org, relationship_note, evidence, notes, created_at FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE nominator_email = %s OR contact_email = %s ORDER BY id', $email, $email ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * Audit subject.
	 *
	 * @param int $id Nomination.
	 * @return array{type: string, id: int}
	 */
	private function subject( int $id ): array {
		return array(
			'type' => 'nomination',
			'id'   => $id,
		);
	}
}
