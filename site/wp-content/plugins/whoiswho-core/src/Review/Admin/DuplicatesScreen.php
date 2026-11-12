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
					}
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
		$out   = '<div class="wrap"><h1>' . esc_html__( 'Duplicates', 'whoiswho' ) . '</h1>';
		$out  .= '<p>' . esc_html__( 'Probable duplicates, highest match first. Nothing is merged unless you do it here.', 'whoiswho' ) . '</p>';
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
		$out  = '<div class="card" style="max-width:none"><h2>' . esc_html( get_the_title( $a ) . ' / ' . get_the_title( $b ) ) . '</h2>';
		/* translators: 1: match score, 2: basis of the match. */
		$out .= '<p>' . esc_html( sprintf( __( 'Match %1$d%% on %2$s.', 'whoiswho' ), $score, $basis ) ) . '</p>';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::MERGE_ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::MERGE_ACTION ) ) . '" />';
		$out .= '<input type="hidden" name="a" value="' . esc_attr( (string) $a ) . '" /><input type="hidden" name="b" value="' . esc_attr( (string) $b ) . '" />';
		$out .= '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Field', 'whoiswho' ) . '</th><th scope="col">' . esc_html( '#' . $a ) . '</th><th scope="col">' . esc_html( '#' . $b ) . '</th></tr></thead><tbody>';
		foreach ( $keys as $key ) {
			$out .= '<tr><th scope="row">' . esc_html( $key ) . '</th>';
			foreach ( array( $a, $b ) as $id ) {
				$out .= '<td>' . esc_html( $this->show( DuplicateMerge::value( $id, $key ) ) ) . '<br /><small>' . esc_html( $this->source( $id, $key ) ) . '</small></td>';
			}
			$out .= '</tr>';
		}
		$out .= '</tbody></table>';
		$out .= '<p><label>' . esc_html__( 'Keep this profile', 'whoiswho' ) . ' <select name="keep"><option value="' . esc_attr( (string) $a ) . '">#' . esc_html( (string) $a ) . '</option><option value="' . esc_attr( (string) $b ) . '">#' . esc_html( (string) $b ) . '</option></select></label></p>';
		$out .= '<fieldset><legend>' . esc_html__( 'Take these values from the other profile', 'whoiswho' ) . '</legend>';
		foreach ( $keys as $key ) {
			$out .= '<label style="margin-right:1em"><input type="checkbox" name="take_' . esc_attr( $key ) . '" value="1" /> ' . esc_html( $key ) . '</label>';
		}
		$out .= '</fieldset>';
		$out .= '<p><label>' . esc_html__( 'Reason', 'whoiswho' ) . ' <input type="text" name="reason" class="regular-text" required="required" /></label> <button class="button button-primary">' . esc_html__( 'Merge', 'whoiswho' ) . '</button></p></form>';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::DISMISS_ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::DISMISS_ACTION ) ) . '" />';
		$out .= '<input type="hidden" name="a" value="' . esc_attr( (string) $a ) . '" /><input type="hidden" name="b" value="' . esc_attr( (string) $b ) . '" />';
		$out .= '<label>' . esc_html__( 'Reason', 'whoiswho' ) . ' <input type="text" name="reason" class="regular-text" required="required" /></label> <button class="button">' . esc_html__( 'Not a duplicate', 'whoiswho' ) . '</button></form></div>';
		return $out;
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
