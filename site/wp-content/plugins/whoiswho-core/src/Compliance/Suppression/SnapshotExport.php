<?php
/**
 * The suppression snapshot an Administrator downloads for acquisition before each run (REQ-207, contract section 11): a
 * snapshot id, when it was generated, and one entry per suppression with its id, entity type, name and external ids. It never
 * holds the reason, the source of the entry, the requester or a staff member. It is personal data, so the file name says so and
 * the download needs wiw_export_data (Administrators by default) and is audited. In this version the transport is a download
 * (contract question U9 is open).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Suppression;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Snapshot export.
 */
final class SnapshotExport {

	public const CAPABILITY = 'wiw_export_data';
	public const ACTION     = 'wiw_suppression_snapshot';

	/**
	 * Suppression list.
	 *
	 * @var Suppression
	 */
	private Suppression $entries;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param Suppression $entries Suppression list.
	 * @param AuditLogger $audit Audit logger.
	 */
	public function __construct( Suppression $entries, AuditLogger $audit ) {
		$this->entries = $entries;
		$this->audit   = $audit;
	}

	/**
	 * Register the download action.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( is_admin() ) {
					ActionGuard::register( self::ACTION, self::CAPABILITY, array( self::class, 'handle' ) );
				}
			},
			1
		);
	}

	/**
	 * The link that downloads a snapshot, with its nonce.
	 */
	public static function url(): string {
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'_wpnonce' => ActionGuard::nonce_for( self::ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The download handler (capability and nonce already checked by ActionGuard).
	 *
	 * @param Request $request Request.
	 */
	public static function handle( Request $request ): void {
		unset( $request );
		$audit = AuditLogger::for_site();
		$file  = ( new self( new Suppression( $audit ), $audit ) )->export( get_current_user_id() );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="suppression-snapshot-' . sanitize_file_name( $file['snapshot_id'] ) . '-PERSONAL-DATA.json"' );
		echo $file['json']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a JSON download with no markup.
		exit;
	}

	/**
	 * Build the snapshot.
	 *
	 * @return array{snapshot_id: string, generated_at: string, entries: array<int, array<string, mixed>>}
	 */
	public function snapshot(): array {
		return array(
			'snapshot_id'  => wp_generate_uuid4(),
			'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'entries'      => $this->entries->snapshot(),
		);
	}

	/**
	 * The snapshot as a download; audited.
	 *
	 * @param int $actor_id The person downloading.
	 * @return array{snapshot_id: string, json: string}
	 * @throws \DomainException Without wiw_export_data.
	 */
	public function export( int $actor_id ): array {
		if ( ! user_can( $actor_id, self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Only a user with wiw_export_data may download the suppression snapshot.' );
		}
		$snapshot = $this->snapshot();
		$this->audit->log(
			'suppression.snapshot_export',
			array(
				'type' => 'suppression_snapshot',
				'id'   => $snapshot['snapshot_id'],
			),
			null,
			array( 'entries' => count( $snapshot['entries'] ) )
		);
		return array(
			'snapshot_id' => $snapshot['snapshot_id'],
			'json'        => (string) wp_json_encode( $snapshot, JSON_PRETTY_PRINT ),
		);
	}
}
