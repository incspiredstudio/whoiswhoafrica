<?php
/**
 * Home nominees block (REQ-198, REQ-033, REQ-257, REQ-001): a dark grid of nominee posters with a group filter, drawn only from the
 * PublicNominee read model and absent until a nominee is Live. Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

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

final class HomeNomineesTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
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
	 * Render the front page.
	 */
	private function render(): string {
		$this->go_to( home_url( '/' ) );
		ob_start();
		include get_template_directory() . '/front-page.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_198_the_nominees_block_is_absent_while_no_nominee_is_live(): void {
		$cat = $this->category();
		$this->nomination( $this->profile( 'Home Hidden Ltd', true ), $cat, 'not_live' );
		$out = $this->render();
		$this->assertStringNotContainsString( 'h-noms', $out );
		$this->assertStringNotContainsString( 'Home Hidden Ltd', $out );
	}

	public function test_REQ_198_a_live_nominee_shows_in_the_block_with_the_heading_the_count_and_a_group_filter(): void {
		$cat = $this->category( 'Digital Pioneer' );
		$this->nomination( $this->profile( 'Home Live Ltd', true ), $cat, 'live' );
		$this->nomination( $this->profile( 'Home Pending Ltd', false ), $cat, 'live' );
		$out = $this->render();
		$this->assertStringContainsString( 'class="h-noms"', $out );
		$this->assertStringContainsString( 'Meet the', $out );
		$this->assertStringContainsString( 'Home Live Ltd', $out );
		$this->assertStringContainsString( 'Digital Pioneer', $out );
		$this->assertStringContainsString( 'data-group="technology-innovation"', $out );
		$this->assertStringContainsString( '1 nominee in 1 category', $out );
		$this->assertStringContainsString( '/awards/nominees/', $out );
		$this->assertStringNotContainsString( 'Home Pending Ltd', $out, 'a profile that is not reviewed and public never shows' );
		$this->assertStringNotContainsString( 'nora.nominator@example.test', $out );
		$this->assertStringNotContainsString( 'press.contact@example.test', $out );
	}
}
