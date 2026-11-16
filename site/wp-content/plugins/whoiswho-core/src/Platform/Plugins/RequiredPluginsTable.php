<?php
/**
 * The Required plugins list as a shared staff list table (REQ-275, REQ-056, REQ-240). One row per plugin in the catalogue, with
 * what it does and its status as a pill. A plugin with something to do (not installed, or installed and not active) has a
 * checkbox; bulk Install selected and Activate selected repeat the single Install and Activate for each selected plugin through
 * the same installer and the same audit (RequiredPluginsScreen::run), so they add no power: the installer still needs WordPress's
 * install_plugins or activate_plugins. Row actions: Install, Install with WordPress (the core screen that asks for FTP details) and
 * Activate. The per-plugin buttons in the Action column post their own small forms, printed after the table (the button names its
 * form with the form attribute, so no form sits inside the list form).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Plugins;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;

/**
 * Required plugins table.
 */
final class RequiredPluginsTable extends ListTable {

	public const BULK = 'wiw_plugins_bulk';

	/**
	 * Status reader.
	 *
	 * @var RequiredPlugins
	 */
	private RequiredPlugins $plugins;

	/**
	 * Installer, or null for the site's own.
	 *
	 * @var PluginInstaller|null
	 */
	private ?PluginInstaller $installer;

