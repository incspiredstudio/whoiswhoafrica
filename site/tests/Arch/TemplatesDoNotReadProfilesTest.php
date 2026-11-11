<?php
/**
 * Public templates read profiles only through PublicProfile (REQ-208): no get_post_meta, WP_Query, get_posts or direct
 * repository use in a theme file that deals with the profile type.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch;

use WhoIsWho\Tests\TestCase;

final class TemplatesDoNotReadProfilesTest extends TestCase {

	public function test_REQ_208_templates_do_not_query_profile_type_directly(): void {
		$root = dirname( __DIR__, 2 ) . '/wp-content/themes';
		$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		$bad  = array();
		foreach ( $it as $file ) {
			$path = str_replace( '\\', '/', (string) $file );
			if ( '.php' !== substr( $path, -4 ) || false !== strpos( $path, '/node_modules/' ) ) {
				continue;
			}
			$code = (string) file_get_contents( $path );
			// Direct use of the storage classes is never allowed in a theme.
			if ( 1 === preg_match( '/\b(StateRepository|StateWriter|ProvenanceRepository|VersionRepository|ProfileFields)\b/', $code ) ) {
				$bad[] = $path . ' (storage class)';
				continue;
			}
			// A file that deals with the profile type may not read or query it directly.
			if ( 1 === preg_match( '/wiw_profile\b|ProfileType/', $code ) && 1 === preg_match( '/\b(get_post_meta|get_post_custom|WP_Query|get_posts|query_posts|get_post|get_the_title)\s*\(|new\s+\\\\?WP_Query/', $code ) ) {
				$bad[] = $path;
			}
		}
		$this->assertSame( array(), $bad, 'Templates must read profiles through PublicProfile.' );
	}

	public function test_REQ_208_the_arch_rule_catches_a_violation(): void {
		$sample = "<?php\n\$q = new WP_Query( array( 'post_type' => 'wiw_profile' ) );\n";
		$this->assertSame( 1, preg_match( '/wiw_profile\b|ProfileType/', $sample ) );
		$this->assertSame( 1, preg_match( '/\b(get_post_meta|get_post_custom|WP_Query|get_posts|query_posts|get_post|get_the_title)\s*\(|new\s+\\\\?WP_Query/', $sample ) );
	}
}
