<?php
/**
 * The Import screen (REQ-237, REQ-092, REQ-244; prototype A20): upload a bundle, read the dry run (result code and field diff per
 * record, filter by code, mapping step for unknown terms), confirm, watch progress, resume, undo and read the history. The
 * import-everything-as-not-public switch is shown to Administrators only. Capability wiw_run_imports on the screen and on every
 * action; each form carries a nonce through ActionGuard. Handlers stay thin and call ImportService. The forms use the staff form kit
 * and the history is a shared staff list table (ImportBatchesTable, REQ-275) with Open and Undo row actions and bulk Undo selected.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Admin;

use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Import\Images\ImageLog;
use WhoIsWho\Core\Import\Mapping\TermMapper;
use WhoIsWho\Core\Import\StateAssigner;
use WhoIsWho\Core\Import\Undo\BatchUndo;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
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
	public const STEP       = 'wiw_import_step';
	public const PIECE      = 'wiw_import_piece';
	public const SERVER     = 'wiw_import_server';

	public const MAX_ROWS = 200;

	/**
	 * One-by-one mapping forms shown; the rest are covered by "Add all".
	 */
	public const MAX_MAPPING_FORMS = 30;

	/**
	 * Size of one upload piece sent by the screen's script: under the smallest common PHP upload limit (2 MB).
	 */
	public const PIECE_BYTES = 1048576;

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
					self::SERVER  => 'handle_server',
				) as $action => $method ) {
					ActionGuard::register( $action, self::CAPABILITY, array( self::class, $method ) );
				}
				ActionGuard::register( self::STEP, self::CAPABILITY, array( self::class, 'handle_step' ), true );
				ActionGuard::register( self::PIECE, self::CAPABILITY, array( self::class, 'handle_piece' ), true );
				ImportBatchesTable::register_bulk_handler();
			},
			1
		);
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Load the screen's script on the Import screen only: the upload in pieces and the progress steps.
	 *
	 * @param string $hook Admin page hook (unused: the page query argument decides).
	 */
	public static function enqueue( string $hook ): void {
		unset( $hook );
		if ( self::SCREEN !== Request::from_globals()->string( 'page' ) || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return;
		}
		wp_enqueue_script( 'wiw-import', plugins_url( 'assets/admin/import/import.js', WIW_CORE_FILE ), array(), WIW_CORE_VERSION, true );
		wp_localize_script(
			'wiw-import',
			'wiwImport',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'screen'    => admin_url( 'admin.php?page=' . self::SCREEN ),
				'pieceSize' => self::PIECE_BYTES,
				'actions'   => array(
					'step'  => self::STEP,
					'piece' => self::PIECE,
				),
				'nonces'    => array(
					'step'  => ActionGuard::nonce_for( self::STEP ),
					'piece' => ActionGuard::nonce_for( self::PIECE ),
				),
				'text'      => array(
					/* translators: %d: percent uploaded. */
					'uploading' => __( 'Uploading: %d%%', 'whoiswho' ),
					/* translators: 1: records checked, 2: records in the bundle. */
					'checking'  => __( 'Checking records: %1$d of %2$d', 'whoiswho' ),
					/* translators: 1: records imported, 2: records in the bundle. */
					'importing' => __( 'Importing records: %1$d of %2$d', 'whoiswho' ),
					'busy'      => __( 'Another window is working on this batch. Waiting...', 'whoiswho' ),
					'failed'    => __( 'The connection dropped. Press Continue to carry on where it stopped.', 'whoiswho' ),
				),
			)
		);
	}

	/**
	 * One step of the dry run or the apply (capability and nonce already checked). The screen's script calls it again and again
	 * and draws the progress; without the script, the Continue button posts here and comes back to the screen.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_step( Request $request ): void {
		$batch = $request->string( 'batch' );
		try {
			$out = ( new ImportService() )->step( $batch, get_current_user_id() );
		} catch ( StateException $e ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => $e->getMessage() ), 400 );
			}
			self::back( 'refused', $batch, $e->getMessage() );
			return;
		}
		if ( wp_doing_ajax() ) {
			wp_send_json_success( $out );
		}
		self::back( 'stepped', $batch );
	}

	/**
	 * One piece of a zip uploaded in pieces (AJAX only; capability and nonce already checked).
	 *
	 * @param Request $request Request: upload_id, offset, total, name and the file "piece".
	 */
	public static function handle_piece( Request $request ): void {
		$file = $request->file( 'piece' );
		if ( null === $file || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The upload failed. Try again.', 'whoiswho' ) ), 400 );
		}
		try {
			$out = ( new ImportService() )->receive_piece( $request->string( 'upload_id' ), $request->int( 'offset' ), $request->int( 'total' ), sanitize_file_name( $request->string( 'name' ) ), (string) $file['tmp_name'], get_current_user_id() );
		} catch ( StateException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 400 );
		}
		if ( '' !== $out['batch'] ) {
			$out['redirect'] = add_query_arg(
				array(
					'page'   => self::SCREEN,
					'result' => 'previewed',
					'batch'  => $out['batch'],
				),
				admin_url( 'admin.php' )
			);
		}
		wp_send_json_success( $out );
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
			$out = ( new ImportService() )->undo( $batch, get_current_user_id() );
			self::back( $out['done'] ? 'undone' : 'undoing', $batch );
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
			if ( '' !== $batch ) {
				( new ImportService() )->restart_dry_run( $batch, get_current_user_id() );
			}
			self::back( 'mapped', $batch );
		} catch ( \InvalidArgumentException | StateException $e ) {
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
		$out = '<div class="wrap wiw-staff wiw-import"><h1>' . esc_html__( 'Import', 'whoiswho' ) . '</h1>';
		if ( '' !== $request->string( ListTable::STEP_ACTION ) ) {
			// The confirmation step of a bulk action (or of a row's Undo) stands alone.
			return $out . ( new ImportBatchesTable() )->html( $request ) . '</div>';
		}
		$out .= '<p class="wiw-lead">' . esc_html__( 'Upload a bundle from the acquisition pipeline, read the dry run, then confirm. Nothing is written before you confirm, and a batch can be undone.', 'whoiswho' ) . '</p>';
		$out .= $this->notice( $request );
		$out .= $this->upload_form();
		$out .= $this->server_html();
		if ( current_user_can( 'manage_options' ) ) {
			$out .= $this->switch_form();
		}
		$batch_id = $request->string( 'batch' );
		$batch    = '' === $batch_id ? null : ( new BatchRepository() )->get( $batch_id );
		if ( null !== $batch ) {
			$out .= $this->batch_html( $batch, $request->string( 'code' ) );
		}
		return $out . $this->history( $request ) . '</div>';
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
			'undoing'   => __( 'Undo started. It carries on in steps below.', 'whoiswho' ),
			'mapped'    => __( 'Mapping saved. The dry run runs again below.', 'whoiswho' ),
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
		return '<form method="post" class="wiw-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '>'
			. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( $action ) ) . '" />';
	}

	/**
	 * The upload form.
	 */
	private function upload_form(): string {
		$limit = (string) ( BundleStore::MAX_BYTES / 1048576 );
		$out   = str_replace( '<form ', '<form data-wiw-import="upload" ', $this->form( self::UPLOAD, true ) );
		$input = '<input type="file" id="wiw-f-bundle" name="bundle" accept=".zip" required="required" aria-describedby="wiw-f-bundle-help wiw-f-bundle-limit" />';
		$out  .= Form::row(
			'wiw-f-bundle',
			__( 'Bundle (.zip with manifest.json, records.jsonl and any images folder)', 'whoiswho' ),
			$input,
			array(
				/* translators: %s: size limit in megabytes. */
				'help' => sprintf( __( 'Up to %s MB. Large files are sent in small pieces, so the host\'s upload limit does not matter. Stored in a private folder outside the web root and deleted after the import.', 'whoiswho' ), $limit ),
			)
		);
		/* translators: %s: the host's upload limit, for example "2 MB". */
		$out .= '<p class="wiw-help" id="wiw-f-bundle-limit">' . esc_html( sprintf( __( 'This server accepts uploads of up to %s at once. If the upload fails, use "Import from the server" below.', 'whoiswho' ), size_format( wp_max_upload_size() ) ) ) . '</p>';
		$out .= '<p class="wiw-import-upload-progress" data-wiw-import="upload-progress" role="status" aria-live="polite"></p>';
		$out .= Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Upload and run the dry run', 'whoiswho' ) . '</button>' ) . '</form>';
		return Form::section( __( 'Upload a bundle', 'whoiswho' ), $out, array( 'id' => 'wiw-import-upload' ) );
	}

	/**
	 * The import-everything-as-not-public switch (Administrators only): one button that turns it on or off.
	 */
	private function switch_form(): string {
		$on   = StateAssigner::import_all_is_on( SettingsService::for_site() );
		$out  = '<p>' . ListTable::pill( $on ? __( 'On', 'whoiswho' ) : __( 'Off', 'whoiswho' ), $on ? 'warning' : 'neutral' ) . ' ' . esc_html( $on ? __( 'On: every imported profile lands as Imported not public.', 'whoiswho' ) : __( 'Off: records land in the state the contract rules give them.', 'whoiswho' ) ) . '</p>';
		$out .= $this->form( self::SWITCH ) . '<input type="hidden" name="value" value="' . esc_attr( $on ? 'off' : 'on' ) . '" />';
		$out .= Form::actions( '<button type="submit" class="button">' . esc_html( $on ? __( 'Turn off', 'whoiswho' ) : __( 'Turn on', 'whoiswho' ) ) . '</button>' ) . '</form>';
		return Form::section( __( 'Import everything as not public', 'whoiswho' ), $out, array( 'id' => 'wiw-import-switch' ) );
	}
	/**
	 * One batch: dry run, mapping, confirm, progress and undo.
	 *
	 * @param array<string, mixed> $batch Batch row.
	 * @param string               $code  Result code filter.
	 */
	private function batch_html( array $batch, string $code ): string {
		$id      = (string) $batch['batch_id'];
		$status  = (string) $batch['status'];
		$out     = '<p>' . esc_html( sprintf( /* translators: 1: status, 2: records handled, 3: records in the bundle. */ __( 'Status: %1$s. %2$d of %3$d records handled.', 'whoiswho' ), $status, (int) $batch['progress_cursor'], (int) $batch['record_count'] ) ) . '</p>';
		$undoing = 'applied' === $status && BatchUndo::in_progress( $id );
		if ( in_array( $status, array( 'checked', 'applying' ), true ) || $undoing ) {
			$out .= $this->progress_html( $batch );
		}
		if ( 'dry_run' === $status ) {
			$out .= $this->dry_run_html( $id, $code );
		} elseif ( 'checked' !== $status ) {
			$out .= $this->counts_html( ( new ResultRepository() )->counts( $id ), $id, $code );
			$out .= $this->images_html( $id );
		}
		if ( 'dry_run' === $status ) {
			$out .= $this->form( self::CONFIRM ) . '<input type="hidden" name="batch" value="' . esc_attr( $id ) . '" />' . Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Confirm and import', 'whoiswho' ) . '</button>' ) . '</form>';
		}
		if ( 'applied' === $status && ! $undoing ) {
			$out .= $this->form( self::UNDO ) . '<input type="hidden" name="batch" value="' . esc_attr( $id ) . '" />' . Form::actions( '<button type="submit" class="button wiw-button--danger">' . esc_html__( 'Undo this batch', 'whoiswho' ) . '</button> <span class="wiw-help">' . esc_html__( 'Reviewed profiles are kept.', 'whoiswho' ) . '</span>' ) . '</form>';
		}
		/* translators: %s: batch id. */
		return Form::section( sprintf( __( 'Batch %s', 'whoiswho' ), $id ), $out, array( 'id' => 'wiw-import-batch' ) );
	}

	/**
	 * The dry run: counts, filter links, records with code and diff, and the mapping step.
	 *
	 * @param string $id   Batch id.
	 * @param string $code Result code filter.
	 */
	private function dry_run_html( string $id, string $code ): string {
		$stored = DryRun::stored( $id );
		if ( array() !== $stored['counts'] ) {
			$preview = array(
				'counts'  => $stored['counts'],
				'records' => $stored['rows'],
			);
			// The stored rows hold no unknown terms; the mapping step reads them from the stored list.
			foreach ( $stored['mapping'] as $term ) {
				$preview['records'][] = array(
					'record_id' => '',
					'code'      => '',
					'field'     => '',
					'diff'      => array(),
					'flags'     => array(),
					'unmapped'  => array( $term ),
					'hidden'    => true,
				);
			}
		} else {
			try {
				$preview = ( new ImportService() )->preview( $id, get_current_user_id() );
			} catch ( StateException $e ) {
				return '<p>' . esc_html( $e->getMessage() ) . '</p>';
			}
		}//end if
		$new_values = false;
		foreach ( $preview['records'] as $entry ) {
			if ( array() !== (array) ( $entry['unmapped'] ?? array() ) ) {
				$new_values = true;
				break;
			}
		}
		$out     = $this->counts_html( $preview['counts'], $id, $code, $new_values );
		$rows    = '';
		$shown   = 0;
		$mapping = array();
		foreach ( $preview['records'] as $entry ) {
			foreach ( (array) ( $entry['unmapped'] ?? array() ) as $term ) {
				$mapping[ $term['field'] . '|' . $term['value'] ] = $term;
			}
			if ( ! empty( $entry['hidden'] ) || ( '' !== $code && $entry['code'] !== $code ) ) {
				continue;
			}
			if ( $shown >= self::MAX_ROWS ) {
				continue;
			}
			++$shown;
			$rows .= '<tr><td>' . esc_html( (string) $entry['record_id'] ) . '</td><td><code>' . esc_html( (string) $entry['code'] ) . '</code></td><td>' . esc_html( implode( ', ', (array) $entry['flags'] ) ) . '</td><td>' . $this->diff_html( (array) $entry['diff'], (string) $entry['field'] ) . '</td></tr>';
		}
		$out .= '<table class="widefat striped wiw-list-table wiw-import-records"><thead><tr><th scope="col">' . esc_html__( 'Record', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Result', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Flags', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Changes', 'whoiswho' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
		/* translators: %d: rows shown. */
		$out .= '<p class="wiw-help">' . esc_html( sprintf( __( 'Showing up to %d records.', 'whoiswho' ), self::MAX_ROWS ) ) . '</p>';
		if ( array() === $mapping ) {
			return $out;
		}
		/* translators: %d: number of values not in the lists. */
		$out  .= '<h3>' . esc_html( sprintf( __( '%d values are not in the site\'s lists yet', 'whoiswho' ), count( $mapping ) ) ) . '</h3>';
		$out  .= '<p>' . esc_html__( 'New head office cities are added by the import under each record\'s country. These cities have no country to go under (the record has none, or is Pan-African), so the records import without them unless you match each one to a place below.', 'whoiswho' ) . '</p>';
		$shown = 0;
		foreach ( $mapping as $term ) {
			if ( ++$shown > self::MAX_MAPPING_FORMS ) {
				break;
			}
			$out .= $this->mapping_form( $id, (string) $term['field'], (string) $term['value'] );
		}
		return $out;
	}

	/**
	 * Import from the server handler (capability and nonce already checked).
	 *
	 * @param Request $request Request: file.
	 */
	public static function handle_server( Request $request ): void {
		try {
			$batch = ( new ImportService() )->stage_from_server( $request->string( 'file' ), get_current_user_id() );
			self::back( 'previewed', $batch );
		} catch ( StateException $e ) {
			self::back( 'refused', '', $e->getMessage() );
		}
	}

	/**
	 * The "Import from the server" block: where to put the zips and the list of zips found there.
	 */
	private function server_html(): string {
		$drop  = new ServerDrop();
		$ready = $drop->ensure();
		$out   = '';
		/* translators: %s: folder path. */
		$out .= '<p>' . esc_html( sprintf( __( 'For large bundles: upload the .zip files with your host\'s file manager (for example cPanel File Manager) into the folder %s, then choose one here. The folder is closed to visitors, and each file is removed from it once it is read.', 'whoiswho' ), $drop->shown_path() ) ) . '</p>';
		if ( ! $ready ) {
			return self::server_section( $out . '<div class="notice notice-error inline"><p>' . esc_html__( 'The folder could not be created. Ask your host to make the uploads folder writable.', 'whoiswho' ) . '</p></div>' );
		}
		$zips = $drop->zips();
		if ( array() === $zips ) {
			return self::server_section( $out . '<p class="wiw-help">' . esc_html__( 'No .zip files in the folder yet.', 'whoiswho' ) . '</p>' );
		}
		$options = array();
		foreach ( $zips as $zip ) {
			$options[ (string) $zip['name'] ] = $zip['name'] . ' (' . size_format( $zip['size'] ) . ')';
		}
		$out .= $this->form( self::SERVER ) . Form::select( 'file', __( 'Bundle on the server', 'whoiswho' ), $options, '', array( 'id' => 'wiw-f-server-file' ) );
		$out .= Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Import this file', 'whoiswho' ) . '</button>' ) . '</form>';
		return self::server_section( $out );
	}

	/**
	 * The "Import from the server" card.
	 *
	 * @param string $body Body markup, already escaped.
	 */
	private static function server_section( string $body ): string {
		return Form::section( __( 'Or import from the server', 'whoiswho' ), $body, array( 'id' => 'wiw-import-server' ) );
	}

	/**
	 * The progress of a batch that is being checked or imported. The screen's script steps it on by itself; without the script,
	 * the Continue button runs one step (each step is short, so no request runs long).
	 *
	 * @param array<string, mixed> $batch Batch row.
	 */
	private function progress_html( array $batch ): string {
		$id      = (string) $batch['batch_id'];
		$status  = (string) $batch['status'];
		$total   = max( 0, (int) $batch['record_count'] );
		$phase   = 'checked' === $status ? 'dry_run' : ( 'applying' === $status ? 'apply' : 'undo' );
		$cursor  = 'dry_run' === $phase ? DryRun::stored( $id )['cursor'] : ( 'apply' === $phase ? (int) $batch['progress_cursor'] : 0 );
		$percent = $total > 0 ? (int) floor( 100 * min( $cursor, $total ) / $total ) : 100;
		if ( 'dry_run' === $phase ) {
			/* translators: 1: records checked, 2: records in the bundle. */
			$text = sprintf( __( 'Checking records: %1$d of %2$d', 'whoiswho' ), $cursor, $total );
		} elseif ( 'apply' === $phase ) {
			/* translators: 1: records imported, 2: records in the bundle. */
			$text = sprintf( __( 'Importing records: %1$d of %2$d', 'whoiswho' ), $cursor, $total );
		} else {
			$text = __( 'Undoing the batch...', 'whoiswho' );
		}
		$out  = '<div class="wiw-import-progress" data-wiw-import="progress" data-batch="' . esc_attr( $id ) . '" data-phase="' . esc_attr( $phase ) . '">';
		$out .= '<progress max="100" value="' . esc_attr( (string) $percent ) . '" data-wiw-import="bar" aria-describedby="wiw-import-progress-text">' . esc_html( $percent . '%' ) . '</progress> ';
		$out .= '<span id="wiw-import-progress-text" data-wiw-import="text" role="status" aria-live="polite">' . esc_html( $text ) . '</span>';
		$out .= '<p class="wiw-help">' . esc_html__( 'You can leave this page: the import carries on in the background and resumes where it stopped. Keeping the page open makes it faster.', 'whoiswho' ) . '</p>';
		$out .= $this->form( self::STEP ) . '<input type="hidden" name="batch" value="' . esc_attr( $id ) . '" />' . Form::actions( '<button type="submit" class="button" data-wiw-import="continue">' . esc_html__( 'Continue', 'whoiswho' ) . '</button>' ) . '</form>';
		return $out . '</div>';
	}

	/**
	 * What happened to the images of a batch.
	 *
	 * @param string $id Batch id.
	 */
	private function images_html( string $id ): string {
		$log = ImageLog::get( $id );
		if ( 0 === $log['imported'] && 0 === $log['refused'] ) {
			return '';
		}
		/* translators: 1: images imported, 2: images refused. */
		$out = '<p>' . esc_html( sprintf( __( 'Images: %1$d imported, %2$d refused (IMAGE_NOT_ALLOWED).', 'whoiswho' ), $log['imported'], $log['refused'] ) ) . '</p>';
		if ( array() === $log['examples'] ) {
			return $out;
		}
		$rows = '';
		foreach ( $log['examples'] as $refusal ) {
			$rows .= '<tr><td>' . esc_html( $refusal['record_id'] ) . '</td><td>' . esc_html( $refusal['image_id'] ) . '</td><td>' . esc_html( self::image_reason( $refusal['reason'] ) ) . '</td></tr>';
		}
		return $out . '<details class="wiw-import-images"><summary>' . esc_html__( 'Refused images', 'whoiswho' ) . '</summary><table class="widefat striped wiw-list-table"><thead><tr><th scope="col">' . esc_html__( 'Record', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Image', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Why', 'whoiswho' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></details>';
	}

	/**
	 * A plain-language reason for a refused image.
	 *
	 * @param string $reason Reason key.
	 */
	private static function image_reason( string $reason ): string {
		$words = array(
			'licence' => __( 'The licence is not an open licence on the allowed list.', 'whoiswho' ),
			'type'    => __( 'Not a JPEG, PNG or WebP image.', 'whoiswho' ),
			'size'    => __( 'Larger than 5 MB.', 'whoiswho' ),
			'hash'    => __( 'The file does not match its checksum.', 'whoiswho' ),
			'missing' => __( 'The file is not in the bundle.', 'whoiswho' ),
			'version' => __( 'Contract 1.0 bundles carry no images.', 'whoiswho' ),
			'store'   => __( 'The image could not be saved to the media library.', 'whoiswho' ),
		);
		return $words[ $reason ] ?? __( 'The image entry is incomplete.', 'whoiswho' );
	}

	/**
	 * One count as staff read it: the code as the contract names it and the number. A value missing from the lists no longer
	 * refuses a record (REQ-009 as amended 2026-11-14), so SCHEMA is always a real error in the record.
	 *
	 * @param string $code       Result code.
	 * @param int    $n          Count.
	 * @param bool   $new_values Whether the dry run found place values not in the list (kept for callers; no effect).
	 */
	public static function count_label( string $code, int $n, bool $new_values = false ): string {
		unset( $new_values );
		return $code . ' (' . $n . ')';
	}

	/**
	 * Counts by code as filter links.
	 *
	 * @param array<string, int> $counts Counts.
	 * @param string             $id     Batch id.
	 * @param string             $active Active filter.
	 * @param bool               $new_values Dry run with values not yet in the lists: SCHEMA is shown in words.
	 */
	private function counts_html( array $counts, string $id, string $active, bool $new_values = false ): string {
		$out  = '<p class="wiw-import-counts">';
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
			$out .= ' | <a href="' . esc_url( $link ) . '"' . ( $active === (string) $code ? ' aria-current="true"' : '' ) . '>' . esc_html( self::count_label( (string) $code, (int) $n, $new_values ) ) . '</a>';
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
		$options  = array( '0' => __( 'Skip this value', 'whoiswho' ) );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$options[ (string) $term->term_id ] = $term->name;
		}
		$select = Form::searchable_select(
			'term_id',
			/* translators: 1: field, 2: the unknown value. */
			sprintf( __( 'Map %1$s "%2$s" to', 'whoiswho' ), $field, $value ),
			$options,
			'0',
			array( 'id' => 'wiw-f-map-' . substr( md5( $field . '|' . $value ), 0, 10 ) )
		);
		$button = '<div class="wiw-inline-fields__action"><button type="submit" class="button">' . esc_html__( 'Save mapping', 'whoiswho' ) . '</button></div>';
		return $out . '<div class="wiw-inline-fields">' . $select . $button . '</div></form>';
	}

	/**
	 * The history of batches: a shared staff list table with Open and Undo row actions and bulk Undo selected.
	 *
	 * @param Request $request Request (the page of the history).
	 */
	private function history( Request $request ): string {
		return Form::section( __( 'History', 'whoiswho' ), ( new ImportBatchesTable() )->html( $request ), array( 'id' => 'wiw-import-history' ) );
	}
}
