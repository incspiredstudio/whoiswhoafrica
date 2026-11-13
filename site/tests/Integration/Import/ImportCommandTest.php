<?php
/**
 * The import command line (REQ-237): a named user holding wiw_run_imports, bundles only from the private import directory, and the
 * same result codes as the screen's dry run and apply. Needs the WordPress test suite. Plain TestCase: it needs the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\Cli\ImportCommand;
use WhoIsWho\Core\Import\Cli\PrivateImportDir;
use WhoIsWho\Core\Import\ContentHasher;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class ImportCommandTest extends TestCase {

	/**
	 * The private import directory of the test, outside the web root.
	 *
	 * @var string
	 */
	private string $root = '';

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	/**
	 * Batch id used by the bundle of this test.
	 *
	 * @var string
	 */
	private string $batch = '';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		if ( ! taxonomy_exists( Taxonomies::ORG_TYPE ) ) {
			( new Taxonomies() )->register();
		}
		if ( ! term_exists( 'commercial-bank', Taxonomies::ORG_TYPE ) ) {
			wp_insert_term( 'Commercial Bank', Taxonomies::ORG_TYPE, array( 'slug' => 'commercial-bank' ) );
		}
		wp_set_current_user( 0 );
		$this->root  = sys_get_temp_dir() . '/wiw-cli-import-' . bin2hex( random_bytes( 4 ) );
		$this->batch = 'B-cli-' . bin2hex( random_bytes( 3 ) );
		mkdir( $this->root, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		$this->bundle( 'bundle-one' );
	}

	protected function tearDown(): void {
		global $wpdb;
		$ids = (array) $wpdb->get_col( 'SELECT profile_id FROM ' . RecordMap::table() . " WHERE record_id = 'rec_cli_1'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->query( 'DELETE FROM ' . RecordMap::table() . " WHERE record_id = 'rec_cli_1'" ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->delete( BatchRepository::table(), array( 'batch_id' => $this->batch ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( ResultRepository::table(), array( 'batch_id' => $this->batch ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->users = array();
		foreach ( glob( $this->root . '/*/*' ) ?: array() as $file ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		foreach ( glob( $this->root . '/*' ) ?: array() as $dir ) {
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		rmdir( $this->root ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Write a one-record bundle folder inside the private import directory.
	 *
	 * @param string $name Folder name.
	 */
	private function bundle( string $name ): void {
		$record                      = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example.json' ), false );
		$record->record_id           = 'rec_cli_1';
		$record->batch_id            = $this->batch;
		$record->external_ids        = new \stdClass();
		$record->fields->name->value = 'Command Line Qzx';
		$record->content_hash        = ContentHasher::hash_record( $record );
		$body                        = (string) wp_json_encode( $record ) . "\n";
		$dir                         = $this->root . '/' . $name;
		mkdir( $dir, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		file_put_contents( $dir . '/records.jsonl', $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$dir . '/manifest.json',
			(string) wp_json_encode(
				array(
					'contract_version'        => '1.0',
					'run_id'                  => 'run_cli',
					'batch_id'                => $this->batch,
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
	}

	private function user( string $role ): string {
		$login = $role . wp_generate_password( 6, false );
		$id    = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $login;
	}

	/**
	 * Run the command.
	 *
	 * @param string[] $args Arguments.
	 * @return array{0: int, 1: string[]}
	 */
	private function cli( array $args ): array {
		$output = new Output( false );
		$code   = ( new ImportCommand( new PrivateImportDir( $this->root ) ) )->run( $args, $output );
		wp_set_current_user( 0 );
		return array( $code, $output->lines() );
	}

	public function test_REQ_237_cli_requires_named_user_with_capability(): void {
		list( $code, $lines ) = $this->cli( array( 'dry-run', '--bundle=bundle-one' ) );
		$this->assertSame( 2, $code, 'no user' );
		$this->assertStringContainsString( 'Refused', $lines[0] );

		list( $code ) = $this->cli( array( 'dry-run', '--bundle=bundle-one', '--user=nobody_here' ) );
		$this->assertSame( 2, $code, 'unknown user' );

		list( $code ) = $this->cli( array( 'dry-run', '--bundle=bundle-one', '--user=' . $this->user( 'subscriber' ) ) );
		$this->assertSame( 2, $code, 'a user without wiw_run_imports' );
		$this->assertFalse( ( new BatchRepository() )->exists( $this->batch ), 'nothing was registered' );

		list( $code ) = $this->cli( array( 'dry-run', '--bundle=bundle-one', '--user=' . $this->user( 'administrator' ) ) );
		$this->assertSame( 0, $code );
	}

	public function test_REQ_237_cli_reads_only_from_the_private_import_directory(): void {
		$admin = $this->user( 'administrator' );
		foreach ( array( '../bundle-one', $this->root . '/bundle-one', 'bundle-one/..', '', 'missing' ) as $name ) {
			list( $code, $lines ) = $this->cli( array( 'dry-run', '--bundle=' . $name, '--user=' . $admin ) );
			$this->assertSame( 1, $code, "refused: {$name}" );
			$this->assertStringContainsString( 'Refused', $lines[0] );
		}
		$this->assertFalse( ( new BatchRepository() )->exists( $this->batch ) );
	}

	public function test_REQ_237_cli_matches_screen_results(): void {
		global $wpdb;
		$admin = $this->user( 'administrator' );

		// What the screen's preview service reports for the same bundle (a copy registered under another batch id is not needed:
		// the preview is a pure function of the bundle and the suppression list).
		list( $code, $lines ) = $this->cli( array( 'dry-run', '--bundle=bundle-one', '--user=' . $admin ) );
		$this->assertSame( 0, $code );
		$this->assertContains( 'Batch: ' . $this->batch, $lines );
		$screen = ( new DryRun( new Suppression( AuditLogger::for_site() ), new BatchRepository() ) )->run( BundleReader::open( $this->root . '/bundle-one' ), $this->batch );
		$this->assertSame( array( 'CREATED' => 1 ), $screen['counts'] );
		$this->assertContains( 'CREATED: 1', $lines );

		list( $code, $lines ) = $this->cli( array( 'apply', '--batch=' . $this->batch, '--user=' . $admin ) );
		$this->assertSame( 0, $code );
		$this->assertContains( 'CREATED: 1', $lines );
		$this->assertSame( array( 'CREATED' => 1 ), ( new ResultRepository() )->counts( $this->batch ) );
		$this->assertSame( 'applied', ( new BatchRepository() )->get( $this->batch )['status'] );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'import.apply' AND subject_id = %s", $this->batch ) ) ); // phpcs:ignore WordPress.DB

		list( $code, $lines ) = $this->cli( array( 'status', '--batch=' . $this->batch, '--user=' . $admin ) );
		$this->assertSame( 0, $code );
		$this->assertContains( 'status: applied', $lines );

		list( $code, $lines ) = $this->cli( array( 'undo', '--batch=' . $this->batch, '--user=' . $admin ) );
		$this->assertSame( 0, $code );
		$this->assertContains( 'removed: 1', $lines );
	}

	public function test_REQ_237_apply_needs_a_previewed_batch(): void {
		list( $code, $lines ) = $this->cli( array( 'apply', '--batch=' . $this->batch, '--user=' . $this->user( 'administrator' ) ) );
		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'dry run first', $lines[0] );
	}
}
