<?php
/**
 * A set of parsed PHP source files for architecture rules (REQ-199, REQ-184, REQ-210, REQ-051). Reads real code from
 * a folder, or fixture code from strings, so each rule can be shown to fail on a violation.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Parsed sources, keyed by path relative to the source root (for example "Awards/Module.php").
 */
final class SourceSet {

	/**
	 * Relative path => statements with names resolved to full names.
	 *
	 * @var array<string, Node[]>
	 */
	private array $files = array();

	/**
	 * Read every .php file under a folder.
	 *
	 * @param string $root Folder.
	 */
	public static function from_directory( string $root ): self {
		$root = rtrim( str_replace( '\\', '/', $root ), '/' );
		$set  = new self();
		$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		$code = array();
		foreach ( $it as $file ) {
			$path = str_replace( '\\', '/', (string) $file );
			if ( '.php' === substr( $path, -4 ) ) {
				$code[ ltrim( substr( $path, strlen( $root ) ), '/' ) ] = (string) file_get_contents( $path );
			}
		}
		ksort( $code );
		foreach ( $code as $relative => $source ) {
			$set->add( $relative, $source );
		}
		return $set;
	}

	/**
	 * Build a set from code strings (fixtures).
	 *
	 * @param array<string, string> $code Relative path => PHP source.
	 */
	public static function from_strings( array $code ): self {
		$set = new self();
		foreach ( $code as $relative => $source ) {
			$set->add( $relative, $source );
		}
		return $set;
	}

	/**
	 * Parse and store one file.
	 *
	 * @param string $relative Relative path.
	 * @param string $source   PHP source.
	 */
	private function add( string $relative, string $source ): void {
		$parser                   = ( new ParserFactory() )->createForNewestSupportedVersion();
		$traverser                = new NodeTraverser( new NameResolver() );
		$stmts                    = $parser->parse( $source );
		$this->files[ $relative ] = $traverser->traverse( $stmts ?? array() );
	}

	/**
	 * Relative paths of every file.
	 *
	 * @return string[]
	 */
	public function paths(): array {
		return array_keys( $this->files );
	}

	/**
	 * Number of files.
	 */
	public function count(): int {
		return count( $this->files );
	}

	/**
	 * Every node of a class in one file.
	 *
	 * @param string $path       Relative path.
	 * @param string $node_class Node class.
	 * @return Node[]
	 */
	public function nodes( string $path, string $node_class ): array {
		return ( new NodeFinder() )->findInstanceOf( $this->files[ $path ], $node_class );
	}

	/**
	 * Every string literal in one file, including the constant parts of interpolated strings.
	 *
	 * @param string $path Relative path.
	 * @return string[]
	 */
	public function strings( string $path ): array {
		$out = array();
		foreach ( $this->nodes( $path, Node\Scalar\String_::class ) as $node ) {
			$out[] = $node->value;
		}
		foreach ( $this->nodes( $path, Node\InterpolatedStringPart::class ) as $node ) {
			$out[] = $node->value;
		}
		return $out;
	}

	/**
	 * Every full class or function name used in one file (use, new, static calls, type hints, instanceof, extends).
	 *
	 * @param string $path Relative path.
	 * @return string[]
	 */
	public function names( string $path ): array {
		$out = array();
		foreach ( $this->nodes( $path, Node\Name::class ) as $node ) {
			$out[] = $node->toString();
		}
		foreach ( $this->nodes( $path, Node\Stmt\UseUse::class ) as $node ) {
			$out[] = $node->name->toString();
		}
		return $out;
	}

	/**
	 * The string keys of every array literal in one file (the column names handed to insert and update calls).
	 *
	 * @param string $path Relative path.
	 * @return string[]
	 */
	public function array_keys( string $path ): array {
		$out = array();
		foreach ( $this->nodes( $path, Node\ArrayItem::class ) as $item ) {
			if ( $item->key instanceof Node\Scalar\String_ ) {
				$out[] = $item->key->value;
			}
		}
		return $out;
	}

	/**
	 * The string values stored under one array key anywhere in a file, for example every 'post_status' => '...'.
	 *
	 * @param string $path Relative path.
	 * @param string $key  Array key.
	 * @return string[]
	 */
	public function array_values_for( string $path, string $key ): array {
		$out = array();
		foreach ( $this->nodes( $path, Node\ArrayItem::class ) as $item ) {
			if ( $item->key instanceof Node\Scalar\String_ && $key === $item->key->value && $item->value instanceof Node\Scalar\String_ ) {
				$out[] = $item->value->value;
			}
		}
		return $out;
	}

	/**
	 * Names of the functions and methods called in one file.
	 *
	 * @param string $path Relative path.
	 * @return string[]
	 */
	public function calls( string $path ): array {
		$out = array();
		foreach ( $this->nodes( $path, Node\Expr\FuncCall::class ) as $node ) {
			if ( $node->name instanceof Node\Name ) {
				$out[] = $node->name->toString();
			}
		}
		foreach ( array( Node\Expr\MethodCall::class, Node\Expr\StaticCall::class ) as $kind ) {
			foreach ( $this->nodes( $path, $kind ) as $node ) {
				if ( $node->name instanceof Node\Identifier ) {
					$out[] = $node->name->toString();
				}
			}
		}
		return $out;
	}
}