	/**
	 * Constructor.
	 *
	 * @param RequiredPlugins|null $plugins   Status reader (tests pass one).
	 * @param PluginInstaller|null $installer Installer (tests pass one).
	 */
	public function __construct( ?RequiredPlugins $plugins = null, ?PluginInstaller $installer = null ) {
		$this->plugins   = $plugins ?? new RequiredPlugins();
		$this->installer = $installer;
		parent::__construct( 'plugin', 'required-plugins' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return self::BULK;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function capability(): string {
		return RequiredPluginsScreen::CAP;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return RequiredPluginsScreen::SCREEN;
	}

	/**
	 * A plugin's row id: its place in the catalogue, from 1. 0 for an unknown slug.
	 *
	 * @param string $slug Slug.
	 */
	public static function id_for( string $slug ): int {
		$position = array_search( $slug, array_keys( RequiredPlugins::catalogue() ), true );
		return false === $position ? 0 : (int) $position + 1;
	}

	/**
	 * The slug of a row id, or an empty string.
	 *
	 * @param int $id Row id.
	 */
	public static function slug_for( int $id ): string {
		$slugs = array_keys( RequiredPlugins::catalogue() );
		return $id >= 1 && isset( $slugs[ $id - 1 ] ) ? (string) $slugs[ $id - 1 ] : '';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'name'   => __( 'Plugin', 'whoiswho' ),
			'why'    => __( 'What it does', 'whoiswho' ),
			'status' => __( 'Status', 'whoiswho' ),
			'action' => __( 'Action', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'name';
	}

	/**
	 * Every plugin of the catalogue with its status.
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		$items = array();
		foreach ( array_keys( RequiredPlugins::catalogue() ) as $slug ) {
			$items[] = array(
				'slug'   => (string) $slug,
				'status' => $this->plugins->status( (string) $slug ),
			);
		}
		return array(
			'items' => array_slice( $items, ( $page - 1 ) * $per_page, $per_page ),
			'total' => count( $items ),
		);
	}

	/**
	 * A checkbox only for a plugin with something to do.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		$status = (string) ( $item['status'] ?? '' );
		return in_array( $status, array( RequiredPlugins::NOT_INSTALLED, RequiredPlugins::INSTALLED ), true ) ? self::id_for( (string) $item['slug'] ) : 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_name( array $item ): string {
		return RequiredPlugins::catalogue()[ (string) $item['slug'] ]['name'] ?? (string) $item['slug'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Row id.
	 */
	protected function name_for_id( int $id ): string {
		$slug = self::slug_for( $id );
		return '' === $slug ? '#' . $id : RequiredPlugins::catalogue()[ $slug ]['name'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		$slug   = (string) $item['slug'];
		$plugin = RequiredPlugins::catalogue()[ $slug ];
		$status = (string) $item['status'];
		switch ( $column ) {
			case 'name':
				return '<strong>' . esc_html( $plugin['name'] ) . '</strong><br><span class="description">' . esc_html( $plugin['required'] ? __( 'Required', 'whoiswho' ) : __( 'Recommended (optional)', 'whoiswho' ) ) . '</span>';
			case 'why':
				return esc_html( $plugin['why'] );
			case 'status':
				return self::status_pill( $status );
			case 'action':
				return self::action_html( $slug, $plugin['name'], $status );
		}
		return '';
	}

	/**
	 * The status as a pill.
	 *
	 * @param string $status Status.
	 */
	private static function status_pill( string $status ): string {
		$words = array(
			RequiredPlugins::NOT_INSTALLED => array( __( 'Not installed', 'whoiswho' ), 'danger' ),
			RequiredPlugins::INSTALLED     => array( __( 'Installed, not active', 'whoiswho' ), 'warning' ),
			RequiredPlugins::ACTIVE        => array( __( 'Active', 'whoiswho' ), 'success' ),
			RequiredPlugins::INCLUDED      => array( __( 'Included with the site plugin', 'whoiswho' ), 'success' ),
		);
		$word  = $words[ $status ] ?? array( $status, 'neutral' );
		return self::pill( $word[0], $word[1] );
	}

	/**
	 * The Action cell: the button that posts the plugin's own form, or what to do instead.
	 *
	 * @param string $slug   Slug.
	 * @param string $name   Plugin name.
	 * @param string $status Status.
	 */
	private static function action_html( string $slug, string $name, string $status ): string {
		if ( RequiredPlugins::NOT_INSTALLED === $status && current_user_can( 'install_plugins' ) ) {
			/* translators: %s: plugin name. */
			return self::form_button( RequiredPluginsScreen::form_id( RequiredPluginsScreen::ACTION_INSTALL, $slug ), sprintf( __( 'Install %s', 'whoiswho' ), $name ) )
				. ' <a href="' . esc_url( PluginInstaller::core_install_url( $slug ) ) . '">' . esc_html__( 'Install with WordPress', 'whoiswho' ) . '</a>';
		}
		if ( RequiredPlugins::INSTALLED === $status && current_user_can( 'activate_plugins' ) ) {
			/* translators: %s: plugin name. */
			return self::form_button( RequiredPluginsScreen::form_id( RequiredPluginsScreen::ACTION_ACTIVATE, $slug ), sprintf( __( 'Activate %s', 'whoiswho' ), $name ) );
		}
		if ( RequiredPlugins::ACTIVE === $status || RequiredPlugins::INCLUDED === $status ) {
			return '<span aria-hidden="true">&#10003;</span> ' . esc_html__( 'Nothing to do', 'whoiswho' );
		}
		return esc_html__( 'Ask the site\'s administrator', 'whoiswho' );
	}

	/**
	 * A submit button for a form printed elsewhere on the page.
	 *
	 * @param string $form  The form's id.
	 * @param string $label Button text.
	 */
	private static function form_button( string $form, string $label ): string {
		return '<button type="submit" form="' . esc_attr( $form ) . '" class="button button-primary">' . esc_html( $label ) . '</button>';
	}

	/**
	 * Install, Install with WordPress and Activate, as the status and WordPress's own capabilities allow.
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$slug    = (string) $item['slug'];
		$status  = (string) $item['status'];
		$actions = array();
		if ( RequiredPlugins::NOT_INSTALLED === $status && current_user_can( 'install_plugins' ) ) {
			$actions['wiw-install']      = array(
				'label'      => __( 'Install', 'whoiswho' ),
				'url'        => self::action_url( RequiredPluginsScreen::ACTION_INSTALL, $slug ),
				'capability' => RequiredPluginsScreen::CAP,
			);
			$actions['wiw-install-core'] = array(
				'label'      => __( 'Install with WordPress', 'whoiswho' ),
				'url'        => PluginInstaller::core_install_url( $slug ),
				'capability' => RequiredPluginsScreen::CAP,
			);
		}
		if ( RequiredPlugins::INSTALLED === $status && current_user_can( 'activate_plugins' ) ) {
			$actions['wiw-activate'] = array(
				'label'      => __( 'Activate', 'whoiswho' ),
				'url'        => self::action_url( RequiredPluginsScreen::ACTION_ACTIVATE, $slug ),
				'capability' => RequiredPluginsScreen::CAP,
			);
		}
		return $actions;
	}

	/**
	 * The address of the single action for one plugin: the same admin-post action and nonce as its button.
	 *
	 * @param string $action Action.
	 * @param string $slug   Slug.
	 */
	private static function action_url( string $action, string $slug ): string {
		return add_query_arg(
			array(
				'action'   => $action,
				'plugin'   => $slug,
				'_wpnonce' => ActionGuard::nonce_for( $action ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Each row keeps the id wiw-plugin-{slug} and its status in data-status, as before the staff kit, so a row can be found by
	 * its plugin (the Required plugins browser test reads them).
	 *
	 * @param array<string, mixed>|object $item Item.
	 */
	public function single_row( $item ): void {
		$item = (array) $item;
		echo '<tr id="' . esc_attr( 'wiw-plugin-' . (string) ( $item['slug'] ?? '' ) ) . '" data-status="' . esc_attr( (string) ( $item['status'] ?? '' ) ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		$all = array(
			'install'  => array(
				'label'      => __( 'Install selected', 'whoiswho' ),
				'capability' => RequiredPluginsScreen::CAP,
				/* translators: %d: number of plugins installed. */
				'done'       => __( '%d installed', 'whoiswho' ),
				'handler'    => fn ( int $id ) => $this->run( 'install', $id ),
			),
			'activate' => array(
				'label'      => __( 'Activate selected', 'whoiswho' ),
				'capability' => RequiredPluginsScreen::CAP,
				/* translators: %d: number of plugins activated. */
				'done'       => __( '%d active', 'whoiswho' ),
				'handler'    => fn ( int $id ) => $this->run( 'activate', $id ),
			),
		);
		// A holder of the screen capability sees only what WordPress lets them do (the installer checks again per plugin); anyone
		// else is refused by the capability check.
		if ( current_user_can( RequiredPluginsScreen::CAP ) && ! current_user_can( 'install_plugins' ) ) {
			unset( $all['install'] );
		}
		if ( current_user_can( RequiredPluginsScreen::CAP ) && ! current_user_can( 'activate_plugins' ) ) {
			unset( $all['activate'] );
		}
		return $all;
	}

	/**
	 * Run the single action for one selected plugin.
	 *
	 * @param string $what install or activate.
	 * @param int    $id   Row id.
	 * @return true|\WP_Error
	 */
	private function run( string $what, int $id ) {
		$slug = self::slug_for( $id );
		if ( '' === $slug ) {
			return new \WP_Error( 'wiw_plugin_unknown', __( 'not a listed plugin', 'whoiswho' ) );
		}
		$result = RequiredPluginsScreen::run( $what, $slug, $this->installer ?? new PluginInstaller( $this->plugins ) );
		$why    = array(
			PluginInstaller::CREDENTIALS => __( 'the host asks for FTP details: use Install with WordPress', 'whoiswho' ),
			PluginInstaller::NOT_ALLOWED => __( 'not allowed on this site', 'whoiswho' ),
			PluginInstaller::UNKNOWN     => __( 'not a listed plugin', 'whoiswho' ),
		);
		if ( PluginInstaller::OK === $result ) {
			return true;
		}
		return new \WP_Error( 'wiw_plugin_' . $result, $why[ $result ] ?? __( 'could not be done just now; try again', 'whoiswho' ) );
	}
}
