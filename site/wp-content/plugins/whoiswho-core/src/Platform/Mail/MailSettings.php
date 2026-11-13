<?php
/**
 * The few values every templated email needs from settings: the Reply-To address, the contact address shown in the footer, the
 * expected response window and the privacy page. Each has a safe fallback, and each works without WordPress (unit tests).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

use WhoIsWho\Core\Platform\Settings\SettingsService;

/**
 * Mail settings.
 */
final class MailSettings {

	/**
	 * A string setting, or the fallback when settings are not available or the value is empty.
	 *
	 * @param string $key      Registered key.
	 * @param string $fallback Fallback.
	 */
	private static function string_setting( string $key, string $fallback = '' ): string {
		if ( ! function_exists( 'get_option' ) ) {
			return $fallback;
		}
		try {
			$value = trim( (string) SettingsService::for_site()->get( $key ) );
		} catch ( \Throwable $e ) {
			return $fallback;
		}
		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Reply-To for all templated mail: the setting when it is a valid address, else the From address.
	 */
	public static function reply_to(): string {
		$address = self::string_setting( 'mail_reply_to' );
		if ( '' !== $address && false !== filter_var( $address, FILTER_VALIDATE_EMAIL ) ) {
			return $address;
		}
		if ( function_exists( 'wp_get_environment_type' ) ) {
			$from = Transport::for_site()->from_address( '' );
			return false !== filter_var( $from, FILTER_VALIDATE_EMAIL ) ? $from : '';
		}
		return '';
	}

	/**
	 * Contact address shown in the footer; empty when none is set.
	 */
	public static function contact_email(): string {
		$address = self::string_setting( 'contact_email' );
		return false !== filter_var( $address, FILTER_VALIDATE_EMAIL ) ? $address : '';
	}

	/**
	 * Expected response window in words, for example "two working days"; empty when none is set.
	 */
	public static function response_window(): string {
		return self::string_setting( 'enquiry_response_window' );
	}

	/**
	 * Address of the privacy page.
	 */
	public static function privacy_url(): string {
		return function_exists( 'home_url' ) ? (string) home_url( '/privacy/' ) : '';
	}

	/**
	 * The brand name, through the filter the child theme answers.
	 */
	public static function brand(): string {
		if ( ! function_exists( 'apply_filters' ) ) {
			return '';
		}
		return trim( (string) apply_filters( 'wiw_brand_name', function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '' ) );
	}
}
