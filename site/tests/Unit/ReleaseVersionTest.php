<?php
/**
 * Every part of the release carries the same version, 0.2.0, so WordPress shows a version change when each part is updated
 * (issue 806, REQ-046, REQ-056).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseVersionTest extends TestCase {

	private const VERSION = '0.2.0';

	/**
	 * The first match of a pattern in a file.
	 *
	 * @param string $file    Path under site/.
	 * @param string $pattern Pattern with one group.
	 */
	private function found( string $file, string $pattern ): string {
		$text = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertSame( 1, preg_match( $pattern, $text, $m ), $file . ' has no version' );
		return $m[1];
	}

	public function test_REQ_046_every_part_of_the_release_says_the_same_version(): void {
		$parts = array(
			'plugin header'      => array( 'wp-content/plugins/whoiswho-core/whoiswho-core.php', '/^ \* Version:\s*(\S+)/m' ),
			'plugin constant'    => array( 'wp-content/plugins/whoiswho-core/whoiswho-core.php', "/'WIW_CORE_VERSION',\s*'([^']+)'/" ),
			'guard header'       => array( 'wp-content/mu-plugins/whoiswho-guard.php', '/^ \* Version:\s*(\S+)/m' ),
			'guard constant'     => array( 'wp-content/mu-plugins/whoiswho-guard.php', "/'WIW_GUARD_VERSION',\s*'([^']+)'/" ),
			'parent theme'       => array( 'wp-content/themes/whoiswho/style.css', '/^Version:\s*(\S+)/m' ),
			'parent constant'    => array( 'wp-content/themes/whoiswho/functions.php', "/'WIW_THEME_VERSION',\s*'([^']+)'/" ),
			'child theme'        => array( 'wp-content/themes/whoiswho-child/style.css', '/^Version:\s*(\S+)/m' ),
			'child constant'     => array( 'wp-content/themes/whoiswho-child/functions.php', "/'WIW_CHILD_VERSION',\s*'([^']+)'/" ),
			'package.json'       => array( 'package.json', '/^  "version":\s*"([^"]+)"/m' ),
			'package-lock.json'  => array( 'package-lock.json', '/^  "version":\s*"([^"]+)"/m' ),
		);
		foreach ( $parts as $label => $part ) {
			$this->assertSame( self::VERSION, $this->found( $part[0], $part[1] ), $label );
		}
	}

	public function test_REQ_046_the_packaging_script_refuses_a_part_with_another_version(): void {
		$script = (string) file_get_contents( dirname( __DIR__, 2 ) . '/bin/package-release.sh' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertStringContainsString( 'whoiswho-child/style.css', $script );
		$this->assertStringContainsString( 'the guard says version', $script );
	}
}
