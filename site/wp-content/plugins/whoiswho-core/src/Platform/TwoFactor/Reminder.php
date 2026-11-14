<?php
/**
 * The two-factor reminder (REQ-047, REQ-235, issue 814). Two-factor stays optional (D-070), but every staff member who has not
 * turned it on is asked to: a warning notice on every admin screen and a line on the dashboard, both with a "Turn it on now" button
 * to their own profile's Two-Factor Options, and one email the first time they sign in without it. "Remind me tomorrow" hides the
 * notice for 24 hours for that user only. Everything stops as soon as the user has a method on, nothing is ever blocked, and
 * nothing shows when the Two Factor plugin is not loaded.
 *
 * Who is staff and who has two-factor on come from StaffStatus and the Two Factor plugin (PluginStatus). The email goes through the
 * normal mail path (Notifier, the shared layout, the job queue and the email log) and stays under the hourly mail cap: over the
 * cap it is not sent and not marked, so it goes at a later sign-in.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\TwoFactor;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Mail\Transport;

/**
 * Two-factor reminder.
 */
final class Reminder {

	public const ACTION       = 'wiw_two_factor_remind_later';
	public const CAP          = 'read';
	public const META_SNOOZE  = 'wiw_two_factor_remind_after';
	public const META_EMAILED = 'wiw_two_factor_reminder_emailed';
	public const NOTICE_ID    = 'wiw-two-factor-reminder';
	public const WIDGET_ID    = 'wiw_two_factor_reminder';
	public const MAIL_SOURCE  = 'staff_account';
	public const ANCHOR       = '#two-factor-options';

	/**
	 * Status source.
	 *
	 * @var SecondFactorStatus
	 */
	private SecondFactorStatus $status;

	/**
	 * Mail service, or null for the site's own (resolved when first needed).
	 *
	 * @var Notifier|null
	 */
	private ?Notifier $notifier;

	/**
	 * Messages per hour the site stays under.
	 *
	 * @var int
	 */
	private int $hourly_cap;

	/**
	 * Constructor.
	 *
	 * @param SecondFactorStatus $status     Status source.
	 * @param Notifier|null      $notifier   Mail service (tests pass one).
	 * @param int                $hourly_cap Messages per hour the site stays under.
	 */
	public function __construct( SecondFactorStatus $status, ?Notifier $notifier = null, int $hourly_cap = Transport::DEFAULT_HOURLY_CAP ) {
		$this->status     = $status;
		$this->notifier   = $notifier;
		$this->hourly_cap = $hourly_cap > 0 ? $hourly_cap : Transport::DEFAULT_HOURLY_CAP;
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( new PluginStatus(), null, Transport::for_site()->hourly_cap() );
	}

