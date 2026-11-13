<?php
/**
 * One-click demo content in resumable steps (REQ-208, REQ-052, REQ-056): batches, resume, retry after a failed step, the production
 * rule for sites with real content, and removal in parts. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Cli\Sample\SampleMarker;
use WhoIsWho\Core\Platform\Cli\Sample\SampleRemover;
use WhoIsWho\Core\Platform\Cli\Sample\SampleSeeder;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Setup\DemoImport;
use WhoIsWho\Tests\WpTestCase;

final class DemoImportTest extends WpTestCase {

	/**
	 * Administrator the import acts as.
	 *
	 * @var int
	 */
	private int $admin = 0;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		delete_option( DemoImport::OPTION );
		delete_transient( DemoImport::LOCK );
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		( new SampleRemover( $this->admin ) )->remove( new \WhoIsWho\Core\Platform\Cli\Output( false ) );
		global $wpdb;
		foreach ( array( AwardsTables::CATEGORIES, AwardsTables::NOMINATIONS, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- test clean-up.
		}
	}

	protected function tearDown(): void {
		delete_option( DemoImport::OPTION );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Run batches until the import says it is done or failed, with a safety stop.
	 *
	 * @param DemoImport $demo Import.
	 * @return array<string, mixed>
	 */
	private function run_to_end( DemoImport $demo ): array {
		$progress = $demo->progress();
		for ( $i = 0; $i < 60 && 'running' === $progress['status']; $i++ ) {
			$progress = $demo->advance( $this->admin );
		}
		return $progress;
	}

	public function test_REQ_208_the_import_runs_in_batches_to_the_end_and_everything_is_marked(): void {
		$demo    = new DemoImport( SampleSeedCommandTest::small(), true );
		$started = $demo->start( $this->admin, '' );
		$this->assertSame( 'running', $started['status'] );
		$this->assertSame( 0, $started['percent'] );
		$first = $demo->advance( $this->admin );
		$this->assertSame( 'running', $first['status'], 'one request does one batch, not everything' );
		$this->assertLessThan( 100, $first['percent'] );
		$done = $this->run_to_end( $demo );
		$this->assertSame( 'done', $done['status'], (string) $done['error'] );
		$this->assertSame( 100, $done['percent'] );
		$this->assertGreaterThan( 0, $done['counts']['profiles'] );
		$this->assertSame( 5, $done['counts']['profiles'] );
		$this->assertSame( 2, $done['counts']['stories'] );
		$this->assertNotSame( array(), SampleMarker::all_post_ids(), 'content:find-sample still finds the demo' );
		$ids = get_posts(
			array(
				'post_type'      => ProfileType::POST_TYPE,
				'post_status'    => get_post_stati(),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$this->assertSame( '1', get_post_meta( $id, SampleMarker::META, true ) );
		}
		$this->assertTrue( $demo->installed() );
	}

	public function test_REQ_208_a_run_that_stopped_resumes_and_makes_nothing_twice(): void {
		$demo = new DemoImport( SampleSeedCommandTest::small(), true );
		$demo->start( $this->admin, '' );
		$demo->advance( $this->admin );
		$demo->advance( $this->admin );
		// A new object, as a new request would have: the position is in the database.
		$again = new DemoImport( SampleSeedCommandTest::small(), true );
		$this->assertSame( 'running', $again->state()['status'] );
		$this->assertSame( 'running', $again->start( $this->admin, '' )['status'], 'starting again resumes' );
		$done = $this->run_to_end( $again );
		$this->assertSame( 'done', $done['status'], (string) $done['error'] );
		$count = count( SampleMarker::all_post_ids() );
		// Running the whole thing again through the seeder makes nothing new.
		$seeder = new SampleSeeder( SampleSeedCommandTest::small(), $this->admin );
		$seeder->seed( new \WhoIsWho\Core\Platform\Cli\Output( false ) );
		$this->assertSame( array(), $seeder->created() );
		$this->assertSame( $count, count( SampleMarker::all_post_ids() ) );
	}

	public function test_REQ_208_a_failing_step_stops_with_a_clear_message_and_can_be_retried(): void {
		$bad                     = SampleSeedCommandTest::small();
		$bad['events'][0]['url'] = 'not a url with secret-token';
		$demo                    = new DemoImport( $bad, true );
		$demo->start( $this->admin, '' );
		$progress = $this->run_to_end( $demo );
		$this->assertSame( 'failed', $progress['status'] );
		$this->assertStringContainsString( 'Try again', (string) $progress['error'] );
		$this->assertStringContainsString( 'events', (string) $progress['error'] );
		$this->assertStringNotContainsString( 'secret-token', (string) $progress['error'], 'only the step and the class are reported' );
		// The dataset is fixed, and the same import carries on from the failed step.
		$fixed = new DemoImport( SampleSeedCommandTest::small(), true );
		$this->assertSame( 'running', $fixed->start( $this->admin, '' )['status'] );
		$done = $this->run_to_end( $fixed );
		$this->assertSame( 'done', $done['status'], (string) $done['error'] );
	}

	public function test_REQ_208_only_one_batch_runs_at_a_time(): void {
		$demo = new DemoImport( SampleSeedCommandTest::small(), true );
		$demo->start( $this->admin, '' );
		set_transient( DemoImport::LOCK, '1', 60 );
		$busy = $demo->advance( $this->admin );
		$this->assertTrue( $busy['busy'] );
		$this->assertSame( 0, $busy['percent'] );
	}

	public function test_REQ_056_production_with_no_real_content_may_import_and_with_real_content_needs_the_typed_words(): void {
		$demo = new DemoImport( SampleSeedCommandTest::small(), true );
		$this->assertNull( $demo->refusal( '' ), 'a fresh WordPress has only its own Hello world post' );
		$story = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'A real story',
			)
		);
		$this->assertGreaterThan( 0, $demo->real_content_count() );
		$this->assertNotNull( $demo->refusal( '' ) );
		$this->assertNotNull( $demo->refusal( 'import demo please' ) );
		$this->assertNull( $demo->refusal( 'IMPORT DEMO' ) );
		$refused = $demo->start( $this->admin, '' );
		$this->assertNotSame( 'running', $refused['status'] );
		$this->assertNotSame( '', $refused['error'] );
		$this->assertSame( 'idle', $demo->state()['status'] );
		// Outside production the words are not needed.
		$this->assertNull( ( new DemoImport( SampleSeedCommandTest::small(), false ) )->refusal( '' ) );
		// Real content is never modified by the import.
		$this->assertSame( 'A real story', get_post( $story )->post_title );
		$typed = new DemoImport( SampleSeedCommandTest::small(), true );
		$typed->start( $this->admin, 'IMPORT DEMO' );
		$this->assertSame( 'done', $this->run_to_end( $typed )['status'] );
		$this->assertSame( 'publish', get_post( $story )->post_status );
		$this->assertSame( '', (string) get_post_meta( $story, SampleMarker::META, true ) );
	}

	public function test_REQ_056_demo_content_itself_does_not_count_as_real_content(): void {
		$demo = new DemoImport( SampleSeedCommandTest::small(), true );
		$demo->start( $this->admin, '' );
		$this->run_to_end( $demo );
		$this->assertSame( 0, $demo->real_content_count() );
	}

	public function test_REQ_208_removal_in_parts_ends_with_nothing_left_and_the_settings_back(): void {
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
		$real = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Keep me',
			)
		);
		$demo = new DemoImport( SampleSeedCommandTest::small(), false );
		$demo->start( $this->admin, '' );
		$this->assertSame( 'done', $this->run_to_end( $demo )['status'] );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$result = array();
		for ( $i = 0; $i < 200; $i++ ) {
			$result = $demo->remove( $this->admin, 0.0 );
			if ( ! empty( $result['done'] ) ) {
				break;
			}
		}
		$this->assertTrue( $result['done'], 'removal finishes even when each call has no time left' );
		$this->assertSame( array(), SampleMarker::all_post_ids() );
		$this->assertFalse( $demo->installed() );
		$this->assertSame( 'posts', get_option( 'show_on_front' ) );
		$this->assertSame( 'Keep me', get_post( $real )->post_title );
		$this->assertSame( 'idle', $demo->state()['status'] );
	}

	public function test_REQ_208_the_plan_counts_what_the_confirmation_promises(): void {
		$plan = ( new DemoImport( SampleSeedCommandTest::small(), true ) )->plan();
		$this->assertSame( 5, $plan['profiles'] );
		$this->assertSame( 2, $plan['stories'] );
		$this->assertSame( 2, $plan['events'] );
		$this->assertSame( 3, $plan['pins'] );
	}
}
