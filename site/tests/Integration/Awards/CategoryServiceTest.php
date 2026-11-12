<?php
/**
 * Award categories: service and screen (REQ-266, REQ-200). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\CategoriesScreen;
use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\Integration\Support\AuthorisationMatrix;
use WhoIsWho\Tests\WpTestCase;

final class CategoryServiceTest extends WpTestCase {

	private const PROBE = 'wiw_categories_audit_probe';

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		foreach ( array( AwardsTables::CATEGORIES, AwardsTables::NOMINATIONS ) as $name ) {
			Schema::create_table( $name, AwardsTables::definitions()[ $name ] );
		}
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$wpdb->query( 'DELETE FROM ' . AwardsTables::table( AwardsTables::CATEGORIES ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DELETE FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function service_as( string $role ): CategoryService {
		$user = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user );
		return new CategoryService( new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', static fn (): int => $user ) );
	}

	/**
	 * Audit actions in order.
	 *
	 * @return string[]
	 */
	private function actions(): array {
		global $wpdb;
		return array_map( 'strval', (array) $wpdb->get_col( 'SELECT action FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	private function nominate( int $category ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id' => $category,
				'created_at'  => '2026-11-12 10:00:00',
				'updated_at'  => '2026-11-12 10:00:00',
			)
		);
	}

	public function test_REQ_266_editor_can_add_rename_and_reorder_a_category(): void {
		$service = $this->service_as( 'wiw_editor' );
		$first   = $service->add( 'technology-innovation', 'Digital Pioneer' );
		$second  = $service->add( 'technology-innovation', 'Data Leader' );
		$service->rename( $second, 'Data and AI Leader' );
		$service->reorder( 'technology-innovation', array( $second, $first ) );

		$titles = array_column( $service->in_group( 'technology-innovation' ), 'title' );
		$this->assertSame( array( 'Data and AI Leader', 'Digital Pioneer' ), $titles );
		$this->assertSame( array( 'award_category.add', 'award_category.add', 'award_category.rename', 'award_category.reorder' ), $this->actions() );
	}

	public function test_REQ_266_five_groups_are_shown_and_unknown_groups_are_refused(): void {
		$service = $this->service_as( 'administrator' );
		$this->assertCount( 5, $service->grouped() );
		$this->expectException( \DomainException::class );
		$service->add( 'made-up-group', 'Nope' );
	}

	public function test_REQ_266_category_with_nominations_cannot_be_removed(): void {
		$service = $this->service_as( 'wiw_editor' );
		$used    = $service->add( 'professional-excellence', 'Used' );
		$empty   = $service->add( 'professional-excellence', 'Empty' );
		$this->nominate( $used );

		try {
			$service->remove( $used );
			$this->fail( 'A category with nominations was removed.' );
		} catch ( \DomainException $refusal ) {
			$this->assertStringContainsString( 'has nominations', $refusal->getMessage() );
		}
		$this->assertNotNull( $service->find( $used ) );

		$service->remove( $empty );
		$this->assertNull( $service->find( $empty ) );
		$this->assertContains( 'award_category.remove_refused', $this->actions() );
		$this->assertContains( 'award_category.remove', $this->actions() );

		$service->archive( $used, true );
		$this->assertSame( array(), $service->in_group( 'professional-excellence' ), 'Archived categories are not offered.' );
		$this->assertCount( 1, $service->in_group( 'professional-excellence', true ) );
	}

	public function test_REQ_266_category_change_is_audited_and_shows_on_the_awards_page(): void {
		$purged = array();
		add_action(
			'wiw_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			}
		);
		$service = $this->service_as( 'administrator' );
		$service->add( 'leadership-public-impact', 'Public Servant of the Year' );

		$this->assertSame( array( 'award_category.add' ), $this->actions() );
		$this->assertContains( home_url( '/awards/' ), $purged );
		$this->assertContains( home_url( '/nominate/' ), $purged );
		$listed = $service->grouped()['leadership-public-impact']['categories'];
		$this->assertSame( 'Public Servant of the Year', $listed[0]['title'] );
	}

	public function test_REQ_200_award_categories_screen_requires_wiw_manage_awards(): void {
		$registry = new ScreenRegistry();
		$registry->register( CategoriesScreen::SCREEN, 'Award categories', CategoriesScreen::CAP, '__return_null' );
		$registry->hook();
		$users = AuthorisationMatrix::users( self::factory(), CategoriesScreen::CAP, CategoriesScreen::CAP );
		AuthorisationMatrix::assert_statuses(
			$this,
			CategoriesScreen::SCREEN,
			$users,
			array(
				'reviewer'      => 403,
				'editor'        => 200,
				'administrator' => 200,
				'granted'       => 200,
				'revoked'       => 403,
			)
		);
	}

	public function test_REQ_200_a_user_without_the_capability_cannot_post_or_change_anything(): void {
		$service = $this->service_as( 'administrator' );
		$id      = $service->add( 'technology-innovation', 'Keep me' );

		$reviewer = $this->service_as( 'wiw_reviewer' );
		try {
			$reviewer->remove( $id );
			$this->fail( 'A reviewer removed a category.' );
		} catch ( \DomainException $refusal ) {
			$this->assertNotNull( $service->find( $id ) );
		}
		$this->assertNotNull( ActionGuard::check( CategoriesScreen::ACTION, CategoriesScreen::CAP, new Request( array() ) ), 'The guard refuses a reviewer.' );
		$this->expectException( \DomainException::class );
		( new CategoriesScreen( $reviewer ) )->html();
	}

	public function test_REQ_200_eligibility_text_is_not_a_category_setting(): void {
		$columns = array_keys( $this->columns() );
		foreach ( $columns as $column ) {
			$this->assertDoesNotMatchRegularExpression( '/eligib|criteria|rules|description/i', $column );
		}
		$service = $this->service_as( 'administrator' );
		$service->add( 'technology-innovation', 'Plain' );
		$html = ( new CategoriesScreen( $service ) )->html();
		$this->assertStringNotContainsStringIgnoringCase( 'name="eligibility"', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'name="criteria"', $html );
		$this->assertStringContainsString( 'Leadership and Public Impact', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
	}

	public function test_REQ_266_screen_handler_reports_a_refused_removal(): void {
		$service = $this->service_as( 'administrator' );
		$id      = $service->add( 'technology-innovation', 'Busy' );
		$this->nominate( $id );
		$message = ( new CategoriesScreen( $service ) )->handle(
			new Request(
				array(
					'op'          => 'remove',
					'category_id' => (string) $id,
				)
			)
		);
		$this->assertStringContainsString( 'cannot be removed', $message );
		$this->assertNotNull( $service->find( $id ) );
	}

	/**
	 * Columns of the categories table.
	 *
	 * @return array<string, mixed>
	 */
	private function columns(): array {
		global $wpdb;
		$out = array();
		foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM ' . AwardsTables::table( AwardsTables::CATEGORIES ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
			$out[ (string) $row['Field'] ] = true;
		}
		return $out;
	}
}
