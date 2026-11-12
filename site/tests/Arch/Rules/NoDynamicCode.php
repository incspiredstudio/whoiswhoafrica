<?php
/**
 * No eval, create_function, backtick execution or shell calls (REQ-046). Code that runs text as code or as a command opens the
 * site to injection and has no place in this plugin, the guard or the themes.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use PhpParser\Node;
use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * No dynamic code.
 */
final class NoDynamicCode implements Rule {

	/**
	 * Functions that run text as code or as a command.
	 */
	public const FORBIDDEN_CALLS = array( 'create_function', 'shell_exec', 'exec', 'system', 'passthru', 'popen', 'proc_open', 'pcntl_exec', 'assert' );

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			if ( array() !== $sources->nodes( $path, Node\Expr\Eval_::class ) ) {
				$bad[] = "$path uses eval";
			}
			if ( array() !== $sources->nodes( $path, Node\Expr\ShellExec::class ) ) {
				$bad[] = "$path uses backtick execution";
			}
			foreach ( $sources->nodes( $path, Node\Expr\FuncCall::class ) as $call ) {
				if ( $call->name instanceof Node\Name && in_array( strtolower( $call->name->getLast() ), self::FORBIDDEN_CALLS, true ) ) {
					$bad[] = "$path calls {$call->name->getLast()}";
				}
			}
		}
		return array_values( array_unique( $bad ) );
	}
}
