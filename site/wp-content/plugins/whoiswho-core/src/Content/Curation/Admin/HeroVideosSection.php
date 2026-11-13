<?php
/**
 * The hero videos section of the Home page settings screen (issue 799, REQ-001, REQ-041, REQ-052): four slots, each with an MP4,
 * an optional WebM and a poster picked from the media library, a preview, the total weight of the four clips and a Reset to
 * bundled button. Saving goes through HeroVideos (capability, validation, audit); this class only reads the form and draws it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Curation\Admin;

use WhoIsWho\Core\Content\Home\HeroVideos;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Hero videos section.
 */
final class HeroVideosSection {

	public const ACTION = 'wiw_save_hero_video';

	/**
	 * Service.
	 *
	 * @var HeroVideos
	 */
	private HeroVideos $videos;

	/**
	 * Constructor.
	 *
	 * @param HeroVideos $videos Service.
	 */
	public function __construct( HeroVideos $videos ) {
		$this->videos = $videos;
	}

	/**
	 * Save or reset one slot. A refusal changes nothing and comes back as the message.
	 *
	 * @param Request $request Request (slot, op save|reset, video_id, webm_id, poster_id, reason).
	 * @return string Message for the person.
	 */
	public function handle( Request $request ): string {
		$slot   = $request->int( 'slot' );
		$reason = $request->string( 'reason' );
		try {
			if ( 'reset' === $request->string( 'op' ) ) {
				$this->videos->reset_slot( $slot, $reason );
				return 'Slot ' . $slot . ' now uses the bundled clip.';
			}
			$warnings = $this->videos->save_slot(
				$slot,
				array(
					'video'  => $request->int( 'video_id' ),
					'webm'   => $request->int( 'webm_id' ),
					'poster' => $request->int( 'poster_id' ),
				),
				$reason
			);
		} catch ( \DomainException | \InvalidArgumentException $refusal ) {
			return $refusal->getMessage();
		}
		return 'Saved slot ' . $slot . '.' . ( array() === $warnings ? '' : ' Warning: ' . implode( ' ', $warnings ) );
	}

	/**
	 * The section markup.
	 */
	public function html(): string {
		$saved   = $this->videos->get();
		$dir     = trailingslashit( get_stylesheet_directory() ) . 'assets/home';
		$weights = $this->videos->weights( $dir );
		$out     = '<h2 id="wiw-hero-videos">' . esc_html__( 'Home page hero videos', 'whoiswho' ) . '</h2>';
		$out    .= '<p>' . esc_html__( 'The four clips behind the home page title. Each needs an MP4 and a poster image; a WebM is optional. An empty slot, or one whose files are removed, shows the bundled clip, so the hero is never blank.', 'whoiswho' ) . '</p>';
		$out    .= '<p>' . esc_html__( 'Upload files that are already optimised: portrait 540 x 960, no sound, about 2 MB or less each, made with site/bin/optimise-hero-video.sh. This host cannot convert video, so the site uses your files as they are.', 'whoiswho' ) . '</p>';
		$out    .= '<p class="wiw-hero-total"><strong>' . esc_html__( 'Total weight of the four MP4 clips:', 'whoiswho' ) . '</strong> ' . esc_html( HeroVideos::megabytes( $weights['total'] ) );
		$out    .= $weights['over'] ? ' <em>' . esc_html( sprintf( /* translators: %s: size */ __( '(over %s: the home page will be slow on mobile data)', 'whoiswho' ), HeroVideos::megabytes( HeroVideos::TOTAL_WARN ) ) ) . '</em>' : '';
		$out    .= '</p>';
		for ( $slot = 1; $slot <= HeroVideos::SLOTS; $slot++ ) {
			$out .= $this->slot( $slot, $saved[ $slot ], $weights['slots'][ $slot ] );
		}
		return $out;
	}

