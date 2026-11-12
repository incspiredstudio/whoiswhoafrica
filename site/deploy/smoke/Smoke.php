<?php
/**
 * Post-deploy smoke checks (M5-LCH-03; REQ-273, REQ-208, REQ-046). Pure logic: it judges a list of facts that
 * deploy/smoke-check.php collects from the running site, so staging and live are judged by the same rules and the rules can
 * be tested offline. A fact that is missing gives "skip", never "pass".
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Deploy;

/**
 * Smoke check rules.
 */
final class Smoke {

	public const PASS = 'pass';
	public const FAIL = 'fail';
	public const SKIP = 'skip';

	/**
	 * Judge the facts.
	 *
	 * @param array<string, mixed> $facts Facts collected from the site.
	 * @return list<array{name: string, status: string, detail: string}>
	 */
	public static function evaluate( array $facts ): array {
		$http = isset( $facts['http'] ) && is_array( $facts['http'] ) ? $facts['http'] : array();
		$out  = array();

		$out[] = self::child_theme( $facts );
		$out[] = self::flag( $facts, 'secrets_loaded', 'The secrets file is loaded by wp-config', true );
		$out[] = self::flag( $facts, 'file_edit_disabled', 'File editing is off', true );
		$out[] = self::flag( $facts, 'registration_open', 'Public registration is off', false );
		$out[] = self::migrations( $facts );
		$out[] = self::flag( $facts, 'cron_scheduled', 'Scheduled jobs are queued', true );

		$out[] = self::status( $http, 'home', 'Home page answers 200', array( 200 ) );
		$out[] = self::https( $facts );
		$out[] = self::status( $http, 'xmlrpc', 'XML-RPC is closed', array( 403, 404, 405, 410 ) );
		$out[] = self::status( $http, 'rest', 'REST index is closed to visitors', array( 401, 403, 404 ) );
		$out[] = self::status( $http, 'sitemap', 'Sitemap index answers 200', array( 200 ) );
		$out[] = self::sitemap_body( $http );
		$out[] = self::author_enumeration( $http );
		$out[] = self::status( $http, 'healthz', 'Health endpoint answers 200', array( 200 ) );
		$out[] = self::status( $http, 'gone', 'An unpublished address answers 410 or 404', array( 404, 410 ) );
		return $out;
	}

	/**
	 * True when no check failed and at least one passed. Skips do not fail the run, but an all-skipped run proves nothing.
	 *
	 * @param list<array{name: string, status: string, detail: string}> $results Results.
	 */
	public static function passed( array $results ): bool {
		$passed = 0;
		foreach ( $results as $result ) {
			if ( self::FAIL === $result['status'] ) {
				return false;
			}
			$passed += self::PASS === $result['status'] ? 1 : 0;
		}
		return $passed > 0;
	}

