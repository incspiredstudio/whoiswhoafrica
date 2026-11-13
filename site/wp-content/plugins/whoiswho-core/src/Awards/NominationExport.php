<?php
/**
 * The nominations export (REQ-031, REQ-199, REQ-268): a CSV for the Administrator, behind wiw_export_data and audited. The columns
 * are an allowlist: the nominee, category, stage, status, source, evidence, the nominator and the dates. The staff-only Contact
 * email is never a column, and nothing commercial exists in the awards tables to export (the awards module does not reference
 * commerce). Cells that a spreadsheet could run as a formula are neutralised. The audit entry holds a count, never a row.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Nomination export.
 */
final class NominationExport {

	public const ACTION   = 'wiw_export_nominations';
	public const CAP      = 'wiw_export_data';
	public const MAX_ROWS = 50000;

	/**
	 * The columns, in order: header => nominations column (or a joined one). There is no Contact email.
	 */
	public const COLUMNS = array(
		'Reference'             => 'reference',
		'Nominee'               => 'nominee',
		'Nominee organisation'  => 'nominee_org',
		'Category'              => 'category',
		'Group'                 => 'group',
		'Stage'                 => 'stage',
		'Status'                => 'status',
		'Source'                => 'source',
		'Evidence'              => 'evidence',
		'Nominator name'        => 'nominator_name',
		'Nominator email'       => 'nominator_email',
		'Relationship declared' => 'relationship_declared',
		'Submitted'             => 'created_at',
	);

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
	 * Register the guarded download action, in the admin only.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				ActionGuard::register(
					self::ACTION,
					self::CAP,
					static function ( Request $request ): void {
						unset( $request );
						$csv = ( new self( AuditLogger::for_site() ) )->csv();
						nocache_headers();
						header( 'Content-Type: text/csv; charset=utf-8' );
						header( 'Content-Disposition: attachment; filename="nominations-' . gmdate( 'Y-m-d' ) . '.csv"' );
						header( 'X-Robots-Tag: noindex' );
						echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download, cells neutralised in csv().
						exit;
					}
				);
			},
			1
		);
	}

	/**
	 * The export as CSV. Needs wiw_export_data; the use is audited with the row count.
	 *
	 * @return string CSV text.
	 * @throws \DomainException   When the user may not export.
	 * @throws \RuntimeException When the export cannot be built.
	 */
	public function csv(): string {
		if ( ! current_user_can( self::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'You are not allowed to export data.' );
		}
		$rows   = $this->rows();
		$stream = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory stream.
		if ( false === $stream ) {
			throw new \RuntimeException( 'The export could not be built.' );
		}
		fputcsv( $stream, array_keys( self::COLUMNS ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- an in-memory stream.
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( self::COLUMNS as $column ) {
				$line[] = self::cell( (string) ( $row[ $column ] ?? '' ) );
			}
			fputcsv( $stream, $line ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- an in-memory stream.
		}
		rewind( $stream );
		$csv = (string) stream_get_contents( $stream );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- an in-memory stream.
		$this->audit->log_use(
			self::CAP,
			array(
				'type' => 'export',
				'id'   => 'nominations',
			),
			count( $rows ) . ' rows'
		);
		return $csv;
	}

	/**
	 * Neutralise a cell a spreadsheet could take for a formula.
	 *
	 * @param string $value Cell text.
	 */
	public static function cell( string $value ): string {
		return '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ? "'" . $value : $value;
	}

	/**
	 * The rows: an allowlisted select, nominee name from the linked profile or the typed name.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rows(): array {
		global $wpdb;
		$n    = AwardsTables::table( AwardsTables::NOMINATIONS );
		$c    = AwardsTables::table( AwardsTables::CATEGORIES );
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT n.reference, n.profile_id, n.nominee_name, n.nominee_org, c.title AS category, c.group_key, n.stage, n.status, n.source, n.evidence, n.nominator_name, n.nominator_email, n.relationship_declared, n.created_at FROM ' . $n . ' n INNER JOIN ' . $c . ' c ON c.id = n.category_id ORDER BY n.id LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
				self::MAX_ROWS
			),
			ARRAY_A
		);
		foreach ( $rows as &$row ) {
			$row['nominee']               = (int) $row['profile_id'] > 0 ? (string) get_the_title( (int) $row['profile_id'] ) : (string) $row['nominee_name'];
			$row['group']                 = AwardsTables::GROUPS[ $row['group_key'] ] ?? (string) $row['group_key'];
			$row['relationship_declared'] = '1' === (string) $row['relationship_declared'] ? 'Yes' : 'No';
		}
		unset( $row );
		return $rows;
	}
}