	/**
	 * One slot's form.
	 *
	 * @param int                                       $slot   Slot.
	 * @param array{video: int, webm: int, poster: int} $ids    Saved ids.
	 * @param array{bytes: int, custom: bool}           $weight Weight of what is shown now.
	 */
	private function slot( int $slot, array $ids, array $weight ): string {
		$custom = $weight['custom'];
		$base   = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/home/hero-' . $slot;
		$poster = $custom ? (string) wp_get_attachment_image_url( $ids['poster'], 'thumbnail' ) : $base . '-360.jpg';
		$video  = $custom ? (string) wp_get_attachment_url( $ids['video'] ) : $base . '.mp4';
		$out    = '<h3>' . esc_html( sprintf( /* translators: %d: slot number */ __( 'Clip %d', 'whoiswho' ), $slot ) ) . '</h3>';
		$out   .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-hero-slot" data-slot="' . (int) $slot . '">';
		$out   .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out   .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out   .= '<input type="hidden" name="slot" value="' . (int) $slot . '" />';
		$out   .= '<div class="wiw-hero-preview">';
		$out   .= '<img class="wiw-hero-poster" src="' . esc_url( $poster ) . '" alt="" width="90" height="160" /> ';
		$out   .= '<video class="wiw-hero-clip" src="' . esc_url( $video ) . '" muted="muted" controls="controls" playsinline="playsinline" preload="none" width="90" height="160"></video>';
		$out   .= '</div>';
		$out   .= '<p class="wiw-hero-state">' . esc_html( $custom ? sprintf( /* translators: %s: size */ __( 'Your clip (%s).', 'whoiswho' ), HeroVideos::megabytes( $weight['bytes'] ) ) : sprintf( /* translators: %s: size */ __( 'Bundled clip in use (%s).', 'whoiswho' ), HeroVideos::megabytes( $weight['bytes'] ) ) ) . '</p>';
		$out   .= $this->picker( 'video_id', __( 'Video (MP4, required)', 'whoiswho' ), 'video', $ids['video'] );
		$out   .= $this->picker( 'webm_id', __( 'Video (WebM, optional)', 'whoiswho' ), 'video', $ids['webm'] );
		$out   .= $this->picker( 'poster_id', __( 'Poster image (required)', 'whoiswho' ), 'image', $ids['poster'] );
		$out   .= '<p><label>' . esc_html__( 'Reason (optional)', 'whoiswho' ) . ' <input type="text" name="reason" maxlength="300" class="regular-text" /></label></p>';
		$out   .= '<p><button type="submit" name="op" value="save" class="button button-primary">' . esc_html__( 'Save clip', 'whoiswho' ) . '</button> ';
		$out   .= '<button type="submit" name="op" value="reset" class="button">' . esc_html__( 'Reset to bundled', 'whoiswho' ) . '</button></p></form>';
		return $out;
	}

	/**
	 * One media field: the hidden id, the file name and the button that opens the media library.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param string $kind  video or image.
	 * @param int    $id    Saved attachment id.
	 */
	private function picker( string $name, string $label, string $kind, int $id ): string {
		$file = $id > 0 ? (string) get_attached_file( $id ) : '';
		$text = '' === $file ? __( 'Nothing chosen', 'whoiswho' ) : basename( $file );
		$out  = '<p class="wiw-hero-field"><span>' . esc_html( $label ) . '</span> ';
		$out .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . (int) $id . '" /> ';
		$out .= '<span class="wiw-hero-name">' . esc_html( $text ) . '</span> ';
		$out .= '<button type="button" class="button wiw-hero-pick" data-target="' . esc_attr( $name ) . '" data-kind="' . esc_attr( $kind ) . '">' . esc_html__( 'Choose from the media library', 'whoiswho' ) . '</button> ';
		$out .= '<button type="button" class="button-link wiw-hero-clear" data-target="' . esc_attr( $name ) . '">' . esc_html__( 'Clear', 'whoiswho' ) . '</button></p>';
		return $out;
	}

	/**
	 * Markup this section needs beyond the rest of the screen, for wp_kses.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array {
		return array(
			'img'    => array(
				'class'  => true,
				'src'    => true,
				'alt'    => true,
				'width'  => true,
				'height' => true,
			),
			'video'  => array(
				'class'       => true,
				'src'         => true,
				'muted'       => true,
				'controls'    => true,
				'playsinline' => true,
				'preload'     => true,
				'width'       => true,
				'height'      => true,
			),
			'strong' => array(),
			'h2'     => array( 'id' => true ),
			'p'      => array( 'class' => true ),
			'span'   => array( 'class' => true ),
			'form'   => array(
				'method'    => true,
				'action'    => true,
				'class'     => true,
				'data-slot' => true,
			),
			'button' => array(
				'type'        => true,
				'class'       => true,
				'name'        => true,
				'value'       => true,
				'data-target' => true,
				'data-kind'   => true,
			),
		);
	}
}
