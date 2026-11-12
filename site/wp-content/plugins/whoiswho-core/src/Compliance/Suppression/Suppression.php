<?php
/**
 * The permanent suppression list (REQ-207, REQ-147): people and organisations who asked to be removed, and declined
 * nominees. The matcher is used by import and nominations; a valid removal adds an entry automatically; only a user with
 * wiw_edit_suppression adds or removes one by hand. Every change is audited. The reason text never leaves the site: it is
 * not in the snapshot for acquisition and is not copied into the audit log.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Suppression;

use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Suppression service.
 */
class Suppression {

	public const TABLE = 'wiw_suppression';

	public const EDIT_CAP = 'wiw_edit_suppression';

	public const SOURCES = array( 'removal', 'manual', 'declined_nominee' );

	/**
	 * The refusal code an import gives for a suppressed record.
	 */
	public const REFUSAL_CODE = 'SUPPRESSED';

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		entity_type varchar(20) NOT NULL,
		name varchar(255) NOT NULL,
		name_key varchar(255) NOT NULL,
		id_keys text NULL,
		reason text NULL,
		source varchar(20) NOT NULL,
		support_item_id bigint(20) unsigned NULL,
		added_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY name_key (entity_type, name_key(190)),
		KEY support_item (support_item_id)';

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit Audit logger.
	 */
	public function __construct( AuditLogger $audit ) {
		$this->audit = $audit;
	}

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Normalise a name for matching: lower case, no accents, "&" as "and", punctuation as spaces, single spaces.
	 *
	 * @param string $name Name.
	 */
	public static function normalise_name( string $name ): string {
		$name = remove_accents( $name );
		$name = strtolower( str_replace( '&', ' and ', $name ) );
		$name = (string) preg_replace( '/[^a-z0-9]+/', ' ', $name );
		return trim( $name );
	}

	/**
	 * Add an entry automatically from a valid removal. No capability check: the removal handler has already checked its own.
	 *
	 * @param string               $name            Name as given.
	 * @param string               $entity_type     Entity type.
	 * @param array<string,string> $external_ids    Source => id.
	 * @param int                  $support_item_id The removal item.
	 * @return int Entry id.
	 */
	public function add_from_removal( string $name, string $entity_type, array $external_ids, int $support_item_id ): int {
		return $this->insert( $name, $entity_type, $external_ids, '', 'removal', $support_item_id );
	}

	/**
	 * Add an entry for a declined nominee (called by nominations).
	 *
	 * @param string               $name         Name as given.
	 * @param string               $entity_type  Entity type.
	 * @param array<string,string> $external_ids Source => id.
	 * @return int Entry id.
	 */
	public function add_declined_nominee( string $name, string $entity_type, array $external_ids = array() ): int {
		return $this->insert( $name, $entity_type, $external_ids, '', 'declined_nominee', 0 );
	}

	/**
	 * Add an entry by hand.
	 *
	 * @param string               $name         Name as given.
	 * @param string               $entity_type  Entity type.
	 * @param array<string,string> $external_ids Source => id.
	 * @param string               $reason       Why (stays on the site).
	 * @return int Entry id.
	 * @throws \DomainException Without wiw_edit_suppression.
	 */
	public function add_manual( string $name, string $entity_type, array $external_ids, string $reason ): int {
		$this->require_edit();
		return $this->insert( $name, $entity_type, $external_ids, $reason, 'manual', 0 );
	}

	/**
	 * Remove an entry by hand.
	 *
	 * @param int $id Entry id.
	 * @throws \DomainException Without wiw_edit_suppression.
	 */
	public function remove_manual( int $id ): void {
		global $wpdb;
		$this->require_edit();
		$entry = $this->get( $id );
		if ( null === $entry ) {
			return;
		}
		$wpdb->delete( self::table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->audit->log(
			'suppression.remove',
			array(
				'type' => 'suppression',
				'id'   => $id,
			),
			array(
				'entity_type' => $entry['entity_type'],
				'source'      => $entry['source'],
			)
		);
	}

	/**
	 * One entry.
	 *
	 * @param int $id Entry id.
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Whether a person or organisation is suppressed. A match on any external id, or on the normalised name with the same
	 * entity type; a name match with a different external id still counts until a person decides.
	 *
	 * @param string               $name         Name.
	 * @param string               $entity_type  Entity type.
	 * @param array<string,string> $external_ids Source => id.
	 * @return array{id: int, by: string}|null The matching entry id and "external_id" or "name"; null for no match.
	 */
	public function match( string $name, string $entity_type, array $external_ids = array() ): ?array {
		global $wpdb;
		foreach ( self::id_keys( $external_ids ) as $key ) {
			$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE id_keys LIKE %s ORDER BY id ASC LIMIT 1', '%|' . $wpdb->esc_like( $key ) . '|%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
			if ( null !== $id ) {
				return array(
					'id' => (int) $id,
					'by' => 'external_id',
				);
			}
		}
		$key = self::normalise_name( $name );
		if ( '' === $key ) {
			return null;
		}
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE entity_type = %s AND name_key = %s ORDER BY id ASC LIMIT 1', $entity_type, $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return null === $id ? null : array(
			'id' => (int) $id,
			'by' => 'name',
		);
	}

