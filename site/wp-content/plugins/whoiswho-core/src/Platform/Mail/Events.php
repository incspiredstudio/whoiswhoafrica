<?php
/**
 * The registry of email events: for each, its audience and its default (placeholder) template. Modules fill in their own wording
 * through the wiw_mail_templates filter. Audience is "public" (the address the caller passes), "capability:<name>" (holders of
 * that capability, deactivated users excluded) or "administrators" (Administrators only: renewal and expiry reminders, REQ-094).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

/**
 * Email events.
 */
final class Events {

	public const ENQUIRY_RECEIVED        = 'enquiry_received';
	public const ENQUIRY_ACKNOWLEDGED    = 'enquiry_acknowledged';
	public const ENQUIRY_STAFF_NOTICE    = 'enquiry_staff_notice';
	public const PROFILE_RECEIVED        = 'profile_received';
	public const PROFILE_APPROVED        = 'profile_approved';
	public const PROFILE_REJECTED        = 'profile_rejected';
	public const VERIFY_EMAIL            = 'verify_email';
	public const CORRECTION_RECEIVED     = 'correction_received';
	public const REMOVAL_RECEIVED        = 'removal_received';
	public const REPORT_RECEIVED         = 'report_received';
	public const REQUEST_RESOLVED        = 'request_resolved';
	public const REQUEST_ACKNOWLEDGED    = 'request_acknowledged';
	public const INFORMATION_SENT        = 'information_sent';
	public const CLAIM_ACKNOWLEDGED      = 'claim_acknowledged';
	public const CLAIM_DECISION          = 'claim_decision';
	public const CLAIM_REMINDER          = 'claim_reminder';
	public const NOMINATION_RECEIVED     = 'nomination_received';
	public const NOMINATION_EMAIL        = 'nomination_email';
	public const NOMINATION_WINNER       = 'nomination_winner';
	public const DATA_REQUEST_ACK        = 'data_request_ack';
	public const COMMENT_ACKNOWLEDGED    = 'comment_acknowledged';
	public const CUSTOMER_INVITATION     = 'customer_invitation';
	public const NEWSLETTER_CONFIRMATION = 'newsletter_confirmation';
	public const RENEWAL_REMINDER        = 'renewal_reminder';
	public const EXPIRY_REMINDER         = 'expiry_reminder';
	public const STAFF_ITEM_NEEDS_ATTEND = 'staff_item_needs_attention';
	public const NEWSLETTER_ISSUE        = 'newsletter_issue';
	public const DEADLINE_DISPUTES       = 'deadline_disputes';
	public const DEADLINE_REQUESTS       = 'deadline_requests';
	public const DEADLINE_ENQUIRIES      = 'deadline_enquiries';
	public const DISPUTE_ESCALATED       = 'dispute_escalated';

