<?php
/**
 * Supplies the nominees block of the front page (REQ-198). Awards implements it once nominees can be Live; until then there is no
 * provider and the block is absent.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Home;

/**
 * Nominees provider.
 */
interface NomineesProvider {

	/**
	 * Live nominees for the home grid, or an empty array before any is published.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function nominees(): array;
}
