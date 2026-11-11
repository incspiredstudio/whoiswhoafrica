<?php
/**
 * Reader of config/pii.php. Logging, retention, export and the data-request flow ask this class which columns and meta keys
 * hold personal data and what to do with them. The list itself lives only in the config file.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Pii;

/**
 * PII catalogue.
 */
final class PiiCatalogue {

	public const CLASSES  = array( 'pii', 'financial', 'public_after_approval', 'caller_supplied' );
	public const EXPORTS  = array( 'include', 'hash_only', 'exclude' );
	public const ERASES   = array( 'delete', 'anonymise', 'keep' );
	public const KINDS    = array( 'column', 'meta' );
	public const REDACTED = '[redacted]';

	/**
	 * Column-name patterns that look like personal data. A column matching one must be in the catalogue or be on the allowlist.
	 */
	private const PERSONAL_PATTERN = '/(^|_)(email|name|phone|mobile|ip|address|dob|birth|passport|nin|username|login)($|_)/';

	/**
	 * Columns that look personal but are not (the name of a thing, not of a person). Keep this list short and explain each.
	 */
	public const NOT_PERSONAL = array(
		'wiw_audit_log.action'         => 'The name of an action, not of a person.',
		'wiw_audit_log.subject_type'   => 'The kind of thing audited.',
		'wiw_import_batches.file_name' => 'A batch file name; contains no person.',
	);

	/**
	 * Entries.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $entries;

	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, string>>|null $entries Entries; defaults to config/pii.php.
	 */
	public function __construct( ?array $entries = null ) {
		if ( null === $entries ) {
			$entries = require dirname( __DIR__, 3 ) . '/config/pii.php';
		}
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- inline type hint for static analysis.
		/** @var array<int, array<string, string>> $entries */
		$this->entries = $entries;
	}

	/**
	 * Every entry.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function entries(): array {
		return $this->entries;
	}

	/**
	 * Names of all entries of one kind.
	 *
	 * @param string $kind column or meta.
	 * @return string[]
	 */
	public function names( string $kind = 'column' ): array {
		$out = array();
		foreach ( $this->entries as $entry ) {
			if ( $kind === $entry['kind'] ) {
				$out[] = $entry['name'];
			}
		}
		return $out;
	}

	/**
	 * Whether a column or meta key is catalogued.
	 *
	 * @param string $name Name, for example "wiw_signed_links.email" or a meta key.
	 */
	public function has( string $name ): bool {
		return null !== $this->find( $name );
	}

	/**
	 * One entry by name.
	 *
	 * @param string $name Name.
	 * @return array<string, string>|null
	 */
	public function find( string $name ): ?array {
		foreach ( $this->entries as $entry ) {
			if ( $entry['name'] === $name ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Entries that hold personal data (not the caller-supplied ones).
	 *
	 * @return array<int, array<string, string>>
	 */
	public function personal(): array {
		return array_values( array_filter( $this->entries, static fn ( array $e ): bool => 'caller_supplied' !== $e['class'] ) );
	}

	/**
	 * Names a migration declares as personal that the catalogue does not list.
	 *
	 * @param string[] $declared Declared "table.column" names.
	 * @return string[]
	 */
	public function missing( array $declared ): array {
		return array_values( array_filter( $declared, fn ( string $name ): bool => ! $this->has( $name ) ) );
	}

	/**
	 * Whether a column name looks like personal data.
	 *
	 * @param string $column Column name without the table.
	 */
	public static function looks_personal( string $column ): bool {
		return 1 === preg_match( self::PERSONAL_PATTERN, strtolower( $column ) );
	}

	/**
	 * Columns whose names look personal but are neither catalogued nor on the allowlist.
	 *
	 * @param string[] $columns "table.column" names, with the WordPress prefix already removed.
	 * @return string[]
	 */
	public function unclassified( array $columns ): array {
		$out = array();
		foreach ( $columns as $qualified ) {
			$column = substr( (string) strrchr( $qualified, '.' ), 1 );
			if ( self::looks_personal( $column ) && ! $this->has( $qualified ) && ! isset( self::NOT_PERSONAL[ $qualified ] ) ) {
				$out[] = $qualified;
			}
		}
		return $out;
	}

	/**
	 * Problems with the catalogue's own shape. Empty when it is well formed.
	 *
	 * @return string[]
	 */
	public function validate(): array {
		$problems = array();
		$seen     = array();
		foreach ( $this->entries as $i => $entry ) {
			$label = $entry['name'] ?? "entry {$i}";
			foreach ( array( 'kind', 'name', 'class', 'retention', 'export', 'erase' ) as $field ) {
				if ( empty( $entry[ $field ] ) ) {
					$problems[] = "{$label}: {$field} is missing";
				}
			}
			if ( isset( $entry['kind'] ) && ! in_array( $entry['kind'], self::KINDS, true ) ) {
				$problems[] = "{$label}: unknown kind";
			}
			if ( isset( $entry['class'] ) && ! in_array( $entry['class'], self::CLASSES, true ) ) {
				$problems[] = "{$label}: unknown class";
			}
			if ( isset( $entry['export'] ) && ! in_array( $entry['export'], self::EXPORTS, true ) ) {
				$problems[] = "{$label}: unknown export strategy";
			}
			if ( isset( $entry['erase'] ) && ! in_array( $entry['erase'], self::ERASES, true ) ) {
				$problems[] = "{$label}: unknown erase strategy";
			}
			if ( 'keep' === ( $entry['erase'] ?? '' ) && '' === trim( $entry['note'] ?? '' ) ) {
				$problems[] = "{$label}: a kept entry needs a note saying why";
			}
			if ( isset( $seen[ $label ] ) ) {
				$problems[] = "{$label}: listed twice";
			}
			$seen[ $label ] = true;
		}//end foreach
		return $problems;
	}

	/**
	 * Replace the values of catalogued meta keys in a list of values, for logs and audit callers. Other keys pass through.
	 *
	 * @param array<string, mixed> $values Key => value.
	 * @return array<string, mixed>
	 */
	public function scrub( array $values ): array {
		$meta = $this->names( 'meta' );
		foreach ( $values as $key => $value ) {
			if ( in_array( (string) $key, $meta, true ) ) {
				$values[ $key ] = self::REDACTED;
			}
		}
		return $values;
	}
}
