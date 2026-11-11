<?php
/**
 * The send log: one row per send attempt, bounce or complaint. The recipient is stored only as a keyed hash.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

/**
 * Email log store.
 */
interface EmailLogStore {

	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		message_id varchar(40) NOT NULL,
		event varchar(60) NOT NULL,
		recipient_ref char(64) NOT NULL,
		type varchar(12) NOT NULL,
		result varchar(12) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY event (event),
		KEY recipient_ref (recipient_ref),
		KEY created_at (created_at)';

	/**
	 * Append a row.
	 *
	 * @param string $message_id    Reference of the message (job reference).
	 * @param string $event         Event id.
	 * @param string $recipient_ref Keyed hash of the recipient.
	 * @param string $type          sent, bounce or complaint.
	 * @param string $result        queued, sent or failed (or recorded for bounces).
	 */
	public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void;
}
