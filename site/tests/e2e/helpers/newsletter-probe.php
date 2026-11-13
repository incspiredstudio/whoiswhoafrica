<?php
/**
 * Reads and tidies newsletter and consent records for the newsletter browser specs. TEST ENVIRONMENT ONLY: it refuses unless the
 * site is a local environment. Addresses are only ever the fictional @example.invalid ones the specs make up; the script refuses any
 * other domain, and the records it prints hold hashes, versions and times, never an address. Prints one line of JSON.
 *
 * Usage: WIW_SECRETS_FILE=... php tests/e2e/helpers/newsletter-probe.php <state|issue|unsubscribe-url|remove|reset-limits> [address]
 *   state            subscriber row (confirmed or pending, revoked) and its consent rows with policy version and time
 *   issue            queue one newsletter issue email to a confirmed subscriber (run the jobs afterwards)
 *   unsubscribe-url  a fresh unsubscribe link for the address
 *   remove           delete the subscriber and consent rows of the address
 *   reset-limits     forget the form rate-limit counters (they last an hour), so repeated runs start clean
 *
 * @package WhoIsWho
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';

require dirname( __DIR__, 3 ) . '/wordpress/wp-load.php';

if ( 'local' !== wp_get_environment_type() ) {
	fwrite( STDERR, "probe refused: not a local environment\n" );
	exit( 1 );
}

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Marketing\Marketing;
use WhoIsWho\Core\Platform\Mail\Notifier;

$wiw_cmd   = isset( $argv[1] ) ? (string) $argv[1] : '';
$wiw_email = isset( $argv[2] ) ? strtolower( (string) $argv[2] ) : '';
if ( 'reset-limits' !== $wiw_cmd && 1 !== preg_match( '/^[a-z0-9._-]+@example\.invalid$/', $wiw_email ) ) {
	fwrite( STDERR, "probe refused: only fictional @example.invalid addresses\n" );
	exit( 1 );
}

global $wpdb;
$wiw_subscribers = new SubscriberRepository();
$wiw_hash        = ConsentRecorder::hash_email( $wiw_email );

if ( 'state' === $wiw_cmd ) {
	$wiw_row = $wiw_subscribers->find_by_email_hash( $wiw_hash );
	echo wp_json_encode(
		array(
			'subscribed' => $wiw_subscribers->is_subscribed( $wiw_email ),
			'row'        => null === $wiw_row ? null : array(
				'purpose'      => $wiw_row['purpose'],
				'confirmed_at' => $wiw_row['confirmed_at'],
				'revoked_at'   => $wiw_row['revoked_at'],
			),
			'consents'   => array_map(
				static fn ( array $c ): array => array(
					'purpose'        => $c['purpose'],
					'policy_version' => $c['policy_version'] ?? null,
					'granted_at'     => $c['granted_at'] ?? ( $c['created_at'] ?? null ),
				),
				( new ConsentRecorder() )->for_email( $wiw_email )
			),
		)
	) . "\n";
} elseif ( 'issue' === $wiw_cmd ) {
	$wiw_row = $wiw_subscribers->find_by_email_hash( $wiw_hash );
	if ( null === $wiw_row ) {
		fwrite( STDERR, "no such subscriber\n" );
		exit( 1 );
	}
	$wiw_job = Notifier::for_site()->send_to_record( Events::NEWSLETTER_ISSUE, 'newsletter_subscriber', (int) $wiw_row['id'], array( 'lead' => 'A fictional issue for the browser tests.' ) );
	echo wp_json_encode( array( 'queued' => $wiw_job ) ) . "\n";
} elseif ( 'unsubscribe-url' === $wiw_cmd ) {
	echo wp_json_encode( array( 'url' => Marketing::for_site()->unsubscribe_url( $wiw_email ) ) ) . "\n";
} elseif ( 'remove' === $wiw_cmd ) {
	$wpdb->delete( SubscriberRepository::table(), array( 'email_hash' => $wiw_hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	echo "{}\n";
} elseif ( 'reset-limits' === $wiw_cmd ) {
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wiw\_rl\_%' OR option_name LIKE '\_transient\_timeout\_wiw\_rl\_%'" ); // phpcs:ignore WordPress.DB
	echo "{}\n";
} else {
	fwrite( STDERR, "unknown command\n" );
	exit( 1 );
}
