<?php
/**
 * Data export and erasure for Profile owner accounts (REQ-274, REQ-059, REQ-063). The account's name and email are exported by
 * WordPress core's own user exporter; this handler adds the profiles the account owns. Erasure removes a Profile owner account
 * whose only role is the owner role: its profiles lose their owner (they stay claimed and published) and the account is deleted,
 * with an audit row that holds only the account id. Staff accounts are never touched here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust\Account;

use WhoIsWho\Core\Compliance\Privacy\PrivacyHandler;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Trust\ProfileManager;

/**
 * Owner account privacy handler.
 */
final class OwnerPrivacy implements PrivacyHandler {

	/**
	 * Register with the privacy registry.
	 */
	public static function hooks(): void {
		add_filter(
			'wiw_privacy_handlers',
			static function ( $handlers ): array {
				$handlers   = is_array( $handlers ) ? $handlers : array();
				$handlers[] = new self();
				return $handlers;
			}
		);
	}

	/**
	 * Id.
	 */
	public function id(): string {
		return 'owner-accounts';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'Profile owner account';
	}

	/**
	 * Tables: the account itself is in the core users table, which core handles.
	 *
	 * @return string[]
	 */
	public function tables(): array {
		return array();
	}

	/**
	 * The owner account for an address, when it is one.
	 *
	 * @param string $email Address.
	 */
	private function owner( string $email ): ?\WP_User {
		$user = '' !== $email ? get_user_by( 'email', $email ) : false;
		return $user instanceof \WP_User && OwnerRole::is_owner_only( (int) $user->ID ) ? $user : null;
	}

	/**
	 * Export the profiles the account owns.
	 *
	 * @param string $email Address.
	 * @return array<int, array{item_id: string, data: array<int, array{name: string, value: string}>}>
	 */
	public function export( string $email ): array {
		$user = $this->owner( $email );
		if ( null === $user ) {
			return array();
		}
		$owned = array();
		foreach ( ( new ProfileManager() )->owned( (int) $user->ID ) as $profile_id ) {
			$owned[] = get_the_title( $profile_id );
		}
		return array(
			array(
				'item_id' => 'owner-account-' . (int) $user->ID,
				'data'    => array(
					array(
						'name'  => 'Account type',
						'value' => 'Profile owner',
					),
					array(
						'name'  => 'Name',
						'value' => (string) $user->display_name,
					),
					array(
						'name'  => 'Email address',
						'value' => (string) $user->user_email,
					),
					array(
						'name'  => 'Profiles owned',
						'value' => implode( ', ', $owned ),
					),
				),
			),
		);
	}

	/**
	 * Remove the owner account and its ownership links.
	 *
	 * @param string $email Address.
	 * @return int 1 when an account was removed.
	 */
	public function erase( string $email ): int {
		$user = $this->owner( $email );
		if ( null === $user ) {
			return 0;
		}
		$id     = (int) $user->ID;
		$states = new StateRepository();
		$owned  = ( new ProfileManager() )->owned( $id );
		foreach ( $owned as $profile_id ) {
			$states->update( $profile_id, array( 'assigned_user_id' => null ) );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $id );
		AuditLogger::for_site()->log(
			'owner.account_erased',
			array(
				'type' => 'user',
				'id'   => $id,
			),
			array( 'profiles_owned' => count( $owned ) ),
			null,
			'Profile owner account erased on a data request'
		);
		return 1;
	}
}
