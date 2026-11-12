<?php
/**
 * Newsletter subscriber data in data-request exports, erasure and retention (REQ-058, REQ-061). The subscriber row (wiw_consents,
 * purpose stories_newsletter) is listed in the PII catalogue as exported and erased by deletion. This class registers the
 * WordPress personal data exporter and eraser for it, and offers the retention purge for unsubscribed addresses; how many days
 * to keep them is set by Compliance through the wiw_newsletter_unsubscribed_retention_days filter (no purge until it is set).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Newsletter\Privacy;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;

/**
 * Subscriber privacy hooks.
 */
final class SubscriberPrivacy {

	public const GROUP = 'whoiswho-newsletter';

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
		add_action( 'wiw_retention_run', array( $this, 'run_retention' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param mixed $exporters Registered exporters.
	 * @return array<string, mixed>
	 */
	public function add_exporter( $exporters ): array {
		$exporters                = is_array( $exporters ) ? $exporters : array();
		$exporters[ self::GROUP ] = array(
			'exporter_friendly_name' => 'Stories newsletter',
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param mixed $erasers Registered erasers.
	 * @return array<string, mixed>
	 */
	public function add_eraser( $erasers ): array {
		$erasers                = is_array( $erasers ) ? $erasers : array();
		$erasers[ self::GROUP ] = array(
			'eraser_friendly_name' => 'Stories newsletter',
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export the subscriber row for an address.
	 *
	 * @param string $email_address Address from the data request.
	 * @param int    $page          Page (one page is enough: at most one row per address).
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email_address, int $page = 1 ): array {
		unset( $page );
		$row = ( new SubscriberRepository() )->find_by_email_hash( ConsentRecorder::hash_email( $email_address ) );
		if ( null === $row ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$status = 'Pending confirmation';
		if ( null !== $row['revoked_at'] ) {
			$status = 'Unsubscribed';
		} elseif ( null !== $row['confirmed_at'] ) {
			$status = 'Subscribed';
		}
		$fields = array(
			array(
				'name'  => 'Email address',
				'value' => (string) $row['email'],
			),
			array(
				'name'  => 'Status',
				'value' => $status,
			),
			array(
				'name'  => 'Signed up',
				'value' => (string) $row['granted_at'],
			),
			array(
				'name'  => 'Confirmed',
				'value' => (string) ( $row['confirmed_at'] ?? '' ),
			),
			array(
				'name'  => 'Unsubscribed',
				'value' => (string) ( $row['revoked_at'] ?? '' ),
			),
			array(
				'name'  => 'Privacy policy version',
				'value' => (string) $row['policy_version'],
			),
			array(
				'name'  => 'Where it was given',
				'value' => (string) $row['source'],
			),
		);
		return array(
			'data' => array(
				array(
					'group_id'    => self::GROUP,
					'group_label' => 'Stories newsletter',
					'item_id'     => 'newsletter-' . (int) $row['id'],
					'data'        => $fields,
				),
			),
			'done' => true,
		);
	}

	/**
	 * Erase the subscriber row for an address.
	 *
	 * @param string $email_address Address from the data request.
	 * @param int    $page          Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		unset( $page );
		global $wpdb;
		$removed = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SubscriberRepository::table(),
			array(
				'email_hash' => ConsentRecorder::hash_email( $email_address ),
				'purpose'    => SubscriberRepository::PURPOSE,
			)
		);
		return array(
			'items_removed'  => is_int( $removed ) && $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Retention hook: purge, discarding the count (action callbacks return nothing).
	 */
	public function run_retention(): void {
		$this->purge_unsubscribed();
	}

	/**
	 * Delete unsubscribed addresses older than the retention period. Does nothing until Compliance sets the period.
	 *
	 * @return int Rows deleted.
	 */
	public function purge_unsubscribed(): int {
		global $wpdb;
		$days = apply_filters( 'wiw_newsletter_unsubscribed_retention_days', null );
		if ( ! is_int( $days ) || $days < 1 ) {
			return 0;
		}
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'DELETE FROM ' . SubscriberRepository::table() . ' WHERE purpose = %s AND revoked_at IS NOT NULL AND revoked_at < %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
				SubscriberRepository::PURPOSE,
				$cutoff
			)
		);
		return is_int( $deleted ) ? $deleted : 0;
	}
}
