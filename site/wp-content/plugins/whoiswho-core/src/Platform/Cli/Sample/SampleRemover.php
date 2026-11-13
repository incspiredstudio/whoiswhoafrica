<?php
/**
 * Removes what SampleSeeder made: every post and term carrying `_wiw_sample`, the award categories and nominations it recorded, and
 * puts back the settings it changed (static front page, curation, the Nominations switch). Content that was not marked is never
 * touched, and running it twice is harmless (REQ-208, REQ-052).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli\Sample;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Settings\SettingsService;

/**
 * Sample remover.
 */
final class SampleRemover {

	/**
	 * Staff user that deletes (an Administrator).
	 *
	 * @var int
	 */
	private int $actor;

	/**
	 * Constructor.
	 *
	 * @param int $actor Staff user id.
	 */
	public function __construct( int $actor ) {
		$this->actor = $actor;
	}

	/**
	 * Remove all sample content.
	 *
	 * @param Output $output Output.
	 * @return array<string, int> What was removed, by kind.
	 */
	public function remove( Output $output ): array {
		$removed = array();
		do {
			$chunk = $this->remove_chunk( null );
			foreach ( $chunk['removed'] as $kind => $count ) {
				$removed[ $kind ] = ( $removed[ $kind ] ?? 0 ) + $count;
			}
		} while ( ! $chunk['done'] );
		foreach ( array( 'posts', 'terms', 'award categories', 'nominations' ) as $kind ) {
			$output->line( sprintf( 'Removed %d %s.', $removed[ $kind ] ?? 0, $kind ) );
		}
		return array(
			'posts'            => $removed['posts'] ?? 0,
			'terms'            => $removed['terms'] ?? 0,
			'award categories' => $removed['award categories'] ?? 0,
			'nominations'      => $removed['nominations'] ?? 0,
		);
	}

	/**
	 * Remove sample content for at most about this many seconds, so a shared host with a short time limit can do it in several
	 * requests. Safe to repeat: the markers say what is left, and the settings are put back only on the first call.
	 *
	 * @param float|null $seconds Time budget; null means no limit.
	 * @return array{done: bool, removed: array<string, int>, left: int} Whether nothing is left, what this call removed, and how many sample posts and terms remain.
	 */
	public function remove_chunk( ?float $seconds ): array {
		wp_set_current_user( $this->actor );
		$until    = null === $seconds ? null : microtime( true ) + $seconds;
		$over     = static fn (): bool => null !== $until && microtime( true ) >= $until;
		$registry = SampleMarker::registry();
		$removed  = array(
			'posts'            => 0,
			'terms'            => 0,
			'award categories' => 0,
			'nominations'      => 0,
		);
		if ( empty( $registry['restored'] ) ) {
			$this->restore( (array) ( $registry['prev'] ?? array() ) );
			$removed['nominations']      = $this->delete_rows( AwardsTables::NOMINATIONS, array_values( (array) ( $registry['nominations'] ?? array() ) ), 'id', array( AwardsTables::CHANGES => 'nomination_id' ) );
			$removed['award categories'] = $this->delete_rows( AwardsTables::CATEGORIES, array_values( (array) ( $registry['categories'] ?? array() ) ), 'id', array() );
			$registry['restored']        = true;
			unset( $registry['nominations'], $registry['categories'] );
			SampleMarker::save_registry( $registry );
		}
		foreach ( SampleMarker::all_post_ids() as $id ) {
			if ( ProfileType::POST_TYPE === get_post_type( $id ) ) {
				global $wpdb;
				$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- clean-up of a sample profile's own row.
			}
			if ( false !== wp_delete_post( $id, true ) ) {
				++$removed['posts'];
			}
			if ( $over() ) {
				break;
				// At least one item per call, so a call with no time left still makes progress.
			}
		}
		$left = count( SampleMarker::all_post_ids() );
		if ( 0 === $left ) {
			foreach ( SampleMarker::all_terms( Taxonomies::all() ) as $term_id => $taxonomy ) {
				if ( true === wp_delete_term( $term_id, $taxonomy ) ) {
					++$removed['terms'];
				}
				if ( $over() ) {
					break;
				}
			}
			$left = count( SampleMarker::all_terms( Taxonomies::all() ) );
		}
		if ( 0 === $left ) {
			delete_option( SampleMarker::REGISTRY );
			flush_rewrite_rules( false );
		}
		return array(
			'done'    => 0 === $left,
			'removed' => $removed,
			'left'    => $left,
		);
	}

	/**
	 * Put back the settings the seeder changed.
	 *
	 * @param array<string, mixed> $prev Earlier values by name.
	 */
	private function restore( array $prev ): void {
		foreach ( array( 'show_on_front', 'page_on_front' ) as $option ) {
			if ( array_key_exists( 'option:' . $option, $prev ) ) {
				null === $prev[ 'option:' . $option ] ? delete_option( $option ) : update_option( $option, $prev[ 'option:' . $option ] );
			}
		}
		foreach ( CurationSettings::BLOCKS as $block ) {
			if ( array_key_exists( 'curation:' . $block, $prev ) ) {
				$name = CurationSettings::OPTION_PREFIX . $block;
				null === $prev[ 'curation:' . $block ] ? delete_option( $name ) : update_option( $name, $prev[ 'curation:' . $block ], false );
			}
		}
		if ( array_key_exists( 'nominations', $prev ) ) {
			$settings = SettingsService::for_site();
			if ( $settings->nominations_enabled() !== (bool) $prev['nominations'] ) {
				$settings->set( SettingsService::NOMINATIONS_ENABLED, (bool) $prev['nominations'], 'Sample data removed' );
			}
		}
	}

	/**
	 * Delete rows by id from an Awards table, and their children.
	 *
	 * @param string                $table    Table constant.
	 * @param array<int, mixed>     $ids      Row ids.
	 * @param string                $column   Id column.
	 * @param array<string, string> $children Child table constant => its column for the id.
	 * @return int Rows deleted.
	 */
	private function delete_rows( string $table, array $ids, string $column, array $children ): int {
		global $wpdb;
		$count = 0;
		foreach ( array_filter( array_map( 'intval', $ids ) ) as $id ) {
			foreach ( $children as $child => $child_column ) {
				$wpdb->delete( AwardsTables::table( $child ), array( $child_column => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- clean-up of sample rows by id.
			}
			if ( $wpdb->delete( AwardsTables::table( $table ), array( $column => $id ), array( '%d' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- as above.
				++$count;
			}
		}
		return $count;
	}
}
