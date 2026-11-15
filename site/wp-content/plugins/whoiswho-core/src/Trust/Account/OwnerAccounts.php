<?php
/**
 * Profile owner accounts (REQ-274): staff create them (public registration stays closed), and the owner chooses a password from a
 * branded email. provision() creates an account with the Profile owner role for an email address, or reuses the owner account that
 * already has it; an address that belongs to any other account (a staff member) is refused, so a claim can never hand a staff
 * account to a claimant. The password emails carry a core reset key (get_password_reset_key) and a link to the public
 * /account/lost-password/ page; they are sent at once and never queued, so the key is not stored in the job table. The audit rows
 * hold the account id, never the name or email.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Account;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Owner account service.
 */
final class OwnerAccounts {

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Mailer: callable( to, subject, text, html ): bool.
	 *
	 * @var callable
	 */
	private $mailer;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger|null $audit  Audit logger.
	 * @param callable|null    $mailer Mailer; the default is Notifier::wp_mail_send.
	 */
	public function __construct( ?AuditLogger $audit = null, ?callable $mailer = null ) {
		$this->audit  = $audit ?? AuditLogger::for_site();
		$this->mailer = $mailer ?? array( Notifier::class, 'wp_mail_send' );
	}

	/**
	 * Create the owner account for an address, or reuse the one that has it.
	 *
	 * @param string $email Address (the claimant's work email).
	 * @param string $name  Display name.
	 * @return array{user_id: int, created: bool}
	 * @throws \DomainException When the address is not usable or belongs to an account that is not a Profile owner.
	 */
	public function provision( string $email, string $name ): array {
		$email = sanitize_email( $email );
		if ( '' === $email || false === is_email( $email ) ) {
			throw new \DomainException( 'The claim has no usable email address for an account.' );
		}
		$existing = get_user_by( 'email', $email );
		if ( $existing instanceof \WP_User ) {
			if ( ! OwnerRole::is_owner_only( (int) $existing->ID ) ) {
				throw new \DomainException( 'That email address belongs to a staff account. Approve the claim without an account, or ask the claimant for another address.' );
			}
			$this->audit->log( 'owner.account_reused', $this->subject( (int) $existing->ID ), null, array( 'role' => OwnerRole::ROLE ), 'Existing profile owner account used' );
			return array(
				'user_id' => (int) $existing->ID,
				'created' => false,
			);
		}
		$name = trim( sanitize_text_field( $name ) );
		$id   = wp_insert_user(
			array(
				'user_login'   => $this->login_for( $email ),
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => '' !== $name ? $name : __( 'Profile owner', 'whoiswho' ),
				'first_name'   => '' !== $name ? (string) strtok( $name, ' ' ) : '',
				'role'         => OwnerRole::ROLE,
			)
		);
		if ( ! is_int( $id ) ) {
			throw new \DomainException( 'The account could not be created.' );
		}
		$this->audit->log( 'owner.account_created', $this->subject( $id ), null, array( 'role' => OwnerRole::ROLE ), 'Profile owner account created' );
		return array(
			'user_id' => $id,
			'created' => true,
		);
	}

	/**
	 * A unique login name that is not the address itself: "owner-" and random letters, so no name or email is in the login.
	 *
	 * @param string $email Address (unused beyond uniqueness of the account).
	 */
	private function login_for( string $email ): string {
		unset( $email );
		do {
			$login = 'owner-' . strtolower( wp_generate_password( 10, false, false ) );
		} while ( username_exists( $login ) );
		return $login;
	}

	/**
	 * Send the "Set your password" email to a new or reused owner.
	 *
	 * @param int $user_id Owner account.
	 * @return bool Whether the mailer accepted it.
	 */
	public function send_set_password( int $user_id ): bool {
		return $this->send_key( $user_id, Events::OWNER_SET_PASSWORD );
	}

	/**
	 * Send the "Reset your password" email (the lost-password page). Only a Profile owner account gets one here; staff reset in
	 * wp-login.php.
	 *
	 * @param int $user_id Owner account.
	 * @return bool Whether the mailer accepted it.
	 */
	public function send_reset( int $user_id ): bool {
		return OwnerRole::is_owner_only( $user_id ) && $this->send_key( $user_id, Events::OWNER_RESET_PASSWORD );
	}

	/**
	 * The public reset address for a key.
	 *
	 * @param int    $user_id User.
	 * @param string $key     Core reset key.
	 */
	public static function reset_url( int $user_id, string $key ): string {
		return add_query_arg(
			array(
				'key' => rawurlencode( $key ),
				'id'  => $user_id,
			),
			OwnerRole::url( 'lost-password/' )
		);
	}

	/**
	 * Make a core reset key and mail the link.
	 *
	 * @param int    $user_id User.
	 * @param string $event   Email event.
	 */
	private function send_key( int $user_id, string $event ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || '' === (string) $user->user_email ) {
			return false;
		}
		$key = get_password_reset_key( $user );
		if ( ! is_string( $key ) ) {
			return false;
		}
		$message = Notifier::for_site()->render(
			$event,
			array(
				'cta_url' => self::reset_url( $user_id, $key ),
				'name'    => (string) $user->display_name,
			)
		);
		return (bool) call_user_func( $this->mailer, (string) $user->user_email, $message['subject'], $message['text'], $message['html'] );
	}

	/**
	 * Audit subject.
	 *
	 * @param int $user_id User.
	 * @return array{type: string, id: int}
	 */
	private function subject( int $user_id ): array {
		return array(
			'type' => 'user',
			'id'   => $user_id,
		);
	}
}
