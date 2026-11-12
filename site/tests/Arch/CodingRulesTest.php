<?php
/**
 * The coding rules of CLAUDE.md as architecture tests (REQ-046, REQ-240, REQ-208): admin screens and actions only through the
 * registries, no raw superglobals, no dynamic code, no payment code, prepared SQL. Each rule passes on the real code (plugin,
 * guard and themes) and fails on a fixture that breaks it. Run alone with `composer test -- --testsuite Arch`.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch;

use WhoIsWho\Tests\Arch\Rules\AdminOnlyThroughRegistries;
use WhoIsWho\Tests\Arch\Rules\NoDynamicCode;
use WhoIsWho\Tests\Arch\Rules\NoPaymentCode;
use WhoIsWho\Tests\Arch\Rules\NoRawSuperglobals;
use WhoIsWho\Tests\Arch\Rules\NoUnpreparedSql;
use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;
use WhoIsWho\Tests\TestCase;

final class CodingRulesTest extends TestCase {

	/**
	 * Real code sets by name, parsed once.
	 *
	 * @var array<string, SourceSet>
	 */
	private static array $real = array();

	/**
	 * A real code set: plugin, guard or themes.
	 *
	 * @param string $name plugin, guard or themes.
	 */
	private function real( string $name ): SourceSet {
		$dirs = array(
			'plugin' => '/wp-content/plugins/whoiswho-core/src',
			'guard'  => '/wp-content/mu-plugins/whoiswho-guard',
			'themes' => '/wp-content/themes',
		);
		if ( ! isset( self::$real[ $name ] ) ) {
			self::$real[ $name ] = SourceSet::from_directory( dirname( __DIR__, 2 ) . $dirs[ $name ] );
			$this->assertGreaterThan( 3, self::$real[ $name ]->count(), "the {$name} code must be read" );
		}
		return self::$real[ $name ];
	}

	/**
	 * Assert a rule holds on every named real code set.
	 *
	 * @param Rule     $rule Rule.
	 * @param string[] $sets Set names.
	 */
	private function assertHoldsOn( Rule $rule, array $sets ): void {
		foreach ( $sets as $name ) {
			$this->assertSame( array(), $rule->violations( $this->real( $name ) ), "rule broken in {$name}" );
		}
	}

	/**
	 * Assert fixture code breaks a rule.
	 *
	 * @param Rule   $rule Rule.
	 * @param string $path Path inside the set.
	 * @param string $code PHP.
	 */
	private function assertBroken( Rule $rule, string $path, string $code ): void {
		$this->assertNotSame( array(), $rule->violations( SourceSet::from_strings( array( $path => $code ) ) ), $code );
	}

	/**
	 * Assert fixture code satisfies a rule.
	 *
	 * @param Rule   $rule Rule.
	 * @param string $path Path inside the set.
	 * @param string $code PHP.
	 */
	private function assertFine( Rule $rule, string $path, string $code ): void {
		$this->assertSame( array(), $rule->violations( SourceSet::from_strings( array( $path => $code ) ) ), $code );
	}

	public function test_REQ_240_every_registered_screen_has_a_capability(): void {
		$rule = new AdminOnlyThroughRegistries();
		$this->assertHoldsOn( $rule, array( 'plugin', 'guard', 'themes' ) );
		$this->assertBroken( $rule, 'Review/Bad.php', '<?php add_menu_page( "t", "t", "read", "slug", "cb" );' );
		$this->assertBroken( $rule, 'Review/Bad.php', '<?php add_submenu_page( "p", "t", "t", "manage_options", "slug", "cb" );' );
		$this->assertBroken( $rule, 'Review/Bad.php', '<?php add_action( "admin_post_do_thing", "cb" );' );
		$this->assertBroken( $rule, 'Review/Bad.php', '<?php add_action( "wp_ajax_" . $name, "cb" );' );
		$this->assertFine( $rule, 'Platform/Admin/ScreenRegistry.php', '<?php add_menu_page( "t", "t", $cap, "slug", "cb" );' );
		$this->assertFine( $rule, 'Review/Fine.php', '<?php ScreenRegistry::shared()->register( "slug", "Title", "wiw_review_records", "cb" ); add_action( "init", "cb" );' );
	}

	public function test_REQ_240_screen_registry_refuses_an_empty_capability(): void {
		$this->requireWordPress();
		$this->expectException( \InvalidArgumentException::class );
		( new \WhoIsWho\Core\Platform\Admin\ScreenRegistry() )->register( 'wiw-x', 'X', '', '__return_null' );
	}

	public function test_REQ_046_no_raw_superglobals_outside_validators(): void {
		$rule = new NoRawSuperglobals();
		$this->assertHoldsOn( $rule, array( 'plugin', 'guard', 'themes' ) );
		$this->assertBroken( $rule, 'Review/Bad.php', '<?php $id = $_GET["id"];' );
		$this->assertBroken( $rule, 'Compliance/Bad.php', '<?php $x = wp_unslash( $_POST )["x"];' );
		$this->assertBroken( $rule, 'Directory/Bad.php', '<?php $all = $_REQUEST;' );
		$this->assertFine( $rule, 'Platform/Admin/Request.php', '<?php $v = array_merge( $_GET, $_POST );' );
		$this->assertFine( $rule, 'Platform/Forms/FormInput.php', '<?php return new self( $_POST );' );
		$this->assertFine( $rule, 'Review/Fine.php', '<?php $ip = $_SERVER["REMOTE_ADDR"];' );
	}

	public function test_REQ_046_no_eval_or_dynamic_code(): void {
		$rule = new NoDynamicCode();
		$this->assertHoldsOn( $rule, array( 'plugin', 'guard', 'themes' ) );
		$this->assertBroken( $rule, 'Import/Bad.php', '<?php eval( $code );' );
		$this->assertBroken( $rule, 'Import/Bad.php', '<?php $f = create_function( "$a", "return $a;" );' );
		$this->assertBroken( $rule, 'Import/Bad.php', '<?php $out = `ls -la`;' );
		$this->assertBroken( $rule, 'Import/Bad.php', '<?php shell_exec( "ls" ); system( "ls" ); exec( "ls" );' );
		$this->assertFine( $rule, 'Import/Fine.php', '<?php class Fine { public function exec_job() { return $this->system_name(); } }' );
	}

	public function test_REQ_046_no_payment_or_gateway_code(): void {
		$rule = new NoPaymentCode();
		$this->assertHoldsOn( $rule, array( 'plugin', 'guard', 'themes' ) );
		$this->assertBroken( $rule, 'Commerce/Bad.php', '<?php $c = new \Stripe\StripeClient( "key" );' );
		$this->assertBroken( $rule, 'Commerce/Bad.php', '<?php wp_remote_post( "https://api.paystack.co/transaction/initialize" );' );
		$this->assertBroken( $rule, 'Commerce/Bad.php', '<?php use Flutterwave\Rave; class A {}' );
		$this->assertFine( $rule, 'Commerce/Fine.php', '<?php // The register records payments received offline.' . "\n" . 'echo "Payment received (offline)";' );

		$lock = (string) file_get_contents( dirname( __DIR__, 2 ) . '/composer.lock' );
		$this->assertGreaterThan( 100, strlen( $lock ), 'composer.lock is read' );
		$this->assertSame( array(), $rule->lock_violations( $lock ), 'the real lockfile holds no payment package' );
		$this->assertNotSame( array(), $rule->lock_violations( '{"packages":[{"name":"stripe/stripe-php","version":"1.0"}],"packages-dev":[]}' ) );
		$this->assertNotSame( array(), $rule->lock_violations( '{"packages":[{"name":"wpackagist-plugin/woocommerce","version":"9.0"}]}' ) );
		$this->assertSame( array(), $rule->lock_violations( '{"packages":[{"name":"woocommerce/action-scheduler","version":"3.9"}]}' ), 'Action Scheduler is allowed' );
	}

	public function test_REQ_046_sql_with_variables_goes_through_prepare(): void {
		$rule = new NoUnpreparedSql();
		$this->assertHoldsOn( $rule, array( 'plugin', 'guard' ) );
		$this->assertBroken( $rule, 'Review/Bad.php', '<?php global $wpdb; $wpdb->query( "DELETE FROM t WHERE id = $id" );' );
		$this->assertBroken( $rule, 'Review/Bad.php', '<?php global $wpdb; $wpdb->get_results( "SELECT * FROM t WHERE name = \'" . $name . "\'" );' );
		$this->assertFine( $rule, 'Review/Fine.php', '<?php global $wpdb; $wpdb->get_results( $wpdb->prepare( "SELECT * FROM t WHERE id = %d", $id ) );' );
		$this->assertFine( $rule, 'Review/Fine.php', '<?php global $wpdb; $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() );' );
		$this->assertFine( $rule, 'Review/Fine.php', '<?php global $wpdb; $wpdb->get_var( "SELECT hash FROM {$this->table()} ORDER BY id DESC" );' );
	}
}
