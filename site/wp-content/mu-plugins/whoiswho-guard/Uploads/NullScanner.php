<?php
/**
 * The default scanner: no scanner is configured yet, so it accepts the file. See FileScanner.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Uploads;

/**
 * Null scanner.
 */
final class NullScanner implements FileScanner {

	/**
	 * Always clean.
	 *
	 * @param string $path Path to the uploaded file.
	 */
	public function is_clean( string $path ): bool {
		return true;
	}
}
