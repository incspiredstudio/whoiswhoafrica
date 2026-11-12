<?php
/**
 * The import result sent back to acquisition (contract section 12): the batch id, the role of the person who confirmed it (never
 * their name), a timestamp, counts by result code and a [record_id, code] pair per record. It contains no field values and no
 * names. In this version the transport is a download for Administrators (contract question U9 is open); each download is audited.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Result;

use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Review\StateException;

/**
 * Import result.
 */
final class ImportResult {

	public const CAPABILITY = 'wiw_export_data';
	public const ACTION     = 'wiw_import_result';

	/**
	 * Register the download action.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( is_admin() ) {
					\WhoIsWho\Core\Platform\Admin\ActionGuard::register( self::ACTION, self::CAPABILITY, array( self::class, 'handle' ) );
				}
			},
			1
		);
	}

	/**
	 * The download handler (capability and nonce already checked by ActionGuard).
	 *
	 * @param \WhoIsWho\Core\Platform\Admin\Request $request Request carrying the batch id.
	 */
	public static function handle( \WhoIsWho\Core\Platform\Admin\Request $request ): void {
		try {
			$json = ( new self() )->export( $request->string( 'batch' ), get_current_user_id() );
		} catch ( StateException $e ) {
			unset( $e );
			wp_die( esc_html__( 'That batch has no result to download.', 'whoiswho' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="import-result-' . sanitize_file_name( $request->string( 'batch' ) ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a JSON download with no markup.
		exit;
	}

	/**
	 * The result of a batch.
	 *
	 * @param string $batch_id Batch id.
	 * @return array{batch_id: string, importer_role: string, generated_at: string, counts: array<string, int>, records: array<int, array{0: string, 1: string}>}
	 * @throws StateException When the batch is unknown.
	 */
	public function for_batch( string $batch_id ): array {
		$batch = ( new BatchRepository() )->get( $batch_id );
		if ( null === $batch ) {
			throw StateException::refuse( 'Unknown batch.' );
		}
		$user    = get_userdata( (int) $batch['created_by'] );
		$roles   = false === $user ? array() : array_values( $user->roles );
		$results = new ResultRepository();
		return array(
			'batch_id'      => $batch_id,
			'importer_role' => (string) ( $roles[0] ?? '' ),
			'generated_at'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'counts'        => $results->counts( $batch_id ),
			'records'       => $results->pairs( $batch_id ),
		);
	}

	/**
	 * The result as JSON for a download; audited.
	 *
	 * @param string $batch_id Batch id.
	 * @param int    $actor_id The person downloading.
	 * @throws StateException When not allowed or the batch is unknown.
	 */
	public function export( string $batch_id, int $actor_id ): string {
		if ( ! user_can( $actor_id, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw StateException::refuse( 'Not allowed.' );
		}
		$result = $this->for_batch( $batch_id );
		AuditLogger::for_site()->log(
			'import.result_export',
			array(
				'type' => 'import_batch',
				'id'   => $batch_id,
			),
			null,
			array( 'records' => count( $result['records'] ) ),
			'',
			$batch_id
		);
		return (string) wp_json_encode( $result, JSON_PRETTY_PRINT );
	}
}
