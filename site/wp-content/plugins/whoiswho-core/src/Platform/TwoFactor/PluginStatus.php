<?php
/**
 * Reads second-factor status from the Two Factor plugin (wordpress.org/plugins/two-factor, pinned through composer).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\TwoFactor;

/**
 * Plugin-backed status.
 */
final class PluginStatus implements SecondFactorStatus {

	public const BACKUP_CODES = 'Two_Factor_Backup_Codes';

	/**
	 * Whether the plugin is loaded.
	 */
	public function available(): bool {
		return class_exists( '\Two_Factor_Core' );
	}

	/**
	 * Whether the user has an available provider other than recovery codes.
	 *
	 * @param int $user_id User id.
	 */
	public function is_enrolled( int $user_id ): bool {
		if ( ! $this->available() || $user_id <= 0 ) {
			return false;
		}
		$available = \Two_Factor_Core::get_available_providers_for_user( $user_id );
		if ( ! is_array( $available ) ) {
			return false;
		}
		unset( $available[ self::BACKUP_CODES ] );
		return array() !== $available;
	}
}
