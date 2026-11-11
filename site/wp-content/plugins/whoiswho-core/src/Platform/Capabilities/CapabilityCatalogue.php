<?php
/**
 * The one reader of config/capabilities.php. Screens, the sync and tests use this, never the file directly.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Capabilities;

/**
 * Capability catalogue.
 */
final class CapabilityCatalogue {

	/**
	 * Parsed config.
	 *
	 * @var array{version: int, roles: array<string, array{label: string, bundle: string}>, capabilities: array<string, array{source: string, roles: string[], fixed?: bool}>}
	 */
	private array $config;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed>|null $config Config array; defaults to the plugin's config file.
	 */
	public function __construct( ?array $config = null ) {
		if ( null === $config ) {
			$config = require dirname( __DIR__, 3 ) . '/config/capabilities.php';
		}
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- inline type hint for static analysis.
		/** @var array{version: int, roles: array<string, array{label: string, bundle: string}>, capabilities: array<string, array{source: string, roles: string[], fixed?: bool}>} $config */
		$this->config = $config;
	}

	/**
	 * Catalogue version. Bump it when the config changes.
	 */
	public function version(): int {
		return $this->config['version'];
	}

	/**
	 * Every capability name.
	 *
	 * @return string[]
	 */
	public function all(): array {
		return array_keys( $this->config['capabilities'] );
	}

	/**
	 * Capabilities a default bundle holds.
	 *
	 * @param string $bundle reviewer, editor or administrator.
	 * @return string[]
	 */
	public function for_bundle( string $bundle ): array {
		$out = array();
		foreach ( $this->config['capabilities'] as $cap => $def ) {
			if ( in_array( $bundle, $def['roles'], true ) ) {
				$out[] = $cap;
			}
		}
		return $out;
	}

	/**
	 * Capabilities added by this plugin (wiw_*), as opposed to core ones.
	 *
	 * @return string[]
	 */
	public function plugin_capabilities(): array {
		return array_keys( array_filter( $this->config['capabilities'], static fn ( array $def ): bool => 'plugin' === $def['source'] ) );
	}

	/**
	 * Fixed capabilities: never grantable to a non-Administrator.
	 *
	 * @return string[]
	 */
	public function fixed(): array {
		return array_keys( array_filter( $this->config['capabilities'], static fn ( array $def ): bool => ! empty( $def['fixed'] ) ) );
	}

	/**
	 * The custom roles to create: role name => label and bundle.
	 *
	 * @return array<string, array{label: string, bundle: string}>
	 */
	public function roles(): array {
		return $this->config['roles'];
	}
}
