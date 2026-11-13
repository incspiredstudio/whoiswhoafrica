<?php
/**
 * The four home hero videos an editor can replace (issue 799, REQ-001, REQ-041, REQ-052). Each slot holds the media library
 * attachment ids of an MP4 (required), a WebM (optional) and a poster image. An empty slot, or one whose files are gone or no
 * longer valid, falls back to the clip bundled in the child theme, so the hero is never blank. Saving needs wiw_curate_home and
 * is audited; the theme reads the choice only through wiw_home_hero_overrides() and never an option or a post.
 *
 * The site cannot transcode on a typical cPanel host, so files must be uploaded already optimised (site/bin/optimise-hero-video.sh).
 * The size and shape checks are therefore a guard rail: a file over the block limit is refused, one over the warning limit or off
 * the portrait 540 x 960 shape is accepted with a warning.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Home;

use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Hero videos.
 */
final class HeroVideos {

	public const OPTION     = 'wiw_home_hero_slots';
	public const CAP        = CurationSettings::CAP;
	public const SLOTS      = 4;
	public const WARN_BYTES = 2 * 1024 * 1024;
	public const MAX_BYTES  = 6 * 1024 * 1024;
	public const TOTAL_WARN = 8 * 1024 * 1024;
	public const WIDTH      = 540;
	public const HEIGHT     = 960;
	public const TOLERANCE  = 0.2;
	public const AUDIT      = 'home.hero_video.change';

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit Audit logger.
	 */
	public function __construct( AuditLogger $audit ) {
		$this->audit = $audit;
	}

