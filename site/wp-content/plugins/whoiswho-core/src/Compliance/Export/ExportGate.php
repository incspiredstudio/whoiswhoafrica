<?php
/**
 * Bulk personal-data export is for the Administrator only, and every export is audited (REQ-069, REQ-087). The core Tools > Export
 * and the personal-data export tool answer to wiw_export_data instead of their own capabilities, so a Reviewer or Editor sees no
 * export in the menu and cannot reach it by URL. List-table exports call authorise() before they send a file. The audit row names
 * the actor and the scope (a content type or a list name) and never any personal data.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Export;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Export gate.
 */
final class ExportGate {

	public const CAPABILITY = 'wiw_export_data';

	/**
	 * Core capabilities that guard bulk exports of data.
	 */
	private const CORE_CAPABILITIES = array( 'export', 'export_others_personal_data' );

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
	 * Gate for this site.
	 */
	public static function for_site(): self {
		return new self( AuditLogger::for_site() );
	}

	/**
	 * Register the hooks.
	 */
	public function register(): void {
		add_filter( 'map_meta_cap', array( $this, 'map' ), 10, 2 );
		add_action( 'export_wp', array( $this, 'on_core_export' ), 0 );
	}

	/**
	 * Remove the hooks (tests).
	 */
	public function unregister(): void {
		remove_filter( 'map_meta_cap', array( $this, 'map' ), 10 );
		remove_action( 'export_wp', array( $this, 'on_core_export' ), 0 );
	}

	/**
	 * A core export capability needs wiw_export_data as well.
	 *
	 * @param mixed  $caps Required primitive capabilities.
	 * @param string $cap  Capability being checked.
	 * @return mixed
	 */
	public function map( $caps, $cap ) {
		if ( ! is_array( $caps ) || ! in_array( $cap, self::CORE_CAPABILITIES, true ) ) {
			return $caps;
		}
		$caps[] = self::CAPABILITY;
		return array_values( array_unique( $caps ) );
	}

	/**
	 * The core export is about to run: refuse without the capability, otherwise write the audit row.
	 *
	 * @param mixed $args Export arguments.
	 */
	public function on_core_export( $args = array() ): void {
		$content = is_array( $args ) && isset( $args['content'] ) && is_string( $args['content'] ) ? $args['content'] : 'all';
		// Only the content type is recorded: authors, search terms and dates in the arguments can be personal data.
		$content = 1 === preg_match( '/^[a-z0-9_-]{1,40}$/', $content ) ? $content : 'all';
		if ( ! $this->authorise( $content, null, 'wp_export' ) ) {
			wp_die( esc_html__( 'You are not allowed to export data.', 'whoiswho' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Check that the current user may export, and record the export. Call before sending a file.
	 *
	 * @param string   $scope The list or content type exported (never a person's name or address).
	 * @param int|null $rows  Number of rows, when known.
	 * @param string   $type  Audit subject type.
	 * @return bool False when the user may not export (nothing is recorded).
	 */
	public function authorise( string $scope, ?int $rows = null, string $type = 'list_export' ): bool {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}
		$after = array( 'capability' => self::CAPABILITY );
		if ( null !== $rows ) {
			$after['rows'] = $rows;
		}
		$this->audit->log(
			'export.download',
			array(
				'type' => $type,
				'id'   => $scope,
			),
			null,
			$after
		);
		return true;
	}
}
