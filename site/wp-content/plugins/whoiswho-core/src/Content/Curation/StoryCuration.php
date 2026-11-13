<?php
/**
 * Curation engine for the story blocks on Home and the Stories page (REQ-257, REQ-263, REQ-183). Both blocks use the same modes
 * as the profile block (Latest, Random, Manual, Pinned then fill) and put Featured stories first when the block says so. Every
 * story is read through PublicStory, so only published stories can show, even if a pin or a cached list is stale.
 *
 * The sponsored slot comes back separately and never moves anything: the list of items is built without looking at any placement,
 * so creating or ending a paid arrangement cannot change which stories a block shows or in what order. The slot story may also be
 * in the list; the theme puts the slot at its fixed position and skips that story in the list.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Curation;

use WhoIsWho\Core\Content\PublicStory;

/**
 * Story block curation.
 */
final class StoryCuration {

	private const SURFACES = array(
		CurationSettings::STORIES_HOME => 'home',
		CurationSettings::STORIES_PAGE => 'stories',
	);

	/**
	 * Settings.
	 *
	 * @var CurationSettings
	 */
	private CurationSettings $settings;

	/**
	 * Date used to seed the daily shuffle: fn(): string.
	 *
	 * @var callable
	 */
	private $today;

	/**
	 * Constructor.
	 *
	 * @param CurationSettings $settings Settings.
	 * @param callable|null    $today    Date source, "Y-m-d".
	 */
	public function __construct( CurationSettings $settings, ?callable $today = null ) {
		$this->settings = $settings;
		$this->today    = $today ?? static fn (): string => gmdate( 'Y-m-d' );
	}

	/**
	 * The stories of a block in display order, and the sponsored slot story for its surface.
	 *
	 * @param string $block stories_home or stories_page.
	 * @return array{items: array<int, array<string, mixed>>, slot: array<string, mixed>|null}
	 */
	public function items( string $block ): array {
		if ( ! isset( self::SURFACES[ $block ] ) ) {
			return array(
				'items' => array(),
				'slot'  => null,
			);
		}
		$setting  = $this->settings->get( $block );
		$count    = max( 1, $setting['count'] );
		$live_ids = PublicStory::public_ids();
		$sticky   = $setting['featured_first'] ? array_map( 'intval', (array) get_option( 'sticky_posts', array() ) ) : array();
		switch ( $setting['mode'] ) {
			case 'manual':
				$ids = $this->pinned( $setting['pinned'], PHP_INT_MAX );
				break;
			case 'pinned_then_fill':
				$pins = $this->pinned( $setting['pinned'], $count );
				$fill = array_values( array_diff( $this->ordered( $live_ids, $setting['fill'], $block, $sticky ), $pins ) );
				$ids  = array_merge( $pins, array_slice( $fill, 0, $count - count( $pins ) ) );
				break;
			default:
				$ids = array_slice( $this->ordered( $live_ids, $setting['mode'], $block, $sticky ), 0, $count );
		}
		$items = array();
		foreach ( $ids as $id ) {
			$card = PublicStory::card_if_public( $id );
			if ( null !== $card ) {
				$items[] = $card;
			}
		}
		return array(
			'items' => $items,
			'slot'  => PublicStory::slot( self::SURFACES[ $block ] ),
		);
	}

	/**
	 * Pinned ids that are public now, in the chosen order, without repeats.
	 *
	 * @param array<int, int> $pinned Pinned ids.
	 * @param int             $limit  Maximum ids.
	 * @return array<int, int>
	 */
	private function pinned( array $pinned, int $limit ): array {
		$out = array();
		foreach ( $pinned as $id ) {
			$id = (int) $id;
			if ( count( $out ) >= $limit ) {
				break;
			}
			if ( ! in_array( $id, $out, true ) && null !== PublicStory::card_if_public( $id ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Order the public ids: latest (as given) or a daily shuffle, with Featured stories first when asked. Nothing here reads a
	 * placement or a payment.
	 *
	 * @param array<int, int> $live_ids Public ids, newest first.
	 * @param string          $order  latest or random.
	 * @param string          $block  Block, part of the shuffle seed.
	 * @param array<int, int> $sticky Featured ids to put first (empty when the block does not ask for it).
	 * @return array<int, int>
	 */
	private function ordered( array $live_ids, string $order, string $block, array $sticky ): array {
		$ids = $live_ids;
		if ( 'random' === $order ) {
			$seed = $block . '|' . (string) ( $this->today )();
			usort(
				$ids,
				static function ( int $a, int $b ) use ( $seed ): int {
					$cmp = strcmp( md5( $a . '|' . $seed ), md5( $b . '|' . $seed ) );
					return 0 !== $cmp ? $cmp : $a <=> $b;
				}
			);
		}
		if ( array() === $sticky ) {
			return $ids;
		}
		return array_merge( array_values( array_intersect( $ids, $sticky ) ), array_values( array_diff( $ids, $sticky ) ) );
	}
}
