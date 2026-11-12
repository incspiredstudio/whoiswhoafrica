<?php
/**
 * Event display helpers (REQ-262, REQ-264, REQ-265): date text, place text and status tag from a PublicEvent array. The theme
 * reads events only through PublicEvent; nothing here queries posts.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_event_time' ) ) {
	/**
	 * Timestamp of an event date string ("Y-m-d" or "Y-m-d H:i"), or null.
	 *
	 * @param string $value Date string.
	 */
	function wiw_event_time( string $value ): ?int {
		if ( '' === $value ) {
			return null;
		}
		$time = strtotime( $value . ( 10 === strlen( $value ) ? ' 00:00:00' : '' ) . ' UTC' );
		return false === $time ? null : $time;
	}
}

if ( ! function_exists( 'wiw_event_day' ) ) {
	/**
	 * The parts of the start date for the date block: day, short month, full month, year.
	 *
	 * @param array<string, mixed> $event Event.
	 * @return array{day: string, month_short: string, month: string, year: string, label: string}
	 */
	function wiw_event_day( array $event ): array {
		$time = wiw_event_time( (string) ( $event['start'] ?? '' ) );
		if ( null === $time ) {
			return array(
				'day'         => '',
				'month_short' => '',
				'month'       => '',
				'year'        => '',
				'label'       => '',
			);
		}
		return array(
			'day'         => wp_date( 'j', $time, new DateTimeZone( 'UTC' ) ),
			'month_short' => wp_date( 'M', $time, new DateTimeZone( 'UTC' ) ),
			'month'       => wp_date( 'F', $time, new DateTimeZone( 'UTC' ) ),
			'year'        => wp_date( 'Y', $time, new DateTimeZone( 'UTC' ) ),
			'label'       => wp_date( 'j F Y', $time, new DateTimeZone( 'UTC' ) ),
		);
	}
}//end if

if ( ! function_exists( 'wiw_event_when' ) ) {
	/**
	 * "12 March 2027, 09:00 to 17:00" or a date range.
	 *
	 * @param array<string, mixed> $event Event.
	 */
	function wiw_event_when( array $event ): string {
		$utc   = new DateTimeZone( 'UTC' );
		$start = wiw_event_time( (string) ( $event['start'] ?? '' ) );
		$end   = wiw_event_time( (string) ( $event['end'] ?? '' ) );
		if ( null === $start ) {
			return '';
		}
		$has_time = 16 === strlen( (string) $event['start'] );
		$out      = wp_date( 'j F Y', $start, $utc ) . ( $has_time ? ', ' . wp_date( 'H:i', $start, $utc ) : '' );
		if ( null === $end || $end === $start ) {
			return $out;
		}
		if ( wp_date( 'Y-m-d', $end, $utc ) === wp_date( 'Y-m-d', $start, $utc ) ) {
			return $out . ( 16 === strlen( (string) $event['end'] ) ? ' ' . __( 'to', 'whoiswho' ) . ' ' . wp_date( 'H:i', $end, $utc ) : '' );
		}
		return $out . ' ' . __( 'to', 'whoiswho' ) . ' ' . wp_date( 'j F Y', $end, $utc );
	}
}//end if

if ( ! function_exists( 'wiw_event_where' ) ) {
	/**
	 * Place text: venue and city, or Online.
	 *
	 * @param array<string, mixed> $event Event.
	 */
	function wiw_event_where( array $event ): string {
		if ( ! empty( $event['online'] ) ) {
			return __( 'Online', 'whoiswho' );
		}
		$parts = array_filter( array( (string) ( $event['venue'] ?? '' ), (string) ( $event['city'] ?? '' ), (string) ( $event['country'] ?? '' ) ) );
		return implode( ', ', array_unique( $parts ) );
	}
}

if ( ! function_exists( 'wiw_event_status' ) ) {
	/**
	 * Status tag: Past, Online or Upcoming (a past event is never shown as online or upcoming).
	 *
	 * @param array<string, mixed> $event Event.
	 * @return array{key: string, label: string}
	 */
	function wiw_event_status( array $event ): array {
		if ( empty( $event['upcoming'] ) ) {
			return array(
				'key'   => 'past',
				'label' => __( 'Past', 'whoiswho' ),
			);
		}
		if ( ! empty( $event['online'] ) ) {
			return array(
				'key'   => 'online',
				'label' => __( 'Online', 'whoiswho' ),
			);
		}
		return array(
			'key'   => 'upcoming',
			'label' => __( 'Upcoming', 'whoiswho' ),
		);
	}
}//end if

if ( ! function_exists( 'wiw_event_months' ) ) {
	/**
	 * Group events by month (Y-m), keeping their order.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	function wiw_event_months( array $events ): array {
		$groups = array();
		foreach ( $events as $event ) {
			$key = (string) ( $event['month'] ?? '' );
			if ( '' === $key ) {
				continue;
			}
			$groups[ $key ][] = $event;
		}
		return $groups;
	}
}//end if

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		// Filters of the Events list live in the address (REQ-264): ?city=Abuja&industry=energy.
		return array_merge( $vars, array( 'city', 'industry' ) );
	}
);

if ( ! function_exists( 'wiw_events_filters' ) ) {
	/**
	 * The city and industry filters of the Events list, from the address.
	 *
	 * @return array{city: string, industry: string}
	 */
	function wiw_events_filters(): array {
		return array(
			'city'     => sanitize_text_field( (string) get_query_var( 'city', '' ) ),
			'industry' => sanitize_title( (string) get_query_var( 'industry', '' ) ),
		);
	}
}//end if

if ( ! function_exists( 'wiw_events_industry_options' ) ) {
	/**
	 * Industries used by published events, as slug => name, for the filter.
	 *
	 * @param array<int, array<string, mixed>> $events All published events (unfiltered).
	 * @return array<string, string>
	 */
	function wiw_events_industry_options( array $events ): array {
		$out = array();
		foreach ( $events as $event ) {
			foreach ( (array) ( $event['industries'] ?? array() ) as $name ) {
				$term = taxonomy_exists( 'wiw_industry' ) ? get_term_by( 'name', (string) $name, 'wiw_industry' ) : false;
				if ( $term instanceof WP_Term ) {
					$out[ $term->slug ] = $term->name;
				}
			}
		}
		asort( $out );
		return $out;
	}
}//end if