	/**
	 * Audience and placeholder subject by event.
	 *
	 * @return array<string, array{audience: string, subject: string}>
	 */
	public static function all(): array {
		return array(
			self::ENQUIRY_RECEIVED        => array(
				'audience' => 'capability:wiw_receive_enquiries',
				'subject'  => 'New enquiry received',
			),
			self::ENQUIRY_ACKNOWLEDGED    => array(
				'audience' => 'public',
				'subject'  => 'We received your enquiry',
			),
			self::ENQUIRY_STAFF_NOTICE    => array(
				'audience' => 'capability:wiw_support_inbox',
				'subject'  => 'New enquiry in the inbox',
			),
			self::PROFILE_RECEIVED        => array(
				'audience' => 'public',
				'subject'  => 'We received your profile',
			),
			self::PROFILE_APPROVED        => array(
				'audience' => 'public',
				'subject'  => 'Your profile is approved',
			),
			self::PROFILE_REJECTED        => array(
				'audience' => 'public',
				'subject'  => 'About your profile',
			),
			self::VERIFY_EMAIL            => array(
				'audience' => 'public',
				'subject'  => 'Please confirm your email',
			),
			self::CORRECTION_RECEIVED     => array(
				'audience' => 'capability:wiw_support_inbox',
				'subject'  => 'New correction received',
			),
			self::REMOVAL_RECEIVED        => array(
				'audience' => 'capability:wiw_handle_requests',
				'subject'  => 'New removal request received',
			),
			self::REPORT_RECEIVED         => array(
				'audience' => 'capability:wiw_handle_requests',
				'subject'  => 'New profile report received',
			),
			self::REQUEST_RESOLVED        => array(
				'audience' => 'public',
				'subject'  => 'Your request has been resolved',
			),
			self::INFORMATION_SENT        => array(
				'audience' => 'public',
				'subject'  => 'Information about your request',
			),
			self::REQUEST_ACKNOWLEDGED    => array(
				'audience' => 'public',
				'subject'  => 'We received your request',
			),
			self::CLAIM_ACKNOWLEDGED      => array(
				'audience' => 'public',
				'subject'  => 'We received your claim',
			),
			self::CLAIM_DECISION          => array(
				'audience' => 'public',
				'subject'  => 'Your claim decision',
			),
			self::CLAIM_REMINDER          => array(
				'audience' => 'capability:wiw_review_claims',
				'subject'  => 'A claim is waiting for review',
			),
			self::NOMINATION_RECEIVED     => array(
				'audience' => 'public',
				'subject'  => 'We received your nomination',
			),
			self::NOMINATION_EMAIL        => array(
				'audience' => 'public',
				'subject'  => 'You have been nominated',
			),
			self::NOMINATION_WINNER       => array(
				'audience' => 'public',
				'subject'  => 'Congratulations',
			),
			self::DATA_REQUEST_ACK        => array(
				'audience' => 'public',
				'subject'  => 'We received your data request',
			),
			self::COMMENT_ACKNOWLEDGED    => array(
				'audience' => 'public',
				'subject'  => 'We received your comment',
			),
			self::CUSTOMER_INVITATION     => array(
				'audience' => 'public',
				'subject'  => 'An invitation',
			),
			self::NEWSLETTER_CONFIRMATION => array(
				'audience' => 'public',
				'subject'  => 'Please confirm your subscription',
			),
			self::RENEWAL_REMINDER        => array(
				'audience' => 'administrators',
				'subject'  => 'A listing is due for renewal',
			),
			self::EXPIRY_REMINDER         => array(
				'audience' => 'administrators',
				'subject'  => 'A listing is about to expire',
			),
			self::STAFF_ITEM_NEEDS_ATTEND => array(
				'audience' => 'capability:wiw_review_records',
				'subject'  => 'An item needs attention',
			),
			self::NEWSLETTER_ISSUE        => array(
				'audience' => 'public',
				'subject'  => 'Our latest news',
			),
			self::DEADLINE_DISPUTES       => array(
				'audience' => 'capability:wiw_handle_disputes',
				'subject'  => 'A correction is nearly or past due',
			),
			self::DEADLINE_REQUESTS       => array(
				'audience' => 'capability:wiw_handle_requests',
				'subject'  => 'A removal or report is nearly or past due',
			),
			self::DEADLINE_ENQUIRIES      => array(
				'audience' => 'capability:wiw_support_inbox',
				'subject'  => 'An enquiry is waiting for a reply',
			),
			self::DISPUTE_ESCALATED       => array(
				'audience' => 'administrators',
				'subject'  => 'An unresolved dispute needs the Administrator',
			),
		);
	}

	/**
	 * Marketing events: they pass the consent and unsubscribe gate (REQ-061). Modules add their own through the
	 * wiw_mail_marketing_events filter.
	 *
	 * @return string[]
	 */
	public static function marketing(): array {
		$events = array( self::NEWSLETTER_ISSUE );
		if ( function_exists( 'apply_filters' ) ) {
			$events = array_map( 'strval', (array) apply_filters( 'wiw_mail_marketing_events', $events ) );
		}
		return $events;
	}
}
