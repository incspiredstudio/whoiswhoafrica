<?php
/**
 * The Review item screen (REQ-084, REQ-132, REQ-141, REQ-142, REQ-143, REQ-148, REQ-163; prototype A04): the field-level diff of
 * the proposed change, each claim beside its source URL and retrieval date, the verification report, the free-text scan flags, a
 * five-point neutral checklist and the decisions: approve, or reject with a reason. Approving needs the whole checklist ticked,
 * is refused for the person who imported the record (with the reason on screen), while the scan block stands, and for a fact
 * with low confidence or no source. Editing is done on the profile edit screen, which asks for its own reason. Capability
 * wiw_review_records on the screen and every action; the decisions are audited by the state machine and the scan clearing.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Import\EvidenceRepository;
use WhoIsWho\Core\Import\Scan\ScanBlock;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Review\FactGate;
use WhoIsWho\Core\Review\Metrics\DecisionLog;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\Revisions\RevisionService;
use WhoIsWho\Core\Review\StateException;

/**
 * Review item screen.
 */
final class ReviewItemScreen {

	public const SCREEN     = 'wiw-review-item';
	public const CAPABILITY = 'wiw_review_records';
	public const APPROVE    = 'wiw_review_item_approve';
	public const REJECT     = 'wiw_review_item_reject';
	public const CLEAR      = 'wiw_review_item_clear';
	public const REV_OK     = 'wiw_review_revision_approve';
	public const REV_NO     = 'wiw_review_revision_reject';

