<?php
/**
 * CSV export of sponsored story deals (REQ-087, REQ-069, REQ-077). Administrator only: it needs wiw_export_data, which no Editor or
 * Reviewer holds by default, and every export is written to the audit log with a row count and never a row. Each row carries the
 * counting status (does the deal count toward the sprint target) and what is missing when it does not, so the register can be
 * checked in a spreadsheet. Amounts are whole minor units with the currency. Cells a spreadsheet could run as a formula are
 * neutralised (CustomerExport::cell). Nothing here touches a card number or a payment gateway.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Deal export.
 */
final class DealExport {

	public const CAP      = CustomerExport::CAP;
	public const ACTION   = 'wiw_export_deals';
	public const MAX_ROWS = 50000;

	/**
	 * Columns, in order.
	 */
	public const COLUMNS = array(
		'Deal ID',
		'Customer',
		'Story',
		'Amount (minor units)',
		'Currency',
		'Method',
		'Reference',
		'Date received',
		'Status',
		'Counts toward the target',
		'Missing',
		'Created',
		'Changed',
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
						header( 'Content-Disposition: attachment; filename="sponsored-deals-' . gmdate( 'Y-m-d' ) . '.csv"' );
						header( 'X-Robots-Tag: noindex' );
						echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download, cells neutralised in build().
						exit;
					}
				);
			},
			1
		);
	}

	/**
	 * The deals as CSV.
	 *
	 * @return string CSV text.
	 * @throws \DomainException When the user may not export.
	 */
	public function csv(): string {
		if ( ! current_user_can( self::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'You are not allowed to export data.' );
		}
		$deals     = new ArrangementRepository();
		$places    = new PlacementRepository();
		$customers = new CustomerRepository();
		$today     = gmdate( 'Y-m-d' );
		$lines     = array();
		foreach ( array_slice( $deals->all(), 0, self::MAX_ROWS ) as $row ) {
			$placements = $places->for_arrangement( (int) $row['id'] );
			$customer   = $customers->get( (int) $row['customer_id'] );
			$lines[]    = array(
				(string) $row['external_id'],
				null === $customer ? '' : (string) $customer['name'],
				empty( $row['story_id'] ) ? '' : get_the_title( (int) $row['story_id'] ),
				isset( $row['amount_minor'] ) ? (string) (int) $row['amount_minor'] : '',
				(string) $row['currency'],
				(string) $row['method'],
				(string) $row['reference'],
				(string) ( $row['received_on'] ?? '' ),
				DealStatus::of( $row, $placements, $today ),
				DealStatus::counts( $row ) ? 'Yes' : 'No',
				implode( '; ', self::missing( $row ) ),
				(string) $row['created_at'],
				(string) $row['updated_at'],
			);
		}
		return $this->build( $lines );
	}

	/**
	 * What stops a deal from counting, in words: not agreed, cancelled, and each payment field that is empty.
	 *
	 * @param array<string, mixed> $deal Deal row.
	 * @return string[]
	 */
	public static function missing( array $deal ): array {
		$missing = array();
		if ( empty( $deal['agreed'] ) ) {
			$missing[] = 'Deal not agreed';
		}
		if ( ! empty( $deal['cancelled'] ) ) {
			$missing[] = 'Cancelled';
		}
		return array_merge( $missing, array_values( DealStatus::missing_payment_fields( $deal ) ) );
	}

	/**
	 * Build the CSV and write the audit row.
	 *
	 * @param array<int, string[]> $lines Rows of cells.
	 * @throws \RuntimeException When the file cannot be built.
	 */
	private function build( array $lines ): string {
		$stream = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory stream.
		if ( false === $stream ) {
			throw new \RuntimeException( 'The export could not be built.' );
		}
		fputcsv( $stream, self::COLUMNS ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- an in-memory stream.
		foreach ( $lines as $line ) {
			fputcsv( $stream, array_map( array( CustomerExport::class, 'cell' ), $line ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- an in-memory stream.
		}
		rewind( $stream );
		$csv = (string) stream_get_contents( $stream );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- an in-memory stream.
		$this->audit->log_use(
			self::CAP,
			array(
				'type' => 'export',
				'id'   => 'deals',
			),
			count( $lines ) . ' rows'
		);
		return $csv;
	}
}
