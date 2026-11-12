<?php
/**
 * The read-only metrics panel on the Review queue (REQ-149): decisions per week, the age of the oldest item still waiting, and
 * the count of each rejection reason. No personal data and no per-reviewer ranking.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Metrics;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;

/**
 * Review metrics.
 */
final class ReviewMetrics {

	/**
	 * Rejection reasons with their counts, most common first.
	 *
	 * @return array<string, int>
	 */
	public function rejection_reasons(): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			$wpdb->prepare( 'SELECT reason_code, COUNT(*) AS n FROM ' . DecisionLog::table() . ' WHERE decision = %s GROUP BY reason_code ORDER BY n DESC, reason_code ASC', DecisionLog::REJECTED ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (string) $row['reason_code'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Decisions in each of the last weeks, oldest week first. A week starts on Monday (UTC).
	 *
	 * @param int $weeks How many weeks.
	 * @return array<int, array{week: string, decisions: int}>
	 */
	public function decisions_per_week( int $weeks = 8 ): array {
		global $wpdb;
		$weeks  = max( 1, $weeks );
		$monday = strtotime( 'monday this week 00:00:00 UTC' );
		$start  = $monday - ( $weeks - 1 ) * 7 * DAY_IN_SECONDS;
		$rows   = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			$wpdb->prepare( 'SELECT decided_at FROM ' . DecisionLog::table() . ' WHERE decided_at >= %s', gmdate( 'Y-m-d H:i:s', $start ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
		);
		$out    = array();
		for ( $i = 0; $i < $weeks; $i++ ) {
			$out[ $i ] = array(
				'week'      => gmdate( 'Y-m-d', $start + $i * 7 * DAY_IN_SECONDS ),
				'decisions' => 0,
			);
		}
		foreach ( $rows as $decided_at ) {
			$index = (int) floor( ( strtotime( $decided_at . ' UTC' ) - $start ) / ( 7 * DAY_IN_SECONDS ) );
			if ( isset( $out[ $index ] ) ) {
				++$out[ $index ]['decisions'];
			}
		}
		return $out;
	}

	/**
	 * Age in seconds of the item that has waited longest in the queue, or null when the queue is empty.
	 */
	public function oldest_waiting_seconds(): ?int {
		global $wpdb;
		$oldest = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
			$wpdb->prepare( 'SELECT MIN(updated_at) FROM ' . StateRepository::table() . ' WHERE pub_state IN (%s, %s)', PubState::IMPORTED_NOT_PUBLIC, PubState::PUBLISHED_PENDING ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values prepared.
		);
		return is_string( $oldest ) && '' !== $oldest ? max( 0, time() - (int) strtotime( $oldest . ' UTC' ) ) : null;
	}

	/**
	 * The panel markup.
	 */
	public function html(): string {
		$oldest = $this->oldest_waiting_seconds();
		$out    = '<div class="wiw-review-metrics" style="margin:1em 0"><h2>' . esc_html__( 'Review metrics', 'whoiswho' ) . '</h2>';
		$out   .= '<p>' . esc_html__( 'Oldest item waiting:', 'whoiswho' ) . ' <strong>' . esc_html( null === $oldest ? __( 'nothing waiting', 'whoiswho' ) : $this->age( $oldest ) ) . '</strong></p>';
		$out   .= '<table class="widefat striped" style="max-width:32em"><thead><tr><th scope="col">' . esc_html__( 'Week starting', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Decisions', 'whoiswho' ) . '</th></tr></thead><tbody>';
		foreach ( $this->decisions_per_week() as $week ) {
			$out .= '<tr><td>' . esc_html( $week['week'] ) . '</td><td>' . esc_html( (string) $week['decisions'] ) . '</td></tr>';
		}
		$out    .= '</tbody></table>';
		$reasons = $this->rejection_reasons();
		$out    .= '<table class="widefat striped" style="max-width:32em;margin-top:1em"><thead><tr><th scope="col">' . esc_html__( 'Rejection reason', 'whoiswho' ) . '</th><th scope="col">' . esc_html__( 'Count', 'whoiswho' ) . '</th></tr></thead><tbody>';
		if ( array() === $reasons ) {
			$out .= '<tr><td colspan="2">' . esc_html__( 'No rejections yet.', 'whoiswho' ) . '</td></tr>';
		}
		foreach ( $reasons as $code => $count ) {
			$out .= '<tr><td>' . esc_html( $code ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/**
	 * A short age: days, else hours, else minutes.
	 *
	 * @param int $seconds Seconds.
	 */
	private function age( int $seconds ): string {
		if ( $seconds >= DAY_IN_SECONDS ) {
			/* translators: %d: number of days. */
			return sprintf( _n( '%d day', '%d days', intdiv( $seconds, DAY_IN_SECONDS ), 'whoiswho' ), intdiv( $seconds, DAY_IN_SECONDS ) );
		}
		if ( $seconds >= HOUR_IN_SECONDS ) {
			/* translators: %d: number of hours. */
			return sprintf( _n( '%d hour', '%d hours', intdiv( $seconds, HOUR_IN_SECONDS ), 'whoiswho' ), intdiv( $seconds, HOUR_IN_SECONDS ) );
		}
		/* translators: %d: number of minutes. */
		return sprintf( _n( '%d minute', '%d minutes', intdiv( $seconds, 60 ), 'whoiswho' ), intdiv( $seconds, 60 ) );
	}
}
