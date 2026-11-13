<?php
/**
 * The proposed wording of every email event: a heading, what the message says, and (for staff) what to act on. Plain and warm,
 * no personal data, no brand values. All of it is PROPOSED wording for Brand and Legal review. Modules can still replace the
 * lines of an event through the wiw_mail_templates filter, and a caller can pass its own opening line as context "lead".
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

/**
 * Email copy.
 */
final class EmailCopy {

	/**
	 * Admin screen slug and whether it takes an item id, by staff event. Plain slugs: the screens live in other modules.
	 */
	private const ADMIN = array(
		Events::ENQUIRY_RECEIVED        => array( 'wiw-enquiries', true ),
		Events::ENQUIRY_STAFF_NOTICE    => array( 'wiw-enquiries', true ),
		Events::DEADLINE_ENQUIRIES      => array( 'wiw-enquiries', true ),
		Events::CORRECTION_RECEIVED     => array( 'wiw-corrections', true ),
		Events::DEADLINE_DISPUTES       => array( 'wiw-corrections', true ),
		Events::DISPUTE_ESCALATED       => array( 'wiw-corrections', true ),
		Events::REMOVAL_RECEIVED        => array( 'wiw-requests', true ),
		Events::REPORT_RECEIVED         => array( 'wiw-requests', true ),
		Events::DEADLINE_REQUESTS       => array( 'wiw-requests', true ),
		Events::CLAIM_REMINDER          => array( 'wiw-claims', false ),
		Events::STAFF_ITEM_NEEDS_ATTEND => array( 'wiw-review', false ),
		Events::RENEWAL_REMINDER        => array( 'wiw-dashboard', false ),
		Events::EXPIRY_REMINDER         => array( 'wiw-dashboard', false ),
	);

