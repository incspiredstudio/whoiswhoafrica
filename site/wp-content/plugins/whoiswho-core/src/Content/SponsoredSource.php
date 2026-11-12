<?php
/**
 * Where the stories read side gets sponsored placements from (REQ-263, REQ-182). Commerce implements this in M3; until then the
 * default reads the sponsor fields on the story. The interface is read-only: nothing here writes sponsor data, and a placement
 * never changes the order of any list.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content;

/**
 * Sponsored placement source.
 */
interface SponsoredSource {

	/**
	 * A story's placement, whatever its dates, or null when it has none.
	 *
	 * @param int $story_id Story post id.
	 * @return array{sponsor: string, placement: string, start: string, end: string}|null placement is home, stories or both; start and end are Y-m-d or empty.
	 */
	public function placement( int $story_id ): ?array;

	/**
	 * Ids of stories whose placement covers a surface (home or stories), whatever their dates. The caller applies the dates.
	 *
	 * @param string $surface home or stories.
	 * @return array<int, int>
	 */
	public function story_ids( string $surface ): array;
}