	/**
	 * The check import runs on every record against the live list (contract section 11, check 3).
	 *
	 * @param array<string, mixed> $record Needs entity_type, name and optionally external_ids.
	 * @return string|null The refusal code SUPPRESSED, or null when the record may proceed.
	 */
	public function refusal_for_import( array $record ): ?string {
		$match = $this->match( (string) ( $record['name'] ?? '' ), (string) ( $record['entity_type'] ?? '' ), (array) ( $record['external_ids'] ?? array() ) );
		return null === $match ? null : self::REFUSAL_CODE;
	}

	/**
	 * The snapshot sent to acquisition before a run: stable id, type, name and external ids. Never the reason, the source of the
	 * entry, the requester or the staff member.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function snapshot(): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( 'SELECT id, entity_type, name, id_keys FROM ' . self::table() . ' ORDER BY id ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$out  = array();
		foreach ( $rows as $row ) {
			$ids = array();
			foreach ( array_filter( explode( '|', (string) $row['id_keys'] ) ) as $key ) {
				$parts = explode( ':', $key, 2 );
				if ( 2 === count( $parts ) ) {
					$ids[ $parts[0] ] = $parts[1];
				}
			}
			$out[] = array(
				'suppression_id' => (int) $row['id'],
				'entity_type'    => (string) $row['entity_type'],
				'name'           => (string) $row['name'],
				'external_ids'   => $ids,
			);
		}
		return $out;
	}

	/**
	 * Match keys for external ids: "source:id", lower case.
	 *
	 * @param array<string,string> $external_ids Source => id.
	 * @return string[]
	 */
	private static function id_keys( array $external_ids ): array {
		$keys = array();
		foreach ( $external_ids as $source => $value ) {
			$source = strtolower( trim( (string) $source ) );
			$value  = strtolower( trim( (string) $value ) );
			if ( '' !== $source && '' !== $value && false === strpos( $source . $value, '|' ) ) {
				$keys[] = $source . ':' . $value;
			}
		}
		return $keys;
	}

	/**
	 * Insert and audit.
	 *
	 * @param string               $name         Name.
	 * @param string               $entity_type  Entity type.
	 * @param array<string,string> $external_ids Source => id.
	 * @param string               $reason       Reason.
	 * @param string               $source       One of SOURCES.
	 * @param int                  $item         Support item id or 0.
	 * @return int Id.
	 * @throws \InvalidArgumentException For a bad entity type, source or an empty name.
	 */
	private function insert( string $name, string $entity_type, array $external_ids, string $reason, string $source, int $item ): int {
		global $wpdb;
		$name = trim( $name );
		$key  = self::normalise_name( $name );
		if ( '' === $key ) {
			throw new \InvalidArgumentException( 'A suppression entry needs a name.' );
		}
		if ( ! in_array( $entity_type, StateRepository::ENTITY_TYPES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown entity type.' );
		}
		if ( ! in_array( $source, self::SOURCES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown source.' );
		}
		$keys = self::id_keys( $external_ids );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'entity_type'     => $entity_type,
				'name'            => $name,
				'name_key'        => $key,
				'id_keys'         => array() === $keys ? null : '|' . implode( '|', $keys ) . '|',
				'reason'          => '' === $reason ? null : $reason,
				'source'          => $source,
				'support_item_id' => $item > 0 ? $item : null,
				'added_by'        => (int) get_current_user_id(),
				'created_at'      => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$id = (int) $wpdb->insert_id;
		$this->audit->log(
			'suppression.add',
			array(
				'type' => 'suppression',
				'id'   => $id,
			),
			null,
			array(
				'entity_type'  => $entity_type,
				'source'       => $source,
				'support_item' => $item,
			)
		);
		return $id;
	}

	/**
	 * Require wiw_edit_suppression.
	 *
	 * @throws \DomainException Without it.
	 */
	private function require_edit(): void {
		if ( ! current_user_can( self::EDIT_CAP ) ) {
			throw new \DomainException( 'Only a user with wiw_edit_suppression may change the suppression list by hand.' );
		}
	}
}
