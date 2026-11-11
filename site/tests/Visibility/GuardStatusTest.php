<?php
/**
 * wiw_hidden is registered and is not public (REQ-208). Needs the WordPress test suite (WP_TESTS_DIR).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Tests\TestCase;

final class GuardStatusTest extends TestCase {

	public function test_REQ_208_wiw_hidden_status_is_not_public(): void {
		$this->requireWordPress();
		$status = get_post_status_object( 'wiw_hidden' );
		$this->assertNotNull( $status );
		$this->assertFalse( $status->public );
		$this->assertTrue( $status->exclude_from_search );
		$this->assertNotFalse( has_action( 'init', array( \WhoIsWho\Guard\Guard::class, 'register_status' ) ) );
	}
}
