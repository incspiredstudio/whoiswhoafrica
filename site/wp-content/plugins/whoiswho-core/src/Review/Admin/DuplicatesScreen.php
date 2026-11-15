<?php
/**
 * The Duplicates screen (REQ-123, REQ-145, REQ-146): each open pair side by side, field by field with its source, and two
 * human decisions: merge (the reviewer picks the surviving profile and which values come across) or "not a duplicate" with a
 * reason. Nothing merges on its own.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Review\Duplicates\DuplicateMerge;
use WhoIsWho\Core\Review\Duplicates\DuplicateRepository;
use WhoIsWho\Core\Review\StateException;

/**
 * Duplicates screen.
 */
final class DuplicatesScreen {

	public const SCREEN         = 'wiw-duplicates';
	public const CAPABILITY     = DuplicateMerge::CAPABILITY;
	public const MERGE_ACTION   = 'wiw_duplicates_merge';
	public const DISMISS_ACTION = 'wiw_duplicates_dismiss';

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
					__( 'Duplicates', 'whoiswho' ),
					self::CAPABILITY,
					static function (): void {
						echo ( new self() )->html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					},
					false,
					'directory'
				);
				$registry->hook();
				ActionGuard::register( self::MERGE_ACTION, self::CAPABILITY, array( self::class, 'handle_merge' ) );
				ActionGuard::register( self::DISMISS_ACTION, self::CAPABILITY, array( self::class, 'handle_dismiss' ) );
			},
			1
		);
	}

	/**
	 * Handle the merge form (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_merge( Request $request ): void {
		$keep = $request->int( 'keep' );
		$drop = $request->int( 'a' ) === $keep ? $request->int( 'b' ) : $request->int( 'a' );
		$take = array();
		foreach ( ( new FieldAllowlist() )->all_keys() as $key ) {
			if ( '' !== $request->string( 'take_' . $key ) ) {
				$take[] = $key;
			}
		}
		try {
			( new DuplicateMerge() )->merge( $keep, $drop, $take, $request->string( 'reason' ), get_current_user_id() );
			$notice = 'merged';
		} catch ( StateException $e ) {
			unset( $e );
			$notice = 'refused';
		}
		self::back( $notice );
	}

	/**
	 * Handle the not-a-duplicate form (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_dismiss( Request $request ): void {
		try {
			( new DuplicateMerge() )->dismiss( $request->int( 'a' ), $request->int( 'b' ), $request->string( 'reason' ), get_current_user_id() );
			$notice = 'dismissed';
		} catch ( StateException $e ) {
			unset( $e );
			$notice = 'refused';
		}
		self::back( $notice );
	}

	/**
	 * Redirect back to the screen.
	 *
	 * @param string $notice Result word.
	 */
	private static function back( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => self::SCREEN,
					'result' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * The screen markup.
	 *
	 * @throws \DomainException Without the capability.
	 */
	public function html(): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to resolve duplicates.' );
		}
		$pairs = ( new DuplicateRepository() )->with_status( 'open' );
		$out   = '<div class="wrap wiw-staff"><h1>' . esc_html__( 'Duplicates', 'whoiswho' ) . '</h1>';
		$out  .= $this->notice( Request::from_globals()->string( 'result' ) );
		$out  .= '<p class="wiw-lead">' . esc_html__( 'Probable duplicates, highest match first. Nothing is merged unless you do it here.', 'whoiswho' ) . '</p>';
		if ( array() === $pairs ) {
			$out .= '<p>' . esc_html__( 'No open duplicates.', 'whoiswho' ) . '</p>';
		}
		foreach ( $pairs as $pair ) {
			$out .= $this->pair_html( (int) $pair['profile_a'], (int) $pair['profile_b'], (int) $pair['score'], (string) $pair['basis'] );
		}
		return $out . '</div>';
	}

	/**
	 * One pair: the side-by-side table, the merge form and the dismiss form.
	 *
	 * @param int    $a     First profile.
	 * @param int    $b     Second profile.
	 * @param int    $score Match score.
	 * @param string $basis Match basis.
	 */
	private function pair_html( int $a, int $b, int $score, string $basis ): string {
		$allow = new FieldAllowlist();
		$types = array();
		foreach ( array( $a, $b ) as $id ) {
			$types[ $id ] = (string) ( ( new StateRepository() )->get( $id )['entity_type'] ?? '' );
		}
		$keys = array_values( array_intersect( array_keys( $allow->for_type( $types[ $a ] ) ), array_keys( $allow->for_type( $types[ $b ] ) ) ) );
		$pair = 'wiw-pair-' . $a . '-' . $b;
		/* translators: 1: match score, 2: basis of the match. */
		$body   = '<p>' . ListTable::pill( sprintf( __( 'Match %1$d%%', 'whoiswho' ), $score ), $score >= 90 ? 'warning' : 'neutral' ) . ' ' . esc_html( sprintf( __( 'Match %1$d%% on %2$s.', 'whoiswho' ), $score, $basis ) ) . '</p>';
		$merge  = '<input type="hidden" name="action" value="' . esc_attr( self::MERGE_ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::MERGE_ACTION ) ) . '" />';
		$merge .= '<input type="hidden" name="a" value="' . esc_attr( (string) $a ) . '" /><input type="hidden" name="b" value="' . esc_attr( (string) $b ) . '" />';
		$body  .= Form::open( $merge );
		$body  .= '<table class="widefat striped wiw-compare"><thead><tr><th scope="col">' . esc_html__( 'Field', 'whoiswho' ) . '</th><th scope="col">' . esc_html( '#' . $a ) . '</th><th scope="col">' . esc_html( '#' . $b ) . '</th></tr></thead><tbody>';
		foreach ( $keys as $key ) {
			$body .= '<tr><th scope="row">' . esc_html( $key ) . '</th>';
			foreach ( array( $a, $b ) as $id ) {
				$body .= '<td>' . esc_html( $this->show( DuplicateMerge::value( $id, $key ) ) ) . '<br /><small>' . esc_html( $this->source( $id, $key ) ) . '</small></td>';
			}
			$body .= '</tr>';
		}
		$body .= '</tbody></table>';
		$body .= Form::select(
			'keep',
			__( 'Keep this profile', 'whoiswho' ),
			array(
				(string) $a => '#' . $a . ' ' . get_the_title( $a ),
				(string) $b => '#' . $b . ' ' . get_the_title( $b ),
			),
			(string) $a,
			array( 'id' => $pair . '-keep' )
		);
		$takes = '';
		foreach ( $keys as $key ) {
			$takes .= Form::checkbox( 'take_' . $key, $key, false, array( 'id' => $pair . '-take-' . $key ) );
		}
		$body .= '<fieldset class="wiw-field wiw-take"><legend class="wiw-label">' . esc_html__( 'Take these values from the other profile', 'whoiswho' ) . '</legend><div class="wiw-field-grid">' . $takes . '</div></fieldset>';
		$body .= Form::text(
			'reason',
			__( 'Reason', 'whoiswho' ),
			'',
			array(
				'id'       => $pair . '-merge-reason',
				'required' => true,
			)
		);
		$body .= Form::actions( '<button class="button button-primary">' . esc_html__( 'Merge', 'whoiswho' ) . '</button>' ) . '</form>';

		$dismiss  = '<input type="hidden" name="action" value="' . esc_attr( self::DISMISS_ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::DISMISS_ACTION ) ) . '" />';
		$dismiss .= '<input type="hidden" name="a" value="' . esc_attr( (string) $a ) . '" /><input type="hidden" name="b" value="' . esc_attr( (string) $b ) . '" />';
		$no       = Form::open( $dismiss, array( 'class' => 'wiw-inline-fields' ) );
		$no      .= Form::text(
			'reason',
			__( 'Reason', 'whoiswho' ),
			'',
			array(
				'id'       => $pair . '-dismiss-reason',
				'required' => true,
			)
		);
		$no      .= '<div class="wiw-inline-fields__action"><button class="button">' . esc_html__( 'Not a duplicate', 'whoiswho' ) . '</button></div></form>';
		$body    .= Form::section(
			__( 'Not a duplicate?', 'whoiswho' ),
			$no,
			array(
				'level' => 3,
				'class' => 'wiw-card--inset',
			)
		);
		return Form::section( get_the_title( $a ) . ' / ' . get_the_title( $b ), $body, array( 'id' => $pair ) );
	}

	/**
	 * The result of the last decision.
	 *
	 * @param string $result merged, dismissed or refused.
	 */
	private function notice( string $result ): string {
		$words = array(
			'merged'    => array( 'success', __( 'Merged. The dropped profile now sends visitors to the kept one.', 'whoiswho' ) ),
			'dismissed' => array( 'success', __( 'Marked as not a duplicate.', 'whoiswho' ) ),
			'refused'   => array( 'error', __( 'That was not done: check the reason and try again.', 'whoiswho' ) ),
		);
		if ( ! isset( $words[ $result ] ) ) {
			return '';
		}
		return '<div class="notice notice-' . esc_attr( $words[ $result ][0] ) . '"><p>' . esc_html( $words[ $result ][1] ) . '</p></div>';
	}

	/**
	 * A value as text.
	 *
	 * @param string|string[] $value Value.
	 */
	private function show( $value ): string {
		return is_array( $value ) ? implode( ', ', $value ) : $value;
	}

	/**
	 * The latest source of a field: its URL and retrieval date.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $key        Field.
	 */
	private function source( int $profile_id, string $key ): string {
		$rows = ( new ProvenanceRepository() )->for_profile( $profile_id, $key );
		if ( array() === $rows ) {
			return __( 'No source recorded', 'whoiswho' );
		}
		$row = end( $rows );
		return (string) $row['source_url'] . ' (' . substr( (string) $row['retrieved_at'], 0, 10 ) . ')';
	}
}
