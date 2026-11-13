<?php
/**
 * The retention schedule (REQ-063, REQ-233): how long each class of data is kept before the retention job deletes or anonymises
 * it. Values are config, not code. They are working defaults awaiting Legal (Q21); change them here, not in the job.
 *
 * Each value is a number of days, or null when nothing is purged for that class until a period is set. The words "indefinite"
 * and "kept" are never a number: the suppression list and the audit log are never purged (REQ-207, REQ-148), and the job
 * refuses to touch them whatever this file says.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

return array(
	// Signed links: deleted this long after they expired or were used. Nominator and requester email checks are short-lived.
	'signed_links_days'                  => 14,
	'signed_links_check_days'            => 2,
	'signed_link_check_purposes'         => array( 'verify_nominator', 'verify_requester' ),

	// Logs hold ids and counts only, never personal data: the email send log and the cache purge log.
	'logs_days'                          => 30,

	// Nominations: the nominator's and nominee's personal data, and the Contact email, are removed after this long.
	'nominations_days'                   => 730,

	// Newsletter addresses that unsubscribed. No period is set until Legal decides (Q21): null purges nothing.
	'newsletter_unsubscribed_days'       => null,

	// Subscriber data is kept six years after the last payment, but no payment is recorded for a subscriber today, so this has
	// no subject yet. Flagged for the shared-docs owner.
	'subscriber_after_last_payment_days' => 2190,

	// Comments (REQ-258): kept while the story is public and erased on request, so no number of days. null purges nothing.
	'comments_days'                      => null,

	// Never purged.
	'suppression'                        => 'indefinite',
	'audit_log'                          => 'kept',
);
