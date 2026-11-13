<?php
/**
 * The staff list of nominations (REQ-267, REQ-031, REQ-202): every nomination in every stage and status, filtered by stage,
 * status, category and source. Staff only: it carries the nominee name and category, never the nominator or Contact email,
 * so the list is safe to print, and the screen that shows it checks wiw_manage_awards.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

/**
 * Nomination list query.
 */
final class NominationList {

	public const PER_PAGE = 50;

	/**
	 * Filters that are accepted, with their allowed values (null: a number).
	 */
	public const FILTERS = array(
		'stage'    => AwardsTables::STAGES,
		'status'   => AwardsTables::STATUSES,
		'source'   => AwardsTables::SOURCES,
		'category' => null,
	);

	/**
	 * Keep only filters that carry an allowed value.
	 *
	 * @param array<string, string> $raw Raw values by filter name.
	 * @return array<string, string>
	 */
	public static function clean( array $raw ): array {
		$out = array();
		foreach ( self::FILTERS as $name => $allowed ) {
			$value = $raw[ $name ] ?? '';
			if ( '' === $value ) {
				continue;
			}
			if ( null === $allowed ? ctype_digit( $value ) && (int) $value > 0 : in_array( $value, $allowed, true ) ) {
				$out[ $name ] = $value;
			}
		}
		return $out;
	}

	/**
	 * One page of nominations, newest first.
	 *
	 * @param array<string, string> $filters Cleaned filters.
	 * @param int                   $page    Page, from 1.
	 * @return array{rows: array<int, array<string, mixed>>, total: int, pages: int}
	 */
	public function run( array $filters, int $page = 1 ): array {
		global $wpdb;
		$filters = self::clean( $filters );
		$page    = max( 1, $page );
		$where   = array( '1=1' );
		$args    = array();
		foreach ( array(
			'stage'    => 'n.stage',
			'status'   => 'n.status',
			'source'   => 'n.source',
			'category' => 'n.category_id',
		) as $name => $column ) {
			if ( isset( $filters[ $name ] ) ) {
				$where[] = $column . ( 'category' === $name ? ' = %d' : ' = %s' );
				$args[]  = 'category' === $name ? (int) $filters[ $name ] : $filters[ $name ];
			}
		}
		$n     = AwardsTables::table( AwardsTables::NOMINATIONS );
		$c     = AwardsTables::table( AwardsTables::CATEGORIES );
		$from  = " FROM {$n} n INNER JOIN {$c} c ON c.id = n.category_id LEFT JOIN {$wpdb->posts} p ON p.ID = n.profile_id WHERE " . implode( ' AND ', $where );
		$sql   = 'SELECT COUNT(*)' . $from;
		$cnt   = array() === $args ? $sql : $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- fixed names; values prepared.
		$total = (int) $wpdb->get_var( $cnt ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$sql   = 'SELECT n.id, n.category_id, n.profile_id, n.stage, n.status, n.source, n.created_at, c.title AS category, p.post_title AS nominee, CHAR_LENGTH( n.contact_email ) > 0 AS has_contact' . $from . ' ORDER BY n.id DESC LIMIT %d OFFSET %d';
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- fixed names; values prepared.
		return array(
			'rows'  => $rows,
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / self::PER_PAGE ) ),
		);
	}
}
