<?php
/**
 * The Profile manager role recipe in docs/runbook.md matches the capability catalogue and the assigned-profiles rule (REQ-253, REQ-261, REQ-244).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Trust\AssignedProfiles;
use WhoIsWho\Tests\TestCase;

final class RunbookProfileManagerTest extends TestCase {

	/**
	 * The recipe section of the runbook, or a skip when the docs folder is not present.
	 */
	private function section(): string {
		$file = dirname( __DIR__, 3 ) . '/docs/runbook.md';
		if ( ! is_readable( $file ) ) {
			$this->markTestSkipped( 'docs/ is not present.' );
		}
		$text = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- local doc read in a test.
		$this->assertSame( 1, preg_match( '/^## Profile manager role recipe.*?(?=^## |\z)/ms', $text, $found ), 'the runbook has the recipe section' );
		return $found[0];
	}

	/**
	 * Capabilities named in backticks on the lines of one step.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private function caps_in( string $text ): array {
		preg_match_all( '/`((?:wiw_|read)[a-z_]*)`/', $text, $found );
		return array_values( array_unique( $found[1] ) );
	}

	public function test_REQ_253_runbook_recipe_role_matches_capability_list(): void {
		$section = $this->section();
		$this->assertSame( 1, preg_match( '/Tick exactly these capabilities and no others:(.*?)(?=^4\. )/ms', $section, $tick ) );
		$this->assertSame( 1, preg_match( '/^4\. .*?(?=^5\. )/ms', $section, $not ) );
		$ticked = $this->caps_in( $tick[1] );
		sort( $ticked );
		$this->assertSame( array( 'read', AssignedProfiles::CAPABILITY ), $ticked, 'the role has read and the assigned-profiles capability only' );

		$catalogue = new CapabilityCatalogue();
		$known     = $catalogue->all();
		foreach ( $ticked as $cap ) {
			$this->assertContains( $cap, $known, "$cap is in the capability catalogue" );
		}
		$forbidden = $this->caps_in( $not[0] );
		$this->assertContains( 'wiw_review_records', $forbidden );
		$this->assertContains( 'wiw_manage_capabilities', $forbidden );
		$this->assertSame( array(), array_intersect( $ticked, $forbidden ), 'no capability is both ticked and forbidden' );
		foreach ( $forbidden as $cap ) {
			$this->assertContains( $cap, $known, "$cap named in the recipe exists in the catalogue" );
		}
	}

	public function test_REQ_244_the_assigned_capability_is_not_in_the_default_staff_roles_and_the_recipe_covers_two_factor_and_audit(): void {
		$catalogue = new CapabilityCatalogue();
		$this->assertNotContains( AssignedProfiles::CAPABILITY, $catalogue->for_bundle( 'reviewer' ) );
		$this->assertNotContains( AssignedProfiles::CAPABILITY, $catalogue->for_bundle( 'editor' ) );
		$section = $this->section();
		foreach ( array( 'second factor', 'profile.assign_manager', 'role.create', 'profile.revision_approved', 'claim.approve', 'Checklist for the claim reviewer' ) as $needle ) {
			$this->assertStringContainsString( $needle, $section, $needle );
		}
	}
}
