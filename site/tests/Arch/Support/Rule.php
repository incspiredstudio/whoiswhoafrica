<?php
/**
 * One architecture rule: reads a set of sources and reports every violation as a line of text.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Support;

/**
 * Architecture rule.
 */
interface Rule {

	/**
	 * Violations found, each naming the file and the offence. An empty list means the rule holds.
	 *
	 * @param SourceSet $sources Sources.
	 * @return string[]
	 */
	public function violations( SourceSet $sources ): array;
}
