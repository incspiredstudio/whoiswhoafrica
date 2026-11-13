<?php
/**
 * The home hero reads the editor's clips through one function and falls back to the bundled clip per slot (issue 799):
 * REQ-001, REQ-041, REQ-052. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Content\Home\HeroVideos;
use WhoIsWho\Tests\WpTestCase;

final class HomeHeroVideosThemeTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
		delete_option( HeroVideos::OPTION );
	}

	protected function tearDown(): void {
		delete_option( HeroVideos::OPTION );
		parent::tearDown();
	}

	private function attach( string $name, string $bytes, string $mime, int $w, int $h ): int {
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
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => $w,
				'height' => $h,
				'file'   => _wp_relative_upload_path( $path ),
				'sizes'  => array(),
			)
		);
		return (int) $id;
	}

	/**
	 * Save a chosen clip in a slot without the screen (the screen is tested in HeroVideosTest).
	 *
	 * @param int    $slot Slot 1 to 4.
	 * @param string $stem File name stem.
	 * @return array{video: int, poster: int}
	 */
	private function choose( int $slot, string $stem = 'mine' ): array {
		$video = $this->attach( $stem . '.mp4', "\0\0\0\x18ftypmp42\0\0\0\0mp42isom" . str_repeat( 'x', 40 ), 'video/mp4', 540, 960 );
		ob_start();
		imagejpeg( imagecreatetruecolor( 540, 960 ) );
		$poster = $this->attach( $stem . '.jpg', (string) ob_get_clean(), 'image/jpeg', 540, 960 );
		update_option(
			HeroVideos::OPTION,
			array(
				$slot => array(
					'video'  => $video,
					'webm'   => 0,
					'poster' => $poster,
				),
			)
		);
		return array(
			'video'  => $video,
			'poster' => $poster,
		);
	}

	private function render(): string {
		$this->go_to( home_url( '/' ) );
		ob_start();
		include get_template_directory() . '/front-page.php';
		return (string) ob_get_clean();
	}

	public function test_REQ_041_with_nothing_chosen_the_four_bundled_clips_show(): void {
		$videos = wiw_home_hero_videos();
		$this->assertCount( 4, $videos );
		foreach ( $videos as $i => $video ) {
			$this->assertStringContainsString( '/assets/home/hero-' . ( $i + 1 ) . '.mp4', $video['video'] );
			$this->assertStringContainsString( '/assets/home/hero-' . ( $i + 1 ) . '-540.jpg', $video['poster'] );
		}
		$this->assertStringContainsString( '/assets/home/hero-2.webm', $videos[1]['webm'] );
	}

	public function test_REQ_001_a_chosen_clip_replaces_only_its_own_slot_and_renders(): void {
		$chosen = $this->choose( 2 );
		$videos = wiw_home_hero_videos();
		$this->assertCount( 4, $videos );
		$this->assertSame( wp_get_attachment_url( $chosen['video'] ), $videos[1]['video'] );
		$this->assertSame( '', $videos[1]['webm'], 'A chosen MP4 never borrows the bundled WebM.' );
		$this->assertStringContainsString( 'mine', $videos[1]['poster'] );
		$this->assertSame( 540, $videos[1]['width'] );
		$this->assertStringContainsString( '/assets/home/hero-1.mp4', $videos[0]['video'] );
		$this->assertStringContainsString( '/assets/home/hero-3.mp4', $videos[2]['video'] );

		$html = $this->render();
		$this->assertSame( 4, substr_count( $html, 'class="h-vid"' ) );
		$this->assertStringContainsString( esc_url( (string) wp_get_attachment_url( $chosen['video'] ) ), $html );
		$this->assertStringContainsString( '/assets/home/hero-1.mp4', $html );
		// The loading strategy is unchanged: every clip preloads nothing, the first poster is high priority.
		$this->assertSame( 4, substr_count( $html, 'preload="none"' ) );
		$this->assertSame( 1, substr_count( $html, 'fetchpriority="high"' ) );
		$this->assertSame( 4, preg_match_all( '/<img class="h-vid-poster"[^>]* width="\d+" height="\d+"/', $html ) );
	}

	public function test_REQ_041_the_first_slot_may_be_custom_and_keeps_the_high_priority_poster(): void {
		$this->choose( 1, 'first' );
		$html = $this->render();
		$this->assertMatchesRegularExpression( '/<img class="h-vid-poster"[^>]*first[^>]*fetchpriority="high"/', $html );
	}

	public function test_REQ_041_an_invalid_or_removed_choice_falls_back_to_the_bundled_clip(): void {
		$chosen = $this->choose( 3 );
		wp_delete_attachment( $chosen['video'], true );
		$videos = wiw_home_hero_videos();
		$this->assertStringContainsString( '/assets/home/hero-3.mp4', $videos[2]['video'] );
		// A video choice that is not a video is ignored too.
		$chosen             = $this->choose( 4 );
		$option             = get_option( HeroVideos::OPTION );
		$option[4]['video'] = $chosen['poster'];
		update_option( HeroVideos::OPTION, $option );
		$videos = wiw_home_hero_videos();
		$this->assertStringContainsString( '/assets/home/hero-4.mp4', $videos[3]['video'] );
		update_option( HeroVideos::OPTION, 'garbage' );
		$this->assertCount( 4, wiw_home_hero_videos() );
		$this->assertStringNotContainsString( 'mine', $this->render() );
	}
}
