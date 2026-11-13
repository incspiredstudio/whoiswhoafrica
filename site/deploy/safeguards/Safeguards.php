<?php
/**
 * Launch safeguard rules (M5-LCH-01; REQ-208, REQ-209, REQ-210, REQ-217). Pure logic: judges facts that
 * deploy/launch-safeguards.php reads from the staging copy built from the real import. Facts hold counts and slugs only, never
 * names or personal data.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Deploy;

/**
 * Launch safeguards.
 */
final class Safeguards {

	public const PASS = 'pass';
	public const FAIL = 'fail';

	/**
	 * Judge the facts.
	 *
	 * Facts: states (pub_state => entity_type => count), status_mismatch (profiles whose WordPress status disagrees with their state),
	 * sitemap_slugs (profile slugs in the sitemap), reviewed_slugs (slugs of reviewed profiles), not_reviewed_slugs (slugs of every other
	 * profile), unreviewed_public_people (individuals or entrepreneurs that are public but not reviewed), direct_status_writes_refused (bool).
	 *
	 * @param array<string, mixed> $facts Facts.
	 * @return list<array{name: string, status: string, detail: string}>
	 */
	public static function evaluate( array $facts ): array {
		$out = array();

		$unreviewed = (int) ( $facts['unreviewed_public_people'] ?? -1 );
		$out[]      = $unreviewed < 0
			? self::result( 'Every public individual is Published - reviewed', self::FAIL, 'not collected' )
			: self::result( 'Every public individual is Published - reviewed', 0 === $unreviewed ? self::PASS : self::FAIL, 0 === $unreviewed ? '' : $unreviewed . ' individuals are public without review' );

		$mismatch = (int) ( $facts['status_mismatch'] ?? -1 );
		$out[]    = $mismatch < 0
			? self::result( 'WordPress status agrees with the publication state on every profile', self::FAIL, 'not collected' )
			: self::result( 'WordPress status agrees with the publication state on every profile', 0 === $mismatch ? self::PASS : self::FAIL, 0 === $mismatch ? '' : $mismatch . ' disagree' );

		$sitemap  = array_map( 'strval', (array) ( $facts['sitemap_slugs'] ?? array() ) );
		$reviewed = array_map( 'strval', (array) ( $facts['reviewed_slugs'] ?? array() ) );
		$leaked   = array_values( array_intersect( $sitemap, array_map( 'strval', (array) ( $facts['not_reviewed_slugs'] ?? array() ) ) ) );
		$missing  = array_values( array_diff( $reviewed, $sitemap ) );
		$out[]    = self::result( 'The sitemap lists only reviewed profiles', array() === $leaked ? self::PASS : self::FAIL, array() === $leaked ? '' : count( $leaked ) . ' unreviewed profiles are listed' );
		$out[]    = self::result( 'The sitemap lists every reviewed profile', array() === $missing ? self::PASS : self::FAIL, array() === $missing ? '' : count( $missing ) . ' reviewed profiles are missing' );

		$out[] = self::result( 'Direct status writes are refused', ! empty( $facts['direct_status_writes_refused'] ) ? self::PASS : self::FAIL, ! empty( $facts['direct_status_writes_refused'] ) ? '' : 'a direct write succeeded or was not tried' );

		$counts = array();
		foreach ( (array) ( $facts['states'] ?? array() ) as $state => $types ) {
			$counts[] = $state . '=' . array_sum( array_map( 'intval', (array) $types ) );
		}
		$out[] = self::result( 'Profiles counted per state', array() === $counts ? self::FAIL : self::PASS, array() === $counts ? 'no profiles found' : implode( ', ', $counts ) );

		return $out;
	}

	/**
	 * Whether every rule passed.
	 *
	 * @param list<array{name: string, status: string, detail: string}> $results Results.
	 */
	public static function passed( array $results ): bool {
		foreach ( $results as $result ) {
			if ( self::FAIL === $result['status'] ) {
				return false;
			}
		}
		return array() !== $results;
	}

	/**
	 * A pass or fail list for the runbook.
	 *
	 * @param list<array{name: string, status: string, detail: string}> $results Results.
	 */
	public static function render( array $results ): string {
		$lines = array();
		foreach ( $results as $result ) {
			$lines[] = sprintf( '%-4s  %s%s', strtoupper( $result['status'] ), $result['name'], '' === $result['detail'] ? '' : ' (' . $result['detail'] . ')' );
		}
		$lines[] = self::passed( $results ) ? 'RESULT: nothing unreviewed is public' : 'RESULT: FAILED';
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * One result.
	 *
	 * @param string $name   Rule.
	 * @param string $status pass or fail.
	 * @param string $detail Counts only.
	 * @return array{name: string, status: string, detail: string}
	 */
	private static function result( string $name, string $status, string $detail ): array {
		return array(
			'name'   => $name,
			'status' => $status,
			'detail' => $detail,
		);
	}
}
