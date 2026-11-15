<?php
/**
 * The panels under the Profile edit screen (REQ-060, REQ-018, REQ-161, REQ-187, REQ-125): status and marks, contact and
 * enquiries switches, public URL, "Where each fact comes from" (read-only), "Changes from the source since review" (only when a
 * change is pending), revision history, internal notes, and an empty placeholder for the Claim panel that Trust fills in M3. Each change is an ActionGuard
 * action needing edit_wiw_profiles and is audited; the sources panel also needs wiw_view_sources. Notes are staff-only text in
 * wiw_notes and no public read model touches them.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Compliance\Support\ProfileEnquirySwitch;
use WhoIsWho\Core\Directory\Contact\ContactSwitches;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Url\ProfileSlugs;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Notes\NotesService;
use WhoIsWho\Core\Review\Admin\Labels;

/**
 * Profile panels.
 */
final class ProfilePanels {

	public const CAPABILITY  = 'edit_wiw_profiles';
	public const NOTES_CAP   = 'wiw_review_records';
	public const SOURCES_CAP = 'wiw_view_sources';
	public const CONTACT     = 'wiw_profile_contact';
	public const ENQUIRIES   = 'wiw_profile_enquiries';
	public const SLUG        = 'wiw_profile_slug';
	public const NOTE        = 'wiw_profile_note';

	/**
	 * Register the panel actions.
	 */
	public static function register_actions(): void {
		ActionGuard::register( self::CONTACT, self::CAPABILITY, array( self::class, 'handle_contact' ) );
		ActionGuard::register( self::ENQUIRIES, self::CAPABILITY, array( self::class, 'handle_enquiries' ) );
		ActionGuard::register( self::SLUG, self::CAPABILITY, array( self::class, 'handle_slug' ) );
		ActionGuard::register( self::NOTE, self::CAPABILITY, array( self::class, 'handle_note' ) );
	}

