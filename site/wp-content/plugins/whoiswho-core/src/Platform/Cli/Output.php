<?php
/**
 * Command output. Prints to the terminal, or collects lines when a test wants to read them.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

/**
 * Output.
 */
final class Output {

	/**
	 * Lines written so far.
	 *
	 * @var string[]
	 */
	private array $lines = array();

	/**
	 * Whether to print as well as collect.
	 *
	 * @var bool
	 */
	private bool $to_terminal;

	/**
	 * Constructor.
	 *
	 * @param bool $to_terminal Print each line.
	 */
	public function __construct( bool $to_terminal = true ) {
		$this->print = $to_terminal;
	}

	/**
	 * Write one line.
	 *
	 * @param string $line Line.
	 */
	public function line( string $line ): void {
		$this->lines[] = $line;
		if ( $this->print ) {
			echo $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- terminal output, not a web page.
		}
	}

	/**
	 * Everything written.
	 *
	 * @return string[]
	 */
	public function lines(): array {
		return $this->lines;
	}
}
