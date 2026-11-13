<?php
/**
 * The reference a requester sees on the received page and in email, for example WIW-7K3Q9XPD. No look-alike letters.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

/**
 * Reference generator.
 */
final class Reference {

	public const SEQUENCE_OPTION = 'wiw_enquiry_sequence';

	private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	/**
	 * A new random reference.
	 */
	public static function generate(): string {
		$out = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$out .= self::ALPHABET[ random_int( 0, strlen( self::ALPHABET ) - 1 ) ];
		}
		return 'WIW-' . $out;
	}

	/**
	 * The next enquiry reference in sequence, for example ENQ-0004. The counter moves in one atomic statement, so two
	 * enquiries never share a number.
	 */
	public static function next_enquiry(): string {
		global $wpdb;
		add_option( self::SEQUENCE_OPTION, 0, '', false );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s", self::SEQUENCE_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core table name.
		$number = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( self::SEQUENCE_OPTION, 'options' );
		return 'ENQ-' . str_pad( (string) $number, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Whether a string has the shape of a reference.
	 *
	 * @param string $value Value.
	 */
	public static function is_valid( string $value ): bool {
		return 1 === preg_match( '/^WIW-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$/', $value );
	}

	/**
	 * The one rule for printing a reference a person brought back in an address: two to six capitals, a hyphen and up to twelve
	 * capitals or digits. It covers WIW-5MQHD6QV (requests), ENQ-0001 (enquiries), CLM-0004 (claims) and NOM-1A2B3C
	 * (nominations), and returns an empty string for anything else, so it is safe to print (still escape it).
	 *
	 * @param string $value Value from the address.
	 */
	public static function display_safe( string $value ): string {
		return 1 === preg_match( '/^[A-Z]{2,6}-[A-Z0-9]{1,12}$/', $value ) ? $value : '';
	}
}
