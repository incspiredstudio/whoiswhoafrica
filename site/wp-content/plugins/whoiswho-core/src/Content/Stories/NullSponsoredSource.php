<?php
/**
 * A sponsored source with no placements (REQ-263).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

use WhoIsWho\Core\Content\SponsoredSource;

/**
 * Null sponsored source.
 */
final class NullSponsoredSource implements SponsoredSource {

	/**
	 * No placement.
	 *
	 * @param int $story_id Story.
	 * @return array{sponsor: string, placement: string, start: string, end: string}|null
	 */
	public function placement( int $story_id ): ?array {
		unset( $story_id );
		return null;
	}

	/**
	 * No stories.
	 *
	 * @param string $surface Surface.
	 * @return array<int, int>
	 */
	public function story_ids( string $surface ): array {
		unset( $surface );
		return array();
	}
}
