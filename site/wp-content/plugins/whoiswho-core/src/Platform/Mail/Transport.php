<?php
/**
 * Mail transport (REQ-075, REQ-204), in this order:
 * 1. An active mail plugin that already sends through SMTP (FluentSMTP, the recommended one, or another listed below) owns the
 *    connection: this class leaves PHPMailer and the From address alone.
 * 2. Else, when the WIW_SMTP_* constants (from the secrets file) name a server, mail goes through it.
 * 3. Else WordPress sends with its own wp_mail, as any plain WordPress does.
 * A local mail catcher is used only when WIW_SMTP_HOST points at it. The hourly cap is recorded for bulk mail, and no credential
 * or address is ever written to a log or an error message.
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
	 * Mail plugins that replace the SMTP connection themselves, by plugin file. FluentSMTP is the recommended one.
	 */
	public const SMTP_PLUGINS = array(
		'fluent-smtp/fluent-smtp.php',
		'wp-mail-smtp/wp_mail_smtp.php',
		'wp-mail-smtp-pro/wp_mail_smtp.php',
		'post-smtp/postman-smtp.php',
		'easy-wp-smtp/easy-wp-smtp.php',
		'smtp-mailer/main.php',
	);

	public const FLUENT_SMTP        = 'fluent-smtp/fluent-smtp.php';
	public const FLUENT_SMTP_OPTION = 'fluentmail-settings';

	/**
	 * Messages per hour that bulk mail must stay under (hosts cap messages per hour). Recorded here for the newsletter issues.
	 */
	public const DEFAULT_HOURLY_CAP = 100;

	/**
	 * Settings.
	 *
	 * @var array{host: string, port: int, user: string, password: string, from: string, hourly_cap: int}
	 */
	private array $config;

	/**
	 * Says whether a mail plugin owns the SMTP connection (checked when mail is sent, so plugin load order does not matter).
	 *
	 * @var callable(): bool
	 */
	private $external;

	/**
	 * Constructor.
	 *
	 * @param array{host: string, port: int, user: string, password: string, from: string, hourly_cap: int} $config   Settings.
	 * @param (callable(): bool)|null                                                                   $external Whether a mail plugin owns SMTP; the active plugins by default.
	 */
	public function __construct( array $config, ?callable $external = null ) {
		$this->config   = $config;
		$this->external = $external ?? array( self::class, 'smtp_plugin_active' );
	}

	/**
	 * Transport for this site, from the WIW_SMTP_* constants when they exist (wp-config.php defines them from the secrets file).
	 */
	public static function for_site(): self {
		$get = static fn ( string $name, string $fallback = '' ): string => defined( $name ) && '' !== trim( (string) constant( $name ) ) ? trim( (string) constant( $name ) ) : $fallback;
		$cap = (int) $get( 'WIW_MAIL_HOURLY_CAP', (string) self::DEFAULT_HOURLY_CAP );

		return new self(
			array(
				'host'       => $get( 'WIW_SMTP_HOST' ),
				'port'       => (int) $get( 'WIW_SMTP_PORT', '587' ),
				'user'       => $get( 'WIW_SMTP_USER' ),
				'password'   => $get( 'WIW_SMTP_PASSWORD' ),
				'from'       => $get( 'WIW_SMTP_FROM' ),
				'hourly_cap' => $cap > 0 ? $cap : self::DEFAULT_HOURLY_CAP,
			)
		);
	}

	/**
	 * The plugin files that are active on this site, single-site and network-wide.
	 *
	 * @return string[]
	 */
	private static function active_plugins(): array {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		return array_map( 'strval', $active );
	}

	/**
	 * Whether a mail plugin that replaces the SMTP connection is active. Sites can add their own through the wiw_mail_smtp_plugins filter.
	 */
	public static function smtp_plugin_active(): bool {
		$known = (array) apply_filters( 'wiw_mail_smtp_plugins', self::SMTP_PLUGINS );
		return array() !== array_intersect( array_map( 'strval', $known ), self::active_plugins() );
	}

	/**
	 * Whether FluentSMTP is active.
	 */
	public static function fluent_smtp_active(): bool {
		return in_array( self::FLUENT_SMTP, self::active_plugins(), true );
	}

	/**
	 * Whether FluentSMTP is active and has at least one sending connection set up.
	 */
	public static function fluent_smtp_configured(): bool {
		if ( ! self::fluent_smtp_active() ) {
			return false;
		}
		$settings = get_option( self::FLUENT_SMTP_OPTION, array() );
		return is_array( $settings ) && ! empty( $settings['connections'] ) && is_array( $settings['connections'] );
	}

	/**
	 * The FluentSMTP settings screen.
	 */
	public static function fluent_smtp_url(): string {
		return admin_url( 'options-general.php?page=fluent-mail#/' );
	}

	/**
	 * Whether a mail plugin owns the SMTP connection right now.
	 */
	public function uses_mail_plugin(): bool {
		return (bool) call_user_func( $this->external );
	}

	/**
	 * Whether the WIW_SMTP_* constants name a mail server.
	 */
	public function has_server(): bool {
		return '' !== $this->config['host'];
	}

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'phpmailer_init', array( $this, 'configure' ) );
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'from_name' ) );
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
	 * Point PHPMailer at the server the constants name. With a mail plugin in charge, or with no server named, PHPMailer is left as
	 * WordPress set it up.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $mailer Mailer.
	 */
	public function configure( $mailer ): void { // phpcs:disable WordPress.NamingConventions.ValidVariableName -- PHPMailer property names.
		if ( $this->uses_mail_plugin() ) {
			return;
		}
		// Name ourselves with the sending domain (EHLO and Message-ID), also when a job runs from the command line with no host.
		$at     = strrchr( $this->from_address( '' ), '@' );
		$domain = false === $at ? '' : substr( $at, 1 );
		if ( '' !== $domain ) {
			$mailer->Hostname = $domain;
		}
		// A mail server can be slow to answer after the message (spam scanning); give it time, but not forever.
		$mailer->Timeout = 45;
		if ( ! $this->has_server() ) {
			return;
		}
		$mailer->isSMTP();
		$mailer->Host = $this->config['host'];
		$mailer->Port = $this->config['port'];
		if ( $this->has_credentials() ) {
			$mailer->SMTPAuth   = true;
			$mailer->Username   = $this->config['user'];
			$mailer->Password   = $this->config['password'];
			$mailer->SMTPSecure = 465 === $this->config['port'] ? 'ssl' : 'tls';
			return;
		}
		// A server with no account: a relay, or a local mail catcher such as Mailpit on 127.0.0.1:1025.
		$mailer->SMTPAuth = false;
		if ( in_array( strtolower( $this->config['host'] ), array( '127.0.0.1', 'localhost', '::1' ), true ) ) {
			$mailer->SMTPSecure  = '';
			$mailer->SMTPAutoTLS = false;
		}
	}

	// phpcs:enable WordPress.NamingConventions.ValidVariableName

	/**
	 * The wp_mail_from filter: our From address, unless a mail plugin is in charge of it.
	 *
	 * @param string $from Address WordPress would use.
	 */
	public function mail_from( $from ): string {
		return $this->uses_mail_plugin() ? (string) $from : $this->from_address( $from );
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
	 * The From name: the brand, not WordPress.
	 *
	 * @param string $name Name WordPress would use.
	 */
	public function from_name( $name ): string {
		$brand = trim( (string) apply_filters( 'wiw_brand_name', (string) get_bloginfo( 'name' ) ) );
		return '' !== $brand ? $brand : (string) $name;
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
		if ( 'smtp_credentials_missing' === $code ) {
			// Left by an older version, which refused to send without an SMTP account. Mail now goes out through WordPress.
			delete_option( self::OPTION_ERROR );
			return;
		}
		if ( ! $code || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$text = $this->uses_mail_plugin()
			? __( 'The last email could not be sent. Open your mail plugin\'s settings (for example Settings > FluentSMTP) and send a test email.', 'whoiswho' )
			: __( 'The last email could not be sent. For reliable email, install the free FluentSMTP plugin and connect it to your email account; the Setup screen shows how.', 'whoiswho' );
		echo '<div class="notice notice-error"><p>' . esc_html( $text ) . '</p></div>';
	}
}
