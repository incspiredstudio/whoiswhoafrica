<?php
/**
 * Claim intake (REQ-251, REQ-226, REQ-122, REQ-081, REQ-058): takes a claim from the public form without an account. It validates
 * with one validator, runs the bot challenge, token and throttles (PublicSubmission), refuses a profile still pending review and a
 * profile that is already claimed, stores the claim with its decide-by date, records consent with the policy version, sends the
 * claimant an acknowledgement (the address is looked up when the job runs, never stored in the job) and tells every holder of
 * wiw_review_claims. Claimed itself is never rendered publicly.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Claim intake.
 */
final class ClaimIntake {

	public const RECIPIENT_SOURCE = 'profile_claim';

	public const INVALID         = 'invalid';
	public const NOT_FOUND       = 'not_found';
	public const REVIEW_FIRST    = 'review_first';
	public const ALREADY_CLAIMED = 'already_claimed';

	/**
	 * Claims.
	 *
	 * @var ClaimRepository
	 */
	private ClaimRepository $claims;

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
	 * Consent.
	 *
	 * @var ConsentRecorder
	 */
	private ConsentRecorder $consent;

	/**
	 * Constructor.
	 *
	 * @param ClaimRepository  $claims     Claim storage.
	 * @param Notifier         $notifier   Notifier.
	 * @param PublicSubmission $submission Bot challenge, token and throttles.
	 * @param ConsentRecorder  $consent    Consent records.
	 */
	public function __construct( ClaimRepository $claims, Notifier $notifier, PublicSubmission $submission, ConsentRecorder $consent ) {
		$this->claims     = $claims;
		$this->notifier   = $notifier;
		$this->submission = $submission;
		$this->consent    = $consent;
	}

	/**
	 * Hook the recipient lookup the acknowledgement job uses.
	 */
	public static function register(): void {
		add_filter(
			'wiw_mail_recipient_resolvers',
			static function ( $resolvers ): array {
				$resolvers                           = is_array( $resolvers ) ? $resolvers : array();
				$resolvers[ self::RECIPIENT_SOURCE ] = static function ( int $id ): string {
					$claim = ( new ClaimRepository() )->find( $id );
					return null === $claim ? '' : $claim->work_email;
				};
				return $resolvers;
			}
		);
	}

	/**
	 * Submit a claim for the profile with this slug.
	 *
	 * @param string    $slug  Profile slug from the URL.
	 * @param FormInput $input Submitted fields (including the form token and challenge response).
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, reference: string}
	 */
	public function submit( string $slug, FormInput $input, string $ip ): array {
		$result = ( new ClaimValidator() )->validate( $input );
		if ( ! $result->is_valid() ) {
			return $this->reply( self::INVALID, 'Please check the highlighted fields.', $result->errors() );
		}
		$profile = PublicProfile::find( $slug );
		if ( null === $profile ) {
			return $this->reply( self::NOT_FOUND, 'We could not find that profile.' );
		}
		$values = $result->values();
		$answer = $this->submission->handle(
			'claim',
			$input,
			$ip,
			$values['work_email'],
			fn (): array => $this->create( $profile, $values )
		);
		if ( PublicSubmission::RECEIVED !== $answer['status'] ) {
			return $this->reply( $answer['status'], $answer['message'] );
		}
		/**
		 * The created claim, or a refusal.
		 *
		 * @var array{status: string, message: string, reference: string} $item
		 */
		$item = $answer['item'];
		return $this->reply( $item['status'], $item['message'], array(), $item['reference'] );
	}

	/**
	 * Create the claim once every check passed, unless the profile cannot be claimed.
	 *
	 * @param PublicProfile         $profile Profile.
	 * @param array<string, string> $values  Validated values.
	 * @return array{status: string, message: string, reference: string}
	 */
	private function create( PublicProfile $profile, array $values ): array {
		if ( PubState::PUBLISHED_REVIEWED !== $profile->state ) {
			return array(
				'status'    => self::REVIEW_FIRST,
				'message'   => 'This profile has not been reviewed yet. It must be reviewed before it can be claimed.',
				'reference' => '',
			);
		}
		if ( $profile->claimed ) {
			return array(
				'status'    => self::ALREADY_CLAIMED,
				'message'   => 'This profile has already been claimed.',
				'reference' => '',
			);
		}
		$claim = $this->claims->create(
			array(
				'profile_id'   => $profile->id,
				'relationship' => $values['relationship'],
				'name'         => $values['name'],
				'role'         => $values['role'],
				'work_email'   => $values['work_email'],
				'phone'        => $values['phone'],
				'evidence'     => $values['evidence'],
				'consent'      => true,
			)
		);
		$this->consent->grant( $values['work_email'], ConsentRecorder::CLAIM, 'claim form' );
		$context = array(
			'reference'  => $claim->reference,
			'decide_by'  => $claim->decide_by_at,
			'profile_id' => $profile->id,
		);
		$this->notifier->send_to_record( Events::CLAIM_ACKNOWLEDGED, self::RECIPIENT_SOURCE, $claim->id, $context );
		$this->notifier->send( Events::CLAIM_RECEIVED, '', $context );
		return array(
			'status'    => PublicSubmission::RECEIVED,
			'message'   => 'Thank you. We have received your claim and will reply within five days.',
			'reference' => $claim->reference,
		);
	}

	/**
	 * Build a reply.
	 *
	 * @param string                $status    Status.
	 * @param string                $message   Message for the visitor.
	 * @param array<string, string> $errors    Field errors.
	 * @param string                $reference Claim reference when one was made.
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
