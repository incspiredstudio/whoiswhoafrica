<?php
/**
 * The one place plugin settings are read and written. Every key is registered (type, default, capability to change it, audited
 * or not); unknown keys are refused. Options are stored with autoload off. A change needs the key's named capability and writes
 * an audit row with the old and new values. Brand values (colours, fonts, logos, brand names) are refused: they live in the
 * child theme tokens file, not in settings.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Settings;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Settings service.
 */
final class SettingsService {

	public const OPTION_PREFIX = 'wiw_setting_';

	public const NOMINATIONS_ENABLED = 'nominations_enabled';
	public const NOMINATIONS_REASON  = 'nominations_reason';
	public const POLICY_VERSION      = 'policy_version';
	public const SPRINT_TRACKING     = 'sprint_tracking';

	/**
	 * Key names that look like brand values are never accepted as settings.
	 */
	private const BRAND_KEY_PATTERN = '/colou?r|font|typeface|logo|brand|palette|theme_mod|hex/i';

	/**
	 * Registered keys: type (bool, int, string), default, capability, audited, optional max length for strings.
	 *
	 * @var array<string, array{type: string, default: mixed, capability: string, audited: bool, max?: int}>
	 */
	private array $registry;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger|null
	 */
	private ?AuditLogger $audit;

	/**
	 * Returns whether the current user holds a capability.
	 *
	 * @var callable
	 */
	private $can;

	/**
	 * Constructor.
	 *
	 * @param array<string, array{type: string, default: mixed, capability: string, audited: bool, max?: int}> $registry Registered keys.
	 * @param AuditLogger|null                                                                                 $audit    Audit logger.
	 * @param callable|null                                                                                    $can      fn( string $capability ): bool.
	 * @throws \InvalidArgumentException When a key looks like a brand value or a definition is incomplete.
	 */
	public function __construct( array $registry, ?AuditLogger $audit = null, ?callable $can = null ) {
		foreach ( $registry as $key => $definition ) {
			if ( 1 === preg_match( self::BRAND_KEY_PATTERN, $key ) ) {
				throw new \InvalidArgumentException( 'Brand values live in the theme tokens file, not in settings.' );
			}
			if ( ! in_array( $definition['type'], array( 'bool', 'int', 'string' ), true ) || '' === $definition['capability'] ) {
				throw new \InvalidArgumentException( 'A setting needs a type and a capability.' );
			}
		}
		$this->registry = $registry;
		$this->audit    = $audit;
		$this->can      = $can ?? static fn ( string $capability ): bool => function_exists( 'current_user_can' ) && current_user_can( $capability );
	}

	/**
	 * The keys the plugin knows.
	 *
	 * @return array<string, array{type: string, default: mixed, capability: string, audited: bool, max?: int}>
	 */
	public static function default_registry(): array {
		return array(
			self::NOMINATIONS_ENABLED => array(
				'type'       => 'bool',
				'default'    => false,
				'capability' => 'wiw_toggle_nominations',
				'audited'    => true,
			),
			self::NOMINATIONS_REASON  => array(
				'type'       => 'string',
				'default'    => '',
				'capability' => 'wiw_toggle_nominations',
				'audited'    => true,
				'max'        => 300,
			),
			self::POLICY_VERSION      => array(
				'type'       => 'string',
				'default'    => '1',
				'capability' => 'manage_options',
				'audited'    => true,
				'max'        => 40,
			),
			self::SPRINT_TRACKING     => array(
				'type'       => 'bool',
				'default'    => true,
				'capability' => 'manage_options',
				'audited'    => true,
			),
			'sprint_target_profiles'  => array(
				'type'       => 'int',
				'default'    => 0,
				'capability' => 'manage_options',
				'audited'    => true,
			),
			'sprint_target_verified'  => array(
				'type'       => 'int',
				'default'    => 0,
				'capability' => 'manage_options',
				'audited'    => true,
			),
			'import_all_not_public'   => array(
				'type'       => 'bool',
				'default'    => true,
				'capability' => 'wiw_run_imports',
				'audited'    => true,
			),
		);
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( self::default_registry(), AuditLogger::for_site() );
	}

	/**
	 * Whether Nominations is on. The only feature flag.
	 */
	public function nominations_enabled(): bool {
		return (bool) $this->get( self::NOMINATIONS_ENABLED );
	}

