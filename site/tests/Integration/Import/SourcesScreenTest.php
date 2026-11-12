<?php
/**
 * The sources snapshot and the read-only Sources screen (REQ-243, REQ-136, REQ-242). Needs the WordPress test suite and the real
 * tables, so it is a plain TestCase that cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Directory\Admin\ProfilesScreen;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\Sources\SourceRepository;
use WhoIsWho\Core\Import\Sources\SourcesScreen;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class SourcesScreenTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Bundle folders to remove.
	 *
	 * @var string[]
	 */
	private array $dirs = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DELETE FROM ' . SourceRepository::table() . " WHERE source_id LIKE 'src_t%'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->query( 'DELETE FROM ' . SourceRepository::table() . " WHERE source_id LIKE 'src_t%'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DELETE FROM ' . BatchRepository::table() . " WHERE batch_id LIKE 'B-src-%'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'DELETE FROM ' . ResultRepository::table() . " WHERE batch_id LIKE 'B-src-%'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		foreach ( $this->dirs as $dir ) {
			foreach ( glob( $dir . '/*' ) ?: array() as $file ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		$this->posts = array();
		$this->dirs  = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function as_role( string $role ): void {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
			)
		);
		wp_set_current_user( (int) $id );
	}

	/**
	 * A profile of a source in a state.
	 *
	 * @param string $source Source id.
	 * @param string $state  State to end in.
	 */
	private function profile( string $source, string $state = PubState::IMPORTED_NOT_PUBLIC ): void {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Sources test',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		( new StateRepository() )->update( $id, array( 'source' => $source ) );
		if ( PubState::IMPORTED_NOT_PUBLIC !== $state ) {
			global $wpdb;
			$wpdb->update( StateRepository::table(), array( StateRepository::STATE_COLUMN => $state ), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	public function test_REQ_136_manifest_sources_update_snapshot(): void {
		$repo = new SourceRepository();
		$this->assertSame( 2, $repo->record_manifest( array( array( 'source_id' => 'src_t1', 'name' => 'Sample Regulator' ), array( 'source_id' => 'src_t2', 'name' => 'Sample Registry' ), array( 'source_id' => '', 'name' => 'No id' ), 'junk' ), 'B-src-1' ) ); // phpcs:ignore Generic.Arrays.MultipleStatementAlignment, WordPress.Arrays.ArrayDeclarationSpacing
		$this->assertSame(
			1,
			$repo->record_manifest(
				array(
					array(
						'source_id' => 'src_t1',
						'name'      => 'Sample Regulator (renamed)',
					),
				),
				'B-src-2'
			)
		);
		$rows = array_column( $repo->all(), null, 'source_id' );
		$this->assertSame( 'Sample Regulator (renamed)', $rows['src_t1']['label'] );
		$this->assertSame( 'B-src-1', $rows['src_t1']['first_batch_id'] );
		$this->assertSame( 'B-src-2', $rows['src_t1']['last_batch_id'] );
		$this->assertSame( 'B-src-1', $rows['src_t2']['last_batch_id'] );
	}

	public function test_REQ_136_registering_a_batch_feeds_the_snapshot(): void {
		$dir = sys_get_temp_dir() . '/wiw-src-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		$this->dirs[]         = $dir;
		$record               = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example.json' ), false );
		$record->record_id    = 'rec_src_1';
		$record->batch_id     = 'B-src-reg';
		$record->external_ids = new \stdClass();
		$record->content_hash = ContentHasher::hash_record( $record );
		$body                 = (string) wp_json_encode( $record ) . "\n";
		file_put_contents( $dir . '/records.jsonl', $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$dir . '/manifest.json',
			(string) wp_json_encode(
				array(
					'contract_version'        => '1.0',
					'run_id'                  => 'run_src',
					'batch_id'                => 'B-src-reg',
					'generated_at'            => '2026-11-10T09:00:00Z',
					'pipeline_version'        => 'p1',
					'source_list_version'     => 's1',
					'sources'                 => array(
						array(
							'source_id' => 'src_t_reg',
							'name'      => 'Registered Source',
						),
					),
					'suppression_snapshot_id' => 'snap_1',
					'record_count'            => 1,
					'counts'                  => array( 'organisation' => 1 ),
					'records_sha256'          => 'sha256:' . hash( 'sha256', $body ),
				)
			)
		);
		( new BatchRepository() )->register( BundleReader::open( $dir ), 1, array(), 'bundle' );
		$rows = array_column( ( new SourceRepository() )->all(), null, 'source_id' );
		$this->assertSame( 'Registered Source', $rows['src_t_reg']['label'] );
	}

	public function test_REQ_243_sources_screen_shows_counts_and_links_to_filtered_profiles_list(): void {
		( new SourceRepository() )->record_manifest( array( array( 'source_id' => 'src_t_a', 'name' => 'Alpha Source' ) ), 'B-src-a' ); // phpcs:ignore Generic.Arrays.MultipleStatementAlignment, WordPress.Arrays.ArrayDeclarationSpacing
		$this->profile( 'src_t_a' );
		$this->profile( 'src_t_a' );
		$this->profile( 'src_t_a', PubState::UNPUBLISHED );
		( new ResultRepository() )->record( 'B-src-a', 'r1', 'CREATED' );
		( new ResultRepository() )->record( 'B-src-a', 'r2', 'SCHEMA' );
		$this->as_role( 'wiw_reviewer' );

		$rows = array_column( ( new SourcesScreen() )->rows(), null, 'source_id' );
		$this->assertSame( 3, $rows['src_t_a']['profiles'] );
		$this->assertSame( 1, $rows['src_t_a']['removals'] );
		$this->assertSame( 1, $rows['src_t_a']['errors'] );

		$html = ( new SourcesScreen() )->html();
		$this->assertStringContainsString( 'Alpha Source', $html );
		$this->assertStringContainsString( 'page=' . ProfilesScreen::SCREEN, $html );
		$this->assertStringContainsString( 'source=src_t_a', $html );
	}

	public function test_REQ_243_sources_screen_has_no_write_actions(): void {
		( new SourceRepository() )->record_manifest( array( array( 'source_id' => 'src_t_b', 'name' => 'Beta Source' ) ), 'B-src-b' ); // phpcs:ignore Generic.Arrays.MultipleStatementAlignment, WordPress.Arrays.ArrayDeclarationSpacing
		$this->as_role( 'administrator' );
		$html = ( new SourcesScreen() )->html();
		foreach ( array( '<form', '<button', '<input', '<select', '<textarea', 'admin-post.php' ) as $control ) {
			$this->assertStringNotContainsString( $control, $html );
		}
		global $wp_filter;
		foreach ( array_keys( $wp_filter ) as $hook ) {
			$this->assertFalse( 0 === strpos( (string) $hook, 'admin_post_wiw_source' ), "no handler {$hook}" );
		}
	}

	public function test_REQ_243_sources_screen_refuses_without_the_capability(): void {
		$this->as_role( 'subscriber' );
		$this->expectException( \DomainException::class );
		( new SourcesScreen() )->html();
	}
}
