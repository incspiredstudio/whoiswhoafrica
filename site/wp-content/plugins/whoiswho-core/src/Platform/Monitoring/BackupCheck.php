<?php
/**
 * The backup-age check (REQ-045). The host backup job, or the deploy script, records the time of the last good backup through
 * the wiw_backup_completed action or the backup:record command. Administrators see a warning in wp-admin when no backup was ever
 * recorded or the last one is older than two days.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Monitoring;

/**
 * Backup check.
 */
final class BackupCheck {

	public const OPTION  = 'wiw_backup_last_at';
	public const NEVER   = 'never';
	public const FRESH   = 'fresh';
	public const STALE   = 'stale';
	public const MAX_AGE = 172800;

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
	}

	/**
	 * Record a backup finishing now.
	 */
	public function record(): void {
		update_option( self::OPTION, ( $this->clock )(), false );
	}

	/**
	 * Never, fresh or stale.
	 */
	public function status(): string {
		$last = (int) get_option( self::OPTION, 0 );
		if ( $last <= 0 ) {
			return self::NEVER;
		}
		return ( ( $this->clock )() - $last ) > self::MAX_AGE ? self::STALE : self::FRESH;
	}

	/**
	 * Text for the warning, or an empty string when the backup is fresh.
	 */
	public function message(): string {
		$status = $this->status();
		if ( self::NEVER === $status ) {
			return __( 'No backup has been recorded yet. Check the host backup job.', 'whoiswho' );
		}
		if ( self::STALE === $status ) {
			$days = (int) floor( ( ( $this->clock )() - (int) get_option( self::OPTION, 0 ) ) / 86400 );
			/* translators: %d: days since the last recorded backup. */
			return sprintf( _n( 'The last backup was %d day ago. Check the host backup job.', 'The last backup was %d days ago. Check the host backup job.', $days, 'whoiswho' ), $days );
		}
		return '';
	}

	/**
	 * Admin notice for Administrators.
	 */
	public function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$message = $this->message();
		if ( '' === $message ) {
			return;
		}
		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
	}
}
