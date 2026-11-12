<?php
/**
 * The public handlers for the correction, removal and report forms (REQ-157, REQ-158, REQ-232, REQ-270). Each validates with its
 * own validator, refuses a profile that is not public in the same way as one that does not exist (REQ-122), runs the bot
 * challenge, form token and throttles (PublicSubmission), then creates the item through RequestIntake. Nothing changes publicly.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Request submission.
 */
final class RequestSubmission {

	public const INVALID   = 'invalid';
	public const NOT_FOUND = 'not_found';

	/**
	 * Intake.
	 *
	 * @var RequestIntake
	 */
	private RequestIntake $intake;

	/**
	 * Form protection.
	 *
	 * @var PublicSubmission
	 */
	private PublicSubmission $submission;

	/**
	 * Constructor.
	 *
	 * @param RequestIntake    $intake     Creates the item and queues the emails.
	 * @param PublicSubmission $submission Bot challenge, token and throttles.
	 */
	public function __construct( RequestIntake $intake, PublicSubmission $submission ) {
		$this->intake     = $intake;
		$this->submission = $submission;
	}

	/**
	 * POST /correct/{slug}: ask for a fact to change.
	 *
	 * @param string    $slug  Profile slug.
	 * @param FormInput $input Submitted fields.
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	public function correction( string $slug, FormInput $input, string $ip ): array {
		return $this->handle( ItemType::CORRECTION, 'correction', new CorrectionValidator(), $slug, $input, $ip );
	}

	/**
	 * POST /remove/{slug}: ask for a profile to be removed. This creates a Removal item only, never a data request.
	 *
	 * @param string    $slug  Profile slug.
	 * @param FormInput $input Submitted fields.
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	public function removal( string $slug, FormInput $input, string $ip ): array {
		return $this->handle( ItemType::REMOVAL, 'removal', new RemovalValidator(), $slug, $input, $ip );
	}

	/**
	 * Validate, find the profile, protect, create.
	 *
	 * @param string    $type      Item type.
	 * @param string    $form      Form type for the throttle.
	 * @param Validator $validator The form's one validator; its values may include a composed "message".
	 * @param string    $slug      Profile slug.
	 * @param FormInput $input     Submitted fields.
	 * @param string    $ip        Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	private function handle( string $type, string $form, Validator $validator, string $slug, FormInput $input, string $ip ): array {
		$result = $validator->validate( $input );
		if ( ! $result->is_valid() ) {
			return $this->reply( self::INVALID, 'Please check the highlighted fields.', $result->errors(), '' );
		}
		$profile = PublicProfile::find( $slug );
		if ( null === $profile ) {
			return $this->reply( self::NOT_FOUND, 'We could not find that profile.', array(), '' );
		}
		$values = $result->values();
		$verify = in_array( $values['relationship'] ?? '', RequestIntake::VERIFIED_RELATIONSHIPS, true );
		$answer = $this->submission->handle(
			$form,
			$input,
			$ip,
			$values['email'] ?? '',
			fn (): array => $this->intake->submit( $type, $values, $profile->id, $ip, $verify )
		);
		$ref    = PublicSubmission::RECEIVED === $answer['status'] && is_array( $answer['item'] ) ? (string) ( $answer['item']['reference'] ?? '' ) : '';
		return $this->reply( $answer['status'], PublicSubmission::RECEIVED === $answer['status'] ? 'Request received. Thank you.' : $answer['message'], array(), $ref );
	}

	/**
	 * Build a reply.
	 *
	 * @param string                $status    Status.
	 * @param string                $message   Message for the visitor.
	 * @param array<string, string> $errors    Field errors.
	 * @param string                $reference Item reference.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	private function reply( string $status, string $message, array $errors, string $reference ): array {
		return array(
			'status'    => $status,
			'message'   => $message,
			'errors'    => $errors,
			'reference' => $reference,
		);
	}
}
