<?php
/**
 * No SQL built by putting a variable into the query text (REQ-046): a query given to $wpdb as a concatenated or interpolated
 * string that contains a plain variable must go through $wpdb->prepare. Names that are fixed in code (a table() call, a class
 * constant, $this) are not variables of this kind. This is a heuristic, a backstop for phpcs, not a proof.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use PhpParser\Node;
use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * Prepared SQL.
 */
final class NoUnpreparedSql implements Rule {

	/**
	 * $wpdb methods that run a query given as the first argument.
	 */
	public const QUERY_METHODS = array( 'query', 'get_results', 'get_var', 'get_row', 'get_col' );

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			foreach ( $sources->nodes( $path, Node\Expr\MethodCall::class ) as $call ) {
				if ( ! $call->var instanceof Node\Expr\Variable || 'wpdb' !== $call->var->name || ! $call->name instanceof Node\Identifier ) {
					continue;
				}
				if ( ! in_array( $call->name->toString(), self::QUERY_METHODS, true ) ) {
					continue;
				}
				$query = $call->args[0]->value ?? null;
				if ( ( $query instanceof Node\Expr\BinaryOp\Concat || $query instanceof Node\Scalar\InterpolatedString ) && $this->is_data_query( $query ) && $this->has_plain_variable( $query ) ) {
					$bad[] = $path . ' puts a variable into a $wpdb query without prepare';
				}
			}
		}
		return array_values( array_unique( $bad ) );
	}

	/**
	 * Whether the query reads or writes rows. Identifiers in DDL and transaction statements cannot be placeholders, so they are
	 * not checked here.
	 *
	 * @param Node $query Query expression.
	 */
	private function is_data_query( Node $query ): bool {
		while ( $query instanceof Node\Expr\BinaryOp\Concat ) {
			$query = $query->left;
		}
		$text = '';
		if ( $query instanceof Node\Scalar\String_ ) {
			$text = $query->value;
		} elseif ( $query instanceof Node\Scalar\InterpolatedString && isset( $query->parts[0] ) && $query->parts[0] instanceof Node\InterpolatedStringPart ) {
			$text = $query->parts[0]->value;
		}
		return 1 === preg_match( '/^\s*(SELECT|INSERT|UPDATE|DELETE|REPLACE)\s/i', $text );
	}

	/**
	 * Whether an expression contains a plain variable other than $this or $wpdb.
	 *
	 * @param Node $node Expression.
	 */
	private function has_plain_variable( Node $node ): bool {
		if ( $node instanceof Node\Expr\Variable ) {
			return is_string( $node->name ) && ! in_array( $node->name, array( 'this', 'wpdb' ), true );
		}
		if ( $node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\FuncCall || $node instanceof Node\Expr\PropertyFetch || $node instanceof Node\Expr\ClassConstFetch || $node instanceof Node\Expr\StaticPropertyFetch ) {
			return false;
		}
		foreach ( $node->getSubNodeNames() as $name ) {
			$sub = $node->$name;
			foreach ( is_array( $sub ) ? $sub : array( $sub ) as $child ) {
				if ( $child instanceof Node && $this->has_plain_variable( $child ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
