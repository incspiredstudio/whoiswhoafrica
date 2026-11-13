<?php
/**
 * The Get listed and contact form handlers (REQ-177, REQ-039, REQ-081, REQ-122, REQ-058). Each validates with its own validator,
 * runs the bot challenge, form token and throttles (PublicSubmission), then creates the Enquiry through EnquiryIntake, which
 * queues the acknowledgement and the staff notice. The visitor gets back a status, a message and the reference for the done page.
 * Nothing here mentions prices, packages or checkout.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;

/**
 * Enquiry submission.
 */
final class EnquirySubmission {

	public const INVALID       = 'invalid';
	public const NOT_FOUND     = 'not_found';
	public const ENQUIRIES_OFF = 'enquiries_off';

	/**
	 * Intake.
	 *
	 * @var EnquiryIntake
	 */
	private EnquiryIntake $intake;

	/**
	 * Form protection.
	 *
	 * @var PublicSubmission
	 */
	private PublicSubmission $submission;

	/**
	 * Consent records.
	 *
	 * @var ConsentRecorder
	 */
	private ConsentRecorder $consent;

	/**
	 * Constructor.
	 *
	 * @param EnquiryIntake    $intake     Creates the item and queues the emails.
	 * @param PublicSubmission $submission Bot challenge, token and throttles.
	 * @param ConsentRecorder  $consent    Consent records.
	 */
	public function __construct( EnquiryIntake $intake, PublicSubmission $submission, ConsentRecorder $consent ) {
		$this->intake     = $intake;
		$this->submission = $submission;
		$this->consent    = $consent;
	}

	/**
	 * POST /subscribe: the Get listed form.
	 *
	 * @param FormInput $input Submitted fields (with the form token and challenge response).
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	public function get_listed( FormInput $input, string $ip ): array {
		$result = ( new GetListedValidator() )->validate( $input );
		if ( ! $result->is_valid() ) {
			return $this->invalid( $result );
		}
		$values           = $result->values();
		$values['source'] = 'get-listed';
		return $this->run( 'get_listed', $input, $ip, $values, true );
	}

	/**
	 * POST /contact: the general contact form.
	 *
	 * @param FormInput $input Submitted fields (with the form token and challenge response).
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	public function contact( FormInput $input, string $ip ): array {
		$fields = array( 'source' => 'contact' );
		foreach ( array( 'name', 'organisation', 'email', 'phone', 'message' ) as $name ) {
			$fields[ $name ] = $input->raw( $name );
		}
		$result = ( new EnquiryValidator() )->validate( new FormInput( $fields ) );
		if ( ! $result->is_valid() ) {
			return $this->invalid( $result );
		}
		return $this->run( 'enquiry', $input, $ip, $result->values(), false );
	}

	/**
	 * POST /enquire/{slug}: an enquiry about a public profile. It becomes an Enquiry item for staff; the profile owner's email and
	 * phone are never read, returned or put in any message (REQ-018). A profile whose enquiries are switched off refuses.
	 *
	 * @param string    $slug  Profile slug from the URL.
	 * @param FormInput $input Submitted fields (with the form token and challenge response).
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	public function profile( string $slug, FormInput $input, string $ip ): array {
		$fields = array( 'source' => 'profile' );
		foreach ( array( 'name', 'organisation', 'email', 'phone', 'message' ) as $name ) {
			$fields[ $name ] = $input->raw( $name );
		}
		$result = ( new EnquiryValidator() )->validate( new FormInput( $fields ) );
		if ( ! $result->is_valid() ) {
			return $this->invalid( $result );
		}
		$profile = PublicProfile::find( $slug );
		if ( null === $profile ) {
			return $this->refusal( self::NOT_FOUND, 'We could not find that profile.' );
		}
		return $this->run( 'enquiry', $input, $ip, $result->values(), false, $profile );
	}

	/**
	 * A refusal reply.
	 *
	 * @param string $status  Status.
	 * @param string $message Message for the visitor.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	private function refusal( string $status, string $message ): array {
		return array(
			'status'    => $status,
			'message'   => $message,
			'errors'    => array(),
			'reference' => '',
		);
	}

	/**
	 * Run the protection, then create the enquiry.
	 *
	 * @param string                $form    Form type for the throttle.
	 * @param FormInput             $input   Raw input (token and challenge response).
	 * @param string                $ip      Visitor IP.
	 * @param array<string, string> $values  Validated values.
	 * @param bool                  $consent Whether consent was part of the form.
	 * @param PublicProfile|null    $profile The profile, for a profile enquiry.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	private function run( string $form, FormInput $input, string $ip, array $values, bool $consent, ?PublicProfile $profile = null ): array {
		$email   = $values['email'] ?? '';
		$subject = $email;
		if ( '' === $email ) {
			$subject = $values['phone'] ?? '';
		}
		$answer = $this->submission->handle(
			$form,
			$input,
			$ip,
			$subject,
			function () use ( $values, $ip, $consent, $profile ): array {
				if ( null !== $profile && ! $profile->enquiries_open ) {
					return array( 'refused' => self::ENQUIRIES_OFF );
				}
				$row = $this->intake->submit( $values, null !== $profile ? $profile->id : null, $ip );
				if ( $consent && '' !== ( $values['email'] ?? '' ) ) {
					$this->consent->grant( $values['email'], ConsentRecorder::ENQUIRY, 'get listed form' );
				}
				return $row;
			}
		);
		if ( PublicSubmission::RECEIVED === $answer['status'] && is_array( $answer['item'] ) && isset( $answer['item']['refused'] ) ) {
			return $this->refusal( self::ENQUIRIES_OFF, 'Enquiries are switched off for this profile. Please use the contact page instead.' );
		}
		$reference = '';
		if ( PublicSubmission::RECEIVED === $answer['status'] && is_array( $answer['item'] ) ) {
			$reference = (string) ( $answer['item']['reference'] ?? '' );
		}
		return array(
			'status'    => $answer['status'],
			'message'   => PublicSubmission::RECEIVED === $answer['status'] ? 'Message sent. Thank you, we will be in touch.' : $answer['message'],
			'errors'    => array(),
			'reference' => $reference,
		);
	}

	/**
	 * A reply for a form that did not pass validation.
	 *
	 * @param ValidationResult $result Result.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	private function invalid( ValidationResult $result ): array {
		return array(
			'status'    => self::INVALID,
			'message'   => 'Please check the highlighted fields.',
			'errors'    => $result->errors(),
			'reference' => '',
		);
	}
}
