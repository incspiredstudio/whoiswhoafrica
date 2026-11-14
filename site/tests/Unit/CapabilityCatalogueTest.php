<?php
/**
 * The catalogue matches the documented defaults (REQ-235, REQ-244, REQ-239, REQ-240).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Tests\TestCase;

final class CapabilityCatalogueTest extends TestCase {

	public function test_REQ_235_role_bundles_match_documented_defaults(): void {
		$c        = new CapabilityCatalogue();
		$reviewer = $c->for_bundle( 'reviewer' );
		$editor   = $c->for_bundle( 'editor' );
		$admin    = $c->for_bundle( 'administrator' );

		// Reviewer: review, read-only profiles, bulk unpublish; no editing, no unpublish of a single profile.
		foreach ( array( 'read', 'wiw_review_records', 'wiw_bulk_unpublish', 'read_private_wiw_profiles', 'assign_terms', 'wiw_view_dashboard_review' ) as $cap ) {
			$this->assertContains( $cap, $reviewer, "reviewer should have {$cap}" );
		}
		foreach ( array( 'edit_posts', 'edit_wiw_profiles', 'wiw_unpublish_profiles', 'wiw_run_imports', 'manage_options' ) as $cap ) {
			$this->assertNotContains( $cap, $reviewer, "reviewer should not have {$cap}" );
		}

		// Editor: edits, no bulk unpublish, no imports, no commercial register.
		foreach ( array( 'edit_posts', 'edit_wiw_profiles', 'wiw_unpublish_profiles', 'wiw_curate_home', 'wiw_manage_events', 'wiw_manage_awards', 'wiw_support_inbox', 'wiw_handle_requests' ) as $cap ) {
			$this->assertContains( $cap, $editor, "editor should have {$cap}" );
		}
		foreach ( array( 'wiw_bulk_unpublish', 'wiw_run_imports', 'wiw_manage_register', 'wiw_toggle_nominations', 'manage_options' ) as $cap ) {
			$this->assertNotContains( $cap, $editor, "editor should not have {$cap}" );
		}

		// Administrator holds every capability in the catalogue.
		$this->assertEqualsCanonicalizing( $c->all(), $admin );
	}

	public function test_REQ_244_add_profile_capability_is_editor_and_administrator(): void {
		$c = new CapabilityCatalogue();
		$this->assertContains( 'wiw_add_profile', $c->for_bundle( 'editor' ) );
		$this->assertContains( 'wiw_add_profile', $c->for_bundle( 'administrator' ) );
		$this->assertNotContains( 'wiw_add_profile', $c->for_bundle( 'reviewer' ) );
		$this->assertNotContains( 'wiw_add_profile', $c->fixed() );
	}

	public function test_REQ_235_withdrawn_capabilities_are_not_listed(): void {
		$all = ( new CapabilityCatalogue() )->all();
		$this->assertNotContains( 'wiw_set_trust_labels', $all );
		$this->assertNotContains( 'wiw_manage_leads', $all );
	}

	public function test_REQ_244_fixed_capabilities_are_administrator_only(): void {
		$c = new CapabilityCatalogue();
		foreach ( $c->fixed() as $cap ) {
			$this->assertNotContains( $cap, $c->for_bundle( 'reviewer' ), "{$cap} is fixed" );
			$this->assertNotContains( $cap, $c->for_bundle( 'editor' ), "{$cap} is fixed" );
		}
		foreach ( array( 'wiw_manage_register', 'wiw_toggle_nominations', 'wiw_manage_capabilities', 'manage_options', 'install_plugins', 'promote_users' ) as $cap ) {
			$this->assertContains( $cap, $c->fixed() );
		}
	}

	public function test_REQ_235_plugin_capabilities_all_start_with_wiw(): void {
		foreach ( ( new CapabilityCatalogue() )->plugin_capabilities() as $cap ) {
			$this->assertStringStartsWith( 'wiw_', $cap );
		}
	}
}
