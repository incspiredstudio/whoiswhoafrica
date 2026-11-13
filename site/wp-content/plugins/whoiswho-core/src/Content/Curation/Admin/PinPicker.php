<?php
/**
 * The picker and preview behind the Home page settings pins (REQ-257, REQ-218). It lists only items a visitor could see now
 * (a reviewed, published profile with a portrait; a public story), so a hidden or pending-review profile cannot be picked, and it
 * previews a block by running the same curation engines the public page uses, with the unsaved setting swapped in for the
 * duration of the call. Nothing is saved by previewing.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Curation\Admin;

use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Content\Curation\ProfileCuration;
use WhoIsWho\Core\Content\Curation\StoryCuration;
use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Pin picker.
 */
final class PinPicker {

	public const DEFAULT_LIMIT = 20;

	/**
	 * Eligible items whose title contains the term, newest first.
	 *
	 * @param string $block Block key.
	 * @param string $term  Search text; empty lists the newest.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array{id: int, label: string}>
	 */
	public function candidates( string $block, string $term = '', int $limit = self::DEFAULT_LIMIT ): array {
		$limit = max( 1, min( 100, $limit ) );
		$term  = trim( $term );
		if ( CurationSettings::ON_THE_RECORD === $block ) {
			return $this->profile_candidates( $term, $limit );
		}
		if ( in_array( $block, array( CurationSettings::STORIES_HOME, CurationSettings::STORIES_PAGE ), true ) ) {
			return $this->story_candidates( $term, $limit );
		}
		return array();
	}

	/**
	 * Whether an item may be pinned to a block now.
	 *
	 * @param string $block Block key.
	 * @param int    $id    Post id.
	 */
	public function can_pin( string $block, int $id ): bool {
		if ( CurationSettings::ON_THE_RECORD === $block ) {
			return null !== $this->eligible_profile( $id );
		}
		if ( in_array( $block, array( CurationSettings::STORIES_HOME, CurationSettings::STORIES_PAGE ), true ) ) {
			return null !== PublicStory::card_if_public( $id );
		}
		return false;
	}

	/**
	 * The pinned items in their chosen order, each with a label and whether it is public now (a pin that stopped being public
	 * stays listed so the editor can remove it; the public block drops it).
	 *
	 * @param string          $block Block key.
	 * @param array<int, int> $ids   Pinned ids.
	 * @return array<int, array{id: int, label: string, public: bool}>
	 */
	public function describe( string $block, array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$id    = (int) $id;
			$title = get_the_title( $id );
			$out[] = array(
				'id'     => $id,
				'label'  => '' !== $title ? $title : '#' . $id,
				'public' => $this->can_pin( $block, $id ),
			);
		}
		return $out;
	}

	/**
	 * The items the block would show with this setting, in display order, through the same engine as the public page.
	 *
	 * @param string               $block   Block key.
	 * @param array<string, mixed> $setting A setting as CurationSettings::get returns it.
	 * @return array<int, array{id: int, label: string}>
	 */
	public function preview( string $block, array $setting ): array {
		if ( ! in_array( $block, CurationSettings::BLOCKS, true ) ) {
			return array();
		}
		$hook = 'pre_option_' . CurationSettings::OPTION_PREFIX . $block;
		$swap = static fn () => $setting;
		add_filter( $hook, $swap );
		try {
			$settings = new CurationSettings( AuditLogger::for_site() );
			if ( CurationSettings::ON_THE_RECORD === $block ) {
				$rows = array();
				foreach ( ( new ProfileCuration( $settings ) )->items( $block ) as $profile ) {
					$rows[] = array(
						'id'    => $profile->id,
						'label' => $profile->name,
					);
				}
				return $rows;
			}
			$rows   = array();
			$result = ( new StoryCuration( $settings ) )->items( $block );
			foreach ( $result['items'] as $card ) {
				$rows[] = array(
					'id'    => (int) $card['id'],
					'label' => (string) $card['title'],
				);
			}
			return $rows;
		} finally {
			remove_filter( $hook, $swap );
		}//end try
	}

	/**
	 * The profile if it can show in the On the record block now.
	 *
	 * @param int $id Post id.
	 */
	private function eligible_profile( int $id ): ?PublicProfile {
		$profile = PublicProfile::find_by_id( $id );
		return null !== $profile && PubState::PUBLISHED_REVIEWED === $profile->state && null !== $profile->image_id ? $profile : null;
	}

	/**
	 * Profile candidates.
	 *
	 * @param string $term  Search text.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array{id: int, label: string}>
	 */
	private function profile_candidates( string $term, int $limit ): array {
		global $wpdb;
		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN " . StateRepository::table() . " s ON s.profile_id = p.ID INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value > 0 WHERE p.post_type = %s AND p.post_status = %s AND s.pub_state = %s AND p.post_title LIKE %s ORDER BY s.reviewed_at DESC, p.ID DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
				PublicProfile::IMAGE_META,
				ProfileType::POST_TYPE,
				'publish',
				PubState::PUBLISHED_REVIEWED,
				'%' . $wpdb->esc_like( $term ) . '%',
				$limit * 3
			)
		);
		$out  = array();
		foreach ( (array) $rows as $id ) {
			$profile = $this->eligible_profile( (int) $id );
			if ( null !== $profile ) {
				$out[] = array(
					'id'    => $profile->id,
					'label' => $profile->name,
				);
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Story candidates.
	 *
	 * @param string $term  Search text.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array{id: int, label: string}>
	 */
	private function story_candidates( string $term, int $limit ): array {
		$out = array();
		foreach ( PublicStory::public_ids() as $id ) {
			$card = PublicStory::card_if_public( $id );
			if ( null === $card ) {
				continue;
			}
			$title = (string) $card['title'];
			if ( '' !== $term && false === stripos( $title, $term ) ) {
				continue;
			}
			$out[] = array(
				'id'    => (int) $card['id'],
				'label' => $title,
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}
}
