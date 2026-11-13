<?php
/**
 * The Events list in the admin (REQ-262, REQ-244): columns for start, city, status (Draft, Scheduled, Published) and Upcoming or
 * Past, and filters by status, city and industry. The screen is WordPress's own list for the event type, so the capability
 * wiw_manage_events (mapped to every capability of the type) answers 403 for anyone without it. Filter values are read through
 * Request only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Events\Admin;

use WhoIsWho\Core\Content\Events\EventFields;
use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Events\EventValidator;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Event list screen.
 */
final class EventListScreen {

	public const CITY_VAR     = 'wiw_city';
	public const INDUSTRY_VAR = 'wiw_industry_filter';

	/**
	 * Hook registration.
	 */
	public static function hooks(): void {
		add_filter( 'manage_' . EventPostType::TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . EventPostType::TYPE . '_posts_custom_column', array( self::class, 'cell' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( self::class, 'filters' ), 10, 1 );
		add_action( 'pre_get_posts', array( self::class, 'apply_filters' ) );
	}

	/**
	 * Columns: title, start, city, status, timing.
	 *
	 * @param array<string, string> $columns Default columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		return array(
			'cb'         => $columns['cb'] ?? '',
			'title'      => __( 'Title', 'whoiswho' ),
			'wiw_start'  => __( 'Start', 'whoiswho' ),
			'wiw_city'   => __( 'City', 'whoiswho' ),
			'wiw_status' => __( 'Status', 'whoiswho' ),
			'wiw_timing' => __( 'When', 'whoiswho' ),
		);
	}

	/**
	 * One cell.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Event.
	 */
	public static function cell( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'wiw_start':
				echo esc_html( EventFields::value( $post_id, 'start' ) );
				break;
			case 'wiw_city':
				echo esc_html( EventFields::value( $post_id, 'city' ) );
				break;
			case 'wiw_status':
				echo esc_html( self::status_label( (string) get_post_status( $post_id ) ) );
				break;
			case 'wiw_timing':
				echo esc_html( self::timing( EventFields::value( $post_id, 'start' ), EventFields::value( $post_id, 'end' ), time() ) );
				break;
		}
	}

	/**
	 * Draft, Scheduled or Published for a post status.
	 *
	 * @param string $status Post status.
	 */
	public static function status_label( string $status ): string {
		switch ( $status ) {
			case 'publish':
				return __( 'Published', 'whoiswho' );
			case 'future':
				return __( 'Scheduled', 'whoiswho' );
			case 'trash':
				return __( 'Trash', 'whoiswho' );
			default:
				return __( 'Draft', 'whoiswho' );
		}
	}

	/**
	 * Upcoming or Past: an event is upcoming until its end, or until its start when it has no end.
	 *
	 * @param string $start Start as Y-m-d H:i in the site's time zone, or empty.
	 * @param string $end   End in the same form, or empty.
	 * @param int    $now   Unix time.
	 */
	public static function timing( string $start, string $end, int $now ): string {
		$edge = '' !== $end ? $end : $start;
		if ( '' === $edge ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( EventValidator::DATE_FORMAT, $edge, wp_timezone() );
		if ( false === $date ) {
			return '';
		}
		return $date->getTimestamp() >= $now ? __( 'Upcoming', 'whoiswho' ) : __( 'Past', 'whoiswho' );
	}

	/**
	 * The city and industry drop-downs above the list (status is WordPress's own filter).
	 *
	 * @param string $post_type Post type being listed.
	 */
	public static function filters( string $post_type ): void {
		if ( EventPostType::TYPE !== $post_type ) {
			return;
		}
		$request = Request::from_globals();
		$current = $request->string( self::CITY_VAR );
		global $wpdb;
		$cities = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' ORDER BY meta_value ASC LIMIT 200", EventFields::META_PREFIX . 'city' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a short admin list.
		echo '<label class="screen-reader-text" for="wiw_city_filter">' . esc_html__( 'Filter by city', 'whoiswho' ) . '</label><select name="' . esc_attr( self::CITY_VAR ) . '" id="wiw_city_filter"><option value="">' . esc_html__( 'All cities', 'whoiswho' ) . '</option>';
		foreach ( (array) $cities as $city ) {
			echo '<option value="' . esc_attr( (string) $city ) . '"' . selected( $current, (string) $city, false ) . '>' . esc_html( (string) $city ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		echo '</select>';
		if ( taxonomy_exists( 'wiw_industry' ) ) {
			wp_dropdown_categories(
				array(
					'taxonomy'        => 'wiw_industry',
					'name'            => self::INDUSTRY_VAR,
					'show_option_all' => __( 'All industries', 'whoiswho' ),
					'selected'        => $request->int( self::INDUSTRY_VAR ),
					'hide_empty'      => false,
					'hide_if_empty'   => true,
					'value_field'     => 'term_id',
				)
			);
		}
	}

	/**
	 * Narrow the admin list by city and industry.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function apply_filters( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || EventPostType::TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$request = Request::from_globals();
		$city    = $request->string( self::CITY_VAR );
		if ( '' !== $city ) {
			$meta   = (array) $query->get( 'meta_query' );
			$meta[] = array(
				'key'   => EventFields::META_PREFIX . 'city',
				'value' => $city,
			);
			$query->set( 'meta_query', $meta );
		}
		$industry = $request->int( self::INDUSTRY_VAR );
		if ( $industry > 0 && taxonomy_exists( 'wiw_industry' ) ) {
			$tax   = (array) $query->get( 'tax_query' );
			$tax[] = array(
				'taxonomy' => 'wiw_industry',
				'field'    => 'term_id',
				'terms'    => array( $industry ),
			);
			$query->set( 'tax_query', $tax );
		}
	}
}
