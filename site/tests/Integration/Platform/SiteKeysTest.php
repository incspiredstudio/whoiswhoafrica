<?php
/**
 * Site keys need no wp-config.php edit (REQ-056, REQ-046): with no constant the form-token key, the signed-link key and the
 * health-check token are made once with random_bytes and kept in a non-autoloaded option; a constant always wins.
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Health\HealthEndpoint;
use WhoIsWho\Core\Platform\Secrets\SiteKeys;
use WhoIsWho\Tests\WpTestCase;

final class SiteKeysTest extends WpTestCase {

	private const NAMES = array( SiteKeys::FORM_TOKEN_KEY, SiteKeys::SIGNED_LINK_KEY, SiteKeys::HEALTHZ_TOKEN );

	protected function setUp(): void {
		parent::setUp();
		foreach ( self::NAMES as $name ) {
			delete_option( SiteKeys::option_name( $name ) );
		}
	}

	/**
	 * Whether an option is stored with autoload off.
	 *
	 * @param string $option Option name.
	 */
	private function autoloaded( string $option ): bool {
		global $wpdb;
		$value = (string) $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return in_array( $value, array( 'yes', 'on', 'auto-on', 'auto' ), true );
	}

	/**
	 * The shared checks for one key with no constant set.
	 *
	 * @param string $name Key name.
	 */
	private function assert_generated_once( string $name ): string {
		$none  = '__return_empty_string';
		$first = SiteKeys::get( $name, $none );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $first, '32 random bytes, hex encoded' );
		$this->assertSame( $first, SiteKeys::get( $name, $none ), 'made once, then reused' );
		$this->assertSame( $first, get_option( SiteKeys::option_name( $name ) ) );
		$this->assertFalse( $this->autoloaded( SiteKeys::option_name( $name ) ), 'kept out of the autoloaded options' );
		return $first;
	}

	/**
	 * The constant wins over a stored key.
	 *
	 * @param string $name     Key name.
	 * @param string $constant Constant name it is read from.
	 */
	private function assert_constant_wins( string $name, string $constant ): void {
		$stored = SiteKeys::get( $name, '__return_empty_string' );
		$lookup = static fn ( string $c ): string => $constant === $c ? 'from-the-host' : '';
		$this->assertSame( 'from-the-host', SiteKeys::get( $name, $lookup ) );
		$this->assertSame( $stored, get_option( SiteKeys::option_name( $name ) ), 'the stored key is left alone' );
	}

	public function test_REQ_056_form_token_key_is_generated_when_no_constant_is_set_and_the_constant_wins(): void {
		$key = $this->assert_generated_once( SiteKeys::FORM_TOKEN_KEY );
		$this->assert_constant_wins( SiteKeys::FORM_TOKEN_KEY, 'WIW_FORM_TOKEN_KEY' );
		$this->assertNotSame( wp_salt( 'auth' ), $key, 'no longer borrows a WordPress salt' );

		$token = FormToken::for_site();
		$clock = static fn (): int => time() + 10;
		$this->assertSame( FormToken::OK, ( new FormToken( $key, $clock ) )->verify( $token->issue( 'contact' ), 'contact' ), 'the site signs with the stored key' );
	}

	public function test_REQ_056_signed_link_key_is_generated_when_no_constant_is_set_and_the_constant_wins(): void {
		$key = $this->assert_generated_once( SiteKeys::SIGNED_LINK_KEY );
		$this->assertSame( $key, SiteKeys::signed_link_key() );
		$this->assert_constant_wins( SiteKeys::SIGNED_LINK_KEY, 'WIW_SIGNED_LINK_KEY' );
	}

	public function test_REQ_050_healthz_token_is_generated_when_no_constant_is_set_and_the_constant_wins(): void {
		$key = $this->assert_generated_once( SiteKeys::HEALTHZ_TOKEN );
		$this->assert_constant_wins( SiteKeys::HEALTHZ_TOKEN, 'WIW_HEALTHZ_TOKEN' );

		$endpoint = HealthEndpoint::for_site();
		$this->assertSame( array( 'status' ), array_keys( $endpoint->respond( '' )['body'] ), 'no token, no detail' );
		$this->assertSame( array( 'status' ), array_keys( $endpoint->respond( 'wrong' )['body'] ) );
		$this->assertArrayHasKey( 'database', $endpoint->respond( $key )['body'], 'the generated token unlocks the detail' );
	}

	public function test_REQ_056_an_empty_stored_value_is_replaced_not_used(): void {
		update_option( SiteKeys::option_name( SiteKeys::FORM_TOKEN_KEY ), '', false );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', SiteKeys::get( SiteKeys::FORM_TOKEN_KEY, '__return_empty_string' ) );
	}

	public function test_REQ_046_no_constant_means_the_site_reports_a_generated_source(): void {
		$this->assertSame( 'generated', SiteKeys::source( SiteKeys::HEALTHZ_TOKEN ) );
		$this->expectException( \InvalidArgumentException::class );
		SiteKeys::option_name( 'NOT_A_KEY' );
	}
}