	/**
	 * Attach hooks.
	 */
	public static function register(): void {
		add_action(
			'admin_notices',
			static function (): void {
				echo self::for_site()->notice_html( get_current_user_id(), time() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
			}
		);
		add_action(
			'wp_dashboard_setup',
			static function (): void {
				$html = self::for_site()->dashboard_html( get_current_user_id() );
				if ( '' === $html ) {
					return;
				}
				wp_add_dashboard_widget(
					self::WIDGET_ID,
					esc_html__( 'Two-factor sign-in', 'whoiswho' ),
					static function () use ( $html ): void {
						echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
					}
				);
			}
		);
		add_action(
			'wp_login',
			static function ( $login, $user ): void {
				self::for_site()->on_login( (string) $login, $user );
			},
			10,
			2
		);
		add_action(
			'init',
			static function (): void {
				if ( is_admin() ) {
					ActionGuard::register( self::ACTION, self::CAP, array( self::class, 'handle_snooze' ) );
				}
			},
			1
		);
		self::register_mail_resolvers();
	}

	/**
	 * Let the mail job look up the staff member's address and name when it runs, so neither is kept in the queue.
	 */
	public static function register_mail_resolvers(): void {
		add_filter(
			'wiw_mail_recipient_resolvers',
			static function ( $resolvers ): array {
				$resolvers                      = is_array( $resolvers ) ? $resolvers : array();
				$resolvers[ self::MAIL_SOURCE ] = static function ( int $id ): string {
					$user = StaffStatus::is_staff( $id ) ? get_userdata( $id ) : false;
					return $user instanceof \WP_User ? (string) $user->user_email : '';
				};
				return $resolvers;
			}
		);
		add_filter(
			'wiw_mail_name_resolvers',
			static function ( $resolvers ): array {
				$resolvers                      = is_array( $resolvers ) ? $resolvers : array();
				$resolvers[ self::MAIL_SOURCE ] = static function ( int $id ): string {
					$user = get_userdata( $id );
					return $user instanceof \WP_User ? (string) $user->first_name : '';
				};
				return $resolvers;
			}
		);
	}

	/**
	 * Whether the reminder applies to a user: the plugin is loaded, the user is staff and has no two-factor method on.
	 *
	 * @param int $user_id User id.
	 */
	public function applies( int $user_id ): bool {
		return $user_id > 0 && $this->status->available() && StaffStatus::is_staff( $user_id ) && ! $this->status->is_enrolled( $user_id )
			&& ! TestMode::quiet( $user_id );
	}

	/**
	 * The user's own profile, at the Two Factor plugin's options section.
	 */
	public static function profile_url(): string {
		return admin_url( 'profile.php' ) . self::ANCHOR;
	}

	/**
	 * The reminder sentence.
	 */
	private static function message(): string {
		return __( 'Protect your account: turn on two-factor sign-in. It takes two minutes with an authenticator app.', 'whoiswho' );
	}

	/**
	 * The "Turn it on now" button.
	 */
	private static function button(): string {
		return '<a class="button button-primary" href="' . esc_url( self::profile_url() ) . '">' . esc_html__( 'Turn it on now', 'whoiswho' ) . '</a>';
	}

	/**
	 * The admin notice for a user, or empty when it does not apply or is hidden for now.
	 *
	 * @param int $user_id User id.
	 * @param int $now     Current time.
	 */
	public function notice_html( int $user_id, int $now ): string {
		if ( ! $this->applies( $user_id ) || (int) get_user_meta( $user_id, self::META_SNOOZE, true ) > $now ) {
			return '';
		}
		$later = add_query_arg(
			array(
				'action'   => self::ACTION,
				'_wpnonce' => ActionGuard::nonce_for( self::ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
		return '<div class="notice notice-warning" id="' . esc_attr( self::NOTICE_ID ) . '"><p>' . esc_html( self::message() ) . '</p>'
			. '<p>' . self::button() . ' <a href="' . esc_url( $later ) . '">' . esc_html__( 'Remind me tomorrow', 'whoiswho' ) . '</a></p></div>';
	}

	/**
	 * The dashboard line for a user, or empty when it does not apply. It is not hidden by "Remind me tomorrow".
	 *
	 * @param int $user_id User id.
	 */
	public function dashboard_html( int $user_id ): string {
		if ( ! $this->applies( $user_id ) ) {
			return '';
		}
		return '<div class="wiw-two-factor-reminder"><p>' . esc_html( self::message() ) . '</p><p>' . self::button() . '</p></div>';
	}

	/**
	 * Hide the notice for 24 hours for this user.
	 *
	 * @param int $user_id User id.
	 * @param int $now     Current time.
	 */
	public static function snooze( int $user_id, int $now ): void {
		update_user_meta( $user_id, self::META_SNOOZE, (string) ( $now + DAY_IN_SECONDS ) );
	}

	/**
	 * "Remind me tomorrow" (admin-post; the nonce is checked by ActionGuard, and it only ever changes the current user's own reminder).
	 *
	 * @param Request $request Request.
	 */
	public static function handle_snooze( Request $request ): void {
		unset( $request );
		self::snooze( get_current_user_id(), time() );
		$back = wp_get_referer();
		wp_safe_redirect( false !== $back ? $back : admin_url() );
		exit;
	}

	/**
	 * Sign-in hook.
	 *
	 * @param string $login User login (unused).
	 * @param mixed  $user  The user signing in.
	 */
	public function on_login( string $login, $user ): void {
		unset( $login );
		if ( $user instanceof \WP_User ) {
			$this->maybe_email( (int) $user->ID, time() );
		}
	}

	/**
	 * Transient key counting reminder emails in the current hour.
	 *
	 * @param int $now Current time.
	 */
	public static function hour_key( int $now ): string {
		return 'wiw_2fa_mail_' . gmdate( 'YmdH', $now );
	}

	/**
	 * Send the one reminder email if it is due: staff, two-factor off, never emailed before, and under the hourly cap.
	 *
	 * @param int $user_id User id.
	 * @param int $now     Current time.
	 * @return bool Whether an email was queued.
	 */
	public function maybe_email( int $user_id, int $now ): bool {
		if ( ! $this->applies( $user_id ) || '' !== (string) get_user_meta( $user_id, self::META_EMAILED, true ) ) {
			return false;
		}
		$key  = self::hour_key( $now );
		$sent = (int) get_transient( $key );
		if ( $sent >= $this->hourly_cap ) {
			return false;
		}
		$notifier = $this->notifier ?? Notifier::for_site();
		$notifier->send_to_record( Events::TWO_FACTOR_REMINDER, self::MAIL_SOURCE, $user_id, array( 'cta_url' => self::profile_url() ) );
		update_user_meta( $user_id, self::META_EMAILED, (string) $now );
		set_transient( $key, $sent + 1, HOUR_IN_SECONDS );
		return true;
	}
}