	/**
	 * Whether sprint tracking is on (REQ-272): the dashboard sprint card and the Sprint thresholds screen show only while it is.
	 */
	public function sprint_tracking_enabled(): bool {
		return (bool) $this->get( self::SPRINT_TRACKING );
	}

	/**
	 * Read a setting, falling back to its default.
	 *
	 * @param string $key Registered key.
	 * @return bool|int|string
	 * @throws \InvalidArgumentException For an unregistered key.
	 */
	public function get( string $key ) {
		$definition = $this->definition( $key );
		$stored     = get_option( self::OPTION_PREFIX . $key, null );
		if ( null === $stored ) {
			return $definition['default'];
		}
		return $this->cast( $definition['type'], $stored );
	}

	/**
	 * Change a setting.
	 *
	 * @param string $key    Registered key.
	 * @param mixed  $value  New value.
	 * @param string $reason Optional reason recorded in the audit row.
	 * @throws \DomainException When the user lacks the key's capability (an unregistered key or invalid value throws InvalidArgumentException).
	 */
	public function set( string $key, $value, string $reason = '' ): void {
		$definition = $this->definition( $key );
		if ( ! ( $this->can )( $definition['capability'] ) ) {
			throw new \DomainException( 'Not allowed to change this setting.' );
		}
		$new = $this->validate( $definition, $value );
		$old = $this->get( $key );
		if ( $old === $new ) {
			return;
		}
		// A bool is stored as "1" or "0": WordPress skips a write of false when the option does not exist yet, which would leave a default-on setting on.
		update_option( self::OPTION_PREFIX . $key, is_bool( $new ) ? ( $new ? '1' : '0' ) : $new, false );
		if ( $definition['audited'] && null !== $this->audit ) {
			$this->audit->log(
				'settings.change',
				array(
					'type' => 'setting',
					'id'   => $key,
				),
				$old,
				$new,
				$reason
			);
		}
	}

	/**
	 * A key's definition.
	 *
	 * @param string $key Key.
	 * @return array{type: string, default: mixed, capability: string, audited: bool, max?: int}
	 * @throws \InvalidArgumentException For an unregistered key.
	 */
	private function definition( string $key ): array {
		if ( ! isset( $this->registry[ $key ] ) ) {
			throw new \InvalidArgumentException( 'Unknown setting.' );
		}
		return $this->registry[ $key ];
	}

	/**
	 * Validate and normalise a value for a definition.
	 *
	 * @param array{type: string, default: mixed, capability: string, audited: bool, max?: int} $definition Definition.
	 * @param mixed                                                                             $value      Value.
	 * @return bool|int|string
	 * @throws \InvalidArgumentException When the value does not fit.
	 */
	private function validate( array $definition, $value ) {
		if ( 'bool' === $definition['type'] ) {
			if ( ! is_bool( $value ) ) {
				throw new \InvalidArgumentException( 'Expected true or false.' );
			}
			return $value;
		}
		if ( 'int' === $definition['type'] ) {
			if ( ! is_int( $value ) || $value < 0 ) {
				throw new \InvalidArgumentException( 'Expected a whole number, zero or more.' );
			}
			return $value;
		}
		if ( ! is_string( $value ) ) {
			throw new \InvalidArgumentException( 'Expected text.' );
		}
		$value = trim( $value );
		if ( mb_strlen( $value, 'UTF-8' ) > ( $definition['max'] ?? 200 ) ) {
			throw new \InvalidArgumentException( 'Text is too long.' );
		}
		if ( 1 === preg_match( '/#[0-9a-f]{3,8}\b|\b(?:rgb|hsl)a?\(|font-family/i', $value ) ) {
			throw new \InvalidArgumentException( 'Brand values live in the theme tokens file, not in settings.' );
		}
		return $value;
	}

	/**
	 * Cast a stored option to the declared type.
	 *
	 * @param string $type   Type.
	 * @param mixed  $stored Stored value.
	 * @return bool|int|string
	 */
	private function cast( string $type, $stored ) {
		if ( 'bool' === $type ) {
			return (bool) $stored;
		}
		if ( 'int' === $type ) {
			return (int) $stored;
		}
		return is_scalar( $stored ) ? (string) $stored : '';
	}
}
