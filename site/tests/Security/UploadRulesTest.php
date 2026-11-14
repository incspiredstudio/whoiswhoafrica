<?php
/**
 * Upload rules (REQ-127, REQ-046). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use WhoIsWho\Guard\Uploads\FileScanner;
use WhoIsWho\Guard\Uploads\Uploads;
use WhoIsWho\Tests\WpTestCase;

final class UploadRulesTest extends WpTestCase {

	/**
	 * Temporary files to remove.
	 *
	 * @var string[]
	 */
	private array $files = array();

	protected function tearDown(): void {
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		parent::tearDown();
	}

	/**
	 * Make a temp file with the given bytes.
	 *
	 * @param string $bytes Content.
	 * @param string $ext   Extension.
	 */
	private function temp( string $bytes, string $ext ): string {
		$path = sys_get_temp_dir() . '/wiw-upload-' . uniqid() . '.' . $ext;
		file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->files[] = $path;
		return $path;
	}

	/**
	 * A small real JPEG, optionally with an EXIF segment that carries a recognisable string.
	 *
	 * @param bool $with_exif Whether to inject metadata.
	 */
	private function jpeg( bool $with_exif ): string {
		$img = imagecreatetruecolor( 40, 30 );
		ob_start();
		imagejpeg( $img );
		$bytes = (string) ob_get_clean();
		if ( $with_exif ) {
			$payload = "Exif\0\0SECRET-GPS-AND-CAMERA-DATA";
			$segment = "\xFF\xE1" . pack( 'n', strlen( $payload ) + 2 ) . $payload;
			$bytes   = substr( $bytes, 0, 2 ) . $segment . substr( $bytes, 2 );
		}
		return $bytes;
	}

	/**
	 * Run the pre-upload filter on a file.
	 *
	 * @param string $path Temp path.
	 * @param string $name Original name.
	 * @param int    $size Reported size.
	 * @return array<string, mixed>
	 */
	private function prefilter( string $path, string $name, int $size ): array {
		return apply_filters(
			'wp_handle_upload_prefilter',
			array(
				'name'     => $name,
				'type'     => 'image/jpeg',
				'tmp_name' => $path,
				'error'    => 0,
				'size'     => $size,
			)
		);
	}

	public function test_REQ_127_disallowed_type_is_refused(): void {
		$pdf    = $this->temp( "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF", 'pdf' );
		$result = $this->prefilter( $pdf, 'doc.pdf', (int) filesize( $pdf ) );
		$this->assertNotSame( 0, $result['error'], 'a PDF is not allowed in the media library' );

		$mimes = apply_filters(
			'upload_mimes',
			array(
				'php' => 'application/x-httpd-php',
				'svg' => 'image/svg+xml',
				'exe' => 'application/x-msdownload',
			)
		);
		$this->assertSame( Uploads::IMAGE_TYPES, $mimes );
		$this->assertArrayNotHasKey( 'php', $mimes );
		$this->assertArrayNotHasKey( 'svg', $mimes );
	}

	public function test_REQ_127_oversized_file_is_refused_with_a_plain_message(): void {
		$jpeg   = $this->temp( $this->jpeg( false ), 'jpg' );
		$result = $this->prefilter( $jpeg, 'big.jpg', Uploads::MAX_BYTES + 1 );
		$this->assertStringContainsString( 'too large', (string) $result['error'] );
		$this->assertSame( 0, $this->prefilter( $jpeg, 'ok.jpg', (int) filesize( $jpeg ) )['error'], 'a normal image passes' );
	}

	public function test_REQ_041_hero_videos_are_allowed_only_for_people_who_may_change_the_home_page(): void {
		$mp4  = "\0\0\0\x18ftypmp42\0\0\0\0mp42isom" . str_repeat( 'x', 40 );
		$path = $this->temp( $mp4, 'mp4' );
		wp_set_current_user( 0 );
		$this->assertArrayNotHasKey( 'mp4', Uploads::allowed_types( array() ) );
		$this->assertNotSame( '', (string) $this->prefilter( $path, 'clip.mp4', strlen( $mp4 ) )['error'], 'A visitor cannot upload a video.' );
		$this->assertFalse( (bool) ( wp_check_filetype_and_ext( $path, 'clip.mp4' )['type'] ) );

		$editor = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $editor );
		( new \WP_User( $editor ) )->add_cap( Uploads::VIDEO_CAPABILITY );
		$this->assertArrayHasKey( 'mp4', Uploads::allowed_types( array() ) );
		$this->assertArrayHasKey( 'webm', Uploads::allowed_types( array() ) );
		$this->assertSame( 0, (int) $this->prefilter( $path, 'clip.mp4', strlen( $mp4 ) )['error'] );
		$this->assertSame( 'video/mp4', wp_check_filetype_and_ext( $path, 'clip.mp4' )['type'] );
		$too_big = $this->prefilter( $path, 'clip.mp4', Uploads::VIDEO_MAX_BYTES + 1 );
		$this->assertStringContainsString( 'too large', (string) $too_big['error'] );
		// A script renamed to .mp4 is still refused, whoever uploads it.
		$script = $this->temp( "<?php echo 'x';", 'mp4' );
		$this->assertNotSame( '', (string) $this->prefilter( $script, 'clip.mp4', 20 )['error'] );
		wp_set_current_user( 0 );
	}

	public function test_REQ_127_renamed_php_file_is_refused(): void {
		$php    = $this->temp( "<?php\necho 'owned';\n", 'jpg' );
		$result = $this->prefilter( $php, 'photo.jpg', (int) filesize( $php ) );
		$this->assertStringContainsString( 'not allowed', (string) $result['error'] );

		$check = wp_check_filetype_and_ext( $php, 'photo.jpg' );
		$this->assertFalse( $check['type'], 'core type check refuses it too' );
		$this->assertFalse( $check['ext'] );
	}

	public function test_REQ_127_image_is_reencoded_and_metadata_stripped(): void {
		$path = $this->temp( $this->jpeg( true ), 'jpg' );
		$this->assertStringContainsString( 'SECRET-GPS-AND-CAMERA-DATA', (string) file_get_contents( $path ), 'the fixture carries metadata' );

		$this->assertTrue( Uploads::sanitise_image( $path, 'image/jpeg' ) );
		$after = (string) file_get_contents( $path );
		$this->assertStringNotContainsString( 'SECRET-GPS-AND-CAMERA-DATA', $after );
		$this->assertStringNotContainsString( 'Exif', $after );
		$this->assertSame( 'image/jpeg', Uploads::real_mime( $path ), 'still a valid JPEG' );
	}

	public function test_REQ_127_the_real_upload_handler_applies_every_rule(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$overrides = array(
			'test_form' => false,
			'test_size' => true,
		);

		$evil      = $this->temp( "<?php echo 'owned';", 'jpg' );
		$evil_file = array(
			'name'     => 'evil.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $evil,
			'error'    => 0,
			'size'     => (int) filesize( $evil ),
		);
		$refused   = wp_handle_sideload( $evil_file, $overrides );
		$this->assertArrayHasKey( 'error', $refused, 'a renamed PHP file is refused by the core upload handler' );

		$photo      = $this->temp( $this->jpeg( true ), 'jpg' );
		$photo_file = array(
			'name'     => 'photo.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $photo,
			'error'    => 0,
			'size'     => (int) filesize( $photo ),
		);
		$stored     = wp_handle_sideload( $photo_file, $overrides );
		$this->assertArrayNotHasKey( 'error', $stored );
		$this->files[] = $stored['file'];
		$this->assertStringNotContainsString( 'SECRET-GPS-AND-CAMERA-DATA', (string) file_get_contents( $stored['file'] ), 'the stored file is re-encoded' );
	}

	public function test_REQ_127_metadata_is_stripped_from_jpeg_bytes_whatever_the_encoder_does(): void {
		$bytes   = $this->jpeg( true );
		$comment = "\xFF\xFE" . pack( 'n', 14 ) . 'SECRET-NOTE!';
		$bytes   = substr( $bytes, 0, 2 ) . $comment . substr( $bytes, 2 );
		$this->assertStringContainsString( 'SECRET-NOTE!', $bytes );

		$clean = Uploads::strip_jpeg_metadata( $bytes );
		$this->assertStringNotContainsString( 'SECRET-NOTE!', $clean );
		$this->assertStringNotContainsString( 'SECRET-GPS-AND-CAMERA-DATA', $clean );
		$image = imagecreatefromstring( $clean );
		$this->assertNotFalse( $image, 'the stripped JPEG still decodes' );
		$this->assertSame( 40, imagesx( $image ) );
		$this->assertSame( 'not a jpeg', Uploads::strip_jpeg_metadata( 'not a jpeg' ), 'other bytes are left alone' );
	}

	public function test_REQ_127_metadata_is_stripped_from_png_bytes(): void {
		$img = imagecreatetruecolor( 8, 8 );
		ob_start();
		imagepng( $img );
		$png  = (string) ob_get_clean();
		$data = "Comment\0SECRET-PNG-TEXT";
		$text = pack( 'N', strlen( $data ) ) . 'tEXt' . $data . pack( 'N', crc32( 'tEXt' . $data ) );
		// Insert after the 8-byte signature and the 25-byte IHDR chunk.
		$with = substr( $png, 0, 33 ) . $text . substr( $png, 33 );
		$this->assertStringContainsString( 'SECRET-PNG-TEXT', $with );

		$clean = Uploads::strip_png_metadata( $with );
		$this->assertStringNotContainsString( 'SECRET-PNG-TEXT', $clean );
		$this->assertNotFalse( imagecreatefromstring( $clean ), 'the stripped PNG still decodes' );
	}

	public function test_REQ_127_a_scanner_can_refuse_a_file(): void {
		$jpeg = $this->temp( $this->jpeg( false ), 'jpg' );
		add_filter(
			'wiw_guard_file_scanner',
			static fn () => new class() implements FileScanner {
				/**
				 * Reject everything.
				 *
				 * @param string $path Path.
				 */
				public function is_clean( string $path ): bool {
					return false;
				}
			}
		);
		$result = $this->prefilter( $jpeg, 'x.jpg', (int) filesize( $jpeg ) );
		$this->assertStringContainsString( 'safety check', (string) $result['error'] );
	}

	public function test_REQ_127_sideloaded_files_get_the_same_checks_and_scan_issue_810(): void {
		add_filter(
			'wiw_guard_file_scanner',
			static fn () => new class() implements FileScanner {
				/**
				 * Refuse everything.
				 *
				 * @param string $path Path.
				 */
				public function is_clean( string $path ): bool {
					return false;
				}
			}
		);
		$jpeg   = $this->temp( $this->jpeg( false ), 'jpg' );
		$result = apply_filters(
			'wp_handle_sideload_prefilter',
			array(
				'name'     => 'x.jpg',
				'type'     => 'image/jpeg',
				'tmp_name' => $jpeg,
				'error'    => 0,
				'size'     => (int) filesize( $jpeg ),
			)
		);
		$this->assertStringContainsString( 'safety check', (string) ( $result['error'] ?? '' ), 'a sideload (invitation photo, profile manager photo, import image) is scanned too' );	}
}
