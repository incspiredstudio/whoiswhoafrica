<?php
/**
 * Event structured data (REQ-262, REQ-264). A published event carries schema.org Event JSON-LD that matches what the page shows:
 * name, start, end, online or place, organiser and image. A draft, scheduled, private or removed event has no page and so no
 * markup. The data is built from the public event card, never from raw post meta.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Events;

use WhoIsWho\Core\Content\PublicEvent;

/**
 * Event SEO.
 */
final class EventSeo {

	/**
	 * Hook the output.
	 */
	public static function hooks(): void {
		add_action( 'wp_head', array( self::class, 'print_schema' ), 2 );
	}

	/**
	 * Print the markup on a single event page.
	 */
	public static function print_schema(): void {
		if ( ! is_singular( EventPostType::TYPE ) ) {
			return;
		}
		$event = PublicEvent::find( (int) get_queried_object_id() );
		if ( null !== $event ) {
			echo self::render( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with the hex flags; no markup can break out of the script element.
		}
	}

	/**
	 * The script element for an event card, or an empty string when there is nothing to say.
	 *
	 * @param array<string, mixed> $event Public event card.
	 */
	public static function render( array $event ): string {
		$data = self::for_event( $event );
		if ( null === $data ) {
			return '';
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		return false === $json ? '' : '<script type="application/ld+json">' . $json . "</script>\n";
	}

	/**
	 * The Event data for a public event card.
	 *
	 * @param array<string, mixed> $event Public event card.
	 * @return array<string, mixed>|null Null without a title or a start.
	 */
	public static function for_event( array $event ): ?array {
		$title = (string) ( $event['title'] ?? '' );
		$start = self::iso( (string) ( $event['start'] ?? '' ) );
		if ( '' === $title || '' === $start ) {
			return null;
		}
		$data = array(
			'@context'  => 'https://schema.org',
			'@type'     => 'Event',
			'name'      => $title,
			'url'       => (string) ( $event['url'] ?? '' ),
			'startDate' => $start,
		);
		$end  = self::iso( (string) ( $event['end'] ?? '' ) );
		if ( '' !== $end ) {
			$data['endDate'] = $end;
		}
		if ( true === ( $event['online'] ?? false ) ) {
			$data['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
			$data['location']            = array(
				'@type' => 'VirtualLocation',
				'url'   => (string) ( $event['url'] ?? '' ),
			);
		} elseif ( '' !== (string) ( $event['venue'] ?? '' ) || '' !== (string) ( $event['city'] ?? '' ) ) {
			$data['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
			$place                       = array(
				'@type' => 'Place',
				'name'  => '' !== (string) $event['venue'] ? (string) $event['venue'] : (string) $event['city'],
			);
			$address                     = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'addressLocality' => (string) ( $event['city'] ?? '' ),
					'addressCountry'  => (string) ( $event['country'] ?? '' ),
				),
				static fn ( string $value ): bool => '' !== $value
			);
			if ( count( $address ) > 1 ) {
				$place['address'] = $address;
			}
			$data['location'] = $place;
		}//end if
		if ( '' !== (string) ( $event['organiser'] ?? '' ) ) {
			$data['organizer'] = array(
				'@type' => 'Organization',
				'name'  => (string) $event['organiser'],
			);
		}
		if ( '' !== (string) ( $event['cover'] ?? '' ) ) {
			$data['image'] = (string) $event['cover'];
		}
		if ( '' !== (string) ( $event['summary'] ?? '' ) ) {
			$data['description'] = (string) $event['summary'];
		}
		return $data;
	}

	/**
	 * A site-time "Y-m-d H:i" value as ISO 8601 with the site's offset, or an empty string.
	 *
	 * @param string $value Stored value.
	 */
	private static function iso( string $value ): string {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $value ) ) {
			return '';
		}
		$date = date_create_immutable( $value, wp_timezone() );
		return false === $date ? '' : $date->format( 'c' );
	}
}
