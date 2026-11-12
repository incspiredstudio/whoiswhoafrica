<?php
/**
 * Profile states on the page (REQ-216, REQ-208, REQ-209): the pending label with correction links, "Reviewed on", and the staff
 * preview banner. Needs the WordPress test suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileStatesPageTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__ ) . '/../wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->users = array();
		set_query_var( 'wiw_profile_id', 0 );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Create a profile in a state.
	 *
	 * @param string $name  Name.
	 * @param string $state Target state: published_pending, published_reviewed or rejected.
	 */
	private function profile( string $name, string $state ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		( new ProfileFields() )->set( $id, 'industry', 'Energy' );
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'industry',
				'source_url'   => 'https://example.org/src',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		if ( PubState::REJECTED === $state ) {
			$machine->transition( $id, PubState::REJECTED, 'Test rejection', 1 );
		}
		return $id;
	}

	/**
	 * Create a staff user who may read profiles that are not public.
	 */
	private function staff(): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'statestest_staff_' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password( 20 ),
				'user_email' => 'statestest' . wp_generate_password( 6, false ) . '@example.org',
				'role'       => 'administrator',
			)
		);
		$this->assertIsInt( $user_id );
		$this->users[] = $user_id;
		( new \WP_User( $user_id ) )->add_cap( 'read_private_wiw_profiles' );
		return $user_id;
	}

	/**
	 * Render the profile template for an id.
	 *
	 * @param int $id Profile id.
	 */
	private function render( int $id ): string {
		set_query_var( 'wiw_profile_id', $id );
		ob_start();
		include get_template_directory() . '/templates/profile.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_216_a_pending_profile_shows_the_label_and_correct_and_remove_links(): void {
		$out = $this->render( $this->profile( 'Statestest Pending Org', PubState::PUBLISHED_PENDING ) );
		$this->assertStringContainsString( 'notice--pending', $out );
		$this->assertStringContainsString( '/correct/statestest-pending-org/', $out );
		$this->assertStringContainsString( '/remove/statestest-pending-org/', $out );
		$this->assertStringNotContainsString( 'Reviewed on', $out );
		$this->assertStringNotContainsString( 'Staff preview', $out );
	}

	public function test_REQ_216_a_reviewed_profile_shows_the_reviewed_on_date_and_no_pending_label(): void {
		$out = $this->render( $this->profile( 'Statestest Reviewed Org', PubState::PUBLISHED_REVIEWED ) );
		$this->assertMatchesRegularExpression( '#Reviewed on\s*<time datetime="\d{4}-\d{2}-\d{2}">#', $out );
		$this->assertStringNotContainsString( 'notice--pending', $out );
	}

	public function test_REQ_208_staff_see_a_preview_banner_on_a_profile_that_is_not_public_and_visitors_see_nothing_of_it(): void {
		$id = $this->profile( 'Statestest Rejected Org', PubState::REJECTED );
		wp_set_current_user( 0 );
		$visitor = $this->render( $id );
		$this->assertStringNotContainsString( 'Statestest Rejected Org', $visitor );
		$this->assertStringNotContainsString( 'Staff preview', $visitor );
		$this->assertStringContainsString( 'This profile is not available.', $visitor );

		wp_set_current_user( $this->staff() );
		$staff = $this->render( $id );
		$this->assertStringContainsString( 'notice--preview', $staff );
		$this->assertStringContainsString( 'Staff preview. Not public: rejected. Visitors see HTTP 404.', $staff );
		$this->assertStringContainsString( 'Statestest Rejected Org', $staff );
	}

	public function test_REQ_208_a_public_profile_has_no_preview_banner_even_for_staff(): void {
		$id = $this->profile( 'Statestest Public Org', PubState::PUBLISHED_REVIEWED );
		wp_set_current_user( $this->staff() );
		$this->assertStringNotContainsString( 'Staff preview', $this->render( $id ) );
	}
}
