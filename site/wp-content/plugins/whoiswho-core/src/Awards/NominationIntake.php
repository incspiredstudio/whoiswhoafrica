<?php
/**
 * The server side of the six-step public nomination form (REQ-030, REQ-196, REQ-233, REQ-259). Step 1 takes the nominator's
 * details and emails a signed single-use link (begin); opening it marks the draft verified (the shared /link route consumes it on
 * POST, so a mail scanner's GET spends nothing). Only a verified draft can be submitted (submit): the nominee, category,
 * evidence and the nominator's relationship declaration are validated by one validator and stored as a nomination that is
 * Nominated and Not live, with a reference, source public form, and the nominator's data kept privately. No account or login is
 * created. Both steps run only while the Nominations flag is on, and both pass the bot challenge, form token and throttles
 * (PublicSubmission). Duplicate warnings and the free-text scan arrive in M4-AWD-11.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Nomination intake.
 */
final class NominationIntake {

	public const PURPOSE          = 'verify_nominator';
	public const SUBJECT_TYPE     = 'nomination_draft';
	public const RECIPIENT_SOURCE = 'nomination';

	public const INVALID    = 'invalid';
	public const CLOSED     = 'closed';
	public const UNVERIFIED = 'unverified';
	public const BAD_DRAFT  = 'draft_expired';

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
	 * Drafts.
	 *
	 * @var NominationDrafts
	 */
	private NominationDrafts $drafts;

	/**
	 * Categories.
	 *
	 * @var CategoryService
	 */
	private CategoryService $categories;

	/**
	 * Whether the Nominations flag is on.
	 *
	 * @var callable
	 */
	private $is_open;

	/**
	 * Constructor.
	 *
	 * @param Notifier         $notifier   Notifier.
	 * @param PublicSubmission $submission Bot challenge, token and throttles.
	 * @param SignedLinks      $links      Signed links.
	 * @param CategoryService  $categories Categories.
	 * @param NominationDrafts $drafts     Drafts.
	 * @param callable|null    $is_open    Returns whether nominations are open; the Nominations flag by default.
	 */
	public function __construct( Notifier $notifier, PublicSubmission $submission, SignedLinks $links, CategoryService $categories, ?NominationDrafts $drafts = null, ?callable $is_open = null ) {
		$this->notifier   = $notifier;
		$this->submission = $submission;
		$this->links      = $links;
		$this->categories = $categories;
		$this->drafts     = $drafts ?? new NominationDrafts();
		$this->is_open    = $is_open ?? static fn (): bool => function_exists( 'wiw_nominations_open' ) && wiw_nominations_open();
	}

