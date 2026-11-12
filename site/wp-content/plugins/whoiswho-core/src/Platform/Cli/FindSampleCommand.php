<?php
/**
 * The content:find-sample command (php bin/wiw.php content:find-sample): lists sample content still on the site and exits non-zero while any remains, so the
 * migration smoke checks can prove "no sample content remains" (REQ-208, REQ-052).
 *
 * How sample content is marked (decision recorded in the pull request): post meta or term meta `_wiw_sample` = 1 on any post,
 * media item, event, winner, category, year or other record; or a title/name that starts with "[Sample]". Page bodies that still
 * carry the legal draft note (class notice--draft or the text "Draft note:") are reported too. Only identifiers are printed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- a one-off command line scan of whole tables; caching would hide what is left.

namespace WhoIsWho\Core\Platform\Cli;

/**
 * The content:find-sample command.
 */
final class FindSampleCommand implements Command {

	public const META_KEY     = '_wiw_sample';
	public const TITLE_PREFIX = '[Sample]';

	/**
	 * Command name.
	 */
	public function name(): string {
		return 'content:find-sample';
	}

	/**
	 * Help text.
	 */
	public function description(): string {
		return 'List sample-tagged content and draft notes still present; exit 1 if any.';
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments (none).
	 * @param Output   $output Output.
	 * @return int 0 when clean, 1 when anything remains.
	 */
	public function run( array $args, Output $output ): int {
		unset( $args );
		$found = array_merge( $this->tagged_posts(), $this->tagged_terms(), $this->draft_notes() );
		if ( array() === $found ) {
			$output->line( 'No sample content or draft notes found.' );
			return 0;
		}
		foreach ( $found as $line ) {
			$output->line( $line );
		}
		$output->line( count( $found ) . ' item(s) remain.' );
		return 1;
	}

	/**
	 * Posts of any type and status, media included, that carry the marker.
	 *
	 * @return string[]
	 */
	private function tagged_posts(): array {
		global $wpdb;
		$like = $wpdb->esc_like( self::TITLE_PREFIX ) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_type, p.post_status FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_status <> 'trash' AND p.post_status <> 'auto-draft' AND ( m.meta_value = '1' OR p.post_title LIKE %s )
				ORDER BY p.ID",
				self::META_KEY,
				$like
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = sprintf( 'sample %s #%d (%s)', $row['post_type'], (int) $row['ID'], $row['post_status'] );
		}
		return $out;
	}

	/**
	 * Terms (categories, years, tags) that carry the marker.
	 *
	 * @return string[]
	 */
	private function tagged_terms(): array {
		global $wpdb;
		$like = $wpdb->esc_like( self::TITLE_PREFIX ) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT t.term_id, tt.taxonomy FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				LEFT JOIN {$wpdb->termmeta} m ON m.term_id = t.term_id AND m.meta_key = %s
				WHERE m.meta_value = '1' OR t.name LIKE %s
				ORDER BY t.term_id",
				self::META_KEY,
				$like
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = sprintf( 'sample term #%d (%s)', (int) $row['term_id'], $row['taxonomy'] );
		}
		return $out;
	}

	/**
	 * Pages whose body still has a draft note.
	 *
	 * @return string[]
	 */
	private function draft_notes(): array {
		global $wpdb;
		$rows = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
			AND ( post_content LIKE '%notice--draft%' OR post_content LIKE '%Draft note:%' ) ORDER BY ID"
		);
		$out  = array();
		foreach ( (array) $rows as $id ) {
			$out[] = 'draft note on page #' . (int) $id;
		}
		return $out;
	}
}
