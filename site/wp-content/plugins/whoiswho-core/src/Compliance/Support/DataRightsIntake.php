<?php
/**
 * Data-rights intake (REQ-059, REQ-081, REQ-122, REQ-233): POST /data-rights takes an access, correction, erasure, objection or
 * portability request without an account. It validates with one validator, runs the bot challenge, token and throttles
 * (PublicSubmission), creates a data_request support item with its due date, and sends the requester a confirmation email with a
 * reference and a single-use signed link. The address is looked up when the job runs, never stored in the job. The request counts
 * once the link is used (confirmed_at is set); until then staff see it as unconfirmed. Requests are worked on the Requests and
 * reports screen.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Data rights intake.
 */
final class DataRightsIntake {

	public const INVALID = 'invalid';

	public const LINK_PURPOSE = 'confirm_data_request';

	public const SUBJECT_TYPE = 'support_item';

	/**
	 * Items.
	 *
	 * @var SupportItems
	 */
	private SupportItems $items;

	/**
	 * Notifier.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Form protection.
	 *
	 * @var PublicSubmission
	 */
	private PublicSubmission $submission;

	/**
	 * Signed links.
	 *
	 * @var SignedLinks
	 */
	private SignedLinks $links;

	/**
	 * Constructor.
	 *
	 * @param SupportItems     $items      Support items.
	 * @param Notifier         $notifier   Notifier.
	 * @param PublicSubmission $submission Bot challenge, token and throttles.
	 * @param SignedLinks      $links      Signed links.
	 */
	public function __construct( SupportItems $items, Notifier $notifier, PublicSubmission $submission, SignedLinks $links ) {
		$this->items      = $items;
		$this->notifier   = $notifier;
		$this->submission = $submission;
		$this->links      = $links;
	}

	/**
	 * Hook the link handler that confirms the requester's email. The recipient lookup for the confirmation job is the support_item one EnquiryIntake::register() adds.
	 */
	public static function register(): void {
		SignedLinks::for_site()->register( self::LINK_PURPOSE, array( self::class, 'confirm_from_link' ) );
	}

	/**
	 * The signed-link handler: the requester's email is confirmed.
	 *
	 * @param array<string, mixed> $link The consumed link row.
	 */
	public static function confirm_from_link( array $link ): string {
		( new SupportItems() )->confirm_email( (int) $link['subject_id'] );
		return 'Thank you. Your request is confirmed and we will reply within the time the law allows.';
	}

	/**
	 * Handle a data-rights request.
	 *
	 * @param FormInput $input Submitted fields (with the form token and challenge response).
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	public function submit( FormInput $input, string $ip ): array {
		$result = ( new DataRightsValidator() )->validate( $input );
		if ( ! $result->is_valid() ) {
			return $this->reply( self::INVALID, 'Please check the highlighted fields.', $result->errors() );
		}
		$values = $result->values();
		$answer = $this->submission->handle( 'data_rights', $input, $ip, $values['email'], fn (): array => $this->create( $values, $ip ) );
		if ( PublicSubmission::RECEIVED !== $answer['status'] ) {
			return $this->reply( $answer['status'], $answer['message'] );
		}
		/**
		 * The created item.
		 *
		 * @var array<string, mixed> $item
		 */
		$item = $answer['item'];
		return $this->reply( PublicSubmission::RECEIVED, 'Thank you. We have received your request. Please check your email to confirm it.', array(), (string) $item['reference'] );
	}

	/**
	 * Create the item, issue the confirmation link and queue the email.
	 *
	 * @param array<string, string> $values Validated values.
	 * @param string                $ip     Visitor IP.
	 * @return array<string, mixed> The stored row.
	 */
	private function create( array $values, string $ip ): array {
		$row   = $this->items->create(
			array(
				'type'    => ItemType::DATA_REQUEST,
				'name'    => $values['name'],
				'email'   => $values['email'],
				'message' => $values['details'],
				'source'  => $values['kind'],
				'ip'      => $ip,
			)
		);
		$token = $this->links->issue( self::LINK_PURPOSE, self::SUBJECT_TYPE, (string) $row['id'], $values['email'] );
		$this->notifier->send_to_record(
			Events::DATA_REQUEST_ACK,
			EnquiryIntake::RECIPIENT_SOURCE,
			(int) $row['id'],
			array(
				'reference'  => (string) $row['reference'],
				'link_token' => $token,
				'lead'       => 'We received your data request. Please confirm it with the link below.',
			)
		);
		return $row;
	}

	/**
	 * Build a reply.
	 *
	 * @param string                $status    Status.
	 * @param string                $message   Message for the visitor.
	 * @param array<string, string> $errors    Field errors.
	 * @param string                $reference Item reference when one was made.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	private function reply( string $status, string $message, array $errors = array(), string $reference = '' ): array {
		return array(
			'status'    => $status,
			'message'   => $message,
			'errors'    => $errors,
			'reference' => $reference,
		);
	}
}
