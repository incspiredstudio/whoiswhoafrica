<?php
/**
 * Where log lines go. The default is a daily file; an error tracker can be added later by implementing this interface.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Log;

/**
 * Log sink.
 */
interface LogSink {

	/**
	 * Write one line.
	 *
	 * @param string $line One JSON line.
	 */
	public function write( string $line ): void;
}