	/**
	 * A plain pass or fail list for the runbook.
	 *
	 * @param list<array{name: string, status: string, detail: string}> $results Results.
	 */
	public static function render( array $results ): string {
		$lines = array();
		foreach ( $results as $result ) {
			$lines[] = sprintf( '%-4s  %s%s', strtoupper( $result['status'] ), $result['name'], '' === $result['detail'] ? '' : ' (' . $result['detail'] . ')' );
		}
		$lines[] = self::passed( $results ) ? 'RESULT: all checks passed' : 'RESULT: FAILED';
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * The child theme is active and the parent is present (REQ-273).
	 *
	 * @param array<string, mixed> $facts Facts.
	 * @return array{name: string, status: string, detail: string}
	 */
	private static function child_theme( array $facts ): array {
		$name = 'Child theme whoiswho-child is active and the parent whoiswho is present';
		if ( ! isset( $facts['stylesheet'], $facts['template'] ) ) {
			return self::result( $name, self::SKIP, 'theme facts not collected' );
		}
		if ( 'whoiswho-child' !== $facts['stylesheet'] ) {
			return self::result( $name, self::FAIL, 'active theme is ' . self::text( $facts['stylesheet'] ) );
		}
		if ( 'whoiswho' !== $facts['template'] ) {
			return self::result( $name, self::FAIL, 'the child names parent ' . self::text( $facts['template'] ) );
		}
		if ( empty( $facts['parent_exists'] ) ) {
			return self::result( $name, self::FAIL, 'parent theme folder is missing' );
		}
		return self::result( $name, self::PASS, '' );
	}

	/**
	 * A boolean fact must equal the wanted value.
	 *
	 * @param array<string, mixed> $facts  Facts.
	 * @param string               $key    Fact key.
	 * @param string               $name   Check name.
	 * @param bool                 $wanted Wanted value.
	 * @return array{name: string, status: string, detail: string}
	 */
	private static function flag( array $facts, string $key, string $name, bool $wanted ): array {
		if ( ! array_key_exists( $key, $facts ) ) {
			return self::result( $name, self::SKIP, $key . ' not collected' );
		}
		return (bool) $facts[ $key ] === $wanted ? self::result( $name, self::PASS, '' ) : self::result( $name, self::FAIL, $key . ' is ' . ( $facts[ $key ] ? 'true' : 'false' ) );
	}

	/**
	 * Migrations have run.
	 *
	 * @param array<string, mixed> $facts Facts.
	 * @return array{name: string, status: string, detail: string}
	 */
	private static function migrations( array $facts ): array {
		$name = 'All migrations have run';
		if ( ! isset( $facts['pending_migrations'] ) ) {
			return self::result( $name, self::SKIP, 'pending_migrations not collected' );
		}
		$pending = (int) $facts['pending_migrations'];
		return 0 === $pending ? self::result( $name, self::PASS, '' ) : self::result( $name, self::FAIL, $pending . ' pending' );
	}

	/**
	 * A probe answered with one of the wanted status codes.
	 *
	 * @param array<string, mixed> $http   Probes.
	 * @param string               $key    Probe key.
	 * @param string               $name   Check name.
	 * @param list<int>            $wanted Accepted statuses.
	 * @return array{name: string, status: string, detail: string}
	 */
	private static function status( array $http, string $key, string $name, array $wanted ): array {
		if ( ! isset( $http[ $key ]['status'] ) ) {
			return self::result( $name, self::SKIP, $key . ' probe not run' );
		}
		$got = (int) $http[ $key ]['status'];
		return in_array( $got, $wanted, true ) ? self::result( $name, self::PASS, '' ) : self::result( $name, self::FAIL, 'got ' . $got );
	}

	/**
	 * The site is served over HTTPS.
	 *
	 * @param array<string, mixed> $facts Facts.
	 * @return array{name: string, status: string, detail: string}
	 */
	private static function https( array $facts ): array {
		$name = 'The site is served over HTTPS';
		if ( empty( $facts['base_url'] ) ) {
			return self::result( $name, self::SKIP, 'base_url not given' );
		}
		return 0 === strpos( (string) $facts['base_url'], 'https://' ) ? self::result( $name, self::PASS, '' ) : self::result( $name, self::FAIL, 'base URL is not https' );
	}

	/**
	 * The sitemap index is a sitemap index and lists no preview address.
	 *
	 * @param array<string, mixed> $http Probes.
	 * @return array{name: string, status: string, detail: string}
	 */
	private static function sitemap_body( array $http ): array {
		$name = 'Sitemap index is well formed and lists no staff preview address';
		if ( ! isset( $http['sitemap']['body'] ) ) {
			return self::result( $name, self::SKIP, 'sitemap body not fetched' );
		}
		$body = (string) $http['sitemap']['body'];
		if ( false === strpos( $body, '<sitemapindex' ) ) {
			return self::result( $name, self::FAIL, 'not a sitemap index' );
		}
		return false !== stripos( $body, 'preview' ) ? self::result( $name, self::FAIL, 'a preview address is listed' ) : self::result( $name, self::PASS, '' );
	}

	/**
	 * /?author=1 must not reveal a user name.
	 *
	 * @param array<string, mixed> $http Probes.
	 * @return array{name: string, status: string, detail: string}
	 */
	private static function author_enumeration( array $http ): array {
		$name = 'User names are not revealed by /?author=1';
		if ( ! isset( $http['author_enum']['status'] ) ) {
			return self::result( $name, self::SKIP, 'author_enum probe not run' );
		}
		if ( 0 === (int) $http['author_enum']['status'] ) {
			return self::result( $name, self::FAIL, 'no answer' );
		}
		$location = isset( $http['author_enum']['location'] ) ? (string) $http['author_enum']['location'] : '';
		if ( false !== strpos( $location, '/author/' ) ) {
			return self::result( $name, self::FAIL, 'redirects to an author address' );
		}
		return self::result( $name, self::PASS, '' );
	}

	/**
	 * Build a result row.
	 *
	 * @param string $name   Name.
	 * @param string $status Status.
	 * @param string $detail Detail.
	 * @return array{name: string, status: string, detail: string}
	 */
	private static function result( string $name, string $status, string $detail ): array {
		return array(
			'name'   => $name,
			'status' => $status,
			'detail' => $detail,
		);
	}

	/**
	 * Scalar to short text.
	 *
	 * @param mixed $value Value.
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? (string) $value : 'unknown';
	}
}
