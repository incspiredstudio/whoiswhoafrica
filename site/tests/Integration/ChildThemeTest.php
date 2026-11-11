<?php
/**
 * The child theme is the active theme and extends the parent (REQ-273). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class ChildThemeTest extends TestCase {

	public function test_REQ_273_child_is_active_and_extends_parent(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		require_once get_stylesheet_directory() . '/inc/status.php';
		$status = wiw_child_theme_status();
		$this->assertTrue( $status['child_active'] );
		$this->assertTrue( $status['parent_present'] );
		$this->assertTrue( $status['extends_parent'] );
		$this->assertSame( 'whoiswho', get_template() );
	}

	public function test_REQ_273_template_override_in_child_wins_over_parent(): void {
		$this->requireWordPress();
		switch_theme( 'whoiswho-child' );
		$parent = get_template_directory() . '/template-parts/wiw-override-test.php';
		$child  = get_stylesheet_directory() . '/template-parts/wiw-override-test.php';
		file_put_contents( $parent, '<?php // parent' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		try {
			$this->assertSame( $parent, locate_template( 'template-parts/wiw-override-test.php' ), 'parent used when the child has no override' );
			file_put_contents( $child, '<?php // child' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->assertSame( $child, locate_template( 'template-parts/wiw-override-test.php' ), 'child override wins' );
		} finally {
			@unlink( $parent ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
			@unlink( $child ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		}
	}

	public function test_REQ_273_child_holds_no_copy_of_parent_templates(): void {
		$parent = glob( dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho/templates/*.php' ) ?: array();
		foreach ( $parent as $file ) {
			$this->assertFileDoesNotExist( dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho-child/templates/' . basename( $file ) );
		}
		$this->assertTrue( true );
	}
}
