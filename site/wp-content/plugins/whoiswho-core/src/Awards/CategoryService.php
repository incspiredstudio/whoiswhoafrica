<?php
/**
 * Award categories: add, rename, reorder, archive and remove, under the five award groups (REQ-266). Removal is refused while
 * any nomination uses the category. Every change is audited and purges the pages that list categories. A category holds a group,
 * a title and an order only: eligibility and judging criteria are page copy (REQ-200, REQ-260).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Category service.
 */
final class CategoryService {

	public const CAP       = 'wiw_manage_awards';
	public const MAX_TITLE = 190;

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
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( AuditLogger::for_site() );
	}

	/**
	 * Add a category at the end of its group.
	 *
	 * @param string $group Group key (one of AwardsTables::GROUPS).
	 * @param string $title Title.
	 * @return int New category id.
	 * @throws \DomainException When not allowed or the input is invalid.
	 * @throws \RuntimeException When the row cannot be saved.
	 */
	public function add( string $group, string $title ): int {
		$this->require_cap();
		if ( ! isset( AwardsTables::GROUPS[ $group ] ) ) {
			throw new \DomainException( 'Choose one of the five award groups.' );
		}
		$title = $this->clean_title( $title );
		global $wpdb;
		$table = AwardsTables::table( AwardsTables::CATEGORIES );
		$next  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ' . $table . ' WHERE group_key = %s', $group ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'group_key'  => $group,
				'title'      => $title,
				'sort_order' => $next,
				'archived'   => 0,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( $id < 1 ) {
			throw new \RuntimeException( 'The category could not be saved.' );
		}
		$this->audit->log(
			'award_category.add',
			$this->subject( $id ),
			null,
			array(
				'group' => $group,
				'title' => $title,
			)
		);
		$this->changed();
		return $id;
	}

	/**
	 * Rename a category.
	 *
	 * @param int    $id    Category.
	 * @param string $title New title.
	 * @throws \DomainException When not allowed, unknown or invalid.
	 */
	public function rename( int $id, string $title ): void {
		$this->require_cap();
		$row   = $this->require_row( $id );
		$title = $this->clean_title( $title );
		if ( $title === $row['title'] ) {
			return;
		}
		$this->update( $id, array( 'title' => $title ) );
		$this->audit->log( 'award_category.rename', $this->subject( $id ), array( 'title' => $row['title'] ), array( 'title' => $title ) );
		$this->changed();
	}

	/**
	 * Put the categories of one group in the given order. Ids not in the group are ignored; the group's other categories follow.
	 *
	 * @param string $group Group key.
	 * @param int[]  $ids   Ids in the new order.
	 * @throws \DomainException When not allowed or the group is unknown.
	 */
	public function reorder( string $group, array $ids ): void {
		$this->require_cap();
		if ( ! isset( AwardsTables::GROUPS[ $group ] ) ) {
			throw new \DomainException( 'Choose one of the five award groups.' );
		}
		$current = array_column( $this->in_group( $group, true ), 'id' );
		$wanted  = array();
		foreach ( $ids as $id ) {
			if ( in_array( (int) $id, $current, true ) && ! in_array( (int) $id, $wanted, true ) ) {
				$wanted[] = (int) $id;
			}
		}
		$final = array_merge( $wanted, array_values( array_diff( $current, $wanted ) ) );
		if ( $final === $current ) {
			return;
		}
		foreach ( $final as $position => $id ) {
			$this->update( (int) $id, array( 'sort_order' => $position + 1 ) );
		}
		$this->audit->log(
			'award_category.reorder',
			array(
				'type' => 'award_group',
				'id'   => $group,
			),
			$current,
			$final
		);
		$this->changed();
	}

	/**
	 * Archive or restore a category. An archived category stays on its nominations but is not offered on the Nominate form.
	 *
	 * @param int  $id       Category.
	 * @param bool $archived Archived or not.
	 * @throws \DomainException When not allowed or unknown.
	 */
	public function archive( int $id, bool $archived ): void {
		$this->require_cap();
		$row = $this->require_row( $id );
		if ( (int) $row['archived'] === (int) $archived ) {
			return;
		}
		$this->update( $id, array( 'archived' => $archived ? 1 : 0 ) );
		$this->audit->log( $archived ? 'award_category.archive' : 'award_category.restore', $this->subject( $id ), null, array( 'title' => $row['title'] ) );
		$this->changed();
	}

	/**
	 * Remove a category. Refused while any nomination uses it.
	 *
	 * @param int $id Category.
	 * @throws \DomainException When not allowed, unknown, or in use.
	 */
	public function remove( int $id ): void {
		$this->require_cap();
		$row   = $this->require_row( $id );
		$count = $this->nomination_count( $id );
		if ( $count > 0 ) {
			$this->audit->log( 'award_category.remove_refused', $this->subject( $id ), null, array( 'nominations' => $count ) );
			throw new \DomainException( 'This category has nominations, so it cannot be removed. Archive it instead.' );
		}
		global $wpdb;
		$wpdb->delete( AwardsTables::table( AwardsTables::CATEGORIES ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->audit->log(
			'award_category.remove',
			$this->subject( $id ),
			array(
				'title' => $row['title'],
				'group' => $row['group_key'],
			),
			null
		);
		$this->changed();
	}

	/**
	 * Number of nominations that use a category.
	 *
	 * @param int $id Category.
	 */
	public function nomination_count( int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE category_id = %d', $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * Categories of one group in order.
	 *
	 * @param string $group            Group key.
	 * @param bool   $include_archived Include archived categories.
	 * @return array<int, array<string, mixed>>
	 */
	public function in_group( string $group, bool $include_archived = false ): array {
		global $wpdb;
		$sql  = 'SELECT id, group_key, title, sort_order, archived FROM ' . AwardsTables::table( AwardsTables::CATEGORIES ) . ' WHERE group_key = %s';
		$sql .= $include_archived ? '' : ' AND archived = 0';
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY sort_order, id', $group ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		foreach ( $rows as &$row ) {
			$row['id'] = (int) $row['id'];
		}
		return $rows;
	}

	/**
	 * Categories under every group, in group order, for the Awards page, the Nominate guidance and the home marquee.
	 *
	 * @param bool $include_archived Include archived categories.
	 * @return array<string, array{label: string, categories: array<int, array<string, mixed>>}>
	 */
	public function grouped( bool $include_archived = false ): array {
		$out = array();
		foreach ( AwardsTables::GROUPS as $key => $label ) {
			$out[ $key ] = array(
				'label'      => $label,
				'categories' => $this->in_group( $key, $include_archived ),
			);
		}
		return $out;
	}

	/**
	 * A category row.
	 *
	 * @param int $id Category.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AwardsTables::table( AwardsTables::CATEGORIES ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Row or refusal.
	 *
	 * @param int $id Category.
	 * @return array<string, mixed>
	 * @throws \DomainException When unknown.
	 */
	private function require_row( int $id ): array {
		$row = $this->find( $id );
		if ( null === $row ) {
			throw new \DomainException( 'That category does not exist.' );
		}
		return $row;
	}

	/**
	 * Update columns.
	 *
	 * @param int                  $id     Category.
	 * @param array<string, mixed> $values Columns.
	 */
	private function update( int $id, array $values ): void {
		global $wpdb;
		$wpdb->update( AwardsTables::table( AwardsTables::CATEGORIES ), $values, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Validate a title.
	 *
	 * @param string $title Raw title.
	 * @throws \DomainException When empty or too long.
	 */
	private function clean_title( string $title ): string {
		$title = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $title ) ) );
		if ( '' === $title ) {
			throw new \DomainException( 'Give the category a title.' );
		}
		if ( strlen( $title ) > self::MAX_TITLE ) {
			throw new \DomainException( 'The title is too long.' );
		}
		return $title;
	}

	/**
	 * Audit subject.
	 *
	 * @param int $id Category.
	 * @return array{type: string, id: int}
	 */
	private function subject( int $id ): array {
		return array(
			'type' => 'award_category',
			'id'   => $id,
		);
	}

	/**
	 * Deny by default.
	 *
	 * @throws \DomainException When the user may not manage awards.
	 */
	private function require_cap(): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to manage award categories.' );
		}
	}

	/**
	 * Tell the page cache that the pages listing categories changed.
	 */
	private function changed(): void {
		/**
		 * Award categories changed.
		 */
		do_action( 'wiw_award_categories_changed' );
		do_action( 'wiw_purge_urls', array( home_url( '/' ), home_url( '/awards/' ), home_url( '/nominate/' ) ), false );
	}
}