	/**
	 * The saved choice for every slot: attachment ids, zero when empty.
	 *
	 * @return array<int, array{video: int, webm: int, poster: int}>
	 */
	public function get(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$out    = array();
		for ( $slot = 1; $slot <= self::SLOTS; $slot++ ) {
			$row          = isset( $stored[ $slot ] ) && is_array( $stored[ $slot ] ) ? $stored[ $slot ] : array();
			$out[ $slot ] = array(
				'video'  => (int) ( $row['video'] ?? 0 ),
				'webm'   => (int) ( $row['webm'] ?? 0 ),
				'poster' => (int) ( $row['poster'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Check one slot's submitted ids. Returns what would be saved, what blocks the save and what only warns.
	 *
	 * @param array<string, mixed> $input Submitted ids (video, webm, poster).
	 * @param bool                 $deep  Also read the file content (the save does; the public page does not).
	 * @return array{clean: array{video: int, webm: int, poster: int}, errors: string[], warnings: string[]}
	 */
	public function check( array $input, bool $deep = true ): array {
		$clean    = array(
			'video'  => max( 0, (int) ( $input['video'] ?? 0 ) ),
			'webm'   => max( 0, (int) ( $input['webm'] ?? 0 ) ),
			'poster' => max( 0, (int) ( $input['poster'] ?? 0 ) ),
		);
		$errors   = array();
		$warnings = array();
		if ( 0 === $clean['video'] && 0 === $clean['webm'] && 0 === $clean['poster'] ) {
			return compact( 'clean', 'errors', 'warnings' );
		}
		if ( 0 === $clean['video'] ) {
			$errors[] = 'Choose an MP4 video for the slot, or use Reset to bundled.';
		} else {
			$this->check_video( $clean['video'], 'video/mp4', 'MP4', $deep, $errors, $warnings );
		}
		if ( $clean['webm'] > 0 ) {
			$this->check_video( $clean['webm'], 'video/webm', 'WebM', $deep, $errors, $warnings );
		}
		if ( 0 === $clean['poster'] ) {
			$errors[] = 'Choose a poster image for the slot: it is the first thing visitors see.';
		} else {
			$this->check_poster( $clean['poster'], $errors, $warnings );
		}
		return compact( 'clean', 'errors', 'warnings' );
	}

	/**
	 * Check one clip.
	 *
	 * @param int      $id       Attachment id.
	 * @param string   $mime     Required type.
	 * @param string   $label    Name for messages.
	 * @param bool     $deep     Read the file content.
	 * @param string[] $errors   Blocking problems (appended to).
	 * @param string[] $warnings Warnings (appended to).
	 */
	private function check_video( int $id, string $mime, string $label, bool $deep, array &$errors, array &$warnings ): void {
		$path = $this->file_of( $id );
		if ( '' === $path ) {
			$errors[] = $label . ' file ' . $id . ' is not in the media library or its file is missing.';
			return;
		}
		// The public page does not repeat the content check (a visitor may not hold the video upload right); the save did it.
		$type = $deep ? wp_check_filetype_and_ext( $path, basename( $path ) ) : array( 'type' => $mime );
		if ( (string) ( $type['type'] ?? '' ) !== $mime || (string) get_post_mime_type( $id ) !== $mime ) {
			$errors[] = $label . ' file "' . basename( $path ) . '" must be a ' . $mime . ' file.';
			return;
		}
		if ( $deep && ! $this->has_container_signature( $path, $mime ) ) {
			$errors[] = $label . ' file "' . basename( $path ) . '" is not really a ' . $mime . ' video (its content does not match).';
			return;
		}
		$bytes = (int) filesize( $path );
		if ( $bytes > self::MAX_BYTES ) {
			$errors[] = $label . ' file "' . basename( $path ) . '" is ' . $this->mb( $bytes ) . ', over the ' . $this->mb( self::MAX_BYTES ) . ' limit. Optimise it first (site/bin/optimise-hero-video.sh).';
			return;
		}
		if ( $bytes > self::WARN_BYTES ) {
			$warnings[] = $label . ' file "' . basename( $path ) . '" is ' . $this->mb( $bytes ) . '; aim for ' . $this->mb( self::WARN_BYTES ) . ' or less so the page stays fast.';
		}
		$meta = wp_get_attachment_metadata( $id );
		$w    = is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
		$h    = is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0;
		if ( $w < 1 || $h < 1 ) {
			$warnings[] = $label . ' file "' . basename( $path ) . '": its size could not be read; it should be portrait ' . self::WIDTH . ' x ' . self::HEIGHT . '.';
		} elseif ( ! $this->near_shape( $w, $h ) ) {
			$warnings[] = $label . ' file "' . basename( $path ) . '" is ' . $w . ' x ' . $h . '; the hero expects portrait ' . self::WIDTH . ' x ' . self::HEIGHT . ' and will crop it.';
		}
	}

	/**
	 * Check the poster.
	 *
	 * @param int      $id       Attachment id.
	 * @param string[] $errors   Blocking problems (appended to).
	 * @param string[] $warnings Warnings (appended to).
	 */
	private function check_poster( int $id, array &$errors, array &$warnings ): void {
		$path = $this->file_of( $id );
		if ( '' === $path ) {
			$errors[] = 'Poster ' . $id . ' is not in the media library or its file is missing.';
			return;
		}
		$type = wp_check_filetype_and_ext( $path, basename( $path ) );
		if ( ! in_array( (string) ( $type['type'] ?? '' ), array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) || ! wp_attachment_is_image( $id ) ) {
			$errors[] = 'Poster "' . basename( $path ) . '" must be an image (JPEG, PNG, WebP or AVIF).';
			return;
		}
		$bytes = (int) filesize( $path );
		if ( $bytes > self::MAX_BYTES ) {
			$errors[] = 'Poster "' . basename( $path ) . '" is ' . $this->mb( $bytes ) . ', over the ' . $this->mb( self::MAX_BYTES ) . ' limit.';
			return;
		}
		if ( $bytes > self::WARN_BYTES ) {
			$warnings[] = 'Poster "' . basename( $path ) . '" is ' . $this->mb( $bytes ) . '; the poster is the first thing painted, so keep it small.';
		}
		$meta = wp_get_attachment_metadata( $id );
		$w    = is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
		$h    = is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0;
		if ( $w > 0 && $h > 0 && ! $this->near_shape( $w, $h ) ) {
			$warnings[] = 'Poster "' . basename( $path ) . '" is ' . $w . ' x ' . $h . '; use portrait ' . self::WIDTH . ' x ' . self::HEIGHT . ' so it matches the clip.';
		}
	}

	/**
	 * Whether a size is within the tolerance of portrait 540 x 960.
	 *
	 * @param int $w Width.
	 * @param int $h Height.
	 */
	private function near_shape( int $w, int $h ): bool {
		return abs( $w - self::WIDTH ) <= self::WIDTH * self::TOLERANCE && abs( $h - self::HEIGHT ) <= self::HEIGHT * self::TOLERANCE;
	}

	/**
	 * Whether the file starts like its declared container: an MP4 has "ftyp" at byte 4, a WebM starts with the EBML marker.
	 *
	 * @param string $path File.
	 * @param string $mime Declared type.
	 */
	private function has_container_signature( string $path, string $mime ): bool {
		$head = file_get_contents( $path, false, null, 0, 12 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, a few bytes.
		if ( ! is_string( $head ) || strlen( $head ) < 8 ) {
			return false;
		}
		return 'video/webm' === $mime ? 0 === strpos( $head, "\x1A\x45\xDF\xA3" ) : 'ftyp' === substr( $head, 4, 4 );
	}

	/**
	 * The file of an attachment when it exists on disk, else an empty string.
	 *
	 * @param int $id Attachment id.
	 */
	private function file_of( int $id ): string {
		if ( 'attachment' !== get_post_type( $id ) ) {
			return '';
		}
		$path = get_attached_file( $id );
		return is_string( $path ) && is_readable( $path ) ? $path : '';
	}

	/**
	 * Megabytes for a message.
	 *
	 * @param int $bytes Bytes.
	 */
	private function mb( int $bytes ): string {
		return number_format( $bytes / 1048576, 1 ) . ' MB';
	}

	/**
	 * Validate and save one slot, with an audit row.
	 *
	 * @param int                  $slot   Slot 1 to 4.
	 * @param array<string, mixed> $input  Submitted ids.
	 * @param string               $reason Optional reason for the audit row.
	 * @return string[] Warnings (the slot is saved).
	 * @throws \InvalidArgumentException When the slot or a file is not acceptable, or (as a DomainException) the user lacks wiw_curate_home; nothing is saved.
	 */
	public function save_slot( int $slot, array $input, string $reason = '' ): array {
		$this->guard( $slot );
		$result = $this->check( $input );
		if ( array() !== $result['errors'] ) {
			throw new \InvalidArgumentException( implode( ' ', $result['errors'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- plain text, escaped where the screen prints it.
		}
		$this->write( $slot, $result['clean'], $reason );
		return $result['warnings'];
	}

	/**
	 * Put a slot back to the bundled clip.
	 *
	 * @param int    $slot   Slot 1 to 4.
	 * @param string $reason Optional reason.
	 * @throws \DomainException          When the user lacks wiw_curate_home.
	 * @throws \InvalidArgumentException For an unknown slot.
	 */
	public function reset_slot( int $slot, string $reason = '' ): void {
		$this->guard( $slot );
		$this->write(
			$slot,
			array(
				'video'  => 0,
				'webm'   => 0,
				'poster' => 0,
			),
			$reason
		);
	}

	/**
	 * Capability and slot check.
	 *
	 * @param int $slot Slot.
	 * @throws \DomainException          When the user lacks wiw_curate_home.
	 * @throws \InvalidArgumentException For an unknown slot.
	 */
	private function guard( int $slot ): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to change the home page.' );
		}
		if ( $slot < 1 || $slot > self::SLOTS ) {
			throw new \InvalidArgumentException( 'Unknown hero video slot.' );
		}
	}

	/**
	 * Store one slot and audit the change; nothing happens when it is unchanged.
	 *
	 * @param int                                       $slot   Slot.
	 * @param array{video: int, webm: int, poster: int} $next   New ids.
	 * @param string                                    $reason Reason.
	 */
	private function write( int $slot, array $next, string $reason ): void {
		$all = $this->get();
		$old = $all[ $slot ];
		if ( $old === $next ) {
			return;
		}
		$all[ $slot ] = $next;
		update_option( self::OPTION, $all, false );
		$this->audit->log(
			self::AUDIT,
			array(
				'type' => 'home_hero_slot',
				'id'   => $slot,
			),
			$old,
			$next,
			$reason
		);
		do_action( 'wiw_curation_changed', 'hero_videos' );
	}

	/**
	 * What the theme needs for each slot that has a valid choice; other slots are absent and use the bundled clip.
	 *
	 * @return array<int, array{video: string, webm: string, poster: string, sets: array<string, string>, width: int, height: int, bytes: int}>
	 */
	public function resolved(): array {
		$out = array();
		foreach ( $this->get() as $slot => $ids ) {
			if ( 0 === $ids['video'] ) {
				continue;
			}
			$result = $this->check( $ids, false );
			if ( array() !== $result['errors'] ) {
				continue;
			}
			$video  = wp_get_attachment_url( $ids['video'] );
			$webm   = $ids['webm'] > 0 ? wp_get_attachment_url( $ids['webm'] ) : '';
			$poster = $this->poster( $ids['poster'] );
			if ( ! is_string( $video ) || '' === $video || null === $poster ) {
				continue;
			}
			$out[ $slot ] = array(
				'video'  => $video,
				'webm'   => is_string( $webm ) ? $webm : '',
				'poster' => $poster['url'],
				'sets'   => $poster['sets'],
				'width'  => $poster['width'],
				'height' => $poster['height'],
				'bytes'  => (int) filesize( $this->file_of( $ids['video'] ) ),
			);
		}//end foreach
		return $out;
	}

	/**
	 * The poster as the hero draws it: the large size with its srcset, plus WebP and AVIF sets when WordPress generated them.
	 *
	 * @param int $id Attachment id.
	 * @return array{url: string, sets: array<string, string>, width: int, height: int}|null
	 */
	private function poster( int $id ): ?array {
		$src = wp_get_attachment_image_src( $id, 'large' );
		if ( ! is_array( $src ) || '' === (string) $src[0] ) {
			return null;
		}
		$sets   = array();
		$srcset = wp_get_attachment_image_srcset( $id, 'large' );
		if ( is_string( $srcset ) && '' !== $srcset ) {
			$sets['jpg'] = $srcset;
		}
		$meta = wp_get_attachment_metadata( $id );
		$base = trailingslashit( dirname( (string) wp_get_attachment_url( $id ) ) );
		if ( is_array( $meta ) ) {
			foreach ( array(
				'avif' => 'image/avif',
				'webp' => 'image/webp',
			) as $key => $mime ) {
				$parts = array();
				foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
					$file = isset( $size['sources'][ $mime ]['file'] ) ? (string) $size['sources'][ $mime ]['file'] : '';
					if ( '' !== $file ) {
						$parts[] = $base . $file . ' ' . (int) $size['width'] . 'w';
					}
				}
				if ( array() !== $parts ) {
					$sets[ $key ] = implode( ', ', $parts );
				}
			}
		}
		return array(
			'url'    => (string) $src[0],
			'sets'   => $sets,
			'width'  => max( 1, (int) $src[1] ),
			'height' => max( 1, (int) $src[2] ),
		);
	}

	/**
	 * The size of each slot's MP4 as visitors get it (the chosen file, or the bundled one), and the total.
	 *
	 * @param string $bundled_dir Folder of the bundled clips.
	 * @return array{slots: array<int, array{bytes: int, custom: bool}>, total: int, over: bool}
	 */
	public function weights( string $bundled_dir ): array {
		$slots = array();
		$total = 0;
		$res   = $this->resolved();
		for ( $slot = 1; $slot <= self::SLOTS; $slot++ ) {
			if ( isset( $res[ $slot ] ) ) {
				$bytes  = $res[ $slot ]['bytes'];
				$custom = true;
			} else {
				$file   = trailingslashit( $bundled_dir ) . 'hero-' . $slot . '.mp4';
				$bytes  = is_readable( $file ) ? (int) filesize( $file ) : 0;
				$custom = false;
			}
			$slots[ $slot ] = array(
				'bytes'  => $bytes,
				'custom' => $custom,
			);
			$total         += $bytes;
		}
		return array(
			'slots' => $slots,
			'total' => $total,
			'over'  => $total > self::TOTAL_WARN,
		);
	}

	/**
	 * Megabytes for the screen.
	 *
	 * @param int $bytes Bytes.
	 */
	public static function megabytes( int $bytes ): string {
		return number_format( $bytes / 1048576, 1 ) . ' MB';
	}
}
