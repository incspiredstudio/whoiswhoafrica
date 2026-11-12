<?php
/**
 * Curation engine for the "On the record" profile block (REQ-257, REQ-218, REQ-183). Four modes: Latest, Random, Manual and
 * Pinned then fill. Every item is re-read through PublicProfile on each call, so a profile that stopped being public, reviewed
 * or illustrated never shows, even from a cached list. It reads no payment, arrangement or label input.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Curation;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;

/**
 * Profile block curation.
 */
final class ProfileCuration {

	public const CACHE_PREFIX = 'wiw_cur_';
	public const CACHE_TTL    = 300;
	public const POOL_LIMIT   = 200;

	/**
	 * Settings.
	 *
	 * @var CurationSettings
	 */
	private CurationSettings $settings;

	/**
	 * Clock returning the date used to seed the daily shuffle: fn(): string.
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
	 * The items of a block, in display order. Only On the record has profiles.
	 *
	 * @param string $block Block key.
	 * @return array<int, PublicProfile>
	 */
	public function items( string $block ): array {
		if ( CurationSettings::ON_THE_RECORD !== $block ) {
			return array();
		}
		$setting = $this->settings->get( $block );
		$count   = max( 1, $setting['count'] );
		switch ( $setting['mode'] ) {
			case 'manual':
				return $this->eligible( $setting['pinned'], PHP_INT_MAX );
			case 'pinned_then_fill':
				$pins   = $this->eligible( $setting['pinned'], $count );
				$taken  = array_map( static fn ( PublicProfile $p ): int => $p->id, $pins );
				$fill   = $this->ordered_pool( $block, $setting, $setting['fill'] );
				$fill   = array_values( array_diff( $fill, $taken ) );
				$filled = $this->eligible( $fill, $count - count( $pins ) );
				return array_merge( $pins, $filled );
			case 'random':
				return $this->eligible( $this->ordered_pool( $block, $setting, 'random' ), $count );
			default:
				return $this->eligible( $this->ordered_pool( $block, $setting, 'latest' ), $count );
		}
	}

	/**
	 * Drop every cached candidate list (for tests and for a state change that should show at once).
	 */
	public static function flush_cache(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%'
			)
		);
		wp_cache_flush();
	}

	/**
	 * Re-check ids through the read model and keep the first $limit that are eligible, in the given order.
	 *
	 * @param array<int, int> $ids   Candidate ids.
	 * @param int             $limit Maximum items.
	 * @return array<int, PublicProfile>
	 */
	private function eligible( array $ids, int $limit ): array {
		$out  = array();
		$seen = array();
		foreach ( $ids as $id ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$id = (int) $id;
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$profile     = PublicProfile::find_by_id( $id );
			if ( null !== $profile && PubState::PUBLISHED_REVIEWED === $profile->state && null !== $profile->image_id ) {
				$out[] = $profile;
			}
		}
		return $out;
	}

	/**
	 * The ordered candidate ids for Latest or Random, cached under a key that includes the saved settings.
	 *
	 * @param string               $block   Block.
	 * @param array<string, mixed> $setting Saved settings.
	 * @param string               $order   latest or random.
	 * @return array<int, int>
	 */
	private function ordered_pool( string $block, array $setting, string $order ): array {
		$date = (string) ( $this->today )();
		$hash = md5( (string) wp_json_encode( $setting ) . '|' . $order . '|' . $date );
		$key  = self::CACHE_PREFIX . $block . '_' . $hash;
		$ids  = get_transient( $key );
		if ( is_array( $ids ) ) {
			return array_map( 'intval', $ids );
		}
		$ids = $this->latest_ids();
		if ( 'random' === $order ) {
			$seed = $block . '|' . $date;
			usort(
				$ids,
				static function ( int $a, int $b ) use ( $seed ): int {
					$cmp = strcmp( md5( $a . '|' . $seed ), md5( $b . '|' . $seed ) );
					return 0 !== $cmp ? $cmp : $a <=> $b;
				}
			);
		}
		set_transient( $key, $ids, self::CACHE_TTL );
		return $ids;
	}

	/**
	 * Candidate ids, newest review first then newest post id: published, reviewed, with a portrait.
	 *
	 * @return array<int, int>
	 */
	private function latest_ids(): array {
		global $wpdb;
		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN " . StateRepository::table() . " s ON s.profile_id = p.ID INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value > 0 WHERE p.post_type = %s AND p.post_status = %s AND s.pub_state = %s ORDER BY s.reviewed_at DESC, p.ID DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
				PublicProfile::IMAGE_META,
				ProfileType::POST_TYPE,
				'publish',
				PubState::PUBLISHED_REVIEWED,
				self::POOL_LIMIT
			)
		);
		return array_map( 'intval', (array) $rows );
	}
}
