<?php
/**
 * Settings > Required plugins (REQ-056, REQ-240, REQ-075, REQ-258): who may open it, the install and activate actions behind a
 * capability and a nonce, the installer driving core's upgrader (a fake here, never the network), the reminder notice and the
 * setup checklist lines. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Plugins\MissingPluginsNotice;
use WhoIsWho\Core\Platform\Plugins\PluginInstaller;
use WhoIsWho\Core\Platform\Plugins\RequiredPlugins;
use WhoIsWho\Core\Platform\Plugins\RequiredPluginsScreen;
use WhoIsWho\Core\Platform\Setup\Checklist;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class RequiredPluginsTest extends WpTestCase {

	/**
	 * The fake installed-plugin list.
	 *
	 * @var array<string, mixed>
	 */
	private array $installed = array();

	/**
	 * Active plugin files in the fake.
	 *
	 * @var string[]
	 */
	private array $active = array();

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$this->installed = array();
		$this->active    = array();
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		remove_all_filters( 'user_has_cap' );
		parent::tearDown();
	}

	/**
	 * Mark a plugin file installed in the fake list (the fake upgrader calls it).
	 *
	 * @param string $file Plugin file.
	 */
	public function add_installed( string $file ): void {
		$this->installed[ $file ] = array();
	}

	/**
	 * A stand-in for core's Plugin_Upgrader: records the package and, on success, adds the plugin to the fake list.
	 *
	 * @param string $file   The plugin file the install adds.
	 * @param mixed  $result What install() returns.
	 */
	private function upgrader( string $file, $result = true ): object {
		return new class( $this, $file, $result ) {

			/**
			 * Packages asked for.
			 *
			 * @var string[]
			 */
			public array $packages = array();

			/**
			 * Constructor.
			 *
			 * @param RequiredPluginsTest $test   Test.
			 * @param string              $file   File.
			 * @param mixed               $result Result.
			 */
			public function __construct( private RequiredPluginsTest $test, private string $file, private mixed $result ) {}

			/**
			 * Install a package.
			 *
			 * @param string $package Download link.
			 * @return mixed
			 */
			public function install( string $package ): mixed {
				$this->packages[] = $package;
				if ( true === $this->result ) {
					$this->test->add_installed( $this->file );
				}
				return $this->result;
			}
		};
	}

	private function plugins( bool $two_factor_included = false ): RequiredPlugins {
		return new RequiredPlugins(
			fn (): array => $this->installed,
			fn ( string $file ): bool => in_array( $file, $this->active, true ),
			static fn ( string $slug ): bool => $two_factor_included && 'two-factor' === $slug
		);
	}

	private function admin(): int {
		$id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $id );
		return $id;
	}

	private function info( string $slug ): object {
		return (object) array(
			'slug'          => $slug,
			'download_link' => 'https://downloads.wordpress.org/plugin/' . $slug . '.zip',
		);
	}

	public function test_REQ_240_required_plugins_screen_is_for_administrators_only(): void {
		$registry = new ScreenRegistry();
		RequiredPluginsScreen::register( $registry );
		$registry->hook();
		$this->assertSame( 'settings', $registry->screens()[ RequiredPluginsScreen::SCREEN ]['group'] );
		$users = AuthorisationMatrix::users( self::factory(), RequiredPluginsScreen::CAP, 'wiw_review_records' );
		AuthorisationMatrix::assert_statuses(
			$this,
			RequiredPluginsScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 403,
				'administrator' => 200,
				'granted'       => 403, // A fixed capability cannot be granted to a non-Administrator.
				'revoked'       => 403,
			)
		);
	}

	public function test_REQ_240_install_activate_and_dismiss_need_the_capability_and_a_nonce(): void {
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		foreach ( array( RequiredPluginsScreen::ACTION_INSTALL, RequiredPluginsScreen::ACTION_ACTIVATE, MissingPluginsNotice::ACTION ) as $action ) {
			wp_set_current_user( $editor );
			$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( $action, RequiredPluginsScreen::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( $action ) ) ) ), "{$action} for an editor" );
			wp_set_current_user( $admin );
			$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( $action, RequiredPluginsScreen::CAP, new Request( array() ) ), "{$action} without a nonce" );
			$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( $action, RequiredPluginsScreen::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( 'something_else' ) ) ) ), "{$action} with another action's nonce" );
			$this->assertNull( ActionGuard::check( $action, RequiredPluginsScreen::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( $action ) ) ) ), "{$action} for an administrator" );
		}
	}

	public function test_REQ_056_status_is_not_installed_installed_active_or_included(): void {
		$plugins = $this->plugins( true );
		$this->assertSame( RequiredPlugins::NOT_INSTALLED, $plugins->status( 'wpdiscuz' ) );
		$this->assertSame( array( 'fluent-smtp', 'wpdiscuz' ), $plugins->missing_required() );
		$this->installed['wpdiscuz/class.WpdiscuzCore.php'] = array();
		$this->assertSame( RequiredPlugins::INSTALLED, $plugins->status( 'wpdiscuz' ) );
		$this->active[] = 'wpdiscuz/class.WpdiscuzCore.php';
		$this->assertSame( RequiredPlugins::ACTIVE, $plugins->status( 'wpdiscuz' ) );
		$this->installed['fluent-smtp/fluent-smtp.php'] = array();
		$this->active[]                                 = 'fluent-smtp/fluent-smtp.php';
		$this->assertSame( array(), $plugins->missing_required(), 'two-factor is recommended, not required' );
		$this->assertSame( RequiredPlugins::INCLUDED, $plugins->status( 'two-factor' ), 'the copy inside whoiswho-core counts' );
		$this->assertSame( '', $plugins->file( 'akismet' ), 'only our plugins are known' );
	}

	public function test_REQ_056_a_plugin_in_a_renamed_file_is_still_found_by_its_folder(): void {
		$this->installed['fluent-smtp/main.php'] = array();
		$this->assertSame( 'fluent-smtp/main.php', $this->plugins()->file( 'fluent-smtp' ) );
	}

	public function test_REQ_056_install_drives_core_upgrader_with_the_wordpress_org_download(): void {
		$this->admin();
		$asked     = array();
		$upgrader  = $this->upgrader( 'wpdiscuz/class.WpdiscuzCore.php' );
		$installer = new PluginInstaller(
			$this->plugins(),
			function ( string $slug ) use ( &$asked ) {
				$asked[] = $slug;
				return $this->info( $slug );
			},
			static fn (): object => $upgrader,
			static fn (): bool => true
		);
		$this->assertSame( PluginInstaller::OK, $installer->install( 'wpdiscuz' ) );
		$this->assertSame( array( 'wpdiscuz' ), $asked );
		$this->assertSame( array( 'https://downloads.wordpress.org/plugin/wpdiscuz.zip' ), $upgrader->packages );
		$this->assertSame( PluginInstaller::OK, $installer->install( 'wpdiscuz' ), 'installing again is a no-op' );
		$this->assertCount( 1, $upgrader->packages );
	}

	public function test_REQ_056_install_refuses_what_it_should(): void {
		$upgrader = $this->upgrader( 'fluent-smtp/fluent-smtp.php' );
		$make     = fn ( $api, bool $writable = true ): PluginInstaller => new PluginInstaller( $this->plugins(), $api, static fn (): object => $upgrader, static fn (): bool => $writable );
		$ok_api   = fn ( string $slug ) => $this->info( $slug );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$this->assertSame( PluginInstaller::NOT_ALLOWED, $make( $ok_api )->install( 'fluent-smtp' ), 'needs install_plugins' );

		$this->admin();
		$this->assertSame( PluginInstaller::UNKNOWN, $make( $ok_api )->install( 'hello-dolly' ), 'only the listed plugins' );
		$this->assertSame( PluginInstaller::CREDENTIALS, $make( $ok_api, false )->install( 'fluent-smtp' ), 'FTP hosts get the core screen' );
		$this->assertSame( PluginInstaller::FAILED, $make( static fn () => new \WP_Error( 'http', 'offline' ) )->install( 'fluent-smtp' ) );
		$this->assertSame(
			PluginInstaller::FAILED,
			$make(
				static fn () => (object) array( 'download_link' => 'https://example.com/fluent-smtp.zip' )
			)->install( 'fluent-smtp' ),
			'a download from anywhere but wordpress.org is refused'
		);
		$this->assertSame( array(), $upgrader->packages, 'the upgrader never ran' );

		add_filter(
			'user_has_cap',
			static function ( array $caps ): array {
				$caps['install_plugins'] = false;
				return $caps;
			}
		);
		$this->assertSame( PluginInstaller::NOT_ALLOWED, $make( $ok_api )->install( 'fluent-smtp' ), 'file changes switched off on the host' );
	}

	public function test_REQ_056_a_failed_upgrade_is_reported(): void {
		$this->admin();
		$upgrader = $this->upgrader( 'fluent-smtp/fluent-smtp.php', new \WP_Error( 'folder_exists', 'x' ) );
		$result   = ( new PluginInstaller( $this->plugins(), fn ( string $slug ) => $this->info( $slug ), static fn (): object => $upgrader, static fn (): bool => true ) )->install( 'fluent-smtp' );
		$this->assertSame( PluginInstaller::FAILED, $result );
	}

	public function test_REQ_056_activate_needs_activate_plugins_and_an_installed_plugin(): void {
		$activated = array();
		$installer = new PluginInstaller(
			$this->plugins(),
			null,
			null,
			null,
			function ( string $file ) use ( &$activated ) {
				$activated[]    = $file;
				$this->active[] = $file;
				return null;
			}
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$this->assertSame( PluginInstaller::NOT_ALLOWED, $installer->activate( 'wpdiscuz' ) );
		$this->admin();
		$this->assertSame( PluginInstaller::FAILED, $installer->activate( 'wpdiscuz' ), 'not installed yet' );
		$this->installed['wpdiscuz/class.WpdiscuzCore.php'] = array();
		$this->assertSame( PluginInstaller::OK, $installer->activate( 'wpdiscuz' ) );
		$this->assertSame( array( 'wpdiscuz/class.WpdiscuzCore.php' ), $activated );
		$this->assertSame( PluginInstaller::OK, $installer->activate( 'wpdiscuz' ) );
		$this->assertCount( 1, $activated, 'an active plugin is not activated twice' );
		$this->assertSame( PluginInstaller::UNKNOWN, $installer->activate( 'hello-dolly' ) );
	}

	public function test_REQ_056_the_core_install_link_carries_core_nonce(): void {
		$this->admin();
		$url = PluginInstaller::core_install_url( 'wpdiscuz' );
		$this->assertStringContainsString( 'update.php?action=install-plugin&amp;plugin=wpdiscuz', $url );
		$this->assertStringContainsString( '_wpnonce=', $url );
	}

	public function test_REQ_056_the_screen_lists_each_plugin_with_the_right_button(): void {
		$this->admin();
		$this->installed['wpdiscuz/class.WpdiscuzCore.php'] = array();
		$html = RequiredPluginsScreen::html( new Request( array() ), $this->plugins( true ) );
		$this->assertStringContainsString( 'Install FluentSMTP', $html );
		$this->assertStringContainsString( 'Install with WordPress', $html );
		$this->assertStringContainsString( 'Activate wpDiscuz', $html );
		$this->assertStringNotContainsString( 'Install wpDiscuz', $html );
		$this->assertStringContainsString( 'Included with the site plugin', $html );
		$this->assertStringContainsString( 'Recommended (optional)', $html );
		$this->assertStringContainsString( 'value="' . RequiredPluginsScreen::ACTION_INSTALL . '"', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );

		$html = RequiredPluginsScreen::html(
			new Request(
				array(
					'wiw_what'   => 'install',
					'wiw_plugin' => 'fluent-smtp',
					'wiw_result' => PluginInstaller::CREDENTIALS,
				)
			),
			$this->plugins()
		);
		$this->assertStringContainsString( 'FTP', $html );
	}

	public function test_REQ_056_without_install_plugins_the_screen_explains_and_shows_no_install_button(): void {
		$this->admin();
		add_filter(
			'user_has_cap',
			static function ( array $caps ): array {
				$caps['install_plugins'] = false;
				return $caps;
			}
		);
		$html = RequiredPluginsScreen::html( new Request( array() ), $this->plugins() );
		$this->assertStringContainsString( 'cannot be added from the admin', $html );
		$this->assertStringNotContainsString( 'Install FluentSMTP', $html );
	}

	public function test_REQ_240_the_screen_refuses_a_user_without_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$this->expectException( \DomainException::class );
		RequiredPluginsScreen::html( new Request( array() ) );
	}

	public function test_REQ_056_the_notice_shows_for_administrators_until_dismissed_for_seven_days(): void {
		$admin  = $this->admin();
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$now    = 1_800_000_000;
		$html   = MissingPluginsNotice::html( $admin, '', $this->plugins(), $now );
		$this->assertStringContainsString( 'FluentSMTP, wpDiscuz', $html );
		$this->assertStringContainsString( 'page=' . RequiredPluginsScreen::SCREEN, $html );
		$this->assertStringContainsString( 'Remind me in 7 days', $html );
		$this->assertSame( '', MissingPluginsNotice::html( $editor, '', $this->plugins(), $now ), 'not for staff without the capability' );
		$this->assertSame( '', MissingPluginsNotice::html( $admin, RequiredPluginsScreen::SCREEN, $this->plugins(), $now ), 'not on the screen itself' );

		MissingPluginsNotice::dismiss( $admin, $now );
		$this->assertSame( '', MissingPluginsNotice::html( $admin, '', $this->plugins(), $now + 6 * DAY_IN_SECONDS ) );
		$this->assertNotSame( '', MissingPluginsNotice::html( $admin, '', $this->plugins(), $now + 7 * DAY_IN_SECONDS + 1 ), 'it comes back after seven days' );
		$other = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertNotSame( '', MissingPluginsNotice::html( $other, '', $this->plugins(), $now + DAY_IN_SECONDS ), 'dismissal is per user' );

		$this->installed['fluent-smtp/fluent-smtp.php']     = array();
		$this->installed['wpdiscuz/class.WpdiscuzCore.php'] = array();
		$this->active                                       = array( 'fluent-smtp/fluent-smtp.php', 'wpdiscuz/class.WpdiscuzCore.php' );
		$this->assertSame( '', MissingPluginsNotice::html( $other, '', $this->plugins(), $now ), 'nothing missing, no notice' );
	}

	public function test_REQ_056_the_setup_checklist_has_a_line_per_required_plugin(): void {
		$this->admin();
		$items = array_column( Checklist::items(), null, 'id' );
		foreach ( array(
			'plugin_fluent_smtp' => 'FluentSMTP',
			'plugin_wpdiscuz'    => 'wpDiscuz',
		) as $id => $name ) {
			$this->assertArrayHasKey( $id, $items );
			$this->assertStringContainsString( $name, $items[ $id ]['title'] );
			if ( Checklist::OK !== $items[ $id ]['status'] ) {
				$this->assertSame( Checklist::ERROR, $items[ $id ]['status'] );
				$this->assertStringContainsString( 'Required plugins', $items[ $id ]['fix'] );
			}
		}
	}

	public function test_REQ_056_the_allowlist_and_register_name_both_plugins(): void {
		$root      = dirname( __DIR__, 4 );
		$allowlist = json_decode( (string) file_get_contents( $root . '/site/config/plugin-allowlist.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$packages  = array_column( $allowlist['plugins'], 'package' );
		$register  = (string) file_get_contents( $root . '/docs/plugins/third-party.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( array( 'fluent-smtp', 'wpdiscuz' ) as $slug ) {
			$this->assertContains( 'wpackagist-plugin/' . $slug, $packages );
			$this->assertTrue( RequiredPlugins::catalogue()[ $slug ]['required'] );
		}
		$this->assertStringContainsString( 'FluentSMTP', $register );
		$this->assertStringContainsString( 'wpDiscuz', $register );
	}
}
