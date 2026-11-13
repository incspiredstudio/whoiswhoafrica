<?php
/**
 * The short-lived draft of a public nomination (REQ-196, REQ-233): the nominator's name and email, and whether the email link has
 * been opened, kept until the nomination is submitted. A draft is held in a transient keyed by an unguessable token (carried in
 * the form), expires after the link does, and is deleted on submit. It is not a record: nothing public or staff-side reads it,
 * and no account is created.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

/**
 * Nomination drafts.
 */
final class NominationDrafts {

	public const PREFIX = 'wiw_nomdraft_';
	public const TTL    = 1209600;
	// 14 days, the life of the verification link.

	/**
	 * Create a draft and return its token.
	 *
	 * @param string $name  Nominator name.
	 * @param string $email Nominator email.
	 * @return string Token.
	 */
	public function create( string $name, string $email ): string {
		$token = bin2hex( random_bytes( 16 ) );
		set_transient(
			self::PREFIX . $token,
			array(
				'name'        => $name,
				'email'       => $email,
				'verified_at' => null,
			),
			self::TTL
		);
		return $token;
	}

	/**
	 * A draft, or null when the token is unknown, malformed or expired.
	 *
	 * @param string $token Token.
	 * @return array{name: string, email: string, verified_at: string|null}|null
	 */
	public function get( string $token ): ?array {
		if ( 1 !== preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
			return null;
		}
		$draft = get_transient( self::PREFIX . $token );
		return is_array( $draft ) && isset( $draft['name'], $draft['email'] ) ? array(
			'name'        => (string) $draft['name'],
			'email'       => (string) $draft['email'],
			'verified_at' => isset( $draft['verified_at'] ) ? (string) $draft['verified_at'] : null,
		) : null;
	}

	/**
	 * Mark the email as verified.
	 *
	 * @param string $token Token.
	 * @return bool False when the draft is gone.
	 */
	public function verify( string $token ): bool {
		$draft = $this->get( $token );
		if ( null === $draft ) {
			return false;
		}
		$draft['verified_at'] = gmdate( 'Y-m-d H:i:s' );
		set_transient( self::PREFIX . $token, $draft, self::TTL );
		return true;
	}

	/**
	 * Delete a draft.
	 *
	 * @param string $token Token.
	 */
	public function delete( string $token ): void {
		delete_transient( self::PREFIX . $token );
	}
}
