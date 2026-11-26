<?php
/**
 * Security headers and the HTTPS redirect. Every response gets HSTS (on HTTPS), nosniff, Referrer-Policy, Permissions-Policy
 * and a Content-Security-Policy. While a public page is built, trusted scripts carry the per-request nonce from wiw_csp_nonce()
 * (WordPress adds it to every script it prints). When the finished page leaves PHP, the nonce is traded for the SHA-256 hashes of
 * those inline scripts (and the origins of trusted scripts on other hosts), and the header is replaced to match. So the stored
 * body of a cached page holds no per-request token, and its policy comes from the body alone: a page cache that stores the
 * body and its headers (WP Super Cache does) always serves a matching pair (issue 719). A page that cannot be finished this way
 * (flushed in parts, or not HTML) keeps the nonce and the nonce header, which also match.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Guard\Headers;

/**
 * Headers.
 */
final class Headers {

	/**
	 * The nonce for this request.
	 *
	 * @var string
	 */
	private static string $nonce = '';

	/**
	 * Attach hooks.
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', array( self::class, 'redirect_to_https' ), 1 );
		add_action( 'send_headers', array( self::class, 'send' ) );
		add_action( 'admin_init', array( self::class, 'send_admin' ), 1 );
		add_action( 'login_init', array( self::class, 'send_admin' ), 1 );
		add_filter( 'wp_script_attributes', array( self::class, 'add_nonce_attribute' ) );
		add_filter( 'wp_inline_script_attributes', array( self::class, 'add_nonce_attribute' ) );
		add_filter( 'secure_signon_cookie', array( self::class, 'secure_cookie' ) );
	}

	/**
	 * The nonce for this request, created on first use.
	 */
	public static function nonce(): string {
		if ( '' === self::$nonce ) {
			self::$nonce = rtrim( strtr( base64_encode( random_bytes( 16 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		return self::$nonce;
	}

	/**
	 * Extra allowed sources, from sources.php.
	 *
	 * @return array<string, string[]>
	 */
	public static function sources(): array {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- inline type hint for static analysis.
		/** @var array<string, string[]> $sources */
		$sources = require __DIR__ . '/sources.php';
		return $sources;
	}

	/**
	 * The policy for public pages: scripts only with the nonce, nothing framed, nothing plugin-based.
	 *
	 * @param string                  $nonce   The request nonce; empty for none.
	 * @param array<string, string[]> $extra   Extra sources by directive.
	 * @param string[]                $scripts Script hashes and origins taken from a finished page.
	 */
	public static function csp( string $nonce, array $extra = array(), array $scripts = array() ): string {
		$script_src = array( "'self'" );
		if ( '' !== $nonce ) {
			$script_src[] = "'nonce-{$nonce}'";
		}
		$directives = array(
			'default-src'     => array( "'self'" ),
			'script-src'      => array_merge( $script_src, $scripts ),
			'style-src'       => array( "'self'", "'unsafe-inline'" ),
			'img-src'         => array( "'self'", 'data:' ),
			'font-src'        => array( "'self'" ),
			'connect-src'     => array( "'self'" ),
			'frame-src'       => array( "'none'" ),
			'media-src'       => array( "'self'" ),
			'object-src'      => array( "'none'" ),
			'base-uri'        => array( "'self'" ),
			'form-action'     => array( "'self'" ),
			'frame-ancestors' => array( "'none'" ),
		);
		foreach ( $extra as $directive => $list ) {
			if ( array() === $list || ! isset( $directives[ $directive ] ) ) {
				continue;
			}
			$base                     = 'frame-src' === $directive ? array() : $directives[ $directive ];
			$directives[ $directive ] = array_merge( $base, $list );
		}
		$parts = array();
		foreach ( $directives as $name => $values ) {
			$parts[] = $name . ' ' . implode( ' ', $values );
		}
		return implode( '; ', $parts );
	}

	/**
	 * A policy for wp-admin and the sign-in page. WordPress admin needs inline scripts and styles, so this one only keeps the
	 * strict parts (no framing, no plugins, same-origin forms). It is sent report-only first.
	 */
	public static function admin_csp(): string {
		return "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";
	}

	/**
	 * The headers for a response.
	 *
	 * @param bool   $https Whether the request is over HTTPS.
	 * @param bool   $admin Whether this is wp-admin or the sign-in page.
	 * @param string $nonce The request nonce.
	 * @return array<string, string>
	 */
	public static function headers( bool $https, bool $admin, string $nonce ): array {
		$headers = array(
			'X-Content-Type-Options' => 'nosniff',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
			'X-Frame-Options'        => 'DENY',
		);
		if ( $https ) {
			$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
		}
		if ( $admin ) {
			$headers['Content-Security-Policy-Report-Only'] = self::admin_csp();
		} else {
			$headers['Content-Security-Policy'] = self::csp( $nonce, self::sources() );
		}
		return $headers;
	}

	/**
	 * Send the headers for public pages.
	 */
	public static function send(): void {
		if ( is_admin() ) {
			return;
		}
		self::emit( self::headers( is_ssl(), false, self::nonce() ) );
		// Web requests only: the test suite and WP-CLI print nothing to a browser.
		if ( 'cli' !== PHP_SAPI ) {
			ob_start( array( self::class, 'finish' ) );
		}
	}

	/**
	 * Output-buffer callback for public pages: once the whole page is ready, trade the nonce for script hashes and replace the
	 * policy header to match (issue 719). Anything else passes through untouched, with the nonce header sent earlier.
	 *
	 * @param string $buffer The page.
	 * @param int    $phase  PHP output-handler phase flags.
	 */
	public static function finish( string $buffer, int $phase ): string {
		$whole = ( $phase & PHP_OUTPUT_HANDLER_START ) && ( $phase & PHP_OUTPUT_HANDLER_FINAL ) && ! headers_sent();
		$done  = self::finish_policy( $buffer, self::nonce(), self::content_type(), $whole );
		if ( null === $done ) {
			return $buffer;
		}
		header( 'Content-Security-Policy: ' . $done[1], true );
		return $done[0];
	}

	/**
	 * The finished body and its policy, or null when the output must be left as it is: not HTML, or not the whole page in one
	 * piece (a page flushed in parts has already sent its header, so it keeps the nonce that header names).
	 *
	 * @param string $buffer       The output.
	 * @param string $nonce        The request nonce.
	 * @param string $content_type The response Content-Type ('' when none was set, which PHP sends as HTML).
	 * @param bool   $whole        Whether this is the whole page and the header can still be replaced.
	 * @return array{0: string, 1: string}|null
	 */
	public static function finish_policy( string $buffer, string $nonce, string $content_type, bool $whole ): ?array {
		if ( ! $whole || '' === $nonce || ( '' !== $content_type && false === stripos( $content_type, 'text/html' ) ) ) {
			return null;
		}
		list( $body, $scripts ) = self::finalise( $buffer, $nonce, (string) get_option( 'home' ) );
		return array( $body, self::csp( '', self::sources(), $scripts ) );
	}

	/**
	 * Remove this request's nonce from every script tag that carries it, and list what the policy must allow for those scripts:
	 * the SHA-256 hash of each inline script that runs, and the origin of each script file on another host. Scripts without the
	 * nonce (anything that slipped into the content) are left out, so they stay blocked. The result depends only on the page, not
	 * on the nonce, so two requests for the same page give the same body and the same policy.
	 *
	 * @param string $html  The page.
	 * @param string $nonce The request nonce.
	 * @param string $home  The site address, to tell same-origin files from other hosts.
	 * @return array{0: string, 1: string[]}
	 */
	public static function finalise( string $html, string $nonce, string $home ): array {
		$attr    = '/\snonce=(["\'])' . preg_quote( $nonce, '/' ) . '\1/';
		$own     = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$scripts = array();
		$body    = preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			static function ( array $m ) use ( $attr, $own, &$scripts ): string {
				if ( 1 !== preg_match( $attr, $m[1] ) ) {
					return $m[0];
				}
				$attrs = (string) preg_replace( $attr, '', $m[1] );
				if ( 1 === preg_match( '/\ssrc=(["\'])(.*?)\1/i', $attrs, $src ) ) {
					$url    = 0 === strpos( $src[2], '//' ) ? 'https:' . $src[2] : $src[2];
					$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
					$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
					if ( '' !== $host && $host !== $own && in_array( $scheme, array( 'https', 'http' ), true ) ) {
						$scripts[] = $scheme . '://' . $host;
					}
				} elseif ( self::runs( $attrs ) ) {
					$scripts[] = "'sha256-" . base64_encode( hash( 'sha256', $m[2], true ) ) . "'"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- CSP hashes are base64.
				}
				return '<script' . $attrs . '>' . $m[2] . '</script>';
			},
			$html
		);
		$scripts = array_values( array_unique( $scripts ) );
		sort( $scripts );
		return array( null === $body ? $html : $body, $scripts );
	}

	/**
	 * Whether an inline script with these attributes is run by the browser (and so needs to be allowed). Data blocks such as
	 * JSON-LD are never run.
	 *
	 * @param string $attrs The tag's attributes.
	 */
	private static function runs( string $attrs ): bool {
		if ( 1 !== preg_match( '/\stype=(["\'])(.*?)\1/i', $attrs, $type ) ) {
			return true;
		}
		$type = strtolower( trim( $type[2] ) );
		return in_array( $type, array( '', 'text/javascript', 'application/javascript', 'module', 'speculationrules' ), true );
	}

	/**
	 * The Content-Type this response will be sent with, or '' when none has been set yet.
	 */
	private static function content_type(): string {
		foreach ( headers_list() as $line ) {
			if ( 0 === stripos( $line, 'content-type:' ) ) {
				return trim( substr( $line, 13 ) );
			}
		}
		return '';
	}

	/**
	 * Send the headers for wp-admin and the sign-in page, which are never cacheable.
	 */
	public static function send_admin(): void {
		self::emit( self::headers( is_ssl(), true, self::nonce() ) );
		nocache_headers();
	}

	/**
	 * Emit headers unless output has already started.
	 *
	 * @param array<string, string> $headers Headers.
	 */
	private static function emit( array $headers ): void {
		if ( headers_sent() ) {
			return;
		}
		foreach ( $headers as $name => $value ) {
			header( $name . ': ' . $value );
		}
	}

	/**
	 * Put the nonce on every script tag WordPress prints.
	 *
	 * @param array<string, mixed> $attributes Script attributes.
	 * @return array<string, mixed>
	 */
	public static function add_nonce_attribute( array $attributes ): array {
		$attributes['nonce'] = self::nonce();
		return $attributes;
	}

	/**
	 * Whether a request should be redirected to HTTPS: only in production, only over plain HTTP, and only when the
	 * site address (Settings > General) is already https. A new host without a certificate yet keeps working over
	 * HTTP until the owner switches the site address, so nobody is locked out at install time (REQ-046, REQ-056).
	 *
	 * @param string $env  Environment type.
	 * @param bool   $ssl  Whether this request is HTTPS.
	 * @param string $home The site address.
	 */
	public static function should_redirect( string $env, bool $ssl, string $home ): bool {
		return 'production' === $env && ! $ssl && self::is_https_url( $home );
	}

	/**
	 * Whether cookies must be marked secure: in production once the site address is https.
	 *
	 * @param string $env  Environment type.
	 * @param string $home The site address.
	 */
	public static function wants_secure_cookie( string $env, string $home ): bool {
		return 'production' === $env && self::is_https_url( $home );
	}

	/**
	 * Whether a URL uses https.
	 *
	 * @param string $url URL.
	 */
	private static function is_https_url( string $url ): bool {
		return 0 === strpos( strtolower( trim( $url ) ), 'https://' );
	}

	/**
	 * In production, redirect HTTP to HTTPS once the site address is https.
	 */
	public static function redirect_to_https(): void {
		if ( wp_doing_cron() || 'cli' === PHP_SAPI || ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) ) {
			return;
		}
		if ( ! self::should_redirect( wp_get_environment_type(), is_ssl(), (string) get_option( 'home' ) ) ) {
			return;
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		if ( '' === $host ) {
			return;
		}
		wp_safe_redirect( 'https://' . $host . $uri, 301 );
		exit;
	}

	/**
	 * Cookies are secure in production once the site address is https.
	 *
	 * @param bool $secure Current value.
	 */
	public static function secure_cookie( $secure ): bool {
		return self::wants_secure_cookie( wp_get_environment_type(), (string) get_option( 'home' ) ) ? true : (bool) $secure;
	}
}
