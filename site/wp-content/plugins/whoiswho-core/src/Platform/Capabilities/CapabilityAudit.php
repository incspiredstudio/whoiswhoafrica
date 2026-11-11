<?php
/**
 * Audits every change to capabilities, whichever screen or plugin made it (REQ-244, REQ-188, REQ-051): a user's capabilities
 * (the capabilities user meta, which also holds the user's role), the role definitions (the user_roles option: a role created,
 * deleted or given or denied a capability). Each row carries the acting user, the subject and the diff as old and new values.
 * Changes made with nobody signed in (the command line, the role sync) are logged with actor 0 and a reason saying so.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Capabilities;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Capability audit.
 */
final class CapabilityAudit {

	public const SYSTEM_REASON = 'system: no signed-in user (command line or role sync)';

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Capabilities as they were before a user meta write, by user id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $before = array();

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit Audit logger.
	 */
	public function __construct( AuditLogger $audit ) {
		$this->audit = $audit;
	}

	/**
	 * Listener for this site.
	 */
	public static function for_site(): self {
		return new self( AuditLogger::for_site() );
	}

	/**
	 * The user meta key holding a user's capabilities.
	 */
	public static function user_meta_key(): string {
		global $wpdb;
		return $wpdb->get_blog_prefix() . 'capabilities';
	}

