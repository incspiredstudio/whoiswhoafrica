<?php
/**
 * A fixed set of sponsored placements for tests of the stories read side.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Support;

use WhoIsWho\Core\Content\SponsoredSource;

/**
 * Fixture sponsored source.
 */
final class FixtureSponsoredSource implements SponsoredSource {

	/**
	 * Placements by story id.
	 *
	 * @var array<int, array{sponsor: string, placement: string, start: string, end: string}>
	 */
	public array $placements = array();

	/**
	 * A story's placement.
	 *
	 * @param int $story_id Story.
	 * @return array{sponsor: string, placement: string, start: string, end: string}|null
	 */
	public function placement( int $story_id ): ?array {
		return $this->placements[ $story_id ] ?? null;
	}

	/**
	 * Stories covering a surface.
	 *
	 * @param string $surface Surface.
	 * @return array<int, int>
	 */
	public function story_ids( string $surface ): array {
		$ids = array();
		foreach ( $this->placements as $id => $p ) {
			if ( in_array( $p['placement'], array( $surface, 'both' ), true ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}
}
