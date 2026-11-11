<?php
/**
 * The one validator for the event form (REQ-262).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Events;

/**
 * Event form validator.
 */
final class EventValidator {

	public const DATE_FORMAT = 'Y-m-d H:i';

	/**
	 * Names of the event fields.
	 *
	 * @return array<int, string>
	 */
	public function field_names(): array {
		return array( 'start', 'end', 'online', 'venue', 'city', 'country', 'organiser', 'registration_url', 'price_note' );
	}

	/**
	 * Validate and clean a submission.
	 *
	 * @param array<string, string> $input Raw field values (already read through Request).
	 * @return array{values: array<string, string>, errors: array<string, string>}
	 */
	public function validate( array $input ): array {
		$errors = array();
		$values = array();
		$get    = static function ( string $key ) use ( $input ): string {
			return isset( $input[ $key ] ) ? trim( sanitize_text_field( $input[ $key ] ) ) : '';
		};

		$online           = '' !== $get( 'online' ) && '0' !== $get( 'online' );
		$values['online'] = $online ? '1' : '0';
		$values['start']  = '';
		$values['end']    = '';
		$start            = $this->parse_date( $get( 'start' ) );
		$end              = $this->parse_date( $get( 'end' ) );
		$start_given      = '' !== $get( 'start' );
		$end_given        = '' !== $get( 'end' );

		if ( ! $start_given || null === $start ) {
			$errors['start'] = __( 'Enter a start date and time.', 'whoiswho' );
		} else {
			$values['start'] = $start->format( self::DATE_FORMAT );
		}
		if ( $end_given ) {
			if ( null === $end ) {
				$errors['end'] = __( 'Enter a valid end date and time.', 'whoiswho' );
			} elseif ( null !== $start && $end < $start ) {
				$errors['end'] = __( 'The end cannot be before the start.', 'whoiswho' );
			} else {
				$values['end'] = $end->format( self::DATE_FORMAT );
			}
		}

		$values['venue']     = $online ? 'Online' : $get( 'venue' );
		$values['city']      = $online ? '' : $get( 'city' );
		$values['country']   = $online ? '' : $get( 'country' );
		$values['organiser'] = $get( 'organiser' );
		if ( ! $online ) {
			if ( '' === $values['city'] ) {
				$errors['city'] = __( 'Enter the city.', 'whoiswho' );
			}
			if ( '' === $values['country'] ) {
				$errors['country'] = __( 'Enter the country.', 'whoiswho' );
			}
		}

		$values['registration_url'] = '';
		$url                        = $get( 'registration_url' );
		if ( '' !== $url ) {
			if ( ! $this->is_external_https( $url ) ) {
				$errors['registration_url'] = __( 'The registration link must be an external https address.', 'whoiswho' );
			} else {
				$values['registration_url'] = esc_url_raw( $url, array( 'https' ) );
			}
		}

		$values['price_note'] = wp_strip_all_tags( $get( 'price_note' ) );

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Parse a datetime-local value or a stored value.
	 *
	 * @param string $value Date text.
	 */
	private function parse_date( string $value ): ?\DateTimeImmutable {
		if ( '' === $value ) {
			return null;
		}
		foreach ( array( 'Y-m-d\TH:i', self::DATE_FORMAT ) as $format ) {
			$date   = \DateTimeImmutable::createFromFormat( '!' . $format, $value );
			$errors = \DateTimeImmutable::getLastErrors();
			if ( false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) ) {
				return $date;
			}
		}
		return null;
	}

	/**
	 * An https URL whose host is not this site.
	 *
	 * @param string $url Address.
	 */
	private function is_external_https( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) ) {
			return false;
		}
		$own = wp_parse_url( home_url() );
		return ! is_array( $own ) || strtolower( (string) ( $own['host'] ?? '' ) ) !== strtolower( $parts['host'] );
	}
}