	/**
	 * The option holding the role definitions.
	 */
	public static function roles_option(): string {
		global $wpdb;
		return $wpdb->get_blog_prefix() . 'user_roles';
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'update_user_metadata', array( $this, 'remember_user_caps' ), 1, 4 );
		add_action( 'added_user_meta', array( $this, 'on_user_meta_added' ), 10, 4 );
		add_action( 'updated_user_meta', array( $this, 'on_user_meta_updated' ), 10, 4 );
		add_action( 'update_option_' . self::roles_option(), array( $this, 'on_roles_updated' ), 10, 2 );
		add_action( 'add_option_' . self::roles_option(), array( $this, 'on_roles_added' ), 10, 2 );
	}

	/**
	 * Detach hooks (for tests).
	 */
	public function unregister(): void {
		remove_filter( 'update_user_metadata', array( $this, 'remember_user_caps' ), 1 );
		remove_action( 'added_user_meta', array( $this, 'on_user_meta_added' ), 10 );
		remove_action( 'updated_user_meta', array( $this, 'on_user_meta_updated' ), 10 );
		remove_action( 'update_option_' . self::roles_option(), array( $this, 'on_roles_updated' ), 10 );
		remove_action( 'add_option_' . self::roles_option(), array( $this, 'on_roles_added' ), 10 );
	}

	/**
	 * Which capabilities are granted (true) or denied (false) in a stored map.
	 *
	 * @param mixed $caps Stored capabilities.
	 * @return array<string, bool>
	 */
	public static function normalise( $caps ): array {
		$out = array();
		foreach ( is_array( $caps ) ? $caps : array() as $name => $granted ) {
			$out[ (string) $name ] = (bool) $granted;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * The change between two capability maps.
	 *
	 * @param array<string, bool> $old_caps Before.
	 * @param array<string, bool> $new_caps After.
	 * @return array{granted: string[], revoked: string[]} Names now granted that were not, and names no longer granted.
	 */
	public static function diff( array $old_caps, array $new_caps ): array {
		$had = array_keys( array_filter( $old_caps ) );
		$has = array_keys( array_filter( $new_caps ) );
		return array(
			'granted' => array_values( array_diff( $has, $had ) ),
			'revoked' => array_values( array_diff( $had, $has ) ),
		);
	}

	/**
	 * Remember the capabilities before WordPress overwrites them.
	 *
	 * @param mixed  $check    Short-circuit value, passed through.
	 * @param int    $user_id  User.
	 * @param string $key      Meta key.
	 * @param mixed  $value    New value.
	 * @return mixed
	 */
	public function remember_user_caps( $check, $user_id, $key, $value ) {
		unset( $value );
		if ( self::user_meta_key() === $key ) {
			$this->before[ (int) $user_id ] = self::normalise( get_user_meta( (int) $user_id, $key, true ) );
		}
		return $check;
	}

	/**
	 * A user's capabilities were first written.
	 *
	 * @param int    $meta_id  Meta id.
	 * @param int    $user_id  User.
	 * @param string $key      Meta key.
	 * @param mixed  $value    Value.
	 */
	public function on_user_meta_added( $meta_id, $user_id, $key, $value ): void {
		unset( $meta_id );
		if ( self::user_meta_key() === $key ) {
			$this->record_user( (int) $user_id, array(), self::normalise( $value ) );
		}
	}

	/**
	 * A user's capabilities were changed.
	 *
	 * @param int    $meta_id  Meta id.
	 * @param int    $user_id  User.
	 * @param string $key      Meta key.
	 * @param mixed  $value    Value.
	 */
	public function on_user_meta_updated( $meta_id, $user_id, $key, $value ): void {
		unset( $meta_id );
		if ( self::user_meta_key() !== $key ) {
			return;
		}
		$old_caps = $this->before[ (int) $user_id ] ?? array();
		unset( $this->before[ (int) $user_id ] );
		$this->record_user( (int) $user_id, $old_caps, self::normalise( $value ) );
	}

	/**
	 * The role definitions were changed.
	 *
	 * @param mixed $old_caps Old roles.
	 * @param mixed $new_caps New roles.
	 */
	public function on_roles_updated( $old_caps, $new_caps ): void {
		$old_caps = is_array( $old_caps ) ? $old_caps : array();
		$new_caps = is_array( $new_caps ) ? $new_caps : array();
		foreach ( array_diff( array_keys( $new_caps ), array_keys( $old_caps ) ) as $role ) {
			$this->write( 'role.create', (string) $role, array(), array( 'granted' => array_keys( array_filter( self::normalise( $new_caps[ $role ]['capabilities'] ?? array() ) ) ) ) );
		}
		foreach ( array_diff( array_keys( $old_caps ), array_keys( $new_caps ) ) as $role ) {
			$this->write( 'role.delete', (string) $role, array( 'revoked' => array_keys( array_filter( self::normalise( $old_caps[ $role ]['capabilities'] ?? array() ) ) ) ), array() );
		}
		foreach ( array_intersect( array_keys( $old_caps ), array_keys( $new_caps ) ) as $role ) {
			$change = self::diff( self::normalise( $old_caps[ $role ]['capabilities'] ?? array() ), self::normalise( $new_caps[ $role ]['capabilities'] ?? array() ) );
			if ( array() !== $change['granted'] || array() !== $change['revoked'] ) {
				$this->write( 'role.capabilities', (string) $role, array( 'revoked' => $change['revoked'] ), array( 'granted' => $change['granted'] ) );
			}
		}
	}

	/**
	 * The role definitions were first stored.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Roles.
	 */
	public function on_roles_added( $option, $value ): void {
		unset( $option );
		$this->on_roles_updated( array(), $value );
	}

	/**
	 * Audit a user's capability change when anything was granted or revoked.
	 *
	 * @param int                 $user_id User.
	 * @param array<string, bool> $old_caps     Before.
	 * @param array<string, bool> $new_caps     After.
	 */
	private function record_user( int $user_id, array $old_caps, array $new_caps ): void {
		$change = self::diff( $old_caps, $new_caps );
		if ( array() === $change['granted'] && array() === $change['revoked'] ) {
			return;
		}
		$this->write( 'user.capabilities', (string) $user_id, array( 'revoked' => $change['revoked'] ), array( 'granted' => $change['granted'] ), 'user' );
	}

	/**
	 * Write one audit row. A failure to log never breaks the change being made.
	 *
	 * @param string $action  Action name.
	 * @param string $subject Subject id.
	 * @param mixed  $before  Old value.
	 * @param mixed  $after   New value.
	 * @param string $type    Subject type.
	 */
	private function write( string $action, string $subject, $before, $after, string $type = 'role' ): void {
		global $wpdb;
		$reason   = 0 === (int) get_current_user_id() ? self::SYSTEM_REASON : '';
		$previous = $wpdb->suppress_errors( true );
		try {
			$this->audit->log(
				$action,
				array(
					'type' => $type,
					'id'   => $subject,
				),
				$before,
				$after,
				$reason
			);
		} catch ( \Throwable $e ) {
			unset( $e );
			// The change stands even when the log is busy or its table does not exist yet.
		} finally {
			$wpdb->suppress_errors( $previous );
		}
	}
}
