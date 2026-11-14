<?php
/**
 * Featured and Sponsored on stories (REQ-155, REQ-182, REQ-184, REQ-188, REQ-257). Featured is WordPress's sticky flag and needs
 * edit_others_posts. The sponsor fields are written only here (manual changes need wiw_set_sponsored; the Commerce label service
 * uses commerce_write()); any other write to them is refused. Neither flag exists on profiles. Every change is audited. A paid
 * arrangement never touches the Featured flag.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Story flags.
 */
final class StoryFlags {

	public const SPONSOR_KEYS = array( 'wiw_sponsor_name', 'wiw_sponsor_placement', 'wiw_sponsor_start', 'wiw_sponsor_end' );
	public const PLACEMENTS   = array( 'home', 'stories', 'both' );
	public const SPONSOR_CAP  = 'wiw_set_sponsored';

	/**
	 * Whether the sanctioned writer is running.
	 *
	 * @var bool
	 */
	private static bool $open = false;

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
	 * Refuse writes to the sponsor fields from anywhere but this class.
	 */
	public static function hooks(): void {
		foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $filter ) {
			add_filter( $filter, array( self::class, 'guard_meta' ), 1, 3 );
		}
	}

	/**
	 * Meta filter: false (refuse) for a sponsor key unless the sanctioned writer is running.
	 *
	 * @param mixed  $check    Short-circuit value.
	 * @param int    $post_id  Post.
	 * @param string $meta_key Key.
	 * @return mixed
	 */
	public static function guard_meta( $check, $post_id, $meta_key ) {
		unset( $post_id );
		if ( ! self::$open && in_array( $meta_key, self::SPONSOR_KEYS, true ) ) {
			return false;
		}
		return $check;
	}

	/**
	 * Feature or unfeature a story.
	 *
	 * @param int  $post_id Story.
	 * @param bool $on      Featured or not.
	 * @throws \DomainException When the user may not, or the post is not a story.
	 */
	public function set_featured( int $post_id, bool $on ): void {
		$this->require_story( $post_id );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			throw new \DomainException( 'Not allowed to feature stories.' );
		}
		$was = is_sticky( $post_id );
		if ( $was === $on ) {
			return;
		}
		if ( $on ) {
			stick_post( $post_id );
		} else {
			unstick_post( $post_id );
		}
		$this->audit->log( 'story.featured', $this->subject( $post_id ), $was, $on );
	}

	/**
	 * Manually mark a story Sponsored, or clear it (empty sponsor name).
	 *
	 * @param int    $post_id   Story.
	 * @param string $sponsor   Sponsor name; empty clears.
	 * @param string $placement home, stories or both.
	 * @throws \DomainException When the user may not, or the post is not a story.
	 */
	public function set_sponsored( int $post_id, string $sponsor, string $placement ): void {
		$this->require_story( $post_id );
		if ( ! current_user_can( self::SPONSOR_CAP ) ) {
			throw new \DomainException( 'Not allowed to sponsor stories.' );
		}
		$this->commerce_write( $post_id, $sponsor, $placement, '', '' );
	}

	/**
	 * The one writer of the sponsor fields. Called by the manual path above and by the Commerce label service. It never changes
	 * the Featured flag.
	 *
	 * @param int    $post_id   Story.
	 * @param string $sponsor   Sponsor name; empty clears all sponsor fields.
	 * @param string $placement home, stories or both.
	 * @param string $start     Start date (Y-m-d) or empty.
	 * @param string $end       End date (Y-m-d) or empty.
	 * @throws \InvalidArgumentException For a bad placement or date.
	 */
	public function commerce_write( int $post_id, string $sponsor, string $placement, string $start, string $end ): void {
		$this->require_story( $post_id );
		$sponsor = trim( sanitize_text_field( $sponsor ) );
		if ( '' !== $sponsor && ! in_array( $placement, self::PLACEMENTS, true ) ) {
			throw new \InvalidArgumentException( 'Placement must be home, stories or both.' );
		}
		foreach ( array( $start, $end ) as $date ) {
			if ( '' !== $date && 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				throw new \InvalidArgumentException( 'Dates are Y-m-d.' );
			}
		}
		$before = array(
			'sponsor'   => (string) get_post_meta( $post_id, 'wiw_sponsor_name', true ),
			'placement' => (string) get_post_meta( $post_id, 'wiw_sponsor_placement', true ),
			'start'     => (string) get_post_meta( $post_id, 'wiw_sponsor_start', true ),
			'end'       => (string) get_post_meta( $post_id, 'wiw_sponsor_end', true ),
		);
		$after  = '' === $sponsor ? array(
			'sponsor'   => '',
			'placement' => '',
			'start'     => '',
			'end'       => '',
		) : array(
			'sponsor'   => $sponsor,
			'placement' => $placement,
			'start'     => $start,
			'end'       => $end,
		);
		if ( $before === $after ) {
			return;
		}
		self::$open = true;
		try {
			$map = array(
				'sponsor'   => 'wiw_sponsor_name',
				'placement' => 'wiw_sponsor_placement',
				'start'     => 'wiw_sponsor_start',
				'end'       => 'wiw_sponsor_end',
			);
			foreach ( $map as $field => $key ) {
				if ( '' === $after[ $field ] ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, wp_slash( $after[ $field ] ) );
				}
			}
		} finally {
			self::$open = false;
		}
		$this->audit->log( 'story.sponsored', $this->subject( $post_id ), $before, $after );
	}

	/**
	 * Only core posts (stories) can be featured or sponsored.
	 *
	 * @param int $post_id Post.
	 * @throws \DomainException For any other post type.
	 */
	private function require_story( int $post_id ): void {
		if ( 'post' !== get_post_type( $post_id ) ) {
			throw new \DomainException( 'Only stories can be featured or sponsored.' );
		}
	}

	/**
	 * Audit subject.
	 *
	 * @param int $post_id Post.
	 * @return array{type: string, id: int}
	 */
	private function subject( int $post_id ): array {
		return array(
			'type' => 'story',
			'id'   => $post_id,
		);
	}
}
