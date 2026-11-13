<?php
/**
 * Supplies the award category names for the front page marquee (REQ-264). Awards implements it; until then there is no provider
 * and the marquee is absent.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Home;

/**
 * Categories provider.
 */
interface CategoriesProvider {

	/**
	 * Category names, text only.
	 *
	 * @return array<int, string>
	 */
	public function names(): array;
}
