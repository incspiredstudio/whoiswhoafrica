<?php
/**
 * FluentSMTP's email log forced off (REQ-233, REQ-046). FluentSMTP can keep a copy of every email it sends, body included, in its
 * own table. Our emails carry signed links (claims, corrections, data requests, confirmations), so a stored body would be a
 * working key in the database, and personal data kept outside the PII catalogue. Its settings (the fluentmail-settings option) are
 * filtered on every read so `misc.log_emails` is always 'no', whatever its settings screen saved. Our own email log keeps only
 * what it is allowed to keep. No FluentSMTP file is edited.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

/**
 * FluentSMTP logging switch.
 */
final class FluentSmtpLogging {

	public const OPTION = Transport::FLUENT_SMTP_OPTION;

	/**
	 * Attach the filter. It does nothing while FluentSMTP is not installed (the option does not exist).
	 */
	public function register(): void {
		add_filter( 'option_' . self::OPTION, array( self::class, 'force' ), 99 );
	}

	/**
	 * Force the email log off in FluentSMTP's settings as they are read.
	 *
	 * @param mixed $value Stored value (an array, or serialised).
	 * @return mixed
	 */
	public static function force( $value ) {
		$settings = maybe_unserialize( $value );
		if ( ! is_array( $settings ) ) {
			return $value;
		}
		$misc               = isset( $settings['misc'] ) && is_array( $settings['misc'] ) ? $settings['misc'] : array();
		$misc['log_emails'] = 'no';
		$settings['misc']   = $misc;
		return $settings;
	}
}
