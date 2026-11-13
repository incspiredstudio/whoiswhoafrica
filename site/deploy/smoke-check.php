<?php
/**
 * Post-deploy smoke check (M5-LCH-03; REQ-273, REQ-208, REQ-046). Runs the same checks against staging and live, prints a
 * pass or fail list for the runbook and exits non-zero when any check fails.
 *
 * Usage (from the site folder, on the host):
 *   php deploy/smoke-check.php --url=https://example.org [--healthz-token=TOKEN] [--gone-path=/profile/unpublished-slug]
 *   php deploy/smoke-check.php --facts=facts.json        (judge saved facts; no WordPress, no network; used by the tests)
 *   php deploy/smoke-check.php --url=... --no-wp          (HTTP checks only, from another machine)
 *
 * Prints names of checks only; never personal data, never a secret value.
 *
 * @package WhoIsWho
 */

// phpcs:disable -- a command line deploy script outside the linted source.

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Forbidden\n" );
}

require __DIR__ . '/smoke/Smoke.php';

use WhoIsWho\Deploy\Smoke;

$opts = getopt( '', array( 'url::', 'healthz-token::', 'gone-path::', 'facts::', 'no-wp' ) );

/**
 * Plain HTTP probe without following redirects.
 *
 * @return array{status: int, location: string, body: string}
 */
function wiw_probe( string $url, string $extra_header = '' ): array {
	$ctx  = stream_context_create(
		array(
			'http' => array(
				'method'          => 'GET',
				'timeout'         => 20,
				'follow_location' => 0,
				'ignore_errors'   => true,
				'header'          => "User-Agent: wiw-smoke-check\r\n" . $extra_header,
			),
		)
	);
	$body = @file_get_contents( $url, false, $ctx );
	$out  = array( 'status' => 0, 'location' => '', 'body' => false === $body ? '' : substr( $body, 0, 20000 ) );
	foreach ( (array) ( $http_response_header ?? array() ) as $line ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $line, $m ) ) {
			$out['status'] = (int) $m[1];
		} elseif ( 0 === stripos( $line, 'Location:' ) ) {
			$out['location'] = trim( substr( $line, 9 ) );
		}
	}
	return $out;
}

$facts = array();

if ( isset( $opts['facts'] ) ) {
	$decoded = json_decode( (string) file_get_contents( (string) $opts['facts'] ), true );
	$facts   = is_array( $decoded ) ? $decoded : array();
} else {
	$base = isset( $opts['url'] ) ? rtrim( (string) $opts['url'], '/' ) : '';

	if ( ! isset( $opts['no-wp'] ) ) {
		$wp_load = dirname( __DIR__ ) . '/wordpress/wp-load.php';
		if ( ! is_readable( $wp_load ) ) {
			fwrite( STDERR, "FAIL  WordPress was not found next to the deploy folder\n" );
			exit( 1 );
		}
		// wp-config stops with a plain message when the secrets file is missing; turn that into a failure.
		$loaded = false;
		register_shutdown_function(
			static function () use ( &$loaded ): void {
				if ( ! $loaded ) {
					fwrite( STDERR, "FAIL  The secrets file is loaded by wp-config (WordPress did not start)\n" );
					exit( 1 );
				}
			}
		);
		$_SERVER['HTTP_HOST']   = $base ? (string) parse_url( $base, PHP_URL_HOST ) : 'localhost';
		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
		define( 'WP_USE_THEMES', false );
		require $wp_load;
		$loaded = true;

		$facts['stylesheet']         = get_stylesheet();
		$facts['template']           = get_template();
		$facts['parent_exists']      = is_readable( WP_CONTENT_DIR . '/themes/whoiswho/style.css' );
		$facts['secrets_loaded']     = defined( 'DB_NAME' ) && '' !== (string) DB_NAME && defined( 'WIW_FORM_TOKEN_KEY' ) && '' !== (string) WIW_FORM_TOKEN_KEY;
		$facts['file_edit_disabled'] = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
		$facts['registration_open']  = (bool) get_option( 'users_can_register' );
		$facts['cron_scheduled']     = (bool) _get_cron_array();
		if ( class_exists( '\WhoIsWho\Core\Platform\Migrations\Runner' ) ) {
			$facts['pending_migrations'] = count( \WhoIsWho\Core\Platform\Migrations\Runner::for_site()->pending() );
		}
		if ( '' === $base ) {
			$base = rtrim( (string) home_url(), '/' );
		}
	}

	if ( '' !== $base ) {
		$facts['base_url'] = $base;
		$token             = isset( $opts['healthz-token'] ) ? (string) $opts['healthz-token'] : '';
		$gone              = isset( $opts['gone-path'] ) ? '/' . ltrim( (string) $opts['gone-path'], '/' ) : '';
		$facts['http']     = array(
			'home'        => wiw_probe( $base . '/' ),
			'xmlrpc'      => wiw_probe( $base . '/xmlrpc.php' ),
			'rest'        => wiw_probe( $base . '/wp-json/' ),
			'sitemap'     => wiw_probe( $base . '/sitemap.xml' ),
			'author_enum' => wiw_probe( $base . '/?author=1' ),
			'healthz'     => wiw_probe( $base . '/healthz', '' !== $token ? 'X-Health-Token: ' . $token . "\r\n" : '' ),
		);
		if ( '' !== $gone ) {
			$facts['http']['gone'] = wiw_probe( $base . $gone );
		}
	}
}

$results = Smoke::evaluate( $facts );
echo Smoke::render( $results );
exit( Smoke::passed( $results ) ? 0 : 1 );
