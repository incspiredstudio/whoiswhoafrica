<?php
/**
 * The commercial tiles of the Dashboard (REQ-181, REQ-189, REQ-272): counted deals and confirmed revenue against the sprint
 * thresholds, and the live slots ending within 30 days. All need wiw_view_dashboard_commercial, so Editors and Reviewers never see
 * them. The two sprint figures show only while Sprint tracking is on. The slots list is information only: nothing here acts
 * against a sponsor.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Platform\Dashboard\TileRegistry;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Platform\Settings\SprintScreen;

/**
 * Commercial dashboard tiles.
 */
final class DashboardTiles {

	public const CAPABILITY = 'wiw_view_dashboard_commercial';
	public const DAYS       = 30;
	public const KOBO       = 100;

	/**
	 * Hook the tiles into the dashboard.
	 */
	public static function hooks(): void {
		add_action(
			'wiw_dashboard_tiles',
			static function ( TileRegistry $tiles ): void {
				self::register_into( $tiles, SettingsService::for_site(), new ArrangementRepository(), new PlacementRepository(), static fn (): string => gmdate( 'Y-m-d' ) );
			}
		);
	}

	/**
	 * Register the tiles.
	 *
	 * @param TileRegistry          $tiles      Registry.
	 * @param SettingsService       $settings   Settings.
	 * @param ArrangementRepository $deals      Deals.
	 * @param PlacementRepository   $placements Placements.
	 * @param callable              $today      fn(): string, the date as YYYY-MM-DD.
	 */
	public static function register_into( TileRegistry $tiles, SettingsService $settings, ArrangementRepository $deals, PlacementRepository $placements, callable $today ): void {
		if ( isset( $tiles->all()['slots_ending'] ) ) {
			return;
		}
		if ( $settings->sprint_tracking_enabled() ) {
			$tiles->register(
				'deals_counted',
				__( 'Counted deals', 'whoiswho' ),
				self::CAPABILITY,
				TileRegistry::COUNT,
				static fn (): int => DealStatus::counted_total( $deals->all() )
			);
			$tiles->register(
				'revenue_confirmed',
				__( 'Confirmed revenue in NGN', 'whoiswho' ),
				self::CAPABILITY,
				TileRegistry::COUNT,
				static fn (): int => intdiv( DealStatus::confirmed_revenue( $deals->all(), Money::DEFAULT_CURRENCY ), self::KOBO )
			);
		}
		$tiles->register(
			'slots_ending',
			/* translators: %d: number of days. */
			sprintf( __( 'Sponsored slots ending in %d days', 'whoiswho' ), self::DAYS ),
			self::CAPABILITY,
			TileRegistry::LIST,
			static fn (): array => self::slots_ending( $placements, (string) $today() )
		);
	}

	/**
	 * Lines for the live slots that end within 30 days, soonest first.
	 *
	 * @param PlacementRepository $placements Placements.
	 * @param string              $today      Date as YYYY-MM-DD.
	 * @return array<int, string>
	 */
	public static function slots_ending( PlacementRepository $placements, string $today ): array {
		$limit = ( new \DateTimeImmutable( $today, new \DateTimeZone( 'UTC' ) ) )->modify( '+' . self::DAYS . ' days' )->format( 'Y-m-d' );
		$lines = array();
		foreach ( $placements->all_with_deal_state() as $row ) {
			if ( ! empty( $row['ended'] ) || ! empty( $row['deal_cancelled'] ) || (string) $row['starts_on'] > $today || (string) $row['ends_on'] < $today || (string) $row['ends_on'] > $limit ) {
				continue;
			}
			$lines[ (string) $row['ends_on'] . '-' . (int) $row['id'] ] = (string) $row['sponsor_name'] . ' (' . (string) $row['slot'] . ') ends ' . (string) $row['ends_on'];
		}
		ksort( $lines );
		return array_values( $lines );
	}
}
