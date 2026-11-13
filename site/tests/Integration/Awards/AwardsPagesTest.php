<?php
/**
 * Awards public pages (M4-AWD-25, M4-AWD-26; REQ-033, REQ-198, REQ-064, REQ-200, REQ-266): the nominees list, a category page, a
 * nominee page and the overview and rules pages, drawn only from the read models. Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsRoutes;
use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\PublicNominee;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\WpTestCase;

final class AwardsPagesTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES, AwardsTables::EDITIONS ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function profile( string $name, bool $reviewed ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( $reviewed ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		return $id;
	}

	private function category( string $title = 'Digital Pioneer' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => $title,
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function nomination( int $profile_id, int $category_id, string $status ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'     => $category_id,
				'profile_id'      => $profile_id,
				'nominator_name'  => 'Nora Nominator',
				'nominator_email' => 'nora.nominator@example.test',
				'contact_email'   => 'press.contact@example.test',
				'evidence'        => 'Evidence text from the nominator.',
				'stage'           => 'nominated',
				'status'          => $status,
				'source'          => 'public_form',
				'created_at'      => '2026-11-12 10:00:00',
				'updated_at'      => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Render an Awards view.
	 *
	 * @param string $view View.
	 * @param string $arg  Argument.
	 */
	private function view( string $view, string $arg = '' ): string {
		set_query_var( AwardsRoutes::VIEW_VAR, $view );
		set_query_var( AwardsRoutes::ARG_VAR, $arg );
		ob_start();
		include get_template_directory() . '/templates/awards.php';
		$out = (string) ob_get_clean();
		set_query_var( AwardsRoutes::VIEW_VAR, '' );
		set_query_var( AwardsRoutes::ARG_VAR, '' );
		return $out;
	}

	/**
	 * Render the overview or rules template for a page.
	 *
	 * @param int $page Page id.
	 */
	private function page( int $page ): string {
		$this->go_to( (string) get_permalink( $page ) );
		ob_start();
		include get_template_directory() . '/templates/awards-page.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_198_nominees_page_shows_an_empty_state_when_nothing_is_live(): void {
		$this->nomination( $this->profile( 'Hidden Ltd', true ), $this->category(), 'not_live' );
		$out = $this->view( 'nominees' );
		$this->assertStringContainsString( 'Nominees are not published yet', $out );
		$this->assertStringNotContainsString( 'Hidden Ltd', $out );
	}

	public function test_REQ_033_nominees_page_groups_live_nominees_and_has_no_nominator(): void {
		$this->nomination( $this->profile( 'Visible Ltd', true ), $this->category(), 'live' );
		$out = $this->view( 'nominees' );
		$this->assertStringContainsString( 'Visible Ltd', $out );
		$this->assertStringContainsString( 'Technology and Innovation', $out );
		$this->assertStringContainsString( 'href="#group-technology-innovation"', $out );
		foreach ( array( 'Nora Nominator', 'nora.nominator', 'press.contact', 'Evidence text' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $out );
		}
	}

	public function test_REQ_033_category_page_lists_live_nominees_only(): void {
		$cat = $this->category( 'Digital Pioneer' );
		$this->nomination( $this->profile( 'Pioneer Ltd', true ), $cat, 'live' );
		$this->nomination( $this->profile( 'Draft Ltd', true ), $cat, 'not_live' );
		$out = $this->view( 'category', (string) $cat );
		$this->assertStringContainsString( 'Digital Pioneer', $out );
		$this->assertStringContainsString( 'Pioneer Ltd', $out );
		$this->assertStringNotContainsString( 'Draft Ltd', $out );
		$this->assertStringContainsString( 'There is no public voting', $out );
	}

	public function test_REQ_198_category_page_without_nominees_shows_the_empty_state(): void {
		$this->assertStringContainsString( 'No nominees yet', $this->view( 'category', (string) $this->category( 'Quiet Category' ) ) );
	}

	public function test_REQ_064_nominee_page_has_the_extract_and_never_names_the_nominator(): void {
		$id  = $this->nomination( $this->profile( 'Named Ltd', true ), $this->category(), 'live' );
		$out = $this->view( 'nominee', PublicNominee::ref_for( $id ) );
		$this->assertStringContainsString( '<h1>Named Ltd</h1>', $out );
		$this->assertStringContainsString( 'On the record', $out );
		$this->assertStringContainsString( 'Read the full profile', $out );
		$this->assertStringContainsString( 'The nominator is never named', $out );
		$this->assertStringNotContainsString( 'Nora Nominator', $out );
		$this->assertStringNotContainsString( 'Staff preview', $out );
	}

	public function test_REQ_198_not_live_nominee_is_unavailable_to_visitors_and_previewed_by_staff(): void {
		$ref = PublicNominee::ref_for( $this->nomination( $this->profile( 'Later Ltd', true ), $this->category(), 'not_live' ) );
		$out = $this->view( 'nominee', $ref );
		$this->assertStringContainsString( 'This nominee is not available', $out );
		$this->assertStringNotContainsString( 'Later Ltd', $out );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
		$out = $this->view( 'nominee', $ref );
		$this->assertStringContainsString( 'Staff preview', $out );
		$this->assertStringContainsString( 'Later Ltd', $out );
	}

	public function test_REQ_266_the_awards_pages_are_picked_by_slug(): void {
		$overview = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_name'  => 'awards',
				'post_title' => 'Awards',
			)
		);
		$rules    = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'rules',
				'post_title'  => 'Rules',
				'post_parent' => $overview,
			)
		);
		$this->go_to( (string) get_permalink( $overview ) );
		$this->assertSame( 'overview', wiw_awards_page_kind() );
		$this->go_to( (string) get_permalink( $rules ) );
		$this->assertSame( 'rules', wiw_awards_page_kind() );
	}

	public function test_REQ_200_overview_lists_categories_from_the_screen_and_marks_the_stage(): void {
		$cat = $this->category( 'Digital Pioneer' );
		$this->nomination( $this->profile( 'Counted Ltd', true ), $cat, 'live' );
		$overview = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'awards',
				'post_title'   => 'Awards',
				'post_content' => '<p>Eligibility copy from the editor.</p>',
			)
		);
		$out      = $this->page( $overview );
		$this->assertStringContainsString( 'Digital Pioneer', $out );
		$this->assertStringContainsString( 'href="' . home_url( '/awards/category/' . $cat . '/' ) . '"', $out );
		$this->assertStringContainsString( 'Eligibility copy from the editor.', $out );
		$this->assertSame( 1, substr_count( $out, 'aria-current="step"' ), 'one step is marked Now' );
	}

	public function test_REQ_266_rules_page_states_no_public_voting_and_payment_has_no_effect(): void {
		$overview = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_name'  => 'awards',
				'post_title' => 'Awards',
			)
		);
		$rules    = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'rules',
				'post_title'   => 'Rules',
				'post_parent'  => $overview,
				'post_content' => '<p>Rule copy.</p>',
			)
		);
		$out      = $this->page( $rules );
		$this->assertStringContainsString( 'Rule copy.', $out );
		$this->assertStringContainsString( 'There is no public voting', $out );
		$this->assertStringContainsString( 'Payment never affects', $out );
	}
}
