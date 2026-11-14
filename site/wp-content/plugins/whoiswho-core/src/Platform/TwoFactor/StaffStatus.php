<?php
/**
 * Which staff have two-factor sign-in switched on (REQ-047, REQ-235). Advice only: two-factor is available and recommended for
 * staff, not enforced (owner decision 2026-11-14, D-070). The Setup screen lists who has it on and who has not.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\TwoFactor;

use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;

/**
 * Staff two-factor status.
 */
final class StaffStatus {

	/**
	 * Most staff accounts listed by name.
	 */
	public const LIMIT = 50;

	/**
	 * Status source.
	 *
	 * @var SecondFactorStatus
	 */
	private SecondFactorStatus $status;

	/**
	 * Constructor.
	 *
	 * @param SecondFactorStatus $status Status source.
	 */
	public function __construct( SecondFactorStatus $status ) {
		$this->status = $status;
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( new PluginStatus() );
	}

	/**
	 * The roles that count as staff: the Administrator and the plugin's staff roles.
	 *
	 * @return string[]
	 */
	public static function staff_roles(): array {
		return array_merge( array( 'administrator' ), array_keys( ( new CapabilityCatalogue() )->roles() ) );
	}

	/**
	 * Whether a user is staff: one of the staff roles, or holding any of the plugin's wiw_* capabilities (granted one by one).
	 *
	 * @param int $user_id User id.
	 */
	public static function is_staff( int $user_id ): bool {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user instanceof \WP_User ) {
			return false;
		}
		if ( array() !== array_intersect( self::staff_roles(), (array) $user->roles ) ) {
			return true;
		}
		foreach ( (array) $user->allcaps as $cap => $granted ) {
			if ( $granted && 0 === strpos( (string) $cap, 'wiw_' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the two-factor software is loaded.
	 */
	public function available(): bool {
		return $this->status->available();
	}

	/**
	 * Staff split by whether two-factor is on, by display name, sorted.
	 *
	 * @return array{on: string[], off: string[], total: int}
	 */
	public function summary(): array {
		$users = get_users(
			array(
				'role__in' => self::staff_roles(),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'number'   => self::LIMIT,
				'fields'   => array( 'ID', 'display_name' ),
			)
		);
		$out   = array(
			'on'    => array(),
			'off'   => array(),
			'total' => 0,
		);
		foreach ( $users as $user ) {
			$key           = $this->status->is_enrolled( (int) $user->ID ) ? 'on' : 'off';
			$out[ $key ][] = (string) $user->display_name;
			++$out['total'];
		}
		return $out;
	}
}
