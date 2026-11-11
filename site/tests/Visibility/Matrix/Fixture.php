<?php
/**
 * One record in one publication state, as the visibility matrix sees it (REQ-208, REQ-217).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility\Matrix;

/**
 * A fixture: an id, the unique search token in its title, its author and whether a visitor may see it.
 */
final class Fixture {

	/**
	 * Build a fixture.
	 *
	 * @param string $state    Name of the state (a key of FixtureFactory::states()).
	 * @param int    $id       Post id.
	 * @param int    $author   Author user id.
	 * @param string $token    Unique word in the title, for the search surface.
	 * @param bool   $is_public Whether an anonymous visitor may reach it where a surface is open.
	 */
	public function __construct(
		public readonly string $state,
		public readonly int $id,
		public readonly int $author,
		public readonly string $token,
		public readonly bool $is_public
	) {}
}
