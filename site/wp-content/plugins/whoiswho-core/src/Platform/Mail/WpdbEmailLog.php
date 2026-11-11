<?php
/**
 * The send log in the wiw_email_events table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

/**
 * Table-backed email log.
 */
final class WpdbEmailLog implements EmailLogStore {

	/**
	 * Append a row.
	 *
	 * @param string $message_id    Message reference.
	 * @param string $event         Event id.
	 * @param string $recipient_ref Keyed hash.
	 * @param string $type          Type.
	 * @param string $result        Result.
	 */
	public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'wiw_email_events',
			array(
				'message_id'    => $message_id,
				'event'         => $event,
				'recipient_ref' => $recipient_ref,
				'type'          => $type,
				'result'        => $result,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}
}
