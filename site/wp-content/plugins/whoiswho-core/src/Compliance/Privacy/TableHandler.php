<?php
/**
 * One personal-data handler for one table: find the rows that belong to an email address, export the listed columns, and erase by
 * deleting the rows or by anonymising named columns (REQ-059, REQ-063, REQ-064). Which columns are listed and how each is erased
 * follows config/pii.php: columns exported "include" are shown, "hash_only" and "exclude" ones never are, "delete" removes the row
 * and "anonymise" overwrites the column and keeps the row for the case or audit need. Only counts leave this class.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Privacy;

use WhoIsWho\Core\Platform\Pii\PiiCatalogue;

/**
 * Table handler.
 */
final class TableHandler implements PrivacyHandler {

	/**
	 * Id.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Label for the privacy tools.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Table without the site prefix.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * The column that holds the address, or a hash of it.
	 *
	 * @var string
	 */
	private string $match_column;

	/**
	 * Turns the requester's address into the value stored in the match column (the address itself by default).
	 *
	 * @var callable
	 */
	private $match_value;

	/**
	 * Columns shown in an export: column => label.
	 *
	 * @var array<string, string>
	 */
	private array $export;

	/**
	 * Columns to overwrite on erase: column => REDACTED or null. Empty means delete the rows.
	 *
	 * @var array<string, string|null>
	 */
	private array $anonymise;

	/**
	 * Constructor.
	 *
	 * @param string                     $id           Id.
	 * @param string                     $label        Label.
	 * @param string                     $table        Table without prefix.
	 * @param string                     $match_column Column holding the address (or its hash).
	 * @param array<string, string>      $export       Columns shown in an export.
	 * @param array<string, string|null> $anonymise    Columns to overwrite on erase; empty deletes the rows.
	 * @param callable|null              $match_value  Address to stored value; the address unchanged by default.
	 */
	public function __construct( string $id, string $label, string $table, string $match_column, array $export, array $anonymise = array(), ?callable $match_value = null ) {
		$this->id           = $id;
		$this->label        = $label;
		$this->table        = $table;
		$this->match_column = $match_column;
		$this->export       = $export;
		$this->anonymise    = $anonymise;
		$this->match_value  = $match_value ?? static fn ( string $email ): string => $email;
	}

	/**
	 * Id.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Tables covered.
	 *
	 * @return string[]
	 */
	public function tables(): array {
		return array( $this->table );
	}

	/**
	 * Export the rows of an address.
	 *
	 * @param string $email Address.
	 * @return array<int, array{item_id: string, data: array<int, array{name: string, value: string}>}>
	 */
	public function export( string $email ): array {
		$out = array();
		foreach ( $this->rows( $email ) as $row ) {
			$data = array();
			foreach ( $this->export as $column => $label ) {
				if ( isset( $row[ $column ] ) && '' !== (string) $row[ $column ] ) {
					$data[] = array(
						'name'  => $label,
						'value' => (string) $row[ $column ],
					);
				}
			}
			$out[] = array(
				'item_id' => $this->table . '-' . (int) $row['id'],
				'data'    => $data,
			);
		}
		return $out;
	}

	/**
	 * Erase the rows of an address.
	 *
	 * @param string $email Address.
	 * @return int Rows deleted or anonymised.
	 */
	public function erase( string $email ): int {
		global $wpdb;
		$full  = $wpdb->prefix . $this->table;
		$match = ( $this->match_value )( $email );
		if ( '' === trim( $email ) || ! $this->exists() ) {
			return 0;
		}
		if ( array() === $this->anonymise ) {
			return (int) $wpdb->delete( $full, array( $this->match_column => $match ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$changed = 0;
		foreach ( $this->rows( $email ) as $row ) {
			$values = array();
			foreach ( $this->anonymise as $column => $replacement ) {
				$values[ $column ] = 'redact' === $replacement ? PiiCatalogue::REDACTED : null;
			}
			$changed += false === $wpdb->update( $full, $values, array( 'id' => (int) $row['id'] ) ) ? 0 : 1; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return $changed;
	}

	/**
	 * Rows of an address that are not already anonymised.
	 *
	 * @param string $email Address.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows( string $email ): array {
		global $wpdb;
		if ( '' === trim( $email ) || ! $this->exists() ) {
			return array();
		}
		$full   = $wpdb->prefix . $this->table;
		$column = preg_replace( '/[^a-z_]/', '', $this->match_column );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$full}` WHERE `{$column}` = %s ORDER BY id", ( $this->match_value )( $email ) ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table and column names, value prepared.
	}

	/**
	 * Whether the table exists.
	 */
	private function exists(): bool {
		global $wpdb;
		return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $this->table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
