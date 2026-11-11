<?php
/**
 * Mail transport: sends every message through the cPanel SMTP account named in the secrets file. In production it fails closed:
 * with no credentials nothing is sent through PHP mail, and a staff-visible error says why. Locally, mail goes to a mail catcher
 * on 127.0.0.1:1025 when no SMTP account is set. No credential is ever written to a log or an error message.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

/**
 * Transport.
 */
final class Transport {

	public const OPTION_ERROR = 'wiw_mail_error';

	/**
	 * Messages per hour that bulk mail must stay under (hosts cap messages per hour). Recorded here for the newsletter issues.
	 */
	public const DEFAULT_HOURLY_CAP = 100;

	/**
	 * Settings.
	 *
	 * @var array{host: string, port: int, user: string, password: string, from: string, production: bool, hourly_cap: int}
	 */
	private array $config;

	/**
	 * Constructor.
	 *
	 * @param array{host: string, port: int, user: string, password: string, from: string, production: bool, hourly_cap: int} $config Settings.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Transport for this site, from the constants wp-config.php defines from the secrets file.
	 */
	public static function for_site(): self {
		$get = static fn ( string $name, string $fallback = '' ): string => defined( $name ) ? (string) constant( $name ) : $fallback;
		$cap = (int) $get( 'WIW_MAIL_HOURLY_CAP', (string) self::DEFAULT_HOURLY_CAP );

		return new self(
			array(
				'host'       => $get( 'WIW_SMTP_HOST' ),
				'port'       => (int) $get( 'WIW_SMTP_PORT', '587' ),
				'user'       => $get( 'WIW_SMTP_USER' ),
				'password'   => $get( 'WIW_SMTP_PASSWORD' ),
				'from'       => $get( 'WIW_SMTP_FROM' ),
				'production' => 'production' === wp_get_environment_type(),
				'hourly_cap' => $cap > 0 ? $cap : self::DEFAULT_HOURLY_CAP,
			)
		);
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'phpmailer_init', array( $this, 'configure' ) );
		add_filter( 'pre_wp_mail', array( $this, 'pre_send' ), 10, 2 );
		add_filter( 'wp_mail_from', array( $this, 'from_address' ) );
		add_action( 'wp_mail_failed', array( $this, 'log_failure' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Whether an SMTP account is configured.
	 */
	public function has_credentials(): bool {
		return '' !== $this->config['host'] && '' !== $this->config['user'] && '' !== $this->config['password'];
	}

	/**
	 * Messages per hour the site should stay under.
	 */
	public function hourly_cap(): int {
		return $this->config['hourly_cap'];
	}

	/**
	 * Point PHPMailer at the SMTP account, or at the local mail catcher.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $mailer Mailer.
	 */
	public function configure( $mailer ): void { // phpcs:disable WordPress.NamingConventions.ValidVariableName -- PHPMailer property names.
		if ( $this->has_credentials() ) {
			$mailer->isSMTP();
			$mailer->Host       = $this->config['host'];
			$mailer->Port       = $this->config['port'];
			$mailer->SMTPAuth   = true;
			$mailer->Username   = $this->config['user'];
			$mailer->Password   = $this->config['password'];
			$mailer->SMTPSecure = 465 === $this->config['port'] ? 'ssl' : 'tls';
			return;
		}
		if ( ! $this->config['production'] ) {
			// Local development: the mail catcher (Mailpit) listens on 1025.
			$mailer->isSMTP();
			$mailer->Host        = '127.0.0.1';
			$mailer->Port        = 1025;
			$mailer->SMTPAuth    = false;
			$mailer->SMTPSecure  = '';
			$mailer->SMTPAutoTLS = false;
		}
	}

	// phpcs:enable WordPress.NamingConventions.ValidVariableName

	/**
	 * In production with no credentials, refuse to send: no silent fallback to PHP mail. Returning false stops wp_mail.
	 *
	 * @param mixed                $pre  Earlier short-circuit value.
	 * @param array<string, mixed> $atts Message attributes (not logged).
	 * @return mixed
	 */
	public function pre_send( $pre, $atts = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- hook signature.
		if ( $this->config['production'] && ! $this->has_credentials() ) {
			update_option( self::OPTION_ERROR, 'smtp_credentials_missing', false );
			return false;
		}
		return $pre;
	}

	/**
	 * The From address: the configured one, else no-reply on the site's own domain.
	 *
	 * @param string $from Address WordPress would use.
	 */
	public function from_address( $from ): string {
		if ( '' !== $this->config['from'] && is_email( $this->config['from'] ) ) {
			return $this->config['from'];
		}
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return '' !== $host ? 'no-reply@' . preg_replace( '/^www\./', '', $host ) : (string) $from;
	}

	/**
	 * Log a failed send without any credential. The message is scrubbed of the password and the user name.
	 *
	 * @param \WP_Error $error Error from wp_mail.
	 */
	public function log_failure( \WP_Error $error ): void {
		$message = $error->get_error_message();
		$message = $this->scrub( $message );
		update_option( self::OPTION_ERROR, 'send_failed', false );
		error_log( 'wiw mail: send failed: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Remove credentials and recipient-looking text from a message.
	 *
	 * @param string $text Text.
	 */
	public function scrub( string $text ): string {
		foreach ( array( $this->config['password'], $this->config['user'] ) as $secret ) {
			if ( '' !== $secret ) {
				$text = str_replace( $secret, '[hidden]', $text );
			}
		}
		return (string) preg_replace( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[address]', $text );
	}

	/**
	 * The error shown to staff.
	 */
	public function notice(): void {
		$code = get_option( self::OPTION_ERROR );
		if ( ! $code || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$text = 'smtp_credentials_missing' === $code
			? __( 'Email is not being sent: the SMTP account is not set in the secrets file.', 'whoiswho' )
			: __( 'The last email could not be sent. Check the SMTP account and the server log.', 'whoiswho' );
		echo '<div class="notice notice-error"><p>' . esc_html( $text ) . '</p></div>';
	}
}
