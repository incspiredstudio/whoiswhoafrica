<?php
/**
 * Hook for scanning uploaded files for malware. REQ-127 says uploads are "scanned" but the docs name no scanner, so the
 * default implementation accepts every file that passed the type and size checks. Swap it by registering another
 * implementation with the filter wiw_guard_file_scanner when one is chosen.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Uploads;

/**
 * File scanner contract.
 */
interface FileScanner {

	/**
	 * Whether the file at this path is clean.
	 *
	 * @param string $path Path to the uploaded file.
	 */
	public function is_clean( string $path ): bool;
}
