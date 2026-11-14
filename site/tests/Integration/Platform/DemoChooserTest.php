<?php
/**
 * The demo content chooser (REQ-208, REQ-056, REQ-240, REQ-258, REQ-237): parts and what they need, only the chosen steps run,
 * removal of one part keeps what a person changed, the invented comments, and the sample profiles shipped in the plugin's
 * sample/ folder (a small fictional bundle here) imported through the normal import path and approved, with the fallback to
 * invented profiles when there is no such folder. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Cli\Sample\SampleMarker;
use WhoIsWho\Core\Platform\Cli\Sample\SampleRemover;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Setup\DemoImport;
use WhoIsWho\Core\Platform\Setup\DemoParts;
use WhoIsWho\Core\Platform\Setup\SampleBundle;
use WhoIsWho\Core\Platform\Setup\SetupScreen;
use WhoIsWho\Tests\WpTestCase;

final class DemoChooserTest extends WpTestCase {

	/**
	 * Administrator the import acts as.
	 *
	 * @var int
	 */
	private int $admin = 0;

	/**
	 * Temporary sample folders to remove.
	 *
	 * @var string[]
	 */
	private array $dirs = array();

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
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->clean();
		if ( ! term_exists( 'commercial-bank', Taxonomies::ORG_TYPE ) ) {
			wp_insert_term( 'Commercial Bank', Taxonomies::ORG_TYPE, array( 'slug' => 'commercial-bank' ) );
		}
	}

	protected function tearDown(): void {
		$this->clean();
		foreach ( $this->dirs as $dir ) {
			foreach ( glob( $dir . '/*' ) ?: array() as $file ) {
				unlink( $file );
			}
			rmdir( $dir );
		}
		$this->dirs = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Remove every demo item, import row and option a test may have left.
	 */
	private function clean(): void {
		global $wpdb;
		( new SampleRemover( $this->admin ) )->remove( new Output( false ) );
		foreach ( array_keys( DemoParts::comment_keys() ) as $id ) {
			wp_delete_comment( $id, true );
		}
		$ids = (array) $wpdb->get_col( 'SELECT profile_id FROM ' . RecordMap::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- test clean-up.
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( array( RecordMap::table(), ResultRepository::table(), BatchRepository::table(), AwardsTables::table( AwardsTables::CATEGORIES ), AwardsTables::table( AwardsTables::NOMINATIONS ), AwardsTables::table( AwardsTables::CHANGES ) ) as $table ) {
			$wpdb->query( 'DELETE FROM ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names.
		}
		foreach ( array( DemoImport::OPTION, DemoImport::PARTS_OPTION, SampleBundle::OPTION, SampleMarker::REGISTRY ) as $option ) {
			delete_option( $option );
		}
		delete_transient( DemoImport::LOCK );
	}

	/**
	 * An empty temporary folder that stands in for the plugin's sample/ folder.
	 */
	private function folder(): string {
		$dir = str_replace( '\\', '/', sys_get_temp_dir() ) . '/wiw-sample-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		$this->dirs[] = $dir;
		return $dir;
	}

	/**
	 * A small fictional contract 1.0 bundle (organisations only, no real people) in a temporary sample/ folder.
	 *
	 * @param string[] $names Organisation names.
	 */
	private function sample_folder( array $names ): string {
		$dir   = $this->folder();
		$lines = array();
		foreach ( $names as $n => $name ) {
			$record                      = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example.json' ), false );
			$record->record_id           = 'rec_demo_' . $n;
			$record->batch_id            = 'B-demo';
			$record->external_ids        = new \stdClass();
			$record->fields->name->value = $name;
			$record->content_hash        = ContentHasher::hash_record( $record );
			$lines[]                     = (string) json_encode( $record ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test.
		}
		$body = implode( "\n", $lines ) . "\n";
		file_put_contents( $dir . '/records.jsonl', $body );
		file_put_contents(
			$dir . '/manifest.json',
			(string) wp_json_encode(
				array(
					'contract_version'        => '1.0',
					'run_id'                  => 'run_demo',
					'batch_id'                => 'B-demo',
					'generated_at'            => '2026-11-14T09:00:00Z',
					'pipeline_version'        => 'p1',
					'source_list_version'     => 's1',
					'sources'                 => array(),
					'suppression_snapshot_id' => 'snap_1',
					'record_count'            => count( $lines ),
					'counts'                  => array( 'organisation' => count( $lines ) ),
					'records_sha256'          => 'sha256:' . hash( 'sha256', $body ),
				)
			)
		);
		return $dir;
	}

	/**
	 * A demo import for the small dataset, with a sample folder (empty by default: no shipped profiles).
	 *
	 * @param string|null $sample Sample folder.
	 */
	private function demo( ?string $sample = null ): DemoImport {
		return new DemoImport( SampleSeedCommandTest::small(), false, new SampleBundle( $sample ?? $this->folder() ) );
	}

	/**
	 * Start some parts and run them to the end.
	 *
	 * @param DemoImport $demo  Import.
	 * @param string[]   $parts Parts.
	 * @return array<string, mixed>
	 */
	private function import( DemoImport $demo, array $parts ): array {
		$progress = $demo->start( $this->admin, '', $parts );
		for ( $i = 0; $i < 80 && 'running' === $progress['status']; $i++ ) {
			$progress = $demo->advance( $this->admin );
		}
		$this->assertSame( 'done', $progress['status'], (string) $progress['error'] );
		return $progress;
	}

	/**
	 * Remove one part to the end, collecting what was kept.
	 *
	 * @param DemoImport $demo Import.
	 * @param string     $part Part.
	 * @return array<int, array{title: string, url: string}>
	 */
	private function remove_part( DemoImport $demo, string $part ): array {
		$kept = array();
		for ( $i = 0; $i < 200; $i++ ) {
			$result = $demo->remove_part( $part, $this->admin, 0.0 );
			$kept   = array_merge( $kept, (array) ( $result['kept'] ?? array() ) );
			if ( ! empty( $result['done'] ) ) {
				return $kept;
			}
		}
		$this->fail( 'removal did not finish' );
	}

	/**
	 * Posts of a type, in any status.
	 *
	 * @param string $type Post type.
	 * @return int[]
	 */
	private function posts( string $type ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => get_post_stati(),
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);
	}

	public function test_REQ_208_a_part_brings_what_it_needs_and_removal_takes_what_needs_it(): void {
		$this->assertSame( array( 'profiles', 'awards' ), DemoParts::closure( array( 'awards' ) ) );
		$this->assertSame( array( 'stories', 'comments' ), DemoParts::closure( array( 'comments' ) ) );
		$this->assertSame( array( 'profiles', 'stories', 'curation' ), DemoParts::closure( array( 'curation' ) ) );
		$this->assertSame( array( 'pages' ), DemoParts::closure( array( 'pages', 'nonsense' ) ) );
		$this->assertSame( array( 'awards', 'curation' ), DemoParts::dependents( 'profiles' ) );
		$this->assertSame( array( 'comments', 'curation' ), DemoParts::dependents( 'stories' ) );
		$this->assertSame( array(), DemoParts::dependents( 'events' ) );
		$this->assertSame( array( 'terms', 'stories', 'comments', 'images' ), DemoParts::steps( array( 'comments' ) ) );
		foreach ( array_keys( DemoParts::NEEDS ) as $part ) {
			$this->assertNotSame( '', DemoParts::reasons()[ $part ], "{$part} says why it needs another part" );
		}
	}

	public function test_REQ_240_the_chooser_sends_only_the_chosen_parts_behind_the_capability_and_a_nonce(): void {
		$this->assertSame( array( 'events', 'awards' ), SetupScreen::parts_from( new Request( array( 'parts' => 'awards, events,<script>' ) ) ) );
		$this->assertSame( array(), SetupScreen::parts_from( new Request( array( 'parts' => '' ) ) ) );
		$this->assertSame( DemoParts::PARTS, SetupScreen::parts_from( new Request( array() ) ), 'an older page without the field asks for everything' );
		$editor = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		wp_set_current_user( $editor );
		$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( SetupScreen::ACTION_PART, SetupScreen::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( SetupScreen::ACTION_PART ) ) ) ) );
		wp_set_current_user( $this->admin );
		$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( SetupScreen::ACTION_PART, SetupScreen::CAP, new Request( array() ) ) );
		$this->assertNull( ActionGuard::check( SetupScreen::ACTION_PART, SetupScreen::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( SetupScreen::ACTION_PART ) ) ) ) );
		$this->assertNotSame( '', (string) $this->demo()->start( $this->admin, '', array() )['error'], 'nothing ticked, nothing starts' );
	}

	public function test_REQ_208_only_the_steps_of_the_chosen_parts_run(): void {
		$demo     = $this->demo();
		$progress = $this->import( $demo, array( 'events' ) );
		$this->assertSame( array( 'events' ), $progress['parts'] );
		$this->assertSame( array( 'terms', 'events', 'images' ), $progress['steps'] );
		$this->assertCount( 2, $this->posts( EventPostType::TYPE ) );
		$this->assertSame( array(), $this->posts( ProfileType::POST_TYPE ), 'no profiles were asked for' );
		$this->assertSame( 0, DemoParts::count( 'stories' ) );
		$this->assertSame( 0, DemoParts::count( 'pages' ) );
		$status = $demo->parts_status();
		$this->assertSame( array( 'events' ), array_keys( $status ) );
		$this->assertSame( 2, $status['events']['count'] );
		$this->assertGreaterThan( time() - 600, $status['events']['at'] );
		$this->assertTrue( $demo->installed() );
	}

	public function test_REQ_258_the_comments_part_adds_invented_comments_that_follow_the_rules(): void {
		$demo = $this->demo();
		$this->import( $demo, array( 'comments' ) );
		$story = (int) SampleMarker::find_post( 'story:test-story', 'post' );
		$this->assertGreaterThan( 0, $story, 'comments brought their stories' );
		$comments = get_comments(
			array(
				'post_id' => $story,
				'status'  => 'all',
			)
		);
		$this->assertGreaterThanOrEqual( 2, count( $comments ) );
		$this->assertLessThanOrEqual( 4, count( $comments ) );
		$states = array();
		foreach ( $comments as $comment ) {
			$this->assertStringEndsWith( '@example.com', $comment->comment_author_email );
			$this->assertStringContainsString( 'demo', strtolower( $comment->comment_author ) );
			$this->assertSame( 'comment', $comment->comment_type );
			$this->assertSame( '0', (string) $comment->user_id, 'guests, no accounts' );
			$this->assertSame( '1', get_comment_meta( (int) $comment->comment_ID, SampleMarker::META, true ) );
			$states[] = (string) $comment->comment_approved;
		}
		$this->assertContains( '1', $states, 'some are approved' );
		$this->assertContains( '0', $states, 'some wait for moderation' );
		// The theme's comment list (and wpDiscuz) read core approved comments, whichever form plugin is active.
		$this->assertNotSame(
			array(),
			get_comments(
				array(
					'post_id' => $story,
					'status'  => 'approve',
				)
			)
		);
		$later = (int) SampleMarker::find_post( 'story:test-later', 'post' );
		$this->assertSame(
			0,
			(int) get_comments(
				array(
					'post_id' => $later,
					'count'   => true,
					'status'  => 'all',
				)
			),
			'no comments on a story that is not out yet'
		);
		// Running the part again makes nothing twice.
		$count = count( DemoParts::comment_keys() );
		$this->import( $demo, array( 'comments' ) );
		$this->assertSame( $count, count( DemoParts::comment_keys() ) );
		$this->assertSame( $count, $demo->parts_status()['comments']['count'] );
	}

	public function test_REQ_208_removing_a_part_keeps_what_a_person_changed_and_reports_it(): void {
		$demo = $this->demo();
		$this->import( $demo, array( 'comments', 'events' ) );
		$events = $this->posts( EventPostType::TYPE );
		$this->assertCount( 2, $events );
		wp_update_post(
			array(
				'ID'         => $events[0],
				'post_title' => 'Our own event now',
			)
		);
		$kept = $this->remove_part( $demo, 'events' );
		$this->assertCount( 1, $kept );
		$this->assertSame( 'Our own event now', $kept[0]['title'] );
		$this->assertSame( array( $events[0] ), $this->posts( EventPostType::TYPE ) );
		$this->assertSame( '', (string) get_post_meta( $events[0], SampleMarker::META, true ), 'it is the site\'s own content now' );
		$this->assertArrayNotHasKey( 'events', $demo->parts_status() );
		$this->assertArrayHasKey( 'stories', $demo->parts_status(), 'other parts stay' );

		// A changed comment is kept; removing Stories removes Comments first, and a story with someone's own comment stays.
		$comment = (int) array_keys( DemoParts::comment_keys() )[0];
		wp_update_comment(
			array(
				'comment_ID'      => $comment,
				'comment_content' => 'A moderator rewrote this.',
			)
		);
		$story = (int) SampleMarker::find_post( 'story:test-story', 'post' );
		$later = (int) SampleMarker::find_post( 'story:test-later', 'post' );
		$kept  = $this->remove_part( $demo, 'stories' );
		$this->assertSame( array(), DemoParts::comment_keys() );
		$this->assertNotNull( get_comment( $comment ) );
		$this->assertNotNull( get_post( $story ), 'the story with a kept comment stays' );
		$this->assertNull( get_post( $later ) );
		$this->assertCount( 2, $kept );
		$this->assertSame( array(), $demo->parts_status() );
		$this->assertSame( 'publish', get_post_status( $events[0] ) );
	}

	public function test_REQ_208_removing_all_takes_every_part_and_puts_settings_back(): void {
		update_option( 'show_on_front', 'posts' );
		$demo = $this->demo();
		$this->import( $demo, DemoParts::PARTS );
		$this->assertSame( DemoParts::PARTS, array_keys( $demo->parts_status() ) );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$result = array();
		for ( $i = 0; $i < 300; $i++ ) {
			$result = $demo->remove( $this->admin, 0.0 );
			if ( ! empty( $result['done'] ) ) {
				break;
			}
		}
		$this->assertTrue( $result['done'] );
		$this->assertSame( array(), SampleMarker::all_post_ids() );
		$this->assertSame( array(), DemoParts::comment_keys() );
		$this->assertSame( 'posts', get_option( 'show_on_front' ) );
		$this->assertFalse( $demo->installed() );
	}

	public function test_REQ_208_without_a_sample_folder_the_profiles_are_the_invented_ones(): void {
		$demo = $this->demo();
		$this->assertFalse( $demo->ships_profiles() );
		$this->assertSame( 5, $demo->plan()['profiles'] );
		$this->import( $demo, array( 'profiles' ) );
		$this->assertSame( 5, DemoParts::count( 'profiles' ) );
		$this->assertNotNull( SampleMarker::find_post( 'profile:test-person', ProfileType::POST_TYPE ) );
		$this->assertSame( '', (string) get_option( SampleBundle::OPTION, '' ), 'no import batch' );
	}

	public function test_REQ_237_shipped_sample_profiles_go_through_the_import_and_are_approved_by_the_administrator(): void {
		$sample = $this->sample_folder( array( 'Demo Fixture Bank One', 'Demo Fixture Bank Two', 'Demo Fixture Bank Three' ) );
		$demo   = $this->demo( $sample );
		$this->assertTrue( $demo->ships_profiles() );
		$this->assertSame( 3, $demo->plan()['profiles'] );
		$this->import( $demo, array( 'awards' ) );
		$batch = ( new BatchRepository() )->get( 'B-demo' );
		$this->assertNotNull( $batch, 'a real import batch' );
		$this->assertSame( 'applied', $batch['status'] );
		$this->assertSame( $this->admin, (int) $batch['created_by'] );
		$created = ( new ResultRepository() )->created_profile_ids( 'B-demo' );
		$this->assertCount( 3, $created );
		$states = new StateRepository();
		foreach ( $created as $id ) {
			$row = (array) $states->get( $id );
			$this->assertSame( PubState::PUBLISHED_REVIEWED, $states->pub_state( $id ) );
			$this->assertSame( $this->admin, (int) $row['reviewer_id'] );
			$this->assertSame( 'import', (string) $row['origin'] );
			$this->assertSame( '1', get_post_meta( $id, SampleMarker::META, true ) );
			$this->assertStringStartsWith( SampleBundle::KEY_PREFIX . 'rec_demo_', (string) get_post_meta( $id, SampleMarker::KEY_META, true ) );
			$this->assertNotSame( array(), ( new ProvenanceRepository() )->for_profile( $id ), 'provenance from the bundle' );
		}
		$this->assertNull( SampleMarker::find_post( 'profile:test-person', ProfileType::POST_TYPE ), 'no invented profiles when the bundle ships' );
		$this->assertSame( 3, $demo->parts_status()['profiles']['count'] );
		$this->assertGreaterThan( 0, $demo->parts_status()['awards']['count'], 'awards use the shipped profiles' );
		global $wpdb;
		$audited = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = %s", 'demo.profiles_approved' ) ); // phpcs:ignore WordPress.DB
		$this->assertGreaterThan( 0, $audited );

		// Removing Profiles removes Awards first, the profiles and the demo batch, and leaves the shipped folder alone.
		$this->remove_part( $demo, 'profiles' );
		foreach ( $created as $id ) {
			$this->assertNull( get_post( $id ) );
		}
		$this->assertNull( ( new BatchRepository() )->get( 'B-demo' ) );
		$this->assertSame( array(), $demo->parts_status() );
		$this->assertFileExists( $sample . '/records.jsonl' );
		// It can be imported again afterwards.
		$this->import( $demo, array( 'profiles' ) );
		$this->assertSame( 3, DemoParts::count( 'profiles' ) );
	}
}
