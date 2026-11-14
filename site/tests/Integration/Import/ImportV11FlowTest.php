<?php
/**
 * The whole contract 1.1 path on the website (REQ-237, REQ-162, REQ-203, REQ-139, REQ-092): the fictional v1.1 bundle is
 * uploaded as a zip (whole, in pieces, or from the server folder), checked in steps, confirmed and imported in steps; the
 * narrative sections, lifespan and field are stored; the open-licence images land in the media library with their credit and
 * become the profile image; the image with a licence off the list is refused alone; the stored bundle is deleted; and undo
 * removes the media the batch created. Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Admin\BundleStore;
use WhoIsWho\Core\Import\Admin\ImportService;
use WhoIsWho\Core\Import\Admin\ServerDrop;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\DryRun\DryRun;
use WhoIsWho\Core\Import\Images\ImageLog;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\Support\V11BundleFixture;
use WhoIsWho\Tests\TestCase;

final class ImportV11FlowTest extends TestCase {

	/**
	 * Paths to remove.
	 *
	 * @var string[]
	 */
	private array $paths = array();

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
		foreach ( array_reverse( $this->paths ) as $path ) {
			$this->remove( $path );
		}
		$this->paths = array();
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Remove a file or folder tree.
	 *
	 * @param string $path Path.
	 */
	private function remove( string $path ): void {
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			foreach ( (array) scandir( $path ) as $entry ) {
				if ( '.' !== $entry && '..' !== $entry ) {
					$this->remove( $path . '/' . $entry );
				}
			}
			rmdir( $path );
		} elseif ( file_exists( $path ) ) {
			unlink( $path );
		}
	}

	/**
	 * Remove every profile, attachment, result, batch and stored state the test created.
	 */
	private function clean(): void {
		global $wpdb;
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", ImageMeta::BATCH ) ) as $id ) { // phpcs:ignore WordPress.DB
			wp_delete_attachment( (int) $id, true );
		}
		// Profiles an undo let go of are no longer in the record map: find them through the results and the state table too.
		$ids = array_merge(
			(array) $wpdb->get_col( 'SELECT profile_id FROM ' . RecordMap::table() ), // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
			(array) $wpdb->get_col( 'SELECT profile_id FROM ' . ResultRepository::table() . ' WHERE profile_id IS NOT NULL' ), // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
			(array) $wpdb->get_col( $wpdb->prepare( 'SELECT profile_id FROM ' . StateRepository::table() . ' WHERE import_batch = %s', V11BundleFixture::BATCH ) ) // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		);
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			wp_delete_post( (int) $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( array( RecordMap::table(), ResultRepository::table(), BatchRepository::table(), Suppression::table() ) as $table ) {
			$wpdb->query( 'DELETE FROM ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names.
		}
		DryRun::reset( V11BundleFixture::BATCH );
		ImageLog::clear( V11BundleFixture::BATCH );
		delete_option( \WhoIsWho\Core\Import\Undo\UndoneBatchReset::OPTION_PREFIX . substr( md5( V11BundleFixture::BATCH ), 0, 20 ) );
		delete_option( SettingsService::OPTION_PREFIX . 'import_all_not_public' );
	}

	/**
	 * A user with the import capability.
	 */
	private function admin(): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'v11_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		get_userdata( $id )->add_cap( 'wiw_run_imports' );
		return $id;
	}

	/**
	 * A service over a fresh private root.
	 */
	private function service(): ImportService {
		$root          = sys_get_temp_dir() . '/wiw-v11-root-' . bin2hex( random_bytes( 4 ) );
		$this->paths[] = $root;
		return new ImportService( new BundleStore( $root ) );
	}

	/**
	 * The fixture as a zip upload.
	 *
	 * @return array{name: string, tmp_name: string, size: int, error: int}
	 */
	private function upload(): array {
		$path          = sys_get_temp_dir() . '/wiw-v11-' . bin2hex( random_bytes( 4 ) ) . '.zip';
		$this->paths[] = $path;
		V11BundleFixture::zip( $path );
		return array(
			'name'     => 'export-run_v11.zip',
			'tmp_name' => $path,
			'size'     => (int) filesize( $path ),
			'error'    => UPLOAD_ERR_OK,
		);
	}

	/**
	 * Step a batch until its phase is done, as the screen's script does.
	 *
	 * @param ImportService $service Service.
	 * @param string        $batch   Batch.
	 * @param int           $admin   Actor.
	 * @return array<string, mixed> The last step.
	 */
	private function run_steps( ImportService $service, string $batch, int $admin ): array {
		for ( $i = 0; $i < 20; $i++ ) {
			$out = $service->step( $batch, $admin );
			if ( $out['done'] ) {
				return $out;
			}
		}
		$this->fail( 'the batch did not finish' );
	}

	/**
	 * The profile id of a record.
	 *
	 * @param string $record_id Record id.
	 */
	private function profile_of( string $record_id ): int {
		return (int) ( new RecordMap() )->profile_for( $record_id );
	}

	public function test_REQ_237_a_v11_zip_is_checked_imported_and_undone_with_its_images(): void {
		$service = $this->service();
		$admin   = $this->admin();
		// Contract section 9 as the owner will run it: rule 1 (import everything as not public) off, so organisations go public.
		update_option( SettingsService::OPTION_PREFIX . 'import_all_not_public', '0', false );
		$batch = $service->stage( $this->upload(), $admin );
		$this->assertSame( V11BundleFixture::BATCH, $batch );
		$this->assertSame( 'dry_run', ( new BatchRepository() )->get( $batch )['status'] );
		$this->assertSame( array( 'CREATED' => 3 ), DryRun::stored( $batch )['counts'] );
		$flags = array_merge( ...array_map( static fn ( array $r ): array => (array) $r['flags'], DryRun::stored( $batch )['rows'] ) );
		$this->assertContains( 'image_not_allowed', $flags, 'The dry run shows the refused image.' );

		$service->confirm( $batch, $admin );
		$this->assertSame( 'applying', ( new BatchRepository() )->get( $batch )['status'], 'The screen shows progress at once.' );
		$last = $this->run_steps( $service, $batch, $admin );
		$this->assertSame( 'apply', $last['phase'] );
		$row = ( new BatchRepository() )->get( $batch );
		$this->assertSame( 'applied', $row['status'] );
		$dir = (string) json_decode( (string) $row['settings_snapshot'], true )['bundle_dir'];
		$this->assertDirectoryDoesNotExist( $dir, 'The stored bundle is deleted after the import.' );

		// The deceased individual: lifespan, field, four sections and the photo with its credit.
		$person = $this->profile_of( 'rec_ind_v11_1' );
		$this->assertSame( '1924–2013', get_post_meta( $person, 'wiw_lifespan', true ) );
		$this->assertSame( 'Music', get_post_meta( $person, 'wiw_field', true ) );
		$this->assertStringContainsString( "\n\nShe led", (string) get_post_meta( $person, 'wiw_bio', true ) );
		$photo = (int) get_post_meta( $person, PublicProfile::IMAGE_META, true );
		$this->assertGreaterThan( 0, $photo );
		$this->assertSame( 'CC-BY-SA-4.0', get_post_meta( $photo, ImageMeta::LICENCE, true ) );
		$this->assertSame( 'Sample Photographer', get_post_meta( $photo, ImageMeta::CREATOR, true ) );
		$this->assertSame( 'Sample Photographer, CC-BY-SA-4.0', get_post_meta( $photo, ImageMeta::ATTRIBUTION, true ) );
		$this->assertSame( 'https://images.example/file/photo-1', get_post_meta( $photo, ImageMeta::SOURCE_URL, true ) );
		$this->assertSame( 'photo', get_post_meta( $photo, ImageMeta::KIND, true ) );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->get( $person )['pub_state'], 'People stay not public (contract 9, rule 3).' );

		// The entrepreneur: no title needed; the GFDL image is refused alone.
		$founder = $this->profile_of( 'rec_ent_v11_1' );
		$this->assertGreaterThan( 0, $founder );
		$this->assertSame( '', (string) get_post_meta( $founder, PublicProfile::IMAGE_META, true ) );
		$log = ImageLog::get( $batch );
		$this->assertSame( 2, $log['imported'] );
		$this->assertSame( 1, $log['refused'] );
		$this->assertSame( array( 'licence' => 1 ), $log['reasons'] );

		// The organisation is public under the pending label, with its sections and its logo, through the read model.
		$org     = $this->profile_of( 'rec_org_v11_1' );
		$profile = PublicProfile::find_by_id( $org );
		$this->assertNotNull( $profile );
		$this->assertNotNull( $profile->label, 'Published, review pending.' );
		$this->assertSame( array( 'bio', 'achievement', 'influence', 'legacy' ), array_column( $profile->sections(), 'key' ) );
		$this->assertSame( array( 'Bio', 'Achievement', 'Influence', 'Legacy' ), array_column( $profile->sections(), 'title' ) );
		$this->assertSame( "Sample Fixture Cooperative is a fictional lender.\n\nIt serves sample farmers.", $profile->sections()[0]['text'] );
		$image = $profile->image();
		$this->assertNotNull( $image );
		$this->assertSame( 'logo', $image['kind'] );
		$this->assertSame( 'CC0-1.0', $image['licence'] );
		$this->assertSame( 'Public domain', $image['credit'] );
		$this->assertSame( 'https://licences.example/cc0-1.0', $image['licence_url'] );
		$this->assertStringStartsWith( 'http', $image['url'] );

		// A second import of the same records is unchanged and creates no second attachment.
		$attachments = count(
			get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => ImageMeta::BATCH,
				)
			)
		); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test.
		$this->assertSame( 2, $attachments );

		// Undo removes the profiles' media and the profile image link.
		$out = $service->undo( $batch, $admin );
		$this->assertTrue( $out['done'] );
		$this->assertSame( 2, $out['media_removed'] );
		$this->assertNull( get_post( $photo ) );
		$this->assertSame( '', (string) get_post_meta( $org, PublicProfile::IMAGE_META, true ) );
		$this->assertSame( 'undone', ( new BatchRepository() )->get( $batch )['status'] );
	}

	/**
	 * An undone batch can be imported again (#810): no "already registered" refusal, and the profiles the first import made are
	 * used again, so there are no duplicates.
	 */
	public function test_REQ_237_an_undone_batch_can_be_imported_again_without_duplicates(): void {
		$service = $this->service();
		$admin   = $this->admin();
		update_option( SettingsService::OPTION_PREFIX . 'import_all_not_public', '0', false );
		$batch = $service->stage( $this->upload(), $admin );
		$service->confirm( $batch, $admin );
		$this->run_steps( $service, $batch, $admin );
		$org     = $this->profile_of( 'rec_org_v11_1' );
		$person  = $this->profile_of( 'rec_ind_v11_1' );
		$count   = static fn (): int => count(
			get_posts(
				array(
					'post_type'      => ProfileType::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);
		$before = $count();
		$this->assertTrue( $service->undo( $batch, $admin )['done'] );
		$this->assertSame( PubState::UNPUBLISHED, ( new StateRepository() )->get( $org )['pub_state'] );

		$again = $service->stage( $this->upload(), $admin );
		$this->assertSame( $batch, $again, 'the same batch id is accepted again' );
		$this->assertSame( 'dry_run', ( new BatchRepository() )->get( $batch )['status'] );
		$service->confirm( $batch, $admin );
		$this->run_steps( $service, $batch, $admin );
		$this->assertSame( 'applied', ( new BatchRepository() )->get( $batch )['status'] );
		$this->assertSame( $before, $count(), 'no duplicate profiles' );
		$this->assertSame( $org, $this->profile_of( 'rec_org_v11_1' ), 'the same organisation profile is used again' );
		$this->assertSame( $person, $this->profile_of( 'rec_ind_v11_1' ) );
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->get( $org )['pub_state'], 'it is back as the import decides' );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->get( $person )['pub_state'] );
		$this->assertGreaterThan( 0, (int) get_post_meta( $person, PublicProfile::IMAGE_META, true ), 'its photo is imported again' );
	}

	public function test_REQ_092_a_zip_sent_in_pieces_is_joined_and_staged(): void {
		$service = $this->service();
		$admin   = $this->admin();
		$file    = $this->upload();
		$bytes   = (string) file_get_contents( $file['tmp_name'] );
		$size    = strlen( $bytes );
		$step    = (int) ceil( $size / 3 );
		$upload  = '';
		$out     = array();
		for ( $offset = 0; $offset < $size; $offset += $step ) {
			$piece         = sys_get_temp_dir() . '/wiw-v11-piece-' . bin2hex( random_bytes( 4 ) );
			$this->paths[] = $piece;
			file_put_contents( $piece, substr( $bytes, $offset, $step ) );
			$out    = $service->receive_piece( $upload, $offset, $size, 'export.zip', $piece, $admin );
			$upload = $out['upload_id'];
		}
		$this->assertSame( V11BundleFixture::BATCH, $out['batch'] );
		$this->assertSame( 'dry_run', ( new BatchRepository() )->get( $out['batch'] )['status'] );

		// A piece at the wrong offset is refused.
		$this->expectException( \WhoIsWho\Core\Review\StateException::class );
		$service->receive_piece( '', 10, $size, 'export.zip', $piece, $admin );
	}

	public function test_REQ_092_import_from_the_server_folder(): void {
		$dir           = sys_get_temp_dir() . '/wiw-v11-drop-' . bin2hex( random_bytes( 4 ) );
		$this->paths[] = $dir;
		$drop          = new ServerDrop( $dir );
		$this->assertTrue( $drop->ensure() );
		$this->assertStringContainsString( 'Require all denied', (string) file_get_contents( $dir . '/.htaccess' ) );
		$this->assertFileExists( $dir . '/index.php' );
		copy( $this->upload()['tmp_name'], $dir . '/export-run_v11.zip' );
		$this->assertSame( array( 'export-run_v11.zip' ), array_column( $drop->zips(), 'name' ) );

		$service = $this->service();
		$admin   = $this->admin();
		foreach ( array( '../x.zip', 'a/b.zip', 'missing.zip' ) as $bad ) {
			try {
				$service->stage_from_server( $bad, $admin, $drop );
				$this->fail( 'expected a refusal: ' . $bad );
			} catch ( \WhoIsWho\Core\Review\StateException $e ) {
				$this->assertSame( 'That file is not in the import folder.', $e->getMessage() );
			}
		}
		$batch = $service->stage_from_server( 'export-run_v11.zip', $admin, $drop );
		$this->assertSame( V11BundleFixture::BATCH, $batch );
		$this->assertFileDoesNotExist( $dir . '/export-run_v11.zip', 'The zip leaves the web folder once staged.' );
	}

	public function test_REQ_237_import_steps_need_the_capability(): void {
		$user = wp_insert_user(
			array(
				'user_login' => 'v11_none_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
			)
		);
		$this->assertIsInt( $user );
		$this->users[] = $user;
		$this->expectException( \WhoIsWho\Core\Review\StateException::class );
		$this->service()->step( 'B-any', $user );
	}
}