	/**
	 * Wording by event (bare: the message to a nominee carries no link at all, REQ-038): heading, body lines, the label of the action button (when a link is sent), and what to act on (staff).
	 *
	 * @return array<string, array{heading: string, body: string[], cta?: string, act?: string, bare?: bool}>
	 */
	public static function all(): array {
		return array(
			Events::ENQUIRY_ACKNOWLEDGED    => array(
				'heading' => 'We have your enquiry',
				'body'    => array(
					'Thank you for getting in touch. Your message has reached us and a member of the team will read it.',
					'{window}',
					'Please keep the reference below and quote it if you write to us again. You can reply to this email.',
				),
			),
			Events::ENQUIRY_STAFF_NOTICE    => array(
				'heading' => 'A new enquiry needs a reply',
				'body'    => array( 'A new enquiry has arrived in the support inbox.' ),
				'act'     => 'Open it, read it, assign it to yourself and send a reply within the response window.',
			),
			Events::ENQUIRY_RECEIVED        => array(
				'heading' => 'A new enquiry has arrived',
				'body'    => array( 'A new enquiry has arrived.' ),
				'act'     => 'Open it and read it. Nothing is sent to the sender until you reply.',
			),
			Events::PROFILE_RECEIVED        => array(
				'heading' => 'We have your profile',
				'body'    => array(
					'Thank you. We received the details for your profile.',
					'Our team checks every profile before it is shown. We will email you when it has been reviewed.',
				),
			),
			Events::PROFILE_APPROVED        => array(
				'heading' => 'Your profile is approved',
				'body'    => array( 'Good news: your profile has been reviewed and approved, and it can now be found in the directory.' ),
			),
			Events::PROFILE_REJECTED        => array(
				'heading' => 'An update on your profile',
				'body'    => array( 'We have reviewed your profile and are not able to publish it as it stands. You are welcome to reply to this email and we will explain.' ),
			),
			Events::VERIFY_EMAIL            => array(
				'heading' => 'Please confirm your email address',
				'body'    => array( 'Use the button below to confirm this address. The link works once and expires after a while. If you did not ask for this, you can safely ignore this message.' ),
				'cta'     => 'Confirm my email',
			),
			Events::CORRECTION_RECEIVED     => array(
				'heading' => 'A correction has been received',
				'body'    => array( 'Someone has asked for a correction to a public record.' ),
				'act'     => 'Open it, check the claim against the sources and respond before the due date.',
			),
			Events::REMOVAL_RECEIVED        => array(
				'heading' => 'A removal request has been received',
				'body'    => array( 'Someone has asked for a profile to be removed.' ),
				'act'     => 'Open it, confirm who is asking and decide before the due date.',
			),
			Events::REPORT_RECEIVED         => array(
				'heading' => 'A profile has been reported',
				'body'    => array( 'A visitor has reported a profile.' ),
				'act'     => 'Open it, read the reason and decide whether the profile should be taken down while you look.',
			),
			Events::REQUEST_ACKNOWLEDGED    => array(
				'heading' => 'We have your request',
				'body'    => array(
					'Thank you. We received your request and a member of the team will look at it.',
					'Keep the reference below if you need to ask about it.',
				),
			),
			Events::REQUEST_RESOLVED        => array(
				'heading' => 'Your request has been dealt with',
				'body'    => array( 'We have finished looking at your request. If anything is unclear, reply to this email and we will help.' ),
			),
			Events::INFORMATION_SENT        => array(
				'heading' => 'The information you asked for',
				'body'    => array( 'Here is the information about your request.' ),
			),
			Events::CLAIM_ACKNOWLEDGED      => array(
				'heading' => 'We have your claim',
				'body'    => array(
					'Thank you for asking to manage this profile. We received your claim.',
					'We check every claim before we hand over access. We will email you with our decision.',
				),
			),
			Events::CLAIM_DECISION          => array(
				'heading' => 'Our decision on your claim',
				'body'    => array( 'We have made a decision on your claim. You can reply to this email with any questions.' ),
			),
			Events::CLAIM_REMINDER          => array(
				'heading' => 'A claim is waiting for review',
				'body'    => array( 'A profile claim has been waiting for review.' ),
				'act'     => 'Open the claims list, check the evidence and approve or decline.',
			),
			Events::REVISION_DECISION       => array(
				'heading' => 'Your profile change was reviewed',
				'body'    => array( 'We have reviewed the change you proposed to your profile. You can reply to this email with any questions.' ),
			),
			Events::NOMINATION_RECEIVED     => array(
				'heading' => 'We have your nomination',
				'body'    => array(
					'Thank you for taking the time to nominate. We received it.',
					'Our team will review it and will not publish anything without checking. Keep the reference below.',
				),
			),
			Events::NOMINATION_EMAIL        => array(
				'heading' => 'You have been nominated',
				'body'    => array( 'This message is for your information only. You do not need to reply or do anything.' ),
				'bare'    => true,
			),
			Events::NOMINATION_WINNER       => array(
				'heading' => 'Congratulations',
				'body'    => array( 'This message is for your information only. You do not need to reply or do anything.' ),
				'bare'    => true,
			),
			Events::DATA_REQUEST_ACK        => array(
				'heading' => 'We have your data request',
				'body'    => array(
					'We received your request about your personal data.',
					'We will respond within the period the law gives us. Keep the reference below if you need to ask about it.',
				),
			),
			Events::COMMENT_ACKNOWLEDGED    => array(
				'heading' => 'We have your comment',
				'body'    => array( 'Thank you. Your comment is waiting for a moderator and will appear once it has been approved.' ),
			),
			Events::COMMENT_HELD            => array(
				'heading' => 'A comment is waiting for moderation',
				'body'    => array( 'A comment has been held for moderation.' ),
				'act'     => 'Open the comments list, read it and approve it, mark it as spam or delete it.',
			),
			Events::CUSTOMER_INVITATION     => array(
				'heading' => 'You are invited to complete your profile',
				'body'    => array( 'You have been invited to complete your profile. The link below can be used once and expires after a while.' ),
				'cta'     => 'Complete my profile',
			),
			Events::NEWSLETTER_CONFIRMATION => array(
				'heading' => 'Please confirm your subscription',
				'body'    => array( 'Use the button below to confirm that you want our newsletter. If you did not ask for it, ignore this message and nothing will be sent.' ),
				'cta'     => 'Confirm my subscription',
			),
			Events::NEWSLETTER_ISSUE        => array(
				'heading' => 'Our latest news',
				'body'    => array( 'Here is what is new.' ),
			),
			Events::RENEWAL_REMINDER        => array(
				'heading' => 'A listing is due for renewal',
				'body'    => array( 'A listing is coming up for renewal.' ),
				'act'     => 'Contact the customer about renewing, or let it lapse.',
			),
			Events::EXPIRY_REMINDER         => array(
				'heading' => 'A listing is about to expire',
				'body'    => array( 'A listing is about to expire.' ),
				'act'     => 'Renew it or confirm that it should end.',
			),
			Events::STAFF_ITEM_NEEDS_ATTEND => array(
				'heading' => 'An item needs attention',
				'body'    => array( 'An item in the review queue needs a person to look at it.' ),
				'act'     => 'Open the review queue and handle it.',
			),
			Events::DEADLINE_DISPUTES       => array(
				'heading' => 'A correction is nearly or past due',
				'body'    => array( 'A correction is close to its due date or past it.' ),
				'act'     => 'Open it and respond today.',
			),
			Events::DEADLINE_REQUESTS       => array(
				'heading' => 'A removal or report is nearly or past due',
				'body'    => array( 'A removal request or report is close to its due date or past it.' ),
				'act'     => 'Open it and decide today.',
			),
			Events::DEADLINE_ENQUIRIES      => array(
				'heading' => 'An enquiry is waiting for a reply',
				'body'    => array( 'An enquiry has been waiting longer than the response window.' ),
				'act'     => 'Open it and reply.',
			),
			Events::DISPUTE_ESCALATED       => array(
				'heading' => 'An unresolved dispute needs the Administrator',
				'body'    => array( 'A dispute was not resolved in time and has been escalated.' ),
				'act'     => 'Open it, review what has been done so far and decide.',
			),
		);
	}

	/**
	 * Wording for one event, with a safe default for an event that has none.
	 *
	 * @param string $event Event id.
	 * @return array{heading: string, body: string[], cta?: string, act?: string, bare?: bool}
	 */
	public static function for_event( string $event ): array {
		$all = self::all();
		return $all[ $event ] ?? array(
			'heading' => Events::all()[ $event ]['subject'] ?? 'A message from us',
			'body'    => array(),
		);
	}

	/**
	 * The admin page a staff event points at, with the item when the context names one; empty for an event with no screen.
	 *
	 * @param string               $event   Event id.
	 * @param array<string, mixed> $context Context.
	 */
	public static function admin_url( string $event, array $context ): string {
		if ( ! isset( self::ADMIN[ $event ] ) || ! function_exists( 'admin_url' ) ) {
			return '';
		}
		list( $page, $takes_item ) = self::ADMIN[ $event ];
		$query                     = 'admin.php?page=' . $page;
		$id                        = isset( $context['id'] ) ? (int) $context['id'] : 0;
		if ( $takes_item && $id > 0 ) {
			$query .= '&item=' . $id;
		}
		return (string) admin_url( $query );
	}
}
