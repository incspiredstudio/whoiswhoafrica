<?php
/**
 * The Nominations on/off switch: the one feature flag (REQ-194, REQ-259). Only an Administrator changes it; an Editor sees the
 * state read-only (REQ-267). The value lives in the settings service, which audits every change.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Settings\SettingsService;

/**
 * Nominations switch.
 */
final class NominationsSwitch {

	public const SCREEN     = 'wiw-nominations';
	public const ACTION     = 'wiw_toggle_nominations';
	public const TOGGLE_CAP = 'wiw_toggle_nominations';
	public const SCREEN_CAP = 'wiw_manage_awards';

	/**
	 * Settings.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings;

	/**
	 * Constructor.
	 *
	 * @param SettingsService $settings Settings service.
	 */
	public function __construct( SettingsService $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register the screen and the toggle action.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_admin' ), 1 );
	}

	/**
	 * Register the screen and the action, in the admin only.
	 */
	public static function register_admin(): void {
		if ( ! is_admin() ) {
			return;
		}
		$registry = ScreenRegistry::shared();
		$registry->register(
			self::SCREEN,
			__( 'Nominations', 'whoiswho' ),
			self::SCREEN_CAP,
			static function (): void {
				( new NominationsScreen( new self( SettingsService::for_site() ), NominationService::for_site() ) )->render();
			}
		);
		$registry->hook();
		ActionGuard::register(
			self::ACTION,
			self::TOGGLE_CAP,
			static function ( Request $request ): void {
				( new self( SettingsService::for_site() ) )->handle( $request );
			}
		);
	}

	/**
	 * Whether Nominations is on.
	 */
	public function is_on(): bool {
		return $this->settings->nominations_enabled();
	}

	/**
	 * Turn Nominations on or off. Needs the toggle capability and is audited by the settings service.
	 *
	 * @param bool   $on     New state.
	 * @param string $reason Optional reason for the audit row.
	 * @throws \DomainException When the user may not change it.
	 */
	public function apply( bool $on, string $reason = '' ): void {
		$this->settings->set( SettingsService::NOMINATIONS_ENABLED, $on, $reason );
	}

	/**
	 * The admin post handler (capability and nonce already checked by ActionGuard).
	 *
	 * @param Request $request Request.
	 */
	public function handle( Request $request ): void {
		$this->apply( '1' === $request->string( 'state' ), $request->string( 'reason' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SCREEN ) );
		exit;
	}

	/**
	 * The screen markup. The form appears only for a user who can toggle.
	 *
	 * @param bool|null $on State to show; the stored state when null.
	 */
	public function html( ?bool $on = null ): string {
		return '<div class="wrap"><h1>' . esc_html__( 'Nominations', 'whoiswho' ) . '</h1>' . $this->body_html( $on ) . '</div>';
	}

	/**
	 * The state line and, for an Administrator, the toggle form (the screen puts the list below it).
	 *
	 * @param bool|null $on State to show; the stored state when null.
	 */
	public function body_html( ?bool $on = null ): string {
		$on    = $on ?? $this->is_on();
		$state = $on ? __( 'Nominations are on.', 'whoiswho' ) : __( 'Nominations are off.', 'whoiswho' );
		$out   = '<p>' . esc_html( $state ) . '</p>';
		if ( ! current_user_can( self::TOGGLE_CAP ) ) {
			return $out . '<p>' . esc_html__( 'Only an Administrator can change this.', 'whoiswho' ) . '</p>';
		}
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out .= '<input type="hidden" name="state" value="' . ( $on ? '0' : '1' ) . '" />';
		$out .= '<p><label>' . esc_html__( 'Reason (optional)', 'whoiswho' ) . '<br /><input type="text" name="reason" maxlength="300" class="regular-text" /></label></p>';
		$out .= '<p><button type="submit" class="button button-primary">' . esc_html( $on ? __( 'Turn Nominations off', 'whoiswho' ) : __( 'Turn Nominations on', 'whoiswho' ) ) . '</button></p>';
		return $out . '</form>';
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		echo wp_kses(
			$this->html(),
			array(
				'div'    => array( 'class' => true ),
				'h1'     => array(),
				'p'      => array(),
				'br'     => array(),
				'label'  => array(),
				'form'   => array(
					'method' => true,
					'action' => true,
				),
				'input'  => array(
					'type'      => true,
					'name'      => true,
					'value'     => true,
					'maxlength' => true,
					'class'     => true,
				),
				'button' => array(
					'type'  => true,
					'class' => true,
				),
			)
		);
	}
}
