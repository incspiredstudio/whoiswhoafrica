<?php
/**
 * Staff audience resolution against real users (REQ-094). Needs WordPress.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Mail\StaffAudience;
use WhoIsWho\Tests\WpTestCase;

final class StaffAudienceTest extends WpTestCase {

	public function test_REQ_094_capability_holders_and_administrators_exclude_deactivated(): void {
		add_role( 'wiw_probe_role', 'Probe', array( 'wiw_probe_cap' => true ) );
		$admin  = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin-notice@example.org',
			)
		);
		$active = self::factory()->user->create(
			array(
				'role'       => 'wiw_probe_role',
				'user_email' => 'active-editor@example.org',
			)
		);
		$gone   = self::factory()->user->create(
			array(
				'role'       => 'wiw_probe_role',
				'user_email' => 'gone-editor@example.org',
			)
		);
		update_user_meta( $gone, StaffAudience::DEACTIVATED_META, '2026-11-01 00:00:00' );
		$subscriber = self::factory()->user->create( array( 'user_email' => 'sub@example.org' ) );

		$admins = StaffAudience::addresses( 'administrators' );
		$this->assertContains( 'admin-notice@example.org', $admins );
		$this->assertNotContains( 'active-editor@example.org', $admins );

		$holders = StaffAudience::addresses( 'capability:wiw_probe_cap' );
		$this->assertContains( 'active-editor@example.org', $holders );
		$this->assertNotContains( 'gone-editor@example.org', $holders );
		$this->assertNotContains( 'sub@example.org', $holders );

		$this->assertSame( array(), StaffAudience::addresses( 'nonsense' ) );
		remove_role( 'wiw_probe_role' );
		unset( $admin, $active, $subscriber );
	}
}