	/**
	 * Contact handler: store a detail and set its switch, with the reason.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_contact( Request $request ): void {
		$id   = $request->int( 'profile_id' );
		$kind = $request->string( 'kind' );
		try {
			$switches = new ContactSwitches();
			$switches->set_value( $id, $kind, $request->string( 'value' ) );
			$switches->set_public( $id, $kind, '' !== $request->string( 'public' ), $request->string( 'reason' ) );
			self::back( $id, 'saved' );
		} catch ( \RuntimeException $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * Enquiries handler.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_enquiries( Request $request ): void {
		$id = $request->int( 'profile_id' );
		try {
			( new ProfileEnquirySwitch() )->set_open( $id, '' !== $request->string( 'open' ), $request->string( 'reason' ) );
			self::back( $id, 'saved' );
		} catch ( \RuntimeException $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * Slug handler: the old address keeps redirecting.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_slug( Request $request ): void {
		$id = $request->int( 'profile_id' );
		try {
			self::change_slug( $id, $request->string( 'slug' ), $request->string( 'reason' ) );
			self::back( $id, 'saved' );
		} catch ( \RuntimeException $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * Change a profile's slug on purpose: capability, reason, the redirect from the old address, one audit row.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $slug       Wanted slug.
	 * @param string $reason     Why.
	 * @return string The slug set.
	 * @throws \RuntimeException When refused.
	 */
	public static function change_slug( int $profile_id, string $slug, string $reason ): string {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
			throw new \RuntimeException( 'You are not allowed to change the address.' );
		}
		$post = get_post( $profile_id );
		if ( ! $post instanceof \WP_Post || ProfileType::POST_TYPE !== $post->post_type ) {
			throw new \RuntimeException( 'That profile does not exist.' );
		}
		if ( '' === trim( $reason ) || '' === trim( $slug ) ) {
			throw new \RuntimeException( 'A new address and a reason are required.' );
		}
		$old = $post->post_name;
		$new = ( new ProfileSlugs() )->change( $profile_id, $slug );
		if ( $old !== $new ) {
			AuditLogger::for_site()->log(
				'profile.slug_change',
				array(
					'type' => 'profile',
					'id'   => $profile_id,
				),
				array( 'slug' => $old ),
				array( 'slug' => $new ),
				trim( $reason )
			);
		}
		return $new;
	}

	/**
	 * Note handler.
	 *
	 * @param Request $request Request.
	 */
	public static function handle_note( Request $request ): void {
		$id = $request->int( 'profile_id' );
		try {
			NotesService::for_site()->add( 'profile', $id, $request->text( 'body' ), self::NOTES_CAP );
			self::back( $id, 'saved' );
		} catch ( \InvalidArgumentException | \DomainException $e ) {
			self::back( $id, 'refused', $e->getMessage() );
		}
	}

	/**
	 * Redirect back to the edit screen.
	 *
	 * @param int    $profile_id Profile.
	 * @param string $result     Result word.
	 * @param string $why        Refusal message.
	 */
	private static function back( int $profile_id, string $result, string $why = '' ): void {
		$url = add_query_arg( 'result', $result, ProfileEditScreen::url( $profile_id ) );
		wp_safe_redirect( '' === $why ? $url : add_query_arg( 'why', rawurlencode( $why ), $url ) );
		exit;
	}

	/**
	 * All panels for a profile.
	 *
	 * @param int                  $id    Profile.
	 * @param array<string, mixed> $state State row.
	 */
	public function html( int $id, array $state ): string {
		return '<div class="wiw-panels">' . $this->status( $state ) . $this->contact( $id ) . $this->enquiries( $id ) . $this->url_panel( $id )
			. $this->sources( $id ) . $this->sync( $id ) . $this->history( $id ) . $this->notes( $id )
			. self::card( __( 'Claim', 'whoiswho' ), '<p class="wiw-help">' . esc_html__( 'Nothing to show yet.', 'whoiswho' ) . '</p>' ) . '</div>';
	}

	/**
	 * One panel as a card of the staff form kit (REQ-275).
	 *
	 * @param string $title       Heading.
	 * @param string $body        Body markup, already escaped.
	 * @param string $description Optional description.
	 */
	private static function card( string $title, string $body, string $description = '' ): string {
		return Form::section(
			$title,
			$body,
			array(
				'level'       => 3,
				'description' => $description,
				'class'       => 'wiw-card--panel',
			)
		);
	}

	/**
	 * State, origin, source, batch and the Claimed mark (staff only).
	 *
	 * @param array<string, mixed> $state State row.
	 */
	private function status( array $state ): string {
		$rows = array(
			__( 'State', 'whoiswho' )   => Labels::state( (string) $state['pub_state'], (string) ( $state['origin'] ?? '' ) ),
			__( 'Origin', 'whoiswho' )  => Labels::origin( (string) ( $state['origin'] ?? '' ) ),
			__( 'Source', 'whoiswho' )  => 'manual' === (string) ( $state['source'] ?? '' ) ? Labels::origin( 'manual' ) : (string) ( $state['source'] ?? '' ),
			__( 'Batch', 'whoiswho' )   => (string) ( $state['import_batch'] ?? '' ),
			__( 'Claimed', 'whoiswho' ) => (int) ( $state['claimed'] ?? 0 ) > 0 ? __( 'Yes', 'whoiswho' ) : __( 'No', 'whoiswho' ),
		);
		$out  = '<table class="widefat striped wiw-kv"><tbody>';
		foreach ( $rows as $label => $value ) {
			$out .= '<tr><th scope="row">' . esc_html( (string) $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		return self::card( __( 'Status and marks', 'whoiswho' ), $out . '</tbody></table>' );
	}

	/**
	 * One form per contact detail: value, public switch (private by default) and the reason.
	 *
	 * @param int $id Profile.
	 */
	private function contact( int $id ): string {
		$out = '';
		foreach ( ( new ContactSwitches() )->all( $id ) as $kind => $entry ) {
			$key  = 'wiw-contact-' . sanitize_key( (string) $kind );
			$out .= $this->form( self::CONTACT, $id ) . '<input type="hidden" name="kind" value="' . esc_attr( $kind ) . '" /><div class="wiw-inline-fields">';
			$out .= Form::text( 'value', ucfirst( (string) $kind ), (string) $entry['value'], array( 'id' => $key . '-value' ) );
			$out .= Form::text(
				'reason',
				__( 'Reason', 'whoiswho' ),
				'',
				array(
					'id'       => $key . '-reason',
					'required' => true,
				)
			);
			$out .= Form::toggle( 'public', __( 'Show publicly', 'whoiswho' ), (bool) $entry['public'], array( 'id' => $key . '-public' ) );
			$out .= '<div class="wiw-inline-fields__action"><button class="button">' . esc_html__( 'Save', 'whoiswho' ) . '</button></div></div></form>';
		}
		return self::card( __( 'Contact details', 'whoiswho' ), $out, __( 'Private unless you switch one on after the owner asks. A reason is required.', 'whoiswho' ) );
	}

	/**
	 * The enquiries on/off switch.
	 *
	 * @param int $id Profile.
	 */
	private function enquiries( int $id ): string {
		$open  = '1' !== (string) get_post_meta( $id, PublicProfile::ENQUIRIES_OFF_META, true );
		$body  = $this->form( self::ENQUIRIES, $id ) . '<div class="wiw-inline-fields">';
		$body .= Form::text(
			'reason',
			__( 'Reason', 'whoiswho' ),
			'',
			array(
				'id'       => 'wiw-enquiries-reason',
				'required' => true,
			)
		);
		$body .= Form::toggle( 'open', __( 'Accept enquiries about this profile', 'whoiswho' ), $open, array( 'id' => 'wiw-enquiries-open' ) );
		$body .= '<div class="wiw-inline-fields__action"><button class="button">' . esc_html__( 'Save', 'whoiswho' ) . '</button></div></div></form>';
		return self::card( __( 'Enquiries', 'whoiswho' ), $body );
	}

	/**
	 * The public URL and the form that changes it (the old address redirects).
	 *
	 * @param int $id Profile.
	 */
	private function url_panel( int $id ): string {
		$post  = get_post( $id );
		$slug  = $post instanceof \WP_Post ? $post->post_name : '';
		$body  = '<p><code>' . esc_html( ProfileSlugs::url_for_slug( $slug ) ) . '</code></p>' . $this->form( self::SLUG, $id ) . '<div class="wiw-inline-fields">';
		$body .= Form::text( 'slug', __( 'New address', 'whoiswho' ), $slug, array( 'id' => 'wiw-slug-value' ) );
		$body .= Form::text(
			'reason',
			__( 'Reason', 'whoiswho' ),
			'',
			array(
				'id'       => 'wiw-slug-reason',
				'required' => true,
			)
		);
		$body .= '<div class="wiw-inline-fields__action"><button class="button">' . esc_html__( 'Change (the old address keeps redirecting)', 'whoiswho' ) . '</button></div></div></form>';
		return self::card( __( 'Public URL', 'whoiswho' ), $body );
	}

	/**
	 * Each field's source URL, retrieval date, confidence and method. Read-only, with no edit control.
	 *
	 * @param int $id Profile.
	 */
	private function sources( int $id ): string {
		if ( ! current_user_can( self::SOURCES_CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return '';
		}
		$out = '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Field', 'Source', 'Retrieved', 'Confidence', 'Method' ) as $heading ) {
			$out .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		$out    .= '</tr></thead><tbody>';
		$entries = ( new ProvenanceRepository() )->for_profile( $id );
		$type    = (string) ( ( new StateRepository() )->get( $id )['entity_type'] ?? '' );
		foreach ( $entries as $entry ) {
			$out .= '<tr><th scope="row">' . esc_html( Labels::field( (string) $entry['field'], $type ) ) . '</th><td>' . esc_html( (string) $entry['source_url'] ) . '</td><td>' . esc_html( (string) $entry['retrieved_at'] ) . '</td><td>' . esc_html( '' === (string) $entry['confidence'] ? '' : Labels::humanise( (string) $entry['confidence'] ) ) . '</td><td>' . esc_html( Labels::method( (string) $entry['method'] ) ) . '</td></tr>';
		}
		return self::card( __( 'Where each fact comes from', 'whoiswho' ), $out . ( array() === $entries ? '<tr><td colspan="5">' . esc_html__( 'No sources recorded.', 'whoiswho' ) . '</td></tr>' : '' ) . '</tbody></table>' );
	}

	/**
	 * Fields whose source changed since review: listed as flagged, never applied here.
	 *
	 * @param int $id Profile.
	 * @return array<int, array<string, mixed>>
	 */
	public function flagged_changes( int $id ): array {
		return array_values(
			array_filter(
				( new VersionRepository() )->pending_for_profile( $id ),
				static fn ( array $row ): bool => 'import' === $row['created_via']
			)
		);
	}

	/**
	 * The "Changes from the source since review" panel, shown only when a re-import brought a change that waits for review.
	 *
	 * @param int $id Profile.
	 */
	private function sync( int $id ): string {
		$flagged = $this->flagged_changes( $id );
		if ( array() === $flagged ) {
			return '';
		}
		$out = '<div class="notice notice-warning inline"><p>' . esc_html__( 'A source changed. The change is flagged for review and has not been applied.', 'whoiswho' ) . '</p><ul>';
		foreach ( $flagged as $row ) {
			$fields = array_keys( (array) json_decode( (string) $row['new_value'], true ) );
			$out   .= '<li>' . esc_html( implode( ', ', array_map( static fn ( $key ): string => Labels::field( (string) $key ), $fields ) ) . ' (' . (string) $row['created_at'] . ')' ) . '</li>';
		}
		return self::card( __( 'Changes from the source since review', 'whoiswho' ), $out . '</ul></div>', __( 'A later import found different values at the source. They are not shown publicly until a reviewer accepts them.', 'whoiswho' ) );
	}

	/**
	 * Revision history: author, time and reason.
	 *
	 * @param int $id Profile.
	 */
	private function history( int $id ): string {
		$out  = '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'When', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Author', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Via', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Reason', 'whoiswho' ) . '</th></tr></thead><tbody>';
		$rows = ( new VersionRepository() )->for_profile( $id );
		foreach ( $rows as $row ) {
			$user = (int) $row['author_id'] > 0 ? get_userdata( (int) $row['author_id'] ) : false;
			$out .= '<tr><td>' . esc_html( (string) $row['created_at'] ) . '</td><td>' . esc_html( false === $user ? __( 'System', 'whoiswho' ) : (string) $user->display_name ) . '</td><td>' . esc_html( Labels::method( (string) $row['created_via'] ) . ( null !== $row['approval_status'] ? ' (' . Labels::humanise( (string) $row['approval_status'] ) . ')' : '' ) ) . '</td><td>' . esc_html( (string) $row['reason'] ) . '</td></tr>';
		}
		return self::card( __( 'Revision history', 'whoiswho' ), $out . ( array() === $rows ? '<tr><td colspan="4">' . esc_html__( 'No changes yet.', 'whoiswho' ) . '</td></tr>' : '' ) . '</tbody></table>' );
	}

	/**
	 * Internal notes: staff only, never public.
	 *
	 * @param int $id Profile.
	 */
	private function notes( int $id ): string {
		if ( ! current_user_can( self::NOTES_CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return '';
		}
		$out = '<ul class="wiw-notes">';
		foreach ( NotesService::for_site()->list( 'profile', $id, self::NOTES_CAP ) as $note ) {
			$out .= '<li>' . esc_html( $note['created_at'] . ': ' . $note['body'] ) . '</li>';
		}
		$out .= '</ul>' . $this->form( self::NOTE, $id ) . Form::textarea(
			'body',
			__( 'Add a note', 'whoiswho' ),
			'',
			array(
				'id'       => 'wiw-note-body',
				'rows'     => 3,
				'required' => true,
			)
		) . Form::actions( '<button class="button">' . esc_html__( 'Add note', 'whoiswho' ) . '</button>' ) . '</form>';
		return self::card( __( 'Internal notes', 'whoiswho' ), $out, __( 'Staff only. Never shown publicly.', 'whoiswho' ) );
	}

	/**
	 * A form opening tag with the action, nonce and profile.
	 *
	 * @param string $action Action.
	 * @param int    $id     Profile.
	 */
	private function form( string $action, int $id ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-form"><input type="hidden" name="action" value="' . esc_attr( $action ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( $action ) ) . '" /><input type="hidden" name="profile_id" value="' . esc_attr( (string) $id ) . '" />';
	}
}