	/**
	 * Register the link handler and the recipient lookup for the received email.
	 */
	public static function register(): void {
		$drafts = new NominationDrafts();
		SignedLinks::for_site()->register(
			self::PURPOSE,
			static function ( array $link ) use ( $drafts ): string {
				if ( self::SUBJECT_TYPE !== (string) $link['subject_type'] || ! $drafts->verify( (string) $link['subject_id'] ) ) {
					return __( 'This link is no longer valid.', 'whoiswho' );
				}
				return __( 'Thank you. Your email is verified. Go back to the nomination form to continue.', 'whoiswho' );
			}
		);
		add_filter(
			'wiw_mail_recipient_resolvers',
			static function ( $resolvers ): array {
				$resolvers                           = is_array( $resolvers ) ? $resolvers : array();
				$resolvers[ self::RECIPIENT_SOURCE ] = static function ( int $id ): string {
					global $wpdb;
					$email = $wpdb->get_var( $wpdb->prepare( 'SELECT nominator_email FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
					return is_string( $email ) ? $email : '';
				};
				return $resolvers;
			}
		);
	}

	/**
	 * Steps 1 and 2: take the nominator's details and send the verification link.
	 *
	 * @param FormInput $input Submitted fields (including the form token and challenge response).
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, draft: string, reference: string}
	 */
	public function begin( FormInput $input, string $ip ): array {
		if ( ! ( $this->is_open )() ) {
			return $this->reply( self::CLOSED, 'Nominations are closed.' );
		}
		$result = ( new NominatorDetailsValidator() )->validate( $input );
		if ( ! $result->is_valid() ) {
			return $this->reply( self::INVALID, 'Please check the highlighted fields.', $result->errors() );
		}
		$values = $result->values();
		$answer = $this->submission->handle(
			'nomination_start',
			$input,
			$ip,
			$values['nominator_email'],
			function () use ( $values ): string {
				$draft = $this->drafts->create( $values['nominator_name'], $values['nominator_email'] );
				$token = $this->links->reissue( self::PURPOSE, self::SUBJECT_TYPE, $draft, $values['nominator_email'] );
				$this->notifier->send(
					Events::VERIFY_EMAIL,
					$values['nominator_email'],
					array(
						'lead'       => 'Please confirm your email to continue your nomination. The link works once.',
						'link_token' => $token,
					)
				);
				return $draft;
			}
		);
		if ( PublicSubmission::RECEIVED !== $answer['status'] ) {
			return $this->reply( $answer['status'], $answer['message'] );
		}
		return $this->reply( PublicSubmission::RECEIVED, 'We have sent a link to your email. Open it to continue.', array(), (string) $answer['item'] );
	}

	/**
	 * Steps 3 to 6: submit the nomination for a verified draft.
	 *
	 * @param FormInput $input Submitted fields, including "draft".
	 * @param string    $ip    Visitor IP.
	 * @return array{status: string, message: string, errors: array<string, string>, draft: string, reference: string}
	 */
	public function submit( FormInput $input, string $ip ): array {
		if ( ! ( $this->is_open )() ) {
			return $this->reply( self::CLOSED, 'Nominations are closed.' );
		}
		$token = $input->raw( 'draft' );
		$draft = $this->drafts->get( $token );
		if ( null === $draft ) {
			return $this->reply( self::BAD_DRAFT, 'This nomination has expired. Please start again.' );
		}
		if ( null === $draft['verified_at'] ) {
			return $this->reply( self::UNVERIFIED, 'Please open the link we emailed you before submitting.', array(), $token );
		}
		$result = ( new NominationValidator() )->validate( $input );
		$errors = $result->errors();
		if ( ! isset( $errors['category_id'] ) && $this->current_category( (int) $result->value( 'category_id' ) ) === null ) {
			$errors['category_id'] = 'invalid';
		}
		if ( array() !== $errors ) {
			return $this->reply( self::INVALID, 'Please check the highlighted fields.', $errors, $token );
		}
		$values = $result->values();
		$answer = $this->submission->handle(
			'nomination',
			$input,
			$ip,
			$draft['email'],
			fn (): string => $this->create( $draft, $values )
		);
		if ( PublicSubmission::RECEIVED !== $answer['status'] ) {
			return $this->reply( $answer['status'], $answer['message'], array(), $token );
		}
		$this->drafts->delete( $token );
		return $this->reply( PublicSubmission::RECEIVED, 'Thank you. Your nomination has been received.', array(), '', (string) $answer['item'] );
	}

	/**
	 * A category that exists and is not archived, or null.
	 *
	 * @param int $id Category.
	 * @return array<string, mixed>|null
	 */
	private function current_category( int $id ): ?array {
		$row = $id > 0 ? $this->categories->find( $id ) : null;
		return null !== $row && 0 === (int) $row['archived'] ? $row : null;
	}

	/**
	 * Store the nomination, Nominated and Not live, and thank the nominator.
	 *
	 * @param array{name: string, email: string, verified_at: string|null} $draft  Verified draft.
	 * @param array<string, string>                                        $values Validated values.
	 * @return string Reference.
	 * @throws \RuntimeException When the row cannot be saved.
	 */
	private function create( array $draft, array $values ): string {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$id  = 0;
		$ref = '';
		for ( $try = 0; $try < 5 && $id < 1; $try++ ) {
			$ref = 'NOM-' . strtoupper( substr( bin2hex( random_bytes( 4 ) ), 0, 6 ) );
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				AwardsTables::table( AwardsTables::NOMINATIONS ),
				array(
					'reference'             => $ref,
					'category_id'           => (int) $values['category_id'],
					'nominator_name'        => $draft['name'],
					'nominator_email'       => $draft['email'],
					'nominator_verified_at' => $draft['verified_at'],
					'nominee_name'          => $values['nominee_name'],
					'nominee_org'           => '' === $values['nominee_org'] ? null : $values['nominee_org'],
					'contact_email'         => '' === $values['nominee_email'] ? null : $values['nominee_email'],
					'evidence'              => $values['evidence'],
					'relationship_declared' => 'yes' === strtolower( $values['relationship'] ) ? 1 : 0,
					'relationship_note'     => '' === $values['relationship_note'] ? null : $values['relationship_note'],
					'stage'                 => 'nominated',
					'status'                => 'not_live',
					'source'                => 'public_form',
					'created_at'            => $now,
					'updated_at'            => $now,
				)
			);
			$id = (int) $wpdb->insert_id;
		}//end for
		if ( $id < 1 ) {
			throw new \RuntimeException( 'The nomination could not be saved.' );
		}
		$this->notifier->send_to_record( Events::NOMINATION_RECEIVED, self::RECIPIENT_SOURCE, $id, array( 'reference' => $ref ) );
		return $ref;
	}

	/**
	 * Build a reply.
	 *
	 * @param string                $status    Status.
	 * @param string                $message   Message for the visitor.
	 * @param array<string, string> $errors    Field errors.
	 * @param string                $draft     Draft token to carry on in the form.
	 * @param string                $reference Nomination reference once submitted.
	 * @return array{status: string, message: string, errors: array<string, string>, draft: string, reference: string}
	 */
	private function reply( string $status, string $message, array $errors = array(), string $draft = '', string $reference = '' ): array {
		return array(
			'status'    => $status,
			'message'   => $message,
			'errors'    => $errors,
			'draft'     => $draft,
			'reference' => $reference,
		);
	}
}
