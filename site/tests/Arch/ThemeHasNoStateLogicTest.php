<?php
/**
 * The theme contains no profile state logic (REQ-208, REQ-209, REQ-216): it renders what the read model returns and never
 * maps a state to a visibility, a status or a label itself. Only the plugin's StatePolicy does that.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch;

use WhoIsWho\Tests\TestCase;

final class ThemeHasNoStateLogicTest extends TestCase {

	private const PATTERN = '/\b(PubState|StatePolicy|StateMachine|pub_state)\b/';

	public function test_REQ_208_no_theme_file_names_a_profile_state_or_the_policy(): void {
		$root = dirname( __DIR__, 2 ) . '/wp-content/themes';
		$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		$bad  = array();
		foreach ( $it as $file ) {
			$path = str_replace( '\\', '/', (string) $file );
			if ( '.php' !== substr( $path, -4 ) || false !== strpos( $path, '/node_modules/' ) ) {
				continue;
			}
			if ( 1 === preg_match( self::PATTERN, (string) file_get_contents( $path ) ) ) {
				$bad[] = $path;
			}
		}
		$this->assertSame( array(), $bad, 'The theme renders what the plugin returns; it holds no state logic.' );
	}

	public function test_REQ_208_the_rule_catches_a_violation(): void {
		$this->assertSame( 1, preg_match( self::PATTERN, "<?php if ( PubState::PUBLISHED_PENDING === \$state ) {}\n" ) );
		$this->assertSame( 0, preg_match( self::PATTERN, "<?php echo \$profile->label;\n" ) );
	}
}
