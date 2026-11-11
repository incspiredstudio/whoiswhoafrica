<?php
/**
 * Fails when line coverage in build/coverage/clover.xml is below the threshold. Run: composer test:coverage.
 * Threshold starts low and is raised per milestone.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

$wiw_threshold = (float) ( getenv( 'WIW_COVERAGE_MIN' ) ? getenv( 'WIW_COVERAGE_MIN' ) : 0 );
$wiw_file      = dirname( __DIR__ ) . '/build/coverage/clover.xml';

if ( ! is_readable( $wiw_file ) ) {
	fwrite( STDERR, "No coverage report found.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

$wiw_xml     = simplexml_load_file( $wiw_file );
$wiw_metrics = $wiw_xml ? $wiw_xml->project->metrics : null;
$wiw_total   = $wiw_metrics ? (int) $wiw_metrics['statements'] : 0;
$wiw_hit     = $wiw_metrics ? (int) $wiw_metrics['coveredstatements'] : 0;
$wiw_pct     = $wiw_total > 0 ? 100 * $wiw_hit / $wiw_total : 100.0;

printf( "Line coverage %.1f%% (minimum %.1f%%)\n", $wiw_pct, $wiw_threshold ); // phpcs:ignore WordPress.Security.EscapeOutput
exit( $wiw_pct + 0.0001 >= $wiw_threshold ? 0 : 1 );
