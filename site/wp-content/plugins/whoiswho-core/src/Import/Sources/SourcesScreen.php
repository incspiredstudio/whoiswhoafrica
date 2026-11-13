<?php
/**
 * The read-only Sources screen (REQ-243, REQ-136, REQ-242; prototype A59): one row per source with its profile count, last import,
 * import errors and removals, and a link to the Profiles list filtered by that source. It has no add, edit, approve, reject or publish
 * control and registers no action handler. Needs wiw_view_sources.
 *
 * Profile count: profiles whose recorded source is the source. Removals: those profiles that are unpublished or removed. Import errors:
 * records the source's latest batch refused (a refused record has no stored source, so a batch's refusals are shown against every
 * source that batch named).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Sources;

use WhoIsWho\Core\Directory\Admin\ProfilesScreen;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;

/**
 * Sources screen.
 */
final class SourcesScreen {

	public const SCREEN     = 'wiw-sources';
	public const CAPABILITY = 'wiw_view_sources';

	/**
	 * Hook registration into the admin.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				$registry = ScreenRegistry::shared();
				$registry->register(
					self::SCREEN,
					__( 'Sources', 'whoiswho' ),
					self::CAPABILITY,
					static function (): void {
						echo ( new self() )->html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					},
					false,
					'imports'
				);
				$registry->hook();
			},
			1
		);
	}

	/**
	 * Per-source figures, by label.
	 *
	 * @return array<int, array{source_id: string, label: string, profiles: int, removals: int, errors: int, last_import_at: string}>
	 */
	public function rows(): array {
		global $wpdb;
		$counts   = array();
		$removals = array();
		$removed  = array( PubState::UNPUBLISHED, PubState::REMOVED_SUPPRESSED );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names; values prepared.
		$found = (array) $wpdb->get_results( 'SELECT source, ' . StateRepository::STATE_COLUMN . ' AS state, COUNT(*) AS n FROM ' . StateRepository::table() . ' WHERE source IS NOT NULL GROUP BY source, ' . StateRepository::STATE_COLUMN, ARRAY_A );
		// phpcs:enable
		foreach ( $found as $row ) {
			$source              = (string) $row['source'];
			$counts[ $source ]   = ( $counts[ $source ] ?? 0 ) + (int) $row['n'];
			$removals[ $source ] = ( $removals[ $source ] ?? 0 ) + ( in_array( (string) $row['state'], $removed, true ) ? (int) $row['n'] : 0 );
		}
		$results = new ResultRepository();
		$errors  = array();
		$out     = array();
		foreach ( ( new SourceRepository() )->all() as $source ) {
			$batch = (string) $source['last_batch_id'];
			if ( ! isset( $errors[ $batch ] ) ) {
				$errors[ $batch ] = 0;
				foreach ( $results->counts( $batch ) as $code => $n ) {
					$errors[ $batch ] += in_array( $code, ResultRepository::APPLIED_CODES, true ) ? 0 : $n;
				}
			}
			$id    = (string) $source['source_id'];
			$out[] = array(
				'source_id'      => $id,
				'label'          => (string) $source['label'],
				'profiles'       => $counts[ $id ] ?? 0,
				'removals'       => $removals[ $id ] ?? 0,
				'errors'         => $errors[ $batch ],
				'last_import_at' => (string) $source['last_import_at'],
			);
		}
		return $out;
	}

	/**
	 * The screen markup: a table and links, no form.
	 *
	 * @throws \DomainException Without the capability.
	 */
	public function html(): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to view sources.' );
		}
		$out  = '<div class="wrap"><h1>' . esc_html__( 'Sources', 'whoiswho' ) . '</h1><p>' . esc_html__( 'Where the profiles come from. This list is read-only; the source names come from each import manifest.', 'whoiswho' ) . '</p>';
		$out .= '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Source', 'Profiles', 'Last import', 'Import errors', 'Removals' ) as $heading ) {
			$out .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		$rows = $this->rows();
		if ( array() === $rows ) {
			$out .= '<tr><td colspan="5">' . esc_html__( 'No source has been imported yet.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$url  = add_query_arg(
				array(
					'page'   => ProfilesScreen::SCREEN,
					'source' => $row['source_id'],
				),
				admin_url( 'admin.php' )
			);
			$out .= '<tr><th scope="row">' . esc_html( $row['label'] ) . '<br /><small>' . esc_html( $row['source_id'] ) . '</small></th>';
			$out .= '<td><a href="' . esc_url( $url ) . '">' . esc_html( (string) $row['profiles'] ) . '</a></td>';
			$out .= '<td>' . esc_html( $row['last_import_at'] ) . '</td><td>' . esc_html( (string) $row['errors'] ) . '</td><td>' . esc_html( (string) $row['removals'] ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}
}
