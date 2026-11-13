<?php
/**
 * The Sponsored stories screen: list, filter, New sponsored story form (REQ-179, REQ-269). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Commerce\SponsoredStoriesScreen;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class SponsoredStoriesScreenTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( CustomerRepository::table(), ArrangementRepository::table(), PlacementRepository::table() ) as $table ) {
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

	/**
	 * Post a request to the handler.
	 *
	 * @param array<string, string> $values Fields.
	 */
	private function post( array $values ): string {
		return ( new SponsoredStoriesScreen() )->handle( new Request( $values ) );
	}

	public function test_REQ_179_deal_form_has_no_type_choice(): void {
		$this->as_role( 'administrator' );
		$html = ( new SponsoredStoriesScreen() )->html();
		$this->assertStringContainsString( 'New sponsored story', $html );
		$this->assertStringContainsString( 'name="amount"', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'name="type"', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'deal type', $html );
	}

	public function test_REQ_269_new_deal_requires_customer_and_amount(): void {
		$this->as_role( 'administrator' );
		$this->assertStringContainsString(
			'customer',
			strtolower(
				$this->post(
					array(
						'op'     => 'create',
						'amount' => '1000',
					)
				)
			)
		);
		$this->assertStringContainsString(
			'amount',
			strtolower(
				$this->post(
					array(
						'op'            => 'create',
						'customer_name' => 'Obi Holdings',
					)
				)
			)
		);
		$this->assertSame( array(), ( new ArrangementRepository() )->all() );
		$this->assertSame(
			'Sponsored story deal created.',
			$this->post(
				array(
					'op'            => 'create',
					'customer_name' => 'Obi Holdings',
					'amount'        => '500000.50',
					'method'        => 'transfer',
					'reference'     => 'TRX-7',
					'received_on'   => '2026-11-10',
				)
			)
		);
		$deals = ( new ArrangementRepository() )->all();
		$this->assertCount( 1, $deals );
		$this->assertSame( '50000050', (string) $deals[0]['amount_minor'] );
		$this->assertSame( 'TRX-7', $deals[0]['reference'] );
		$this->assertSame( 1, SponsoredStoriesScreen::minor_from_naira( '0.01' ) );
		$this->expectException( \InvalidArgumentException::class );
		SponsoredStoriesScreen::minor_from_naira( '12.345' );
	}

	public function test_REQ_269_list_shows_four_steps_next_step_and_filters_by_status(): void {
		$this->as_role( 'administrator' );
		$this->post(
			array(
				'op'            => 'create',
				'customer_name' => 'Unpaid Ltd',
				'amount'        => '100',
			)
		);
		$this->post(
			array(
				'op'            => 'create',
				'customer_name' => 'Paid Ltd',
				'amount'        => '200',
				'method'        => 'cash',
				'reference'     => 'R1',
				'received_on'   => '2026-11-10',
			)
		);
		$screen = new SponsoredStoriesScreen();
		$all    = $screen->html();
		$this->assertStringContainsString( '●○○○', $all );
		$this->assertStringContainsString( '●●○○', $all );
		$this->assertStringContainsString( 'Record the payment', $all );
		$this->assertStringContainsString( 'Choose the story', $all );
		$only = $screen->html( '', 'needs_payment' );
		$this->assertStringContainsString( '<td>Unpaid Ltd</td>', $only );
		$this->assertStringNotContainsString( '<td>Paid Ltd</td>', $only );
	}

	public function test_REQ_269_editor_list_has_no_amounts(): void {
		$this->as_role( 'administrator' );
		$this->post(
			array(
				'op'            => 'create',
				'customer_name' => 'Obi Holdings',
				'amount'        => '123456',
				'reference'     => 'SECRET-9',
			)
		);
		$this->as_role( 'wiw_editor' );
		$html = ( new SponsoredStoriesScreen() )->html();
		$this->assertStringContainsString( 'Obi Holdings', $html );
		$this->assertStringNotContainsString( '123,456', $html );
		$this->assertStringNotContainsString( 'SECRET-9', $html );
		$this->assertStringNotContainsString( '<th>Amount', $html );
		$this->assertStringNotContainsString( 'New sponsored story', $html );
		$this->assertStringContainsString(
			'refused',
			strtolower(
				'refused ' . $this->post(
					array(
						'op'            => 'create',
						'customer_name' => 'X',
						'amount'        => '5',
					)
				)
			)
		);
		$this->assertCount( 1, ( new ArrangementRepository() )->all(), 'An Editor cannot create a deal.' );
	}

	public function test_REQ_269_screen_requires_wiw_set_sponsored(): void {
		$registry = new ScreenRegistry();
		$registry->register( SponsoredStoriesScreen::SCREEN, 'Sponsored stories', SponsoredStoriesScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), SponsoredStoriesScreen::CAP, SponsoredStoriesScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			SponsoredStoriesScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
		wp_set_current_user( $users['reviewer'] );
		$this->expectException( \DomainException::class );
		( new SponsoredStoriesScreen() )->html();
	}
}
