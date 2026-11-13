<?php
/**
 * The Sprint thresholds screen (REQ-272, REQ-181): the two numbers the sprint card on the dashboard measures against, ten counted
 * deals and NGN 5m by default. The money threshold is stored as integer kobo (the screen takes whole naira). The screen is
 * registered only while Sprint tracking is on, so while it is off the menu entry is gone and the URL answers 403; the stored
 * thresholds are kept. Administrator only, and every change is audited by the settings service. There is no profile-count
 * target and no progress bar.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Settings;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;

/**
 * Sprint thresholds screen.
 */
final class SprintScreen {

	public const SCREEN      = 'wiw-sprint-thresholds';
	public const CAP         = 'manage_options';
	public const ACTION      = 'wiw_sprint_save';
	public const COUNT_KEY   = 'sprint_count_threshold';
	public const REVENUE_KEY = 'sprint_revenue_threshold_minor';
	public const CURRENCY    = 'NGN';
	public const KOBO        = 100;

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
	 * Hook registration into the admin.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				$settings = SettingsService::for_site();
				self::register( ScreenRegistry::shared(), $settings );
				ScreenRegistry::shared()->hook();
				ActionGuard::register( self::ACTION, self::CAP, array( self::class, 'handle' ) );
			},
			1
		);
	}

	/**
	 * Register the screen while Sprint tracking is on.
	 *
	 * @param ScreenRegistry  $registry Registry.
	 * @param SettingsService $settings Settings.
	 */
	public static function register( ScreenRegistry $registry, SettingsService $settings ): void {
		if ( ! $settings->sprint_tracking_enabled() ) {
			return;
		}
		$registry->register(
			self::SCREEN,
			__( 'Sprint thresholds', 'whoiswho' ),
			self::CAP,
			static function () use ( $settings ): void {
				echo ( new self( $settings ) )->html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			},
			false,
			'settings'
		);
	}

	/**
	 * The save handler (capability and nonce already checked).
	 *
	 * @param Request $request Request.
	 */
	public static function handle( Request $request ): void {
		$settings = SettingsService::for_site();
		$notice   = 'saved';
		if ( $settings->sprint_tracking_enabled() ) {
			try {
				( new self( $settings ) )->save( $request->int( 'count' ), $request->int( 'revenue' ) );
			} catch ( \InvalidArgumentException $e ) {
				unset( $e );
				$notice = 'invalid';
			}
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => self::SCREEN,
					'result' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save both thresholds: a count of deals and whole naira (stored as kobo).
	 *
	 * @param int $count Counted deals.
	 * @param int $naira Revenue threshold in whole naira.
	 * @throws \DomainException Without the capability (a negative number is refused by the settings service).
	 */
	public function save( int $count, int $naira ): void {
		$this->settings->set( self::COUNT_KEY, $count );
		$this->settings->set( self::REVENUE_KEY, $naira * self::KOBO );
	}

	/**
	 * The screen markup.
	 */
	public function html(): string {
		$count = (int) $this->settings->get( self::COUNT_KEY );
		$naira = intdiv( (int) $this->settings->get( self::REVENUE_KEY ), self::KOBO );
		$out   = '<div class="wrap"><h1>' . esc_html__( 'Sprint thresholds', 'whoiswho' ) . '</h1>';
		$out  .= '<p>' . esc_html__( 'The sprint card on the dashboard counts the sponsored stories and the revenue against these two numbers. Changes are recorded in the audit log.', 'whoiswho' ) . '</p>';
		$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out  .= '<p><label>' . esc_html__( 'Counted deals', 'whoiswho' ) . ' <input type="number" min="0" step="1" name="count" value="' . esc_attr( (string) $count ) . '" /></label></p>';
		$out  .= '<p><label>' . esc_html( sprintf( /* translators: %s: currency code. */ __( 'Revenue (%s, whole units)', 'whoiswho' ), self::CURRENCY ) ) . ' <input type="number" min="0" step="1" name="revenue" value="' . esc_attr( (string) $naira ) . '" /></label></p>';
		return $out . '<button class="button button-primary">' . esc_html__( 'Save', 'whoiswho' ) . '</button></form></div>';
	}
}
