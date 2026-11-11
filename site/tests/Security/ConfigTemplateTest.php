<?php
/**
 * Static checks on the wp-config template and .env.example (REQ-046, REQ-204).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ConfigTemplateTest extends TestCase {

	private function read( string $file ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $file );
	}

	public function test_REQ_046_env_example_holds_names_only(): void {
		foreach ( explode( "\n", $this->read( '.env.example' ) ) as $line ) {
			if ( preg_match( '/^[A-Z_]+=(.+)$/', $line ) ) {
				$this->fail( 'Value present in .env.example: ' . $line );
			}
		}
		$this->assertStringContainsString( 'SIGNED_LINK_KEY=', $this->read( '.env.example' ) );
	}

	public function test_REQ_046_template_has_no_literal_secret(): void {
		$this->assertDoesNotMatchRegularExpression( '/define\(\s*\'(DB_PASSWORD|AUTH_KEY|NONCE_SALT)\'\s*,\s*\'[^\']+\'/', $this->read( 'wp-config.php' ) );
	}

	public function test_REQ_204_production_disables_file_editing_and_mods(): void {
		$cfg = $this->read( 'wp-config.php' );
		$this->assertStringContainsString( "define( 'DISALLOW_FILE_EDIT', true );", $cfg );
		$this->assertStringContainsString( "define( 'DISALLOW_FILE_MODS', \$wiw_production );", $cfg );
	}

	public function test_REQ_046_missing_secrets_file_gives_plain_error(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'wiw' );
		file_put_contents( $tmp, "<?php putenv('WIW_SECRETS_FILE=/nonexistent'); include '" . str_replace( '\\', '/', dirname( __DIR__, 2 ) ) . "/wp-config.php';" );
		$out = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $tmp ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		unlink( $tmp );
		$this->assertStringContainsString( 'not configured yet', (string) $out );
		$this->assertStringNotContainsString( 'nonexistent', (string) $out );
	}

	public function test_REQ_046_secrets_files_are_git_ignored(): void {
		$ignore = $this->read( '.gitignore' );
		$this->assertStringContainsString( '*.env', $ignore );
	}
}