	/**
	 * The five-point neutral checklist, all of which must be ticked to approve.
	 *
	 * @return array<string, string>
	 */
	public static function checklist(): array {
		return array(
			'c1' => __( 'No allegations, litigation or rumours.', 'whoiswho' ),
			'c2' => __( 'No opinion or praise.', 'whoiswho' ),
			'c3' => __( 'No personal life, family, health or finances.', 'whoiswho' ),
			'c4' => __( 'Every fact is dated and sourced.', 'whoiswho' ),
			'c5' => __( 'Professional facts only.', 'whoiswho' ),
		);
	}

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
					__( 'Review item', 'whoiswho' ),
					self::CAPABILITY,
					static function (): void {
						echo ( new self() )->html( Request::from_globals() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					},
					true,
					// A detail screen, opened from its list: no menu item.
					'review'
				);
				$registry->hook();
				ActionGuard::register( self::APPROVE, self::CAPABILITY, array( self::class, 'handle_approve' ) );
				ActionGuard::register( self::REJECT, self::CAPABILITY, array( self::class, 'handle_reject' ) );
				ActionGuard::register( self::CLEAR, self::CAPABILITY, array( self::class, 'handle_clear' ) );
				ActionGuard::register( self::REV_OK, self::CAPABILITY, array( self::class, 'handle_revision_approve' ) );
				ActionGuard::register( self::REV_NO, self::CAPABILITY, array( self::class, 'handle_revision_reject' ) );
			},
			1
		);
	}

	/**
	 * The link to the screen for a profile.
	 *
	 * @param int $profile_id Profile.
	 */
	public static function url( int $profile_id ): string {
		return add_query_arg(
			array(
				'page'       => self::SCREEN,
				'profile_id' => $profile_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Approve: the checklist, the fact gate, then the review service.
	 *
	 * @param int $profile_id Profile.
	 * @param int $actor_id   Reviewer.
	 * @param int $ticked     Number of checklist points ticked.
	 * @throws StateException With the reason shown on screen.
	 */
	public function approve( int $profile_id, int $actor_id, int $ticked ): void {
		if ( $ticked < count( self::checklist() ) ) {
			throw StateException::refuse( 'Tick all five points of the checklist before approving.' );
		}
		$problems = ( new FactGate() )->problems( $profile_id );
		if ( array() !== $problems ) {
			throw StateException::refuse( implode( ' ', $problems ) . ' Fix it or reject the record.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped when the screen shows it.
		}
		( new ReviewService() )->approve( $profile_id, $actor_id );
	}

	/**
	 * Approve handler (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_approve( Request $request ): void {
		$id     = $request->int( 'profile_id' );
		$ticked = 0;
		foreach ( array_keys( self::checklist() ) as $key ) {
			$ticked += '' !== $request->string( $key ) ? 1 : 0;
		}
		try {
			( new self() )->approve( $id, get_current_user_id(), $ticked );
			self::back( $id, 'approved' );
		} catch ( StateException $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * Reject handler: a reason is required; the reason code feeds the review metrics.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_reject( Request $request ): void {
		$id     = $request->int( 'profile_id' );
		$reason = $request->string( 'reason' );
		if ( '' === trim( $reason ) ) {
			self::back( $id, 'refused', 'A reason is required to reject.' );
		}
		try {
			( new ReviewService() )->reject( $id, $reason, get_current_user_id(), DecisionLog::normalise_code( $request->string( 'reason_code' ) ) );
			self::back( $id, 'rejected' );
		} catch ( StateException $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * Clear-the-block handler.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_clear( Request $request ): void {
		$id = $request->int( 'profile_id' );
		try {
			( new ScanBlock() )->clear( $id, get_current_user_id(), $request->string( 'reason' ) );
			self::back( $id, 'cleared' );
		} catch ( StateException $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * Approve a pending revision of a claimed profile (the author cannot; the service refuses).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_revision_approve( Request $request ): void {
		$id = $request->int( 'profile_id' );
		try {
			( new RevisionService() )->approve( $request->int( 'version_id' ), get_current_user_id() );
			self::back( $id, 'approved' );
		} catch ( StateException $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * Reject a pending revision with a reason.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_revision_reject( Request $request ): void {
		$id = $request->int( 'profile_id' );
		try {
			( new RevisionService() )->reject( $request->int( 'version_id' ), $request->string( 'reason' ), get_current_user_id() );
			self::back( $id, 'rejected' );
		} catch ( StateException $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * The pending revisions of a claimed profile, each with approve and reject.
	 *
	 * @param int $id Profile.
	 */
	private function revisions_html( int $id ): string {
		$out = '';
		foreach ( ( new RevisionService() )->pending( $id ) as $row ) {
			$mine = get_current_user_id() === (int) $row['author_id'];
			$out .= '<h3>' . esc_html__( 'Pending change from the profile manager', 'whoiswho' ) . '</h3><p>' . esc_html( (string) $row['reason'] ) . '</p>';
			if ( $mine ) {
				$out .= '<div class="notice notice-warning inline"><p>' . esc_html__( 'You made this change, so another reviewer must decide it.', 'whoiswho' ) . '</p></div>';
				continue;
			}
			$vid  = (string) $row['id'];
			$out .= $this->form( self::REV_OK, $id ) . '<input type="hidden" name="version_id" value="' . esc_attr( $vid ) . '" /><p><button class="button button-primary">' . esc_html__( 'Approve this change', 'whoiswho' ) . '</button></p></form>';
			$out .= $this->form( self::REV_NO, $id ) . '<input type="hidden" name="version_id" value="' . esc_attr( $vid ) . '" /><p><label>' . esc_html__( 'Reason', 'whoiswho' ) . ' <input type="text" name="reason" class="regular-text" required="required" /></label> <button class="button">' . esc_html__( 'Reject this change', 'whoiswho' ) . '</button></p></form>';
		}
		return $out;
	}

	/**
	 * Redirect back to the item.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $result     Result word.
	 * @param string $why        A refusal message fit to show.
	 */
	private static function back( int $profile_id, string $result, string $why = '' ): void {
		$url = add_query_arg( 'result', $result, self::url( $profile_id ) );
		wp_safe_redirect( '' === $why ? $url : add_query_arg( 'why', rawurlencode( $why ), $url ) );
		exit;
	}

	/**
	 * The screen markup.
	 *
	 * @param Request $request Request (profile_id, result, why).
	 * @throws \DomainException Without the capability.
	 */
	public function html( Request $request ): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'Not allowed to review records.' );
		}
		$id  = $request->int( 'profile_id' );
		$row = $id > 0 ? ( new StateRepository() )->get( $id ) : null;
		$out = '<div class="wrap"><h1>' . esc_html__( 'Review item', 'whoiswho' ) . '</h1>';
		if ( null === $row || null === get_post( $id ) ) {
			return $out . '<p>' . esc_html__( 'Choose an item from the review queue.', 'whoiswho' ) . '</p></div>';
		}
		$out .= $this->notice( $request );
		$out .= '<h2>' . esc_html( get_the_title( $id ) ) . '</h2><p>' . esc_html( Labels::entity_type( (string) $row['entity_type'] ) . ' · ' . Labels::state( (string) $row['pub_state'] ) ) . '</p>';
		$out .= $this->importer_note( $row );
		$out .= $this->diff_html( $id );
		$out .= $this->claims_html( $id );
		$out .= $this->flags_html( $id );
		$out .= $this->revisions_html( $id );
		return $out . $this->decision_html( $id ) . '</div>';
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
			'approved' => __( 'Approved.', 'whoiswho' ),
			'rejected' => __( 'Rejected.', 'whoiswho' ),
			'cleared'  => __( 'Block cleared.', 'whoiswho' ),
		);
		return isset( $words[ $result ] ) ? '<div class="notice notice-success"><p>' . esc_html( $words[ $result ] ) . '</p></div>' : '';
	}

	/**
	 * A message when the current user imported the record and so cannot approve it.
	 *
	 * @param array<string, mixed> $row State row.
	 */
	private function importer_note( array $row ): string {
		if ( (int) ( $row['importer_id'] ?? 0 ) > 0 && get_current_user_id() === (int) $row['importer_id'] ) {
			return '<div class="notice notice-warning inline"><p>' . esc_html__( 'You imported this record, so another reviewer must approve it.', 'whoiswho' ) . '</p></div>';
		}
		return '';
	}

	/**
	 * The field-level diff: the pending import version against the current values, else every current value as new.
	 *
	 * @param int $id Profile.
	 */
	private function diff_html( int $id ): string {
		$diff    = array();
		$pending = ( new VersionRepository() )->pending_for_profile( $id );
		if ( array() !== $pending ) {
			$last = $pending[ count( $pending ) - 1 ];
			$diff = self::diff( (array) json_decode( (string) $last['old_value'], true ), (array) json_decode( (string) $last['new_value'], true ) );
		} else {
			$type = (string) ( ( new StateRepository() )->get( $id )['entity_type'] ?? '' );
			$now  = array();
			foreach ( array_keys( ( new FieldAllowlist() )->for_type( $type ) ) as $key ) {
				$value = 'name' === $key ? get_the_title( $id ) : get_post_meta( $id, (string) FieldAllowlist::meta_key( (string) $key ), true );
				if ( '' !== $value && false !== $value ) {
					$now[ (string) $key ] = $value;
				}
			}
			$diff = self::diff( array(), $now );
		}
		$out = '<h3>' . esc_html__( 'Fields', 'whoiswho' ) . '</h3><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Field', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Change', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Before', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'After', 'whoiswho' ) . '</th></tr></thead><tbody>';
		foreach ( $diff as $change ) {
			$out .= '<tr><th scope="row">' . esc_html( Labels::field( (string) $change['field'] ) ) . '</th><td>' . esc_html( self::change_label( (string) $change['change'] ) ) . '</td><td>' . esc_html( $this->text( $change['old'] ) ) . '</td><td>' . esc_html( $this->text( $change['new'] ) ) . '</td></tr>';
		}
		return $out . ( array() === $diff ? '<tr><td colspan="4">' . esc_html__( 'No field changes.', 'whoiswho' ) . '</td></tr>' : '' ) . '</tbody></table>';
	}

	/**
	 * Field-level diff of two value maps. Pure.
	 *
	 * @param array<string, mixed> $current  Current values.
	 * @param array<string, mixed> $incoming Incoming values.
	 * @return array<int, array{field: string, change: string, old: mixed, new: mixed}>
	 */
	public static function diff( array $current, array $incoming ): array {
		$out  = array();
		$keys = array_unique( array_merge( array_keys( $current ), array_keys( $incoming ) ) );
		sort( $keys );
		foreach ( $keys as $key ) {
			$in_old = array_key_exists( $key, $current );
			$in_new = array_key_exists( $key, $incoming );
			if ( $in_old && $in_new && wp_json_encode( $current[ $key ] ) === wp_json_encode( $incoming[ $key ] ) ) {
				continue;
			}
			$out[] = array(
				'field'  => (string) $key,
				'change' => ! $in_old ? 'added' : ( ! $in_new ? 'removed' : 'changed' ),
				'old'    => $current[ $key ] ?? null,
				'new'    => $incoming[ $key ] ?? null,
			);
		}
		return $out;
	}

	/**
	 * A diff change in words.
	 *
	 * @param string $change added, removed or changed.
	 */
	private static function change_label( string $change ): string {
		$words = array(
			'added'   => __( 'Added', 'whoiswho' ),
			'removed' => __( 'Removed', 'whoiswho' ),
			'changed' => __( 'Changed', 'whoiswho' ),
		);
		return $words[ $change ] ?? Labels::humanise( $change );
	}

	/**
	 * A value as short text.
	 *
	 * @param mixed $value Value.
	 */
	private function text( $value ): string {
		if ( null === $value ) {
			return '';
		}
		$text = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
		return strlen( $text ) > 300 ? substr( $text, 0, 300 ) . '...' : $text;
	}

	/**
	 * Sources per field (URL, retrieval date, confidence, method), the claims with their quotes and the verification report.
	 *
	 * @param int $id Profile.
	 */
	private function claims_html( int $id ): string {
		$out = '<h3>' . esc_html__( 'Sources', 'whoiswho' ) . '</h3><table class="widefat striped"><thead><tr>';
		foreach ( array( 'Field', 'Source', 'Retrieved', 'Confidence', 'Method' ) as $heading ) {
			$out .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		$out    .= '</tr></thead><tbody>';
		$entries = ( new ProvenanceRepository() )->for_profile( $id );
		foreach ( $entries as $entry ) {
			$out .= '<tr><th scope="row">' . esc_html( Labels::field( (string) $entry['field'] ) ) . '</th><td>' . esc_html( (string) $entry['source_url'] ) . '</td><td>' . esc_html( (string) $entry['retrieved_at'] ) . '</td><td>' . esc_html( (string) $entry['confidence'] ) . '</td><td>' . esc_html( (string) $entry['method'] ) . '</td></tr>';
		}
		$out .= ( array() === $entries ? '<tr><td colspan="5">' . esc_html__( 'No sources recorded.', 'whoiswho' ) . '</td></tr>' : '' ) . '</tbody></table>';

		$evidence = ( new EvidenceRepository() )->for_reviewer( $id );
		if ( array() !== $evidence['claims'] ) {
			$out .= '<h3>' . esc_html__( 'Claims', 'whoiswho' ) . '</h3><ul>';
			$urls = array();
			foreach ( $evidence['passages'] as $passage ) {
				$urls[ (string) $passage['passage_id'] ] = (string) $passage['source_url'] . ' (' . (string) $passage['retrieved_at'] . ')';
			}
			foreach ( $evidence['claims'] as $claim ) {
				$out .= '<li><strong>' . esc_html( Labels::field( (string) $claim['field'] ) ) . '</strong>: ' . esc_html( (string) $claim['claim_text'] ) . '<br /><small>' . esc_html( $urls[ (string) $claim['passage_id'] ] ?? '' ) . '</small></li>';
			}
			$out .= '</ul>';
		}
		if ( null !== $evidence['verification'] ) {
			$out .= '<h3>' . esc_html__( 'Verification report', 'whoiswho' ) . '</h3><p>' . esc_html( sprintf( /* translators: 1: claims kept, 2: claims dropped. */ __( 'Claims kept %1$d, dropped %2$d.', 'whoiswho' ), (int) $evidence['verification']['claims_kept'], (int) $evidence['verification']['claims_dropped'] ) ) . '</p>';
		}
		return $out;
	}

	/**
	 * The free-text scan flags and the form that clears them.
	 *
	 * @param int $id Profile.
	 */
	private function flags_html( int $id ): string {
		$flags = ScanBlock::flags( $id );
		if ( array() === $flags ) {
			return '';
		}
		$out = '<h3>' . esc_html__( 'Free-text scan', 'whoiswho' ) . '</h3><div class="notice notice-warning inline"><p>' . esc_html__( 'Flagged. The record cannot be approved until the block is cleared.', 'whoiswho' ) . '</p><ul>';
		foreach ( $flags as $flag ) {
			$out .= '<li>' . esc_html( Labels::field( (string) $flag['field'] ) . ': ' . Labels::humanise( (string) $flag['category'] ) ) . '</li>';
		}
		return $out . '</ul></div>' . $this->form( self::CLEAR, $id ) . '<p><label>' . esc_html__( 'Why the flags do not apply', 'whoiswho' ) . ' <input type="text" name="reason" class="regular-text" required="required" /></label> <button class="button">' . esc_html__( 'Clear the block', 'whoiswho' ) . '</button></p></form>';
	}

	/**
	 * The checklist with approve, and the reject form.
	 *
	 * @param int $id Profile.
	 */
	private function decision_html( int $id ): string {
		$out = '<h3>' . esc_html__( 'Checklist', 'whoiswho' ) . '</h3>' . $this->form( self::APPROVE, $id ) . '<ul>';
		foreach ( self::checklist() as $key => $label ) {
			$out .= '<li><label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1" /> ' . esc_html( $label ) . '</label></li>';
		}
		$out .= '</ul><p><button class="button button-primary">' . esc_html__( 'Approve', 'whoiswho' ) . '</button> ';
		$edit = get_edit_post_link( $id );
		if ( $edit ) {
			$out .= '<a class="button" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit (a reason is required there)', 'whoiswho' ) . '</a>';
		}
		$out .= '</p></form>' . $this->form( self::REJECT, $id ) . '<p><label>' . esc_html__( 'Reason', 'whoiswho' ) . ' <input type="text" name="reason" class="regular-text" required="required" /></label> <select name="reason_code">';
		foreach ( DecisionLog::REASON_CODES as $code ) {
			$out .= '<option value="' . esc_attr( $code ) . '">' . esc_html( Labels::reason( $code ) ) . '</option>';
		}
		return $out . '</select> <button class="button">' . esc_html__( 'Reject', 'whoiswho' ) . '</button></p></form>';
	}

	/**
	 * A form opening tag with the action, nonce and profile.
	 *
	 * @param string $action Action.
	 * @param int    $id     Profile.
	 */
	private function form( string $action, int $id ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( $action ) ) . '" /><input type="hidden" name="profile_id" value="' . esc_attr( (string) $id ) . '" />';
	}
}
