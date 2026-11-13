<?php
/**
 * The Import screen (REQ-237, REQ-092, REQ-244; prototype A20): upload a bundle, read the dry run (result code and field diff per
 * record, filter by code, mapping step for unknown terms), confirm, watch progress, resume, undo and read the history. The
 * import-everything-as-not-public switch is shown to Administrators only. Capability wiw_run_imports on the screen and on every
 * action; each form carries a nonce through ActionGuard. Handlers stay thin and call ImportService.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Admin;

use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\Mapping\TermMapper;
use WhoIsWho\Core\Import\StateAssigner;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateException;

/**
 * Import screen.
 */
final class ImportScreen {

	public const SCREEN     = 'wiw-import';
	public const CAPABILITY = ImportService::CAPABILITY;
	public const UPLOAD     = 'wiw_import_upload';
	public const CONFIRM    = 'wiw_import_confirm';
	public const UNDO       = 'wiw_import_undo';
	public const MAP        = 'wiw_import_map';
	public const SWITCH     = 'wiw_import_switch';

	public const MAX_ROWS = 200;

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
					__( 'Import', 'whoiswho' ),
					self::CAPABILITY,
					static function (): void {
						echo ( new self() )->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					},
					false,
					'imports'
				);
				$registry->hook();
				foreach ( array(
					self::UPLOAD  => 'handle_upload',
					self::CONFIRM => 'handle_confirm',
					self::UNDO    => 'handle_undo',
					self::MAP     => 'handle_map',
					self::SWITCH  => 'handle_switch',
				) as $action => $method ) {
					ActionGuard::register( $action, self::CAPABILITY, array( self::class, $method ) );
				}
			},
			1
		);
	}

	/**
	 * Upload handler (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_upload( Request $request ): void {
		try {
			$batch = ( new ImportService() )->stage( $request->file( 'bundle' ), get_current_user_id() );
			self::back( 'previewed', $batch );
		} catch ( StateException $e ) {
			self::back( 'refused', '', $e->getMessage() );
		}
	}

	/**
	 * Confirm (or resume) handler.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_confirm( Request $request ): void {
		$batch = $request->string( 'batch' );
		try {
			( new ImportService() )->confirm( $batch, get_current_user_id() );
			self::back( 'started', $batch );
		} catch ( StateException $e ) {
			self::back( 'refused', $batch, $e->getMessage() );
		}
	}

	/**
	 * Undo handler.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_undo( Request $request ): void {
		$batch = $request->string( 'batch' );
		try {
			( new ImportService() )->undo( $batch, get_current_user_id() );
			self::back( 'undone', $batch );
		} catch ( StateException $e ) {
			self::back( 'refused', $batch, $e->getMessage() );
		}
	}

	/**
	 * Mapping handler: save a term mapping (or skip) for an unknown value.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_map( Request $request ): void {
		$batch = $request->string( 'batch' );
		try {
			( new TermMapper() )->save( $request->string( 'field' ), $request->string( 'value' ), $request->int( 'term_id' ) );
			self::back( 'mapped', $batch );
		} catch ( \InvalidArgumentException $e ) {
			self::back( 'refused', $batch, $e->getMessage() );
		}
	}

	/**
	 * Switch handler: the import-everything-as-not-public setting, Administrators only.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_switch( Request $request ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'whoiswho' ), '', array( 'response' => 403 ) );
		}
		try {
			SettingsService::for_site()->set( StateAssigner::SETTING, 'on' === $request->string( 'value' ), 'Import screen' );
			self::back( 'switched' );
		} catch ( \Throwable $e ) {
			unset( $e );
			self::back( 'refused', '', 'The setting could not be changed.' );
		}
	}

	/**
	 * Redirect back to the screen.
	 *
	 * @param string $result  Result word.
	 * @param string $batch   Batch id to show.
	 * @param string $message A refusal message fit to show.
	 */
	private static function back( string $result, string $batch = '', string $message = '' ): void {
		$args = array(
			'page'   => self::SCREEN,
			'result' => $result,
		);
		if ( '' !== $batch ) {
			$args['batch'] = $batch;
		}
		if ( '' !== $message ) {
			$args['why'] = $message;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request (batch, code, result, why).
	 * @throws \DomainException Without the capability.
	 */
	public function html( Request $request ): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to run imports.' );
		}
		$out  = '<div class="wrap"><h1>' . esc_html__( 'Import', 'whoiswho' ) . '</h1>';
		$out .= $this->notice( $request );
		$out .= $this->upload_form();
		if ( current_user_can( 'manage_options' ) ) {
			$out .= $this->switch_form();
		}
		$batch_id = $request->string( 'batch' );
		$batch    = '' === $batch_id ? null : ( new BatchRepository() )->get( $batch_id );
		if ( null !== $batch ) {
			$out .= $this->batch_html( $batch, $request->string( 'code' ) );
		}
		return $out . $this->history() . '</div>';
	}

	/**
	 * The result notice.
	 *
	 * @param Request $request Request.
	 */
	private function notice( Request $request ): string {
		$words  = array(
			'previewed' => __( 'Dry run done. Nothing has been written yet. Read the result below, then confirm.', 'whoiswho' ),
			'started'   => __( 'Import started. It runs in the background and resumes where it stopped.', 'whoiswho' ),
			'undone'    => __( 'Batch undone. Reviewed profiles were kept.', 'whoiswho' ),
			'mapped'    => __( 'Mapping saved. Check the dry run again.', 'whoiswho' ),
			'switched'  => __( 'Setting saved.', 'whoiswho' ),
		);
		$result = $request->string( 'result' );
		if ( 'refused' === $result ) {
			return '<div class="notice notice-error"><p>' . esc_html( '' === $request->string( 'why' ) ? __( 'That was refused.', 'whoiswho' ) : $request->string( 'why' ) ) . '</p></div>';
		}
		return isset( $words[ $result ] ) ? '<div class="notice notice-success"><p>' . esc_html( $words[ $result ] ) . '</p></div>' : '';
	}

	/**
	 * A form opening tag with the action and nonce fields.
	 *
	 * @param string $action Action.
	 * @param bool   $upload Whether the form uploads a file.
	 */
	private function form( string $action, bool $upload = false ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '>'
			. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( $action ) ) . '" />';
	}

	/**
	 * The upload form.
	 */
	private function upload_form(): string {
		$limit = (string) ( BundleStore::MAX_BYTES / 1048576 );
		$out   = '<h2>' . esc_html__( 'Upload a bundle', 'whoiswho' ) . '</h2>' . $this->form( self::UPLOAD, true );
		/* translators: %s: size limit in megabytes. */
		$out .= '<p><label>' . esc_html__( 'Bundle (.zip with manifest.json and records.jsonl)', 'whoiswho' ) . ' <input type="file" name="bundle" accept=".zip" required="required" /></label> ';
		/* translators: %s: size limit in megabytes. */
		$out .= '<small>' . esc_html( sprintf( __( 'Up to %s MB. Stored in a private folder outside the web root.', 'whoiswho' ), $limit ) ) . '</small></p>';
		return $out . '<p><button class="button button-primary">' . esc_html__( 'Upload and run the dry run', 'whoiswho' ) . '</button></p></form>';
	}

	/**
	 * The import-everything-as-not-public switch (Administrators only).
	 */
	private function switch_form(): string {
		$on   = StateAssigner::import_all_is_on( SettingsService::for_site() );
		$out  = '<h2>' . esc_html__( 'Import everything as not public', 'whoiswho' ) . '</h2>' . $this->form( self::SWITCH );
		$out .= '<p>' . esc_html( $on ? __( 'On: every imported profile lands as Imported not public.', 'whoiswho' ) : __( 'Off: records land in the state the contract rules give them.', 'whoiswho' ) ) . '</p>';
		return $out . '<input type="hidden" name="value" value="' . esc_attr( $on ? 'off' : 'on' ) . '" /><p><button class="button">' . esc_html( $on ? __( 'Turn off', 'whoiswho' ) : __( 'Turn on', 'whoiswho' ) ) . '</button></p></form>';
	}

	/**
	 * One batch: dry run, mapping, confirm, progress and undo.
	 *
	 * @param array<string, mixed> $batch Batch row.
	 * @param string               $code  Result code filter.
	 */
	private function batch_html( array $batch, string $code ): string {
		$id     = (string) $batch['batch_id'];
		$status = (string) $batch['status'];
		$out    = '<h2>' . esc_html( sprintf( /* translators: %s: batch id. */ __( 'Batch %s', 'whoiswho' ), $id ) ) . '</h2>';
		$out   .= '<p>' . esc_html( sprintf( /* translators: 1: status, 2: records handled, 3: records in the bundle. */ __( 'Status: %1$s. %2$d of %3$d records handled.', 'whoiswho' ), $status, (int) $batch['progress_cursor'], (int) $batch['record_count'] ) ) . '</p>';
		if ( in_array( $status, array( 'checked', 'dry_run' ), true ) ) {
			$out .= $this->dry_run_html( $id, $code );
		} else {
			$out .= $this->counts_html( ( new ResultRepository() )->counts( $id ), $id, $code );
		}
		if ( in_array( $status, array( 'dry_run', 'applying' ), true ) ) {
			$label = 'applying' === $status ? __( 'Resume import', 'whoiswho' ) : __( 'Confirm and import', 'whoiswho' );
			$out  .= $this->form( self::CONFIRM ) . '<input type="hidden" name="batch" value="' . esc_attr( $id ) . '" /><p><button class="button button-primary">' . esc_html( $label ) . '</button></p></form>';
		}
		if ( 'applied' === $status ) {
			$out .= $this->form( self::UNDO ) . '<input type="hidden" name="batch" value="' . esc_attr( $id ) . '" /><p><button class="button">' . esc_html__( 'Undo this batch', 'whoiswho' ) . '</button> <small>' . esc_html__( 'Reviewed profiles are kept.', 'whoiswho' ) . '</small></p></form>';
		}
		return $out;
	}

	/**
	 * The dry run: counts, filter links, records with code and diff, and the mapping step.
	 *
	 * @param string $id   Batch id.
	 * @param string $code Result code filter.
	 */
	private function dry_run_html( string $id, string $code ): string {
		try {
			$preview = ( new ImportService() )->preview( $id, get_current_user_id() );
		} catch ( StateException $e ) {
			return '<p>' . esc_html( $e->getMessage() ) . '</p>';
		}
		$out     = $this->counts_html( $preview['counts'], $id, $code );
		$rows    = '';
		$shown   = 0;
		$mapping = array();
		foreach ( $preview['records'] as $entry ) {
			foreach ( (array) $entry['unmapped'] as $term ) {
				$mapping[ $term['field'] . '|' . $term['value'] ] = $term;
			}
			if ( '' !== $code && $entry['code'] !== $code ) {
				continue;
			}
			if ( $shown >= self::MAX_ROWS ) {
				continue;
			}
			++$shown;
			$rows .= '<tr><td>' . esc_html( (string) $entry['record_id'] ) . '</td><td><code>' . esc_html( (string) $entry['code'] ) . '</code></td><td>' . esc_html( implode( ', ', (array) $entry['flags'] ) ) . '</td><td>' . $this->diff_html( (array) $entry['diff'], (string) $entry['field'] ) . '</td></tr>';
		}
		$out .= '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Record', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Result', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Flags', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Changes', 'whoiswho' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
		/* translators: %d: rows shown. */
		$out .= '<p><small>' . esc_html( sprintf( __( 'Showing up to %d records.', 'whoiswho' ), self::MAX_ROWS ) ) . '</small></p>';
		foreach ( $mapping as $term ) {
			$out .= $this->mapping_form( $id, (string) $term['field'], (string) $term['value'] );
		}
		return $out;
	}

	/**
	 * Counts by code as filter links.
	 *
	 * @param array<string, int> $counts Counts.
	 * @param string             $id     Batch id.
	 * @param string             $active Active filter.
	 */
	private function counts_html( array $counts, string $id, string $active ): string {
		$out  = '<p>';
		$all  = add_query_arg(
			array(
				'page'  => self::SCREEN,
				'batch' => $id,
			),
			admin_url( 'admin.php' )
		);
		$out .= '<a href="' . esc_url( $all ) . '">' . esc_html__( 'All', 'whoiswho' ) . '</a>';
		foreach ( $counts as $code => $n ) {
			$link = add_query_arg( 'code', (string) $code, $all );
			$out .= ' | <a href="' . esc_url( $link ) . '"' . ( $active === (string) $code ? ' aria-current="true"' : '' ) . '>' . esc_html( $code . ' (' . $n . ')' ) . '</a>';
		}
		return $out . '</p>';
	}

	/**
	 * The field diff of one record.
	 *
	 * @param array<int, array<string, mixed>> $diff  Diff.
	 * @param string                           $field Offending field of a refused record.
	 */
	private function diff_html( array $diff, string $field ): string {
		if ( array() === $diff ) {
			return '' === $field ? '' : esc_html( $field );
		}
		$out = '<ul>';
		foreach ( $diff as $change ) {
			$out .= '<li><strong>' . esc_html( (string) $change['field'] ) . '</strong> (' . esc_html( (string) $change['change'] ) . '): ';
			if ( 'added' !== $change['change'] ) {
				$out .= '<del>' . esc_html( $this->text( $change['old'] ) ) . '</del> ';
			}
			if ( 'removed' !== $change['change'] ) {
				$out .= '<ins>' . esc_html( $this->text( $change['new'] ) ) . '</ins>';
			}
			$out .= '</li>';
		}
		return $out . '</ul>';
	}

	/**
	 * A value as short text.
	 *
	 * @param mixed $value Value.
	 */
	private function text( $value ): string {
		$text = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
		return strlen( $text ) > 200 ? substr( $text, 0, 200 ) . '...' : $text;
	}

	/**
	 * The mapping form for one unknown term.
	 *
	 * @param string $id    Batch id.
	 * @param string $field Record field.
	 * @param string $value The unknown value.
	 */
	private function mapping_form( string $id, string $field, string $value ): string {
		$taxonomy = TermMapper::FIELDS[ $field ] ?? '';
		$terms    = '' === $taxonomy ? array() : get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		$out      = $this->form( self::MAP ) . '<input type="hidden" name="batch" value="' . esc_attr( $id ) . '" /><input type="hidden" name="field" value="' . esc_attr( $field ) . '" /><input type="hidden" name="value" value="' . esc_attr( $value ) . '" />';
		/* translators: 1: field, 2: the unknown value. */
		$out .= '<p><label>' . esc_html( sprintf( __( 'Map %1$s "%2$s" to', 'whoiswho' ), $field, $value ) ) . ' <select name="term_id"><option value="0">' . esc_html__( 'Skip this value', 'whoiswho' ) . '</option>';
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$out .= '<option value="' . esc_attr( (string) $term->term_id ) . '">' . esc_html( $term->name ) . '</option>';
		}
		return $out . '</select></label> <button class="button">' . esc_html__( 'Save mapping', 'whoiswho' ) . '</button></p></form>';
	}

	/**
	 * The history of batches.
	 */
	private function history(): string {
		$rows = '';
		foreach ( ( new BatchRepository() )->recent( 20 ) as $batch ) {
			$link  = add_query_arg(
				array(
					'page'  => self::SCREEN,
					'batch' => (string) $batch['batch_id'],
				),
				admin_url( 'admin.php' )
			);
			$rows .= '<tr><td><a href="' . esc_url( $link ) . '">' . esc_html( (string) $batch['batch_id'] ) . '</a></td><td>' . esc_html( (string) $batch['status'] ) . '</td><td>' . esc_html( (string) $batch['record_count'] ) . '</td><td>' . esc_html( (string) $batch['created_at'] ) . '</td></tr>';
		}
		return '<h2>' . esc_html__( 'History', 'whoiswho' ) . '</h2><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Batch', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Status', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Records', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Uploaded', 'whoiswho' ) . '</th></tr></thead><tbody>' . ( '' === $rows ? '<tr><td colspan="4">' . esc_html__( 'No imports yet.', 'whoiswho' ) . '</td></tr>' : $rows ) . '</tbody></table>';
	}
}
