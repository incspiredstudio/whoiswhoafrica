<?php
/**
 * Staff UI phase 2 on the commercial and audience lists (REQ-275, REQ-205, REQ-269, REQ-058): Customers, Sponsored stories and
 * Subscribers are shared staff list tables. None of them has a single action that bulk could repeat (an edit or an invitation
 * needs its own values; subscribers are read only), so they have no checkboxes and no bulk actions; row actions show only what
 * the user may do. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\Admin\CustomersTable;
use WhoIsWho\Core\Commerce\Admin\SponsoredDealsTable;
use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Commerce\SponsoredStoriesScreen;
use WhoIsWho\Core\Content\Newsletter\Admin\SubscribersTable;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\WpTestCase;

final class CommerceStaffUiTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( CustomerRepository::table(), ArrangementRepository::table(), PlacementRepository::table(), SubscriberRepository::table() ) as $table ) {
			$wpdb->query( 'DELETE FROM ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		}
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	public function test_REQ_275_customers_list_has_an_edit_row_action_and_no_checkboxes(): void {
		$id = ( new CustomerRepository() )->insert(
			array(
				'name'    => 'Ui Alpha Bank',
				'segment' => 'finance',
			),
			null,
			new \DateTimeImmutable( '2026-11-13 10:00:00' )
		);
		$this->as_role( 'administrator' );
		$html = ( new CustomersTable() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'Ui Alpha Bank', $html );
		$this->assertStringContainsString( 'customer=' . $id, $html );
		$this->assertStringContainsString( '>Edit<', $html );
		$this->assertStringNotContainsString( 'type="checkbox"', $html );
		$this->assertStringNotContainsString( 'wiw-bulk-select', $html );
		$this->assertSame( array(), ( new CustomersTable() )->allowed_bulk_actions() );

		$this->as_role( 'wiw_editor' );
		$this->assertStringNotContainsString( '>Edit<', ( new CustomersTable() )->html( new Request( array() ) ), 'no row action without wiw_manage_register' );
	}

	public function test_REQ_275_sponsored_deals_list_has_no_checkboxes_and_opens_the_story_only_for_an_editor_of_it(): void {
		$this->as_role( 'administrator' );
		( new SponsoredStoriesScreen() )->handle(
			new Request(
				array(
					'op'            => 'create',
					'customer_name' => 'Ui Sponsor Ltd',
					'amount'        => '100',
				)
			)
		);
		global $wpdb;
		$story = self::factory()->post->create( array( 'post_title' => 'Ui Sponsored Story' ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . ArrangementRepository::table() . ' SET story_id = %d', $story ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$html = ( new SponsoredDealsTable() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'Ui Sponsor Ltd', $html );
		$this->assertStringContainsString( '>Open story<', $html );
		$this->assertStringNotContainsString( 'type="checkbox"', $html );

		$this->as_role( 'wiw_reviewer' );
		$this->assertStringNotContainsString( '>Open story<', ( new SponsoredDealsTable() )->html( new Request( array() ) ), 'no row action for a user who may not edit the story' );
	}

	public function test_REQ_275_subscribers_list_is_read_only_with_no_checkboxes_or_row_actions(): void {
		$repo = new SubscriberRepository( static fn (): string => '2026-11-12 09:00:00', new SettingsService( SettingsService::default_registry() ) );
		$repo->confirm( (string) $repo->add_pending( 'ui.reader@example.test' )['token'] );
		$this->as_role( 'administrator' );
		$html = ( new SubscribersTable( $repo, 'confirmed', 1 ) )->html( new Request( array() ) );
		$this->assertStringContainsString( 'ui.reader@example.test', $html );
		$this->assertStringNotContainsString( 'type="checkbox"', $html );
		$this->assertStringNotContainsString( 'wiw-bulk-select', $html );
		$this->assertStringNotContainsString( 'class="row-actions"', $html );
		$this->assertStringNotContainsString( 'method="post"', $html, 'no bulk form around a read-only list' );
		$this->assertStringContainsString( '<form method="get"', $html, 'a GET form for the pager' );
	}
}
