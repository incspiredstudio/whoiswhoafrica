<?php
/**
 * CSV export of customers and enquiries (REQ-077, REQ-205, REQ-069, REQ-087). Administrator only: it needs wiw_export_data, which no
 * Editor or Reviewer holds by default, and every export is written to the audit log with a row count and never a row. The columns
 * are an allowlist and carry the external id and the change timestamp, so a link to an outside system can be added later (a CSV
 * import is left for later and noted in the runbook). Cells a spreadsheet could run as a formula are neutralised.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Customer and enquiry export.
 */
final class CustomerExport {

	public const CAP              = 'wiw_export_data';
	public const CUSTOMERS_ACTION = 'wiw_export_customers';
	public const ENQUIRIES_ACTION = 'wiw_export_enquiries';
	public const MAX_ROWS         = 50000;

	/**
	 * Customer columns: header => column. The id of the customer in any outside system is the external id.
	 */
	public const CUSTOMER_COLUMNS = array(
		'External ID'   => 'external_id',
		'Name'          => 'name',
		'Segment'       => 'segment',
		'Contact email' => 'contact_email',
		'Contact phone' => 'contact_phone',
		'Enquiry ID'    => 'enquiry_external_id',
		'Created'       => 'created_at',
		'Changed'       => 'updated_at',
	);

	/**
	 * Enquiry columns: header => column. The message and internal notes are not exported.
	 */
	public const ENQUIRY_COLUMNS = array(
		'External ID'   => 'external_id',
		'Reference'     => 'reference',
		'Name'          => 'requester_name',
		'Organisation'  => 'organisation',
		'Email'         => 'requester_email',
		'Phone'         => 'requester_phone',
		'Interested in' => 'interest',
		'Source'        => 'source',
		'Status'        => 'status',
		'Created'       => 'created_at',
		'Changed'       => 'updated_at',
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
	 * Register the two guarded download actions, in the admin only.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				foreach ( array(
					self::CUSTOMERS_ACTION => 'customers',
					self::ENQUIRIES_ACTION => 'enquiries',
				) as $action => $name ) {
					ActionGuard::register(
						$action,
						self::CAP,
						static function ( Request $request ) use ( $name ): void {
							unset( $request );
							$export = new self( AuditLogger::for_site() );
							$csv    = 'customers' === $name ? $export->customers_csv() : $export->enquiries_csv();
							nocache_headers();
							header( 'Content-Type: text/csv; charset=utf-8' );
							header( 'Content-Disposition: attachment; filename="' . $name . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
							header( 'X-Robots-Tag: noindex' );
							echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download, cells neutralised in build().
							exit;
						}
					);
				}
			},
			1
		);
	}

	/**
	 * The customers as CSV.
	 *
	 * @return string CSV text.
	 * @throws \DomainException   When the user may not export.
	 * @throws \RuntimeException When the file cannot be built.
	 */
	public function customers_csv(): string {
		$this->require_capability();
		global $wpdb;
		$c    = CustomerRepository::table();
		$s    = SupportItems::table();
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT c.external_id, c.name, c.segment, c.contact_email, c.contact_phone, e.external_id AS enquiry_external_id, c.created_at, c.updated_at FROM ' . $c . ' c LEFT JOIN ' . $s . ' e ON e.id = c.enquiry_id ORDER BY c.id LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
				self::MAX_ROWS
			),
			ARRAY_A
		);
		return $this->build( self::CUSTOMER_COLUMNS, $rows, 'customers' );
	}

	/**
	 * The enquiries as CSV.
	 *
	 * @return string CSV text.
	 * @throws \DomainException   When the user may not export.
	 * @throws \RuntimeException When the file cannot be built.
	 */
	public function enquiries_csv(): string {
		$this->require_capability();
		global $wpdb;
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT external_id, reference, requester_name, organisation, requester_email, requester_phone, interest, source, status, created_at, updated_at FROM ' . SupportItems::table() . ' WHERE item_type = %s ORDER BY id LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
				ItemType::ENQUIRY,
				self::MAX_ROWS
			),
			ARRAY_A
		);
		return $this->build( self::ENQUIRY_COLUMNS, $rows, 'enquiries' );
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
	 * Build the CSV and write the audit row.
	 *
	 * @param array<string, string>           $columns Header => column.
	 * @param array<int, array<string,mixed>> $rows    Rows.
	 * @param string                          $name    Name of the list, for the audit row.
	 * @throws \RuntimeException When the file cannot be built.
	 */
	private function build( array $columns, array $rows, string $name ): string {
		$stream = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory stream.
		if ( false === $stream ) {
			throw new \RuntimeException( 'The export could not be built.' );
		}
		fputcsv( $stream, array_keys( $columns ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- an in-memory stream.
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $columns as $column ) {
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
				'id'   => $name,
			),
			count( $rows ) . ' rows'
		);
		return $csv;
	}

	/**
	 * Refuse without the capability.
	 *
	 * @throws \DomainException When the user lacks it.
	 */
	private function require_capability(): void {
		if ( ! current_user_can( self::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'You are not allowed to export data.' );
		}
	}
}
