<?php
/**
 * GET /healthz (REQ-050, REQ-045). Without the token from the secrets file the answer is only {"status":"ok"} or
 * {"status":"fail"}; with it the answer lists the database, the job heartbeat, free disk and the release version stamp. The
 * response is never cached and is not indexed. It is served before WordPress routes the request, so it needs no rewrite rule
 * and no REST route (the guard keeps REST closed to visitors).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Health;

use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Health endpoint.
 */
final class HealthEndpoint {

	public const PATH = 'healthz';

	/**
	 * Check.
	 *
	 * @var HealthCheck
	 */
	private HealthCheck $check;

	/**
	 * The configured token; empty means detail is never shown.
	 *
	 * @var string
	 */
	private string $token;

	/**
	 * Reads the release version stamp (release.json), or null.
	 *
	 * @var callable(): (array<string, mixed>|null)
	 */
	private $release;

	/**
	 * Constructor.
	 *
	 * @param HealthCheck                                  $check   Check.
	 * @param string                                       $token   Token from the secrets file.
	 * @param callable(): (array<string, mixed>|null)|null $release Release stamp reader.
	 */
	public function __construct( HealthCheck $check, string $token, ?callable $release = null ) {
		$this->check   = $check;
		$this->token   = $token;
		$this->release = $release ?? array( self::class, 'read_release_stamp' );
	}

	/**
	 * Endpoint for this site.
	 */
	public static function for_site(): self {
		$token = defined( 'WIW_HEALTHZ_TOKEN' ) ? (string) constant( 'WIW_HEALTHZ_TOKEN' ) : '';
		return new self(
			new HealthCheck(
				array(
					'database'   => static function (): bool {
						global $wpdb;
						return '1' === (string) $wpdb->get_var( 'SELECT 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					},
					'heartbeat'  => static fn (): int => JobRunner::for_site()->heartbeat(),
					'now'        => static fn (): int => time(),
					'free_bytes' => static function (): int {
						$free = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the function warns on restricted hosts; false means unknown.
						return false === $free ? PHP_INT_MAX : (int) $free;
					},
				)
			),
			$token
		);
	}

	/**
	 * Attach the hook.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_serve' ), 0 );
	}

	/**
	 * Serve the endpoint when the request is for it.
	 */
	public function maybe_serve(): void {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only the path is compared below.
		$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
		if ( self::PATH !== $path ) {
			return;
		}
		$header   = isset( $_SERVER['HTTP_X_HEALTH_TOKEN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HEALTH_TOKEN'] ) ) : '';
		$response = $this->respond( $header );
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Content-Type: application/json; charset=utf-8' );
		status_header( $response['status'] );
		echo wp_json_encode( $response['body'] );
		exit;
	}

	/**
	 * Decide the answer (no output, so it can be tested).
	 *
	 * @param string $presented The token the caller sent, or an empty string.
	 * @return array{status: int, body: array<string, mixed>}
	 */
	public function respond( string $presented ): array {
		$report = $this->check->report();
		$status = $report['ok'] ? 200 : 503;
		if ( '' === $this->token || '' === $presented || ! hash_equals( $this->token, $presented ) ) {
			return array(
				'status' => $status,
				'body'   => array( 'status' => $report['ok'] ? 'ok' : 'fail' ),
			);
		}
		$release = call_user_func( $this->release );
		return array(
			'status' => $status,
			'body'   => array(
				'status'   => $report['ok'] ? 'ok' : 'fail',
				'database' => $report['database'],
				'jobs'     => $report['jobs'],
				'disk'     => $report['disk'],
				'release'  => is_array( $release ) ? $release : null,
				'plugin'   => defined( 'WIW_CORE_VERSION' ) ? (string) constant( 'WIW_CORE_VERSION' ) : null,
			),
		);
	}

	/**
	 * Read release.json, written next to index.php by deploy/build-release.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function read_release_stamp(): ?array {
		$file = dirname( rtrim( ABSPATH, '/\\' ) ) . '/release.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file written by the build.
		if ( ! is_array( $data ) ) {
			return null;
		}
		return array_intersect_key( $data, array_flip( array( 'version', 'commit', 'built_at' ) ) );
	}
}
