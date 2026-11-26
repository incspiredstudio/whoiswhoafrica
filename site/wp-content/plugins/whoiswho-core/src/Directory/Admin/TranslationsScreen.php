<?php
/**
 * Directory > Translations (REQ-276, REQ-053): upload a translations file, see what it would change, then apply it a batch at a
 * time so a shared host never times out. Every profile change goes through the profile editor (version, audit, purge); the run
 * ends with one audit row of counts, the list of items it skipped, and the number of possible duplicates flagged. Applying the
 * same file again changes nothing. Capability wiw_run_imports; every action through ActionGuard.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Directory\Translate\TranslationApplier;
use WhoIsWho\Core\Directory\Translate\TranslationFile;
use WhoIsWho\Core\Directory\Translate\TranslationRun;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Review\Admin\DuplicatesScreen;

/**
 * Translations screen.
 */
final class TranslationsScreen {

	public const SCREEN     = 'wiw-translations';
	public const CAPABILITY = 'wiw_run_imports';
	public const UPLOAD     = 'wiw_translations_upload';
	public const STEP       = 'wiw_translations_step';
	public const DISCARD    = 'wiw_translations_discard';

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
					__( 'Translations', 'whoiswho' ),
					self::CAPABILITY,
					static function (): void {
						echo ( new self() )->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					},
					false,
					'directory'
				);
				$registry->hook();
				ActionGuard::register( self::UPLOAD, self::CAPABILITY, array( self::class, 'handle_upload' ) );
				ActionGuard::register( self::STEP, self::CAPABILITY, array( self::class, 'handle_step' ) );
				ActionGuard::register( self::DISCARD, self::CAPABILITY, array( self::class, 'handle_discard' ) );
			},
			1
		);
	}

	/**
	 * Upload a file and preview it (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_upload( Request $request ): void {
		self::back( self::upload( $request, get_current_user_id() ) );
	}

	/**
	 * The upload's work, apart from the redirect so it can be tested.
	 *
	 * @param Request $request  Request.
	 * @param int     $actor_id Staff member.
	 * @return string Result word, or "refused:" and the reason.
	 */
	public static function upload( Request $request, int $actor_id ): string {
		$file = $request->file( 'translations' );
		if ( null === $file || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return 'refused:' . __( 'Choose a translations file to upload.', 'whoiswho' );
		}
		if ( $file['size'] > TranslationFile::MAX_BYTES ) {
			return 'refused:' . __( 'The file is larger than 2 MB.', 'whoiswho' );
		}
		$json = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- PHP's own upload temp file.
		return self::start( $json, $file['name'], $actor_id );
	}

	/**
	 * Start a run from a file's text.
	 *
	 * @param string $json      Text.
	 * @param string $file_name Name.
	 * @param int    $actor_id  Staff member.
	 * @return string Result word, or "refused:" and the reason.
	 */
	public static function start( string $json, string $file_name, int $actor_id ): string {
		try {
			( new TranslationRun() )->start( $json, $file_name, $actor_id );
		} catch ( \InvalidArgumentException | \RuntimeException $e ) {
			return 'refused:' . $e->getMessage();
		}
		return 'previewed';
	}

	/**
	 * Apply the next batch (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_step( Request $request ): void {
		unset( $request );
		try {
			$run = ( new TranslationRun() )->step( get_current_user_id() );
		} catch ( \RuntimeException $e ) {
			self::back( 'refused:' . $e->getMessage() );
			return;
		}
		self::back( TranslationRun::DONE === $run['status'] ? 'finished' : 'running' );
	}

	/**
	 * Forget the current file (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_discard( Request $request ): void {
		unset( $request );
		( new TranslationRun() )->discard();
		self::back( 'discarded' );
	}

	/**
	 * Redirect back to the screen.
	 *
	 * @param string $result Result word, or "refused:" and the reason.
	 */
	private static function back( string $result ): void {
		$args = array( 'page' => self::SCREEN );
		if ( 0 === strpos( $result, 'refused:' ) ) {
			$args['result'] = 'refused';
			$args['why']    = rawurlencode( substr( $result, 8 ) );
		} else {
			$args['result'] = $result;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request (result, why).
	 * @throws \DomainException Without the capability.
	 */
	public function html( Request $request ): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to apply translations.' );
		}
		$run  = ( new TranslationRun() )->current();
		$out  = '<div class="wrap wiw-staff"><h1>' . esc_html__( 'Translations', 'whoiswho' ) . '</h1>';
		$out .= $this->notice( $request );
		$out .= '<p class="wiw-lead">' . esc_html__( 'Profiles are in English. Upload a translations file to change names and text that are in another language. The original name is kept for staff and search, and is not shown on the public page. Each change is saved with a version and an audit row, so it can be undone from the profile.', 'whoiswho' ) . '</p>';
		if ( null === $run ) {
			return $out . $this->upload_form() . '</div>';
		}
		if ( TranslationRun::PREVIEW === $run['status'] ) {
			return $out . $this->preview( $run ) . '</div>';
		}
		if ( TranslationRun::RUNNING === $run['status'] ) {
			return $out . $this->progress( $run ) . '</div>';
		}
		return $out . $this->summary( $run ) . '</div>';
	}

	/**
	 * A kit form opening tag with the action and nonce fields.
	 *
	 * @param string $action Action.
	 * @param bool   $upload Whether the form uploads a file.
	 */
	private function form( string $action, bool $upload = false ): string {
		$hidden = '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( $action ) ) . '" />';
		return Form::open( $hidden, array( 'enctype' => $upload ? 'multipart/form-data' : '' ) );
	}

	/**
	 * The upload form.
	 */
	private function upload_form(): string {
		$out   = $this->form( self::UPLOAD, true );
		$input = '<input type="file" id="wiw-f-translations" name="translations" accept=".json,application/json" required="required" aria-describedby="wiw-f-translations-help" />';
		$out  .= Form::row(
			'wiw-f-translations',
			__( 'Translations file (.json)', 'whoiswho' ),
			$input,
			array( 'help' => __( 'Up to 2 MB. Nothing changes until you check the preview and choose Apply.', 'whoiswho' ) )
		);
		$out  .= Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Upload and preview', 'whoiswho' ) . '</button>' ) . '</form>';
		return Form::section( __( 'Upload a translations file', 'whoiswho' ), $out, array( 'id' => 'wiw-translations-upload' ) );
	}

	/**
	 * The preview: what would change, a sample of names, and the Apply and Discard buttons.
	 *
	 * @param array<string, mixed> $run Run.
	 */
	private function preview( array $run ): string {
		$counts = (array) $run['preview'];
		$apply  = (int) $counts[ TranslationApplier::APPLIED ] + (int) $counts[ TranslationApplier::PARTLY ];
		$body   = '<p>' . esc_html( sprintf( /* translators: %s: file name. */ __( 'File: %s', 'whoiswho' ), (string) $run['file'] ) ) . '</p>';
		$body  .= $this->counts_table(
			array(
				__( 'Profiles that will change', 'whoiswho' ) => $apply,
				__( 'Of those, with some fields changed since the file was made (those fields are left alone)', 'whoiswho' ) => (int) $counts[ TranslationApplier::PARTLY ],
				__( 'Already translated', 'whoiswho' )     => (int) $counts[ TranslationApplier::DONE ],
				__( 'Changed since the file was made (left alone)', 'whoiswho' ) => (int) $counts[ TranslationApplier::CHANGED ],
				__( 'Not found on this site', 'whoiswho' ) => (int) $counts[ TranslationApplier::NOT_FOUND ],
				__( 'List terms that will be renamed', 'whoiswho' ) => (int) ( (array) $run['terms_plan'] )[ TranslationApplier::APPLIED ],
			)
		);
		if ( array() !== (array) $run['sample'] ) {
			$body .= '<h3>' . esc_html__( 'Some of the new names', 'whoiswho' ) . '</h3><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Now', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'In English', 'whoiswho' ) . '</th></tr></thead><tbody>';
			foreach ( (array) $run['sample'] as $pair ) {
				$body .= '<tr><td dir="auto">' . esc_html( (string) $pair[0] ) . '</td><td>' . esc_html( (string) $pair[1] ) . '</td></tr>';
			}
			$body .= '</tbody></table>';
		}
		$body .= $this->form( self::STEP ) . Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Apply translations', 'whoiswho' ) . '</button>' ) . '</form>';
		$body .= $this->form( self::DISCARD ) . Form::actions( '<button type="submit" class="button">' . esc_html__( 'Discard this file', 'whoiswho' ) . '</button>' ) . '</form>';
		return Form::section( __( 'Check before applying', 'whoiswho' ), $body, array( 'id' => 'wiw-translations-preview' ) );
	}

	/**
	 * Progress of a running file: the next batch starts by itself, with a button in case it does not.
	 *
	 * @param array<string, mixed> $run Run.
	 */
	private function progress( array $run ): string {
		$next = add_query_arg(
			array(
				'action'   => self::STEP,
				'_wpnonce' => ActionGuard::nonce_for( self::STEP ),
			),
			admin_url( 'admin-post.php' )
		);
		$done = (int) $run['offset'];
		$all  = max( 1, (int) $run['total'] );
		$body = '<meta http-equiv="refresh" content="1;url=' . esc_url( $next ) . '" />';
		/* translators: 1: profiles done, 2: all profiles. */
		$body .= '<p role="status">' . esc_html( sprintf( __( 'Applied %1$d of %2$d. The next batch starts by itself; keep this page open.', 'whoiswho' ), $done, (int) $run['total'] ) ) . '</p>';
		$body .= '<progress max="' . esc_attr( (string) $all ) . '" value="' . esc_attr( (string) $done ) . '"></progress>';
		$body .= $this->form( self::STEP ) . Form::actions( '<button type="submit" class="button button-primary">' . esc_html__( 'Continue', 'whoiswho' ) . '</button>' ) . '</form>';
		return Form::section( __( 'Applying translations', 'whoiswho' ), $body, array( 'id' => 'wiw-translations-progress' ) );
	}

	/**
	 * The result of a finished file.
	 *
	 * @param array<string, mixed> $run Run.
	 */
	private function summary( array $run ): string {
		$counts = (array) $run['counts'];
		$body   = $this->counts_table(
			array(
				__( 'Profiles translated', 'whoiswho' ) => (int) $counts[ TranslationApplier::APPLIED ],
				__( 'Profiles partly translated (some fields had changed)', 'whoiswho' ) => (int) $counts[ TranslationApplier::PARTLY ],
				__( 'Already translated', 'whoiswho' )  => (int) $counts[ TranslationApplier::DONE ],
				__( 'Left alone: changed since the file was made', 'whoiswho' ) => (int) $counts[ TranslationApplier::CHANGED ],
				__( 'Not found', 'whoiswho' )           => (int) $counts[ TranslationApplier::NOT_FOUND ],
				__( 'Refused by the profile checks', 'whoiswho' ) => (int) $counts[ TranslationApplier::REFUSED ],
				__( 'List terms renamed', 'whoiswho' )  => (int) ( (array) $run['terms'] )[ TranslationApplier::APPLIED ],
			)
		);
		$dupes  = (int) $run['duplicates'];
		if ( $dupes > 0 ) {
			$link  = add_query_arg( array( 'page' => DuplicatesScreen::SCREEN ), admin_url( 'admin.php' ) );
			$body .= '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( /* translators: %d: number of pairs. */ _n( '%d possible duplicate flagged', '%d possible duplicates flagged', $dupes, 'whoiswho' ), $dupes ) ) . ' — <a href="' . esc_url( $link ) . '">' . esc_html__( 'see Directory > Duplicates', 'whoiswho' ) . '</a></p></div>';
		}
		$skipped = (array) $run['skipped'];
		if ( array() !== $skipped ) {
			$body .= '<h3>' . esc_html__( 'Not fully applied', 'whoiswho' ) . '</h3><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Record', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Profile', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'What happened', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Fields left alone', 'whoiswho' ) . '</th></tr></thead><tbody>';
			foreach ( $skipped as $row ) {
				$id    = (int) $row['profile'];
				$cell  = $id > 0 ? '<a href="' . esc_url( ProfileEditScreen::url( $id ) ) . '">' . esc_html( '#' . $id ) . '</a>' : '—';
				$body .= '<tr><td>' . esc_html( (string) $row['label'] ) . '</td><td>' . $cell . '</td><td>' . esc_html( $this->word( (string) $row['outcome'] ) ) . '</td><td>' . esc_html( implode( ', ', (array) $row['fields'] ) ) . '</td></tr>';
			}
			$body .= '</tbody></table>';
		}
		$body .= $this->form( self::DISCARD ) . Form::actions( '<button type="submit" class="button">' . esc_html__( 'Upload another file', 'whoiswho' ) . '</button>' ) . '</form>';
		return Form::section( __( 'Translations applied', 'whoiswho' ), $body, array( 'id' => 'wiw-translations-summary' ) );
	}

	/**
	 * A two-column table of counts.
	 *
	 * @param array<string, int> $rows Label => count.
	 */
	private function counts_table( array $rows ): string {
		$out = '<table class="widefat striped wiw-counts"><tbody>';
		foreach ( $rows as $label => $count ) {
			$out .= '<tr><th scope="row">' . esc_html( (string) $label ) . '</th><td>' . ListTable::pill( number_format_i18n( $count ), $count > 0 ? 'info' : 'neutral' ) . '</td></tr>';
		}
		return $out . '</tbody></table>';
	}

	/**
	 * The plain words for an outcome.
	 *
	 * @param string $outcome Outcome.
	 */
	private function word( string $outcome ): string {
		$words = array(
			TranslationApplier::PARTLY    => __( 'Partly applied', 'whoiswho' ),
			TranslationApplier::CHANGED   => __( 'Changed since the file was made', 'whoiswho' ),
			TranslationApplier::NOT_FOUND => __( 'Not found', 'whoiswho' ),
			TranslationApplier::REFUSED   => __( 'Refused by the profile checks', 'whoiswho' ),
		);
		return $words[ $outcome ] ?? $outcome;
	}

	/**
	 * The result notice.
	 *
	 * @param Request $request Request.
	 */
	private function notice( Request $request ): string {
		$result = $request->string( 'result' );
		if ( 'refused' === $result ) {
			return '<div class="notice notice-error"><p>' . esc_html( rawurldecode( $request->string( 'why' ) ) ) . '</p></div>';
		}
		$words = array(
			'previewed' => __( 'File checked. Nothing has changed yet.', 'whoiswho' ),
			'finished'  => __( 'Done. Every change was saved with a version and an audit row.', 'whoiswho' ),
			'discarded' => __( 'File discarded.', 'whoiswho' ),
		);
		return isset( $words[ $result ] ) ? '<div class="notice notice-success"><p>' . esc_html( $words[ $result ] ) . '</p></div>' : '';
	}
}
