<?php
/**
 * The Review item screen (REQ-084, REQ-141, REQ-143, REQ-148). Needs the WordPress test suite and
 * the real tables, so it is a plain TestCase that cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Apply\ChunkApplier;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\Scan\ScanBlock;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\Metrics\DecisionLog;
use WhoIsWho\Core\Review\ReviewService;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\Admin\ReviewItemScreen;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Tests\TestCase;

final class ReviewItemScreenTest extends TestCase {

	/**
	 * Bundle folders to remove.
	 *
	 * @var string[]
	 */
	private array $dirs = array();

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! taxonomy_exists( Taxonomies::ORG_TYPE ) ) {
			( new Taxonomies() )->register();
		}
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		$this->clean();
		if ( ! term_exists( 'commercial-bank', Taxonomies::ORG_TYPE ) ) {
			wp_insert_term( 'Commercial Bank', Taxonomies::ORG_TYPE, array( 'slug' => 'commercial-bank' ) );
		}
		wp_set_current_user( 0 );
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
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Remove every profile, map row, result and batch this test created.
	 */
	private function clean(): void {
		global $wpdb;
		$ids = (array) $wpdb->get_col( 'SELECT profile_id FROM ' . RecordMap::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( array( RecordMap::table(), ResultRepository::table(), BatchRepository::table(), Suppression::table() ) as $table ) {
			$wpdb->query( 'DELETE FROM ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names.
		}
		delete_option( SettingsService::OPTION_PREFIX . 'import_all_not_public' );
	}

	/**
	 * One record line for the given id and name, with a correct content hash.
	 *
	 * @param string $id   Record id.
	 * @param string $name Organisation name.
	 * @param string $city Optional regulator text, to change the hash.
	 * @param string $summary Optional free-text summary.
	 */
	private function line( string $id, string $name, string $city = '', string $summary = '' ): string {
		$record                      = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example.json' ), false );
		$record->record_id           = $id;
		$record->batch_id            = 'B-apply';
		$record->external_ids        = new \stdClass();
		$record->fields->name->value = $name;
		if ( '' !== $city ) {
			$record->fields->regulator->value = $city;
		}
		if ( '' !== $summary ) {
			$record->fields->summary = (object) array(
				'value'      => $summary,
				'provenance' => $record->fields->name->provenance,
			);
		}
		$record->content_hash = ContentHasher::hash_record( $record );
		return (string) json_encode( $record ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test.
	}

	/**
	 * A new user.
	 */
	private function user(): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'apply_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * Count profile posts.
	 */
	private function profile_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", ProfileType::POST_TYPE ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Import one record as the given importer and return its profile id.
	 *
	 * @param int    $importer Importer user id.
	 * @param string $summary  Optional summary text.
	 */
	private function imported( int $importer, string $summary = '' ): int {
		$dir = sys_get_temp_dir() . '/wiw-rv-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		$this->dirs[] = $dir;
		$lines        = array( $this->line( 'rec_a', 'Review Alpha Qwv', '', $summary ) );
		$body         = implode(
			'
',
			$lines
		) . '
';
		file_put_contents( $dir . '/records.jsonl', $body );
		file_put_contents(
			$dir . '/manifest.json',
			wp_json_encode(
				array(
					'contract_version'        => '1.0',
					'run_id'                  => 'run_1',
					'batch_id'                => 'B-rev',
					'generated_at'            => '2026-11-10T09:00:00Z',
					'pipeline_version'        => 'p1',
					'source_list_version'     => 's1',
					'sources'                 => array(),
					'suppression_snapshot_id' => 'snap_1',
					'record_count'            => 1,
					'counts'                  => array( 'organisation' => 1 ),
					'records_sha256'          => 'sha256:' . hash( 'sha256', $body ),
				)
			)
		);
		$bundle = BundleReader::open( $dir );
		( new BatchRepository() )->register( $bundle, $importer, array( 'import_all_not_public' => false ), 'bundle' );
		$this->applier()->run( $bundle, 'B-rev' );
		return (int) ( new RecordMap() )->profile_for( 'rec_a' );
	}

	/**
	 * A reviewer.
	 */
	private function reviewer(): int {
		$id = $this->user();
		get_userdata( $id )->add_cap( 'wiw_review_records' );
		return $id;
	}

	private function applier( ?Suppression $suppression = null ): ChunkApplier {
		return new ChunkApplier( new ProfileApplier( $suppression ?? new Suppression( AuditLogger::for_site() ) ) );
	}

	public function test_REQ_084_review_item_shows_claim_with_source_and_date(): void {
		$id = $this->imported( $this->user() );
		wp_set_current_user( $this->reviewer() );
		$html = ( new ReviewItemScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) );
		$this->assertStringContainsString( 'Review Alpha Qwv', $html );
		$this->assertStringContainsString( 'https://regulator.example/licensed-banks', $html );
		$this->assertStringContainsString( '2026-11-10', $html );
		$this->assertStringContainsString( 'Checklist', $html );
		$this->assertStringContainsString( 'No allegations, litigation or rumours.', $html );
	}

	/**
	 * The review screens speak plain words, not codes (#810): the entity type and state names, field labels and reason labels.
	 */
	public function test_REQ_084_review_item_shows_plain_labels_not_codes(): void {
		$id = $this->imported( $this->user() );
		wp_set_current_user( $this->reviewer() );
		$html = ( new ReviewItemScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) );
		$this->assertStringNotContainsString( 'organisation / ', $html );
		$this->assertStringNotContainsString( 'imported_not_public', $html );
		$this->assertStringNotContainsString( 'published_pending', $html );
		$this->assertStringContainsString( 'Organisation', $html );
		$this->assertStringNotContainsString( '<th scope="row">org_type</th>', $html, 'field keys are shown as their labels' );
		$this->assertStringContainsString( '<th scope="row">Organisation type</th>', $html );
		$this->assertStringContainsString( '<option value="out_of_scope">Out of scope', $html, 'the reason code stays the value, the label is words' );
		$this->assertStringNotContainsString( '>out_of_scope<', $html );

		$this->assertSame( 'Imported, not public', \WhoIsWho\Core\Review\Admin\Labels::state( PubState::IMPORTED_NOT_PUBLIC ) );
		$this->assertSame( 'Person', \WhoIsWho\Core\Review\Admin\Labels::entity_type( 'individual' ) );
		$this->assertSame( 'Current title', \WhoIsWho\Core\Review\Admin\Labels::field( 'current_title' ) );
		$this->assertSame( 'Some new key', \WhoIsWho\Core\Review\Admin\Labels::field( 'some_new_key' ) );
		foreach ( DecisionLog::REASON_CODES as $code ) {
			$this->assertNotSame( $code, \WhoIsWho\Core\Review\Admin\Labels::reason( $code ) );
		}
	}

	public function test_REQ_148_approve_blocked_for_importer_with_message(): void {
		$importer = $this->reviewer();
		$id       = $this->imported( $importer );
		wp_set_current_user( $importer );
		$html = ( new ReviewItemScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) );
		$this->assertStringContainsString( 'You imported this record', $html );
		try {
			( new ReviewItemScreen() )->approve( $id, $importer, 5 );
			$this->fail( 'expected a refusal' );
		} catch ( StateException $e ) {
			$this->assertStringContainsString( 'imported', $e->getMessage() );
		}
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->get( $id )['pub_state'] );
	}

	public function test_REQ_084_approve_needs_the_whole_checklist_and_then_works(): void {
		$id       = $this->imported( $this->user() );
		$reviewer = $this->reviewer();
		try {
			( new ReviewItemScreen() )->approve( $id, $reviewer, 4 );
			$this->fail( 'expected a refusal' );
		} catch ( StateException $e ) {
			$this->assertStringContainsString( 'checklist', $e->getMessage() );
		}
		( new ReviewItemScreen() )->approve( $id, $reviewer, 5 );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->get( $id )['pub_state'] );
	}

	public function test_REQ_143_unsourced_fact_cannot_be_published_from_review(): void {
		global $wpdb;
		$id = $this->imported( $this->user() );
		$wpdb->delete(
			ProvenanceRepository::table(),
			array(
				'profile_id' => $id,
				'field'      => 'regulator',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		try {
			( new ReviewItemScreen() )->approve( $id, $this->reviewer(), 5 );
			$this->fail( 'expected a refusal' );
		} catch ( StateException $e ) {
			$this->assertStringContainsString( 'no source', $e->getMessage() );
		}
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->get( $id )['pub_state'] );
	}

	public function test_REQ_142_low_confidence_fact_cannot_be_published_from_review(): void {
		global $wpdb;
		$id = $this->imported( $this->user() );
		$wpdb->update(
			ProvenanceRepository::table(),
			array( 'confidence' => 'low' ),
			array(
				'profile_id' => $id,
				'field'      => 'name',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->expectException( StateException::class );
		( new ReviewItemScreen() )->approve( $id, $this->reviewer(), 5 );
	}

	public function test_REQ_141_flagged_record_blocked_until_cleared(): void {
		$id       = $this->imported( $this->user(), 'Contact the founder on +234 803 555 0142 about listing.' );
		$reviewer = $this->reviewer();
		wp_set_current_user( $reviewer );
		$this->assertStringContainsString( 'cannot be approved until the block is cleared', ( new ReviewItemScreen() )->html( new Request( array( 'profile_id' => (string) $id ) ) ) );
		try {
			( new ReviewItemScreen() )->approve( $id, $reviewer, 5 );
			$this->fail( 'expected a refusal' );
		} catch ( StateException $e ) {
			$this->assertStringContainsString( 'flagged', $e->getMessage() );
		}
		( new ScanBlock() )->clear( $id, $reviewer, 'The number is the registry office line.' );
		( new ReviewItemScreen() )->approve( $id, $reviewer, 5 );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->get( $id )['pub_state'] );
	}

	public function test_REQ_084_reject_passes_the_reason_code_to_the_decision_log(): void {
		$id       = $this->imported( $this->user() );
		$reviewer = $this->reviewer();
		( new ReviewService() )->reject( $id, 'Cannot be verified.', $reviewer, 'unverifiable' );
		$this->assertSame( 'unverifiable', ( new DecisionLog() )->recent( 1 )[0]['reason_code'] );
	}

	public function test_REQ_084_the_screen_refuses_without_the_capability(): void {
		wp_set_current_user( $this->user() );
		$this->expectException( \DomainException::class );
		( new ReviewItemScreen() )->html( new Request( array() ) );
	}
}
