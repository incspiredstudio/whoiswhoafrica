<?php
/**
 * A small service registry. No container framework: modules and services are added by name and read back by name.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform;

/**
 * Holds module bootstraps and shared services.
 */
final class Registry {

	/**
	 * Registered modules by name.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $modules = array();

	/**
	 * Registered services by id.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Add a module.
	 *
	 * @param ModuleInterface $module Module.
	 */
	public function add_module( ModuleInterface $module ): void {
		$this->modules[ $module->name() ] = $module;
	}

	/**
	 * Module names in registration order.
	 *
	 * @return string[]
	 */
	public function module_names(): array {
		return array_keys( $this->modules );
	}

	/**
	 * Let every module register its hooks.
	 */
	public function boot(): void {
		foreach ( $this->modules as $module ) {
			$module->register();
		}
	}

	/**
	 * Store a service.
	 *
	 * @param string $id      Service id.
	 * @param object $service Service.
	 */
	public function set( string $id, object $service ): void {
		$this->services[ $id ] = $service;
	}

	/**
	 * Read a service.
	 *
	 * @param string $id Service id.
	 * @throws \OutOfBoundsException When the service is unknown.
	 */
	public function get( string $id ): object {
		if ( ! isset( $this->services[ $id ] ) ) {
			throw new \OutOfBoundsException( 'Unknown service: ' . $id ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return $this->services[ $id ];
	}
}
