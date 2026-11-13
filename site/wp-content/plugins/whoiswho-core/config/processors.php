<?php
/**
 * The processor register (REQ-059, REQ-258): every service or plugin that handles personal data on the site's behalf, what for,
 * what data, where it runs and how long it keeps it. Legal reviews this list; it is documentation the privacy notice and the
 * data-request flow read, not a switch. A processor that runs only on our own server is listed with location "own server".
 *
 * Each entry: name, role, purpose, data, location, retention (a name from config/retention.php or a plain statement), enabled_by.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

return array(
	array(
		'name'       => 'wpDiscuz',
		'role'       => 'comment form, threading and subscription plugin',
		'purpose'    => 'Guest comments on stories and optional reply notifications (REQ-258)',
		'data'       => 'Commenter name, email and IP; comment subscription and follow emails (tables wc_comments_subscription and wc_follow_users, catalogued in pii.php)',
		'location'   => 'own server',
		'retention'  => 'Comments are kept while the story is public and erased on request; subscriptions are deleted on request',
		'enabled_by' => 'Plugin active (CommentPolicy keeps the rules in force with or without it)',
	),
	array(
		'name'       => 'Akismet',
		'role'       => 'spam checking service for comments',
		'purpose'    => 'Spam filtering of guest comments',
		'data'       => 'Comment text, commenter name, email and IP, sent to the service for the check',
		'location'   => 'external service',
		'retention'  => 'Held by the service under its own terms',
		'enabled_by' => 'Off unless the spam-protection switch for Akismet is turned on (M4-CMT-03)',
	),
);
