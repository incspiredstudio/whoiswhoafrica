<?php
/**
 * The PII catalogue: the one place that lists every column and meta key holding personal data. Retention, export, erase and the
 * log scrubber all read it, and a test fails when a migration adds a personal-looking column that is not listed here.
 *
 * Each entry: kind (column or meta), name (table.column without the WordPress prefix, or the meta key), class, retention,
 * export, erase, and a note.
 *   class:     pii | financial | public_after_approval | caller_supplied (holds only what callers pass in, never raw personal data)
 *   retention: a named class; the schedules and the job that applies them arrive in M3 (privacy)
 *   export:    include (in a data export) | hash_only (only a keyed hash is stored) | exclude
 *   erase:     delete | anonymise | keep (kept on purpose, with the reason in the note)
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

return array(
	// WordPress accounts (staff only: there are no public accounts).
	array(
		'kind'      => 'column',
		'name'      => 'users.user_email',
		'class'     => 'pii',
		'retention' => 'account_life',
		'export'    => 'include',
		'erase'     => 'anonymise',
		'note'      => 'Staff and claimant accounts.',
	),
	array(
		'kind'      => 'column',
		'name'      => 'users.user_login',
		'class'     => 'pii',
		'retention' => 'account_life',
		'export'    => 'include',
		'erase'     => 'anonymise',
		'note'      => 'Often a person\'s name.',
	),
	array(
		'kind'      => 'column',
		'name'      => 'users.display_name',
		'class'     => 'pii',
		'retention' => 'account_life',
		'export'    => 'include',
		'erase'     => 'anonymise',
		'note'      => '',
	),

	// Tables added in the foundation milestone and the ones planned next.
	array(
		'kind'      => 'column',
		'name'      => 'wiw_signed_links.email',
		'class'     => 'pii',
		'retention' => 'link_lifetime',
		'export'    => 'include',
		'erase'     => 'delete',
		'note'      => 'The address a signed link was sent to. Table arrives with the signed-link service.',
	),
	array(
		'kind'      => 'column',
		'name'      => 'wiw_email_events.recipient_ref',
		'class'     => 'pii',
		'retention' => 'log_life',
		'export'    => 'hash_only',
		'erase'     => 'delete',
		'note'      => 'A keyed hash of the recipient, never the address. Table arrives with the notification service.',
	),
	array(
		'kind'      => 'column',
		'name'      => 'wiw_notes.body',
		'class'     => 'pii',
		'retention' => 'case_life',
		'export'    => 'include',
		'erase'     => 'anonymise',
		'note'      => 'Staff notes may mention a person. Table arrives with the notes feature.',
	),
	array(
		'kind'      => 'column',
		'name'      => 'wiw_audit_log.old_value',
		'class'     => 'caller_supplied',
		'retention' => 'permanent',
		'export'    => 'exclude',
		'erase'     => 'keep',
		'note'      => 'Append-only and hash-chained, so it cannot be edited. Callers pass identifiers and plain values, never raw personal data.',
	),
	array(
		'kind'      => 'column',
		'name'      => 'wiw_audit_log.new_value',
		'class'     => 'caller_supplied',
		'retention' => 'permanent',
		'export'    => 'exclude',
		'erase'     => 'keep',
		'note'      => 'As old_value.',
	),
	array(
		'kind'      => 'column',
		'name'      => 'wiw_audit_log.reason',
		'class'     => 'caller_supplied',
		'retention' => 'permanent',
		'export'    => 'exclude',
		'erase'     => 'keep',
		'note'      => 'A short staff reason; the form tells staff not to type personal data.',
	),
);
