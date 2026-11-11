<?php
/**
 * Daily profile view counts (REQ-236): one row per profile per day with a count, so staff can tell a subject how often their
 * page was viewed. A view is counted only when the profile route served the page (200); a 404 or 410 never reaches the counter.
 * Known bots and requests with no user agent are not counted, and staff previews are not counted. No IP address, user agent or
 * any identifier is stored: the table holds a profile id, a day and a number. The daily row is itself the roll-up, so there is
 * no separate job.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Views;

use WhoIsWho\Core\Directory\Url\ProfileRoute;

/**
 * View counter.
 */
final class ViewCounter {

	public const TABLE = 'wiw_view_counts_daily';

	public const TABLE_DEFINITION = 'profile_id bigint(20) unsigned NOT NULL,
		view_day date NOT NULL,
		views int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (profile_id, view_day),
		KEY view_day (view_day)';

	/**
	 * Words in a user agent that mark a crawler or script.
	 */
	private const BOT_PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|preview|fetch|monitor|uptime|curl|wget|python|java\/|go-http|httpclient|headless|lighthouse|pingdom|scrapy|axios|node-fetch|libwww|okhttp/i';

	/**
	 * Table name with the site prefix.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Hook the counter after the profile route has decided the response.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'count_request' ), 5 );
	}

	/**
	 * Count the request when it is a served profile page.
	 */
	public function count_request(): void {
		$id = (int) get_query_var( ProfileRoute::ID_VAR );
		if ( $id < 1 || is_404() || ( is_user_logged_in() && current_user_can( 'read_private_wiw_profiles' ) ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return;
		}
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only matched against a pattern, never stored.
		$this->record( $id, $agent );
	}

	/**
	 * Whether a user agent is a known bot, or missing.
	 *
	 * @param string $agent User agent string.
	 */
	public static function is_bot( string $agent ): bool {
		return '' === trim( $agent ) || 1 === preg_match( self::BOT_PATTERN, $agent );
	}

	/**
	 * Count one view. The user agent is used to filter bots and is not stored.
	 *
	 * @param int    $profile_id Profile post id.
	 * @param string $agent      User agent.
	 * @param string $day        Day "Y-m-d" (UTC); today by default.
	 * @return bool Whether a view was counted.
	 */
	public function record( int $profile_id, string $agent, string $day = '' ): bool {
		global $wpdb;
		if ( self::is_bot( $agent ) ) {
			return false;
		}
		$day = '' === $day ? gmdate( 'Y-m-d' ) : $day;
		return false !== $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table() . ' (profile_id, view_day, views) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE views = views + 1', $profile_id, $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
	}

	/**
	 * Views per day for a profile, newest first (read-only, for the Profile screen history panel).
	 *
	 * @param int $profile_id Profile post id.
	 * @param int $days       Days to return at most.
	 * @return array<string, int> Day => views.
	 */
	public function daily( int $profile_id, int $days = 90 ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT view_day, views FROM ' . self::table() . ' WHERE profile_id = %d ORDER BY view_day DESC LIMIT %d', $profile_id, max( 1, $days ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (string) $row['view_day'] ] = (int) $row['views'];
		}
		return $out;
	}

	/**
	 * All-time views of a profile.
	 *
	 * @param int $profile_id Profile post id.
	 */
	public function total( int $profile_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(views), 0) FROM ' . self::table() . ' WHERE profile_id = %d', $profile_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
	}
}
