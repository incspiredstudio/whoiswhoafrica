<?php
/**
 * The backup-age check (REQ-045). The host backup job, or the deploy script, records the time of the last good backup through
 * the wiw_backup_completed action or the backup:record command. On a cPanel host nothing reports, so the Administrator can press
 * "I have set up backups" once (recorded and audited). The reminder shows only on the Dashboard and the Setup screen, to people
 * who can act on it, in plain words, and each person can hide it for 30 days.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Monitoring;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Backup check.
 */
final class BackupCheck {

	public const OPTION           = 'wiw_backup_last_at';
	public const OPTION_CONFIRMED = 'wiw_backup_host_confirmed_at';
	public const HIDDEN_META      = 'wiw_backup_notice_hidden_until';
	public const ACTION           = 'wiw_backup_notice';
	public const CAP              = 'wiw_run_setup';
	public const HIDE_FOR         = 2592000;
	public const NEVER            = 'never';
	public const FRESH            = 'fresh';
	public const STALE            = 'stale';
	public const CONFIRMED        = 'confirmed';
	public const MAX_AGE          = 172800;

	/**
	 * Clock returning Unix time.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param callable|null $clock Returns Unix time.
	 */
	public function __construct( ?callable $clock = null ) {
		$this->clock = $clock ?? static fn (): int => time();
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'wiw_backup_completed', array( $this, 'record' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		if ( is_admin() ) {
			ActionGuard::register( self::ACTION, self::CAP, array( $this, 'handle' ) );
		}
	}

	/**
	 * Record a backup finishing now.
	 */
	public function record(): void {
		update_option( self::OPTION, ( $this->clock )(), false );
	}

	/**
	 * Never, fresh, stale or confirmed (the Administrator said the host backups are set up, after the last recorded backup).
	 */
	public function status(): string {
		$last      = (int) get_option( self::OPTION, 0 );
		$confirmed = (int) get_option( self::OPTION_CONFIRMED, 0 );
		if ( $last > 0 && ( ( $this->clock )() - $last ) <= self::MAX_AGE ) {
			return self::FRESH;
		}
		if ( $confirmed > 0 && $confirmed >= $last ) {
			return self::CONFIRMED;
		}
		return $last <= 0 ? self::NEVER : self::STALE;
	}

	/**
	 * Text for the warning, or an empty string when nothing needs doing.
	 */
	public function message(): string {
		$status = $this->status();
		if ( self::NEVER === $status ) {
			return __( 'No backup has been recorded for this site yet. Turn on backups in cPanel (Backup or JetBackup), then click "I have set up backups".', 'whoiswho' );
		}
		if ( self::STALE === $status ) {
			$days = (int) floor( ( ( $this->clock )() - (int) get_option( self::OPTION, 0 ) ) / 86400 );
			/* translators: %d: days since the last recorded backup. */
			return sprintf( _n( 'The last recorded backup was %d day ago. Check in cPanel (Backup or JetBackup) that backups still run, then click "I have set up backups".', 'The last recorded backup was %d days ago. Check in cPanel (Backup or JetBackup) that backups still run, then click "I have set up backups".', $days, 'whoiswho' ), $days );
		}
		return '';
	}

	/**
	 * The reminder for a screen, or an empty string: only on the Dashboard and the Setup screen, only for someone who can act on
	 * it, and not while that person has hidden it.
	 *
	 * @param string $screen_id Current admin screen id.
	 */
	public function notice_html( string $screen_id ): string {
		// The Dashboard, and the Setup screen (its id ends in page_wiw-setup, whichever menu it sits under).
		$shown = 'dashboard' === $screen_id || 'page_wiw-setup' === substr( $screen_id, -14 );
		if ( ! $shown || ! current_user_can( self::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			return '';
		}
		if ( (int) get_user_meta( get_current_user_id(), self::HIDDEN_META, true ) > ( $this->clock )() ) {
			return '';
		}
		$message = $this->message();
		if ( '' === $message ) {
			return '';
		}
		return '<div class="notice notice-warning wiw-backup-notice"><p>' . esc_html( $message ) . '</p><p>'
			. $this->form( 'confirm', __( 'I have set up backups', 'whoiswho' ), 'button button-primary' ) . ' '
			. $this->form( 'hide', __( 'Remind me in 30 days', 'whoiswho' ), 'button-link' )
			. '</p></div>';
	}

	/**
	 * One small form posting to admin-post with the nonce.
	 *
	 * @param string $what  confirm or hide.
	 * @param string $label Button text.
	 * @param string $css   Button class.
	 */
	private function form( string $what, string $label, string $css ): string {
		return '<form method="post" class="wiw-inline-form" style="display:inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />'
			. '<input type="hidden" name="what" value="' . esc_attr( $what ) . '" />'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />'
			. '<button type="submit" class="' . esc_attr( $css ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Admin notice.
	 */
	public function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen ) {
			return;
		}
		echo $this->notice_html( (string) $screen->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built.
	}

	/**
	 * Handle a button (admin-post; ActionGuard has checked the capability and the nonce): "confirm" records that the host backups
	 * are set up (audited); "hide" hides the reminder for this person for 30 days.
	 *
	 * @param Request $request  Request.
	 * @param bool    $redirect Go back to the screen afterwards (tests pass false).
	 */
	public function handle( Request $request, bool $redirect = true ): void {
		$what = $request->string( 'what' );
		if ( 'confirm' === $what ) {
			update_option( self::OPTION_CONFIRMED, ( $this->clock )(), false );
			try {
				AuditLogger::for_site()->log(
					'backup.host_confirmed',
					array(
						'type' => 'site',
						'id'   => 'backup',
					)
				);
			} catch ( \Throwable $e ) {
				unset( $e );
				// A busy audit log must not lose the confirmation.
			}
		} elseif ( 'hide' === $what ) {
			update_user_meta( get_current_user_id(), self::HIDDEN_META, ( $this->clock )() + self::HIDE_FOR );
		}
		if ( $redirect ) {
			$back = wp_get_referer();
			wp_safe_redirect( is_string( $back ) && '' !== $back ? $back : admin_url() );
			exit;
		}
	}
}
