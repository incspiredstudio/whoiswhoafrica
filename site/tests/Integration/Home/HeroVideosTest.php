<?php
/**
 * The four home hero videos an editor can replace (issue 799): REQ-001, REQ-041, REQ-052, REQ-257. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Home;

use WhoIsWho\Core\Content\Curation\Admin\CurationScreen;
use WhoIsWho\Core\Content\Curation\Admin\HeroVideosSection;
use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Content\Home\HeroVideos;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\WpTestCase;

final class HeroVideosTest extends WpTestCase {

	private const PROBE = 'wiw_hero_videos_audit_probe';

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		// The purge log and the profile tables the screen's other blocks read are real tables in the CI database.
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		delete_option( HeroVideos::OPTION );
	}

	protected function tearDown(): void {
		delete_option( HeroVideos::OPTION );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function service_as( string $role ): HeroVideos {
		$user = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user );
		return new HeroVideos( new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-13 10:00:00', static fn (): int => $user ) );
	}

	/**
	 * A media library file with the given bytes.
	 *
	 * @param string $name  File name.
	 * @param string $bytes Content.
	 * @param string $mime  Attachment type.
	 * @param int    $w     Width in the metadata.
	 * @param int    $h     Height in the metadata.
	 */
	private function attach( string $name, string $bytes, string $mime, int $w = 0, int $h = 0 ): int {
		$dir  = wp_upload_dir();
		$path = $dir['path'] . '/' . wp_unique_filename( $dir['path'], $name );
		file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => $name,
				'post_status'    => 'inherit',
			),
			$path
		);
		$this->assertIsInt( $id );
		if ( $w > 0 ) {
			wp_update_attachment_metadata(
				$id,
				array(
					'width'  => $w,
					'height' => $h,
					'file'   => _wp_relative_upload_path( $path ),
					'sizes'  => array(),
				)
			);
		}
		return $id;
	}

	private function mp4( string $name = 'clip.mp4', int $pad = 0, int $w = 540, int $h = 960 ): int {
		return $this->attach( $name, "\0\0\0\x18ftypmp42" . str_repeat( 'x', $pad ), 'video/mp4', $w, $h );
	}

	private function webm( string $name = 'clip.webm' ): int {
		$ebml = "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm" . str_repeat( "\0", 64 );
		return $this->attach( $name, $ebml, 'video/webm', 540, 960 );
	}

	private function poster( string $name = 'poster.jpg', int $w = 540, int $h = 960 ): int {
		$image = imagecreatetruecolor( $w, $h );
		$this->assertNotFalse( $image );
		ob_start();
		imagejpeg( $image );
		return $this->attach( $name, (string) ob_get_clean(), 'image/jpeg', $w, $h );
	}

	private function rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE, ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	public function test_REQ_001_a_valid_clip_is_saved_and_audited(): void {
		$service  = $this->service_as( 'wiw_editor' );
		$ids      = array(
			'video'  => $this->mp4(),
			'webm'   => $this->webm(),
			'poster' => $this->poster(),
		);
		$warnings = $service->save_slot( 2, $ids, 'Spring campaign' );
		$this->assertSame( array(), $warnings );
		$this->assertSame( $ids, $service->get()[2] );
		$this->assertSame(
			array(
				'video'  => 0,
				'webm'   => 0,
				'poster' => 0,
			),
			$service->get()[1]
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$rows = $this->rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'home.hero_video.change', $rows[0]['action'] );
		$this->assertSame( 'home_hero_slot', $rows[0]['subject_type'] );
		$this->assertSame( '2', $rows[0]['subject_id'] );
		$this->assertSame( 'Spring campaign', $rows[0]['reason'] );
		$this->assertStringContainsString( '"video":' . $ids['video'], (string) $rows[0]['new_value'] );
		$this->assertNotSame( '0', (string) $rows[0]['actor_id'] );
	}

	public function test_REQ_257_saving_needs_wiw_curate_home(): void {
		$service = $this->service_as( 'wiw_reviewer' );
		$this->assertFalse( current_user_can( HeroVideos::CAP ) );
		$ids = array(
			'video'  => $this->mp4(),
			'webm'   => 0,
			'poster' => $this->poster(),
		);
		try {
			$service->save_slot( 1, $ids );
			$this->fail( 'A reviewer must not save.' );
		} catch ( \DomainException $refusal ) {
			$this->assertStringContainsString( 'Not allowed', $refusal->getMessage() );
		}
		$this->assertFalse( get_option( HeroVideos::OPTION, false ) );
		$this->assertSame( array(), $this->rows() );
		$msg = ( new HeroVideosSection( $service ) )->handle(
			new Request(
				array(
					'slot'      => '1',
					'video_id'  => (string) $ids['video'],
					'poster_id' => (string) $ids['poster'],
					'op'        => 'save',
				)
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertStringContainsString( 'Not allowed', $msg );
	}

	public function test_REQ_001_a_file_that_is_not_an_mp4_is_refused_and_nothing_changes(): void {
		$service = $this->service_as( 'wiw_editor' );
		$poster  = $this->poster();
		// An image offered as the video.
		$bad = array(
			'video'  => $this->poster( 'not-a-video.jpg' ),
			'webm'   => 0,
			'poster' => $poster,
		);
		$this->expectException( \InvalidArgumentException::class );
		try {
			$service->save_slot( 1, $bad );
		} finally {
			$this->assertFalse( get_option( HeroVideos::OPTION, false ) );
			$this->assertSame( array(), $this->rows() );
		}
	}

	public function test_REQ_001_a_file_named_mp4_with_other_content_is_refused(): void {
		$service = $this->service_as( 'wiw_editor' );
		$fake    = $this->attach( 'fake.mp4', 'This is plain text, not a video at all.', 'video/mp4', 540, 960 );
		$result  = $service->check(
			array(
				'video'  => $fake,
				'poster' => $this->poster(),
			)
		);
		$this->assertNotSame( array(), $result['errors'] );
		$this->assertStringContainsString( 'must be a video/mp4 file', implode( ' ', $result['errors'] ) );
		// A real MP4 container under a .webm name is also refused.
		$result = $service->check(
			array(
				'video'  => $this->mp4(),
				'webm'   => $this->attach( 'wrong.webm', "\0\0\0\x18ftypmp42", 'video/webm', 540, 960 ),
				'poster' => $this->poster( 'p2.jpg' ),
			)
		);
		$this->assertNotSame( array(), $result['errors'] );
	}

	public function test_REQ_041_a_clip_over_the_block_size_is_refused_and_one_over_the_warning_size_saves_with_a_warning(): void {
		$service = $this->service_as( 'wiw_editor' );
		$poster  = $this->poster();
		$huge    = $this->mp4( 'huge.mp4', HeroVideos::MAX_BYTES + 10 );
		$refused = $service->check(
			array(
				'video'  => $huge,
				'poster' => $poster,
			)
		);
		$this->assertStringContainsString( 'over the 6.0 MB limit', implode( ' ', $refused['errors'] ) );
		$big      = $this->mp4( 'big.mp4', HeroVideos::WARN_BYTES + 10 );
		$warnings = $service->save_slot(
			3,
			array(
				'video'  => $big,
				'poster' => $poster,
			)
		);
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'aim for 2.0 MB', $warnings[0] );
		$this->assertSame( $big, $service->get()[3]['video'] );
	}

	public function test_REQ_041_the_wrong_shape_warns_but_does_not_block(): void {
		$service  = $this->service_as( 'wiw_editor' );
		$warnings = $service->save_slot(
			1,
			array(
				'video'  => $this->mp4( 'wide.mp4', 0, 1920, 1080 ),
				'poster' => $this->poster( 'wide.jpg', 800, 800 ),
			)
		);
		$this->assertCount( 2, $warnings );
		$this->assertStringContainsString( '1920 x 1080', $warnings[0] );
		$this->assertStringContainsString( '800 x 800', $warnings[1] );
		$near = $service->check(
			array(
				'video'  => $this->mp4( 'near.mp4', 0, 500, 900 ),
				'poster' => $this->poster( 'near.jpg', 560, 1000 ),
			)
		);
		$this->assertSame( array(), $near['warnings'], 'A small difference is inside the tolerance.' );
	}

	public function test_REQ_001_a_video_needs_a_poster_and_a_poster_must_be_an_image(): void {
		$service = $this->service_as( 'wiw_editor' );
		$video   = $this->mp4();
		$none    = $service->check( array( 'video' => $video ) );
		$this->assertStringContainsString( 'Choose a poster', implode( ' ', $none['errors'] ) );
		$wrong = $service->check(
			array(
				'video'  => $video,
				'poster' => $this->webm( 'poster-as-video.webm' ),
			)
		);
		$this->assertStringContainsString( 'must be an image', implode( ' ', $wrong['errors'] ) );
		$webm_only = $service->check(
			array(
				'webm'   => $this->webm(),
				'poster' => $this->poster(),
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertStringContainsString( 'Choose an MP4', implode( ' ', $webm_only['errors'] ) );
		$missing = $service->check(
			array(
				'video'  => 987654,
				'poster' => $this->poster(),
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertStringContainsString( 'not in the media library', implode( ' ', $missing['errors'] ) );
	}

	public function test_REQ_001_reset_puts_the_slot_back_to_the_bundled_clip_with_an_audit_row(): void {
		$service = $this->service_as( 'wiw_editor' );
		$service->save_slot(
			4,
			array(
				'video'  => $this->mp4(),
				'poster' => $this->poster(),
			)
		);
		$service->reset_slot( 4, 'Campaign over' );
		$this->assertSame( 0, $service->get()[4]['video'] );
		$this->assertArrayNotHasKey( 4, $service->resolved() );
		$rows = $this->rows();
		$this->assertCount( 2, $rows );
		$this->assertSame( 'home.hero_video.change', $rows[1]['action'] );
		$this->assertSame( 'Campaign over', $rows[1]['reason'] );
		// Resetting an untouched slot writes nothing.
		$service->reset_slot( 1 );
		$this->assertCount( 2, $this->rows() );
		$this->expectException( \InvalidArgumentException::class );
		$service->reset_slot( 5 );
	}

	public function test_REQ_041_a_removed_file_makes_the_slot_fall_back(): void {
		$service = $this->service_as( 'wiw_editor' );
		$video   = $this->mp4();
		$service->save_slot(
			2,
			array(
				'video'  => $video,
				'poster' => $this->poster(),
			)
		);
		$resolved = $service->resolved();
		$this->assertSame( wp_get_attachment_url( $video ), $resolved[2]['video'] );
		$this->assertSame( 540, $resolved[2]['width'] );
		$this->assertSame( 960, $resolved[2]['height'] );
		$this->assertNotSame( '', $resolved[2]['poster'] );
		wp_delete_attachment( $video, true );
		$this->assertArrayNotHasKey( 2, $service->resolved(), 'The slot is empty again, so the theme shows the bundled clip.' );
	}

	public function test_REQ_041_the_weight_counts_the_chosen_clip_or_the_bundled_one(): void {
		$service = $this->service_as( 'wiw_editor' );
		$bundle  = get_theme_root() . '/whoiswho-child/assets/home';
		$before  = $service->weights( $bundle );
		$this->assertGreaterThan( 0, $before['total'] );
		$this->assertFalse( $before['slots'][1]['custom'] );
		$service->save_slot(
			1,
			array(
				'video'  => $this->mp4( 'tiny.mp4', 100 ),
				'poster' => $this->poster(),
			)
		);
		$after = $service->weights( $bundle );
		$this->assertTrue( $after['slots'][1]['custom'] );
		$this->assertLessThan( $before['total'], $after['total'] );
	}

	public function test_REQ_257_the_screen_shows_four_slots_with_help_and_a_reset_button(): void {
		$user = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		wp_set_current_user( $user );
		$audit  = new AuditLogger( new WpdbConnection( self::PROBE ) );
		$screen = new CurationScreen( new CurationSettings( $audit ), null, new HeroVideos( $audit ) );
		$html   = $screen->html();
		$this->assertSame( 4, substr_count( $html, 'class="wiw-hero-slot"' ) );
		$this->assertSame( 4, substr_count( $html, 'Reset to bundled' ) );
		$this->assertStringContainsString( 'optimise-hero-video.sh', $html );
		$this->assertStringContainsString( 'Total weight of the four MP4 clips', $html );
		$this->assertStringContainsString( 'Bundled clip in use', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
		$this->assertStringContainsString( 'value="wiw_save_hero_video"', $html );
	}

	public function test_REQ_257_a_save_through_the_form_handler_reports_warnings_and_audits(): void {
		$service = $this->service_as( 'wiw_editor' );
		$section = new HeroVideosSection( $service );
		$message = $section->handle(
			new Request(
				array(
					'slot'      => '3',
					'op'        => 'save',
					'video_id'  => (string) $this->mp4( 'a.mp4', HeroVideos::WARN_BYTES + 5 ),
					'poster_id' => (string) $this->poster(),
				)
			)
		);
		$this->assertStringStartsWith( 'Saved slot 3.', $message );
		$this->assertStringContainsString( 'Warning:', $message );
		$this->assertCount( 1, $this->rows() );
		$refused = $section->handle(
			new Request(
				array(
					'slot'     => '3',
					'op'       => 'save',
					'video_id' => '999999',
				)
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertStringContainsString( 'not in the media library', $refused );
		$this->assertStringContainsString(
			'bundled',
			$section->handle(
				new Request(
					array(
						'slot' => '3',
						'op'   => 'reset',
					)
				)
			)
		);
		$this->assertSame(
			'Unknown hero video slot.',
			$section->handle(
				new Request(
					array(
						'slot' => '9',
						'op'   => 'reset',
					)
				)
			)
		);
	}
}
