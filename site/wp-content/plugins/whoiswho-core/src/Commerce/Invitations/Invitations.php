<?php
/**
 * Customer invitations (REQ-013, REQ-014, REQ-233, REQ-141, REQ-122). An Administrator issues a single-use invitation link for a
 * customer; it is emailed to them and the issue is audited without any personal data. The customer opens /invite/{token}, fills in
 * their own profile (the form is saved as a draft against the link) and submits once. The submission passes the one validator, the
 * free-text scan and the picture checks, then creates a profile in the review queue (origin customer, not public) and uses the link
 * up. An unknown, used, revoked or expired link answers the same "expired" result. Nothing is public until a reviewer approves.
 * Domain service; the route stays thin.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce\Invitations;

use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Commerce\CustomerRepository;

/**
 * Invitations.
 */
final class Invitations {

	public const PURPOSE  = 'profile_invitation';
	public const SUBJECT  = 'customer';
	public const CAP      = 'wiw_manage_register';
	public const TTL_DAYS = 14;

	/**
	 * Signed links.
	 *
	 * @var SignedLinks
	 */
	private SignedLinks $links;

	/**
	 * Notifier.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Customers.
	 *
	 * @var CustomerRepository
	 */
	private CustomerRepository $customers;

	/**
	 * Validator.
	 *
	 * @var InvitationValidator
	 */
	private InvitationValidator $validator;

	/**
	 * The submission being stored while the link is used up.
	 *
	 * @var array{values: array<string, string>, image: array{name: string, tmp_name: string, size: int, error: int}|null}|null
	 */
	private ?array $submission = null;

	/**
	 * Constructor.
	 *
	 * @param SignedLinks              $links     Signed links.
	 * @param Notifier                 $notifier  Notifier.
	 * @param AuditLogger              $audit     Audit logger.
	 * @param CustomerRepository|null  $customers Customers.
	 * @param InvitationValidator|null $validator Validator.
	 */
	public function __construct( SignedLinks $links, Notifier $notifier, AuditLogger $audit, ?CustomerRepository $customers = null, ?InvitationValidator $validator = null ) {
		$this->links     = $links;
		$this->notifier  = $notifier;
		$this->audit     = $audit;
		$this->customers = $customers ?? new CustomerRepository();
		$this->validator = $validator ?? new InvitationValidator();
	}

	/**
	 * Service for this site.
	 */
	public static function for_site(): self {
		return new self( SignedLinks::for_site(), Notifier::for_site(), AuditLogger::for_site() );
	}

	/**
	 * Issue a link for a customer and email it. A newer link replaces any earlier one.
	 *
	 * @param int    $customer_id Customer.
	 * @param string $email       Where to send it (kept only by the link service and the mail queue).
	 * @return string The link, for tests and for the screen to show once.
	 * @throws \DomainException When the user may not, the customer is unknown or the address is not usable.
	 */
	public function issue( int $customer_id, string $email ): string {
		if ( ! current_user_can( self::CAP ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			throw new \DomainException( 'You are not allowed to issue invitations.' );
		}
		if ( null === $this->customers->get( $customer_id ) ) {
			throw new \DomainException( 'There is no such customer.' );
		}
		$email = sanitize_email( $email );
		if ( '' === $email || ! is_email( $email ) ) {
			throw new \DomainException( 'Enter a valid email address.' );
		}
		$token = $this->links->reissue( self::PURPOSE, self::SUBJECT, (string) $customer_id, $email, self::TTL_DAYS * DAY_IN_SECONDS );
		$url   = self::url( $token );
		$this->notifier->send(
			Events::CUSTOMER_INVITATION,
			$email,
			array(
				'id'      => $customer_id,
				'cta_url' => $url,
			)
		);
		$this->audit->log(
			'invitation.issue',
			array(
				'type' => self::SUBJECT,
				'id'   => $customer_id,
			),
			null,
			array( 'expires_in_days' => self::TTL_DAYS )
		);
		return $url;
	}

	/**
	 * The public address of an invitation.
	 *
	 * @param string $token Token.
	 */
	public static function url( string $token ): string {
		return home_url( '/invite/' . $token );
	}

	/**
	 * Whether a token is a usable invitation.
	 *
	 * @param string $token Token.
	 */
	public function usable( string $token ): bool {
		$link = $this->links->peek( $token );
		return null !== $link && self::PURPOSE === (string) $link['purpose'];
	}

	/**
	 * The draft saved against a link, or an empty list.
	 *
	 * @param string $token Token.
	 * @return array<string, string>
	 */
	public function draft( string $token ): array {
		$draft = get_transient( self::draft_key( $token ) );
		return is_array( $draft ) ? array_map( 'strval', $draft ) : array();
	}

	/**
	 * Save the form as a draft against the link. Nothing is created and the link is not used up.
	 *
	 * @param string               $token  Token.
	 * @param array<string, mixed> $values Raw field values.
	 * @return bool False when the link is not usable.
	 */
	public function save_draft( string $token, array $values ): bool {
		if ( ! $this->usable( $token ) ) {
			return false;
		}
		$clean = array();
		foreach ( array( 'name', 'type', 'summary', 'website' ) as $field ) {
			$clean[ $field ] = isset( $values[ $field ] ) && is_scalar( $values[ $field ] ) ? (string) $values[ $field ] : '';
		}
		set_transient( self::draft_key( $token ), $clean, self::TTL_DAYS * DAY_IN_SECONDS );
		return true;
	}

	/**
	 * Submit the form. Validation, the free-text scan and the picture checks run first; only a clean submission uses the link up.
	 *
	 * @param string                                                            $token  Token.
	 * @param array<string, mixed>                                              $values Raw field values.
	 * @param array{name: string, tmp_name: string, size: int, error: int}|null $image  The picture, when one was sent.
	 * @return array{state: string, errors: array<string, string>} state is expired, invalid or done.
	 */
	public function submit( string $token, array $values, ?array $image = null ): array {
		if ( ! $this->usable( $token ) ) {
			return array(
				'state'  => 'expired',
				'errors' => array(),
			);
		}
		$result = $this->validator->validate( new FormInput( $values ) );
		$errors = $result->errors();
		if ( null !== $image && UPLOAD_ERR_NO_FILE !== $image['error'] ) {
			$problem = ImageCheck::check( $image );
			if ( null !== $problem ) {
				$errors['image'] = $problem;
			}
		} else {
			$image = null;
		}
		if ( array() !== $errors ) {
			$this->save_draft( $token, $result->values() );
			return array(
				'state'  => 'invalid',
				'errors' => $errors,
			);
		}
		$this->submission = array(
			'values' => $result->values(),
			'image'  => $image,
		);
		// The purpose has a handler only while a submission is being stored, so the generic /link route can never use an invitation up.
		$this->links->register(
			self::PURPOSE,
			function ( array $link ): string {
				return $this->create_profile( (int) $link['subject_id'] );
			}
		);
		$message          = $this->links->use_link( $token );
		$this->submission = null;
		delete_transient( self::draft_key( $token ) );
		return array(
			'state'  => null === $message ? 'expired' : 'done',
			'errors' => array(),
		);
	}

	/**
	 * Create the profile for the submission in hand (the link handler). It starts in the review queue and is never public.
	 *
	 * @param int $customer_id Customer the link was issued for.
	 */
	private function create_profile( int $customer_id ): string {
		$submission = $this->submission;
		if ( null === $submission ) {
			return '';
		}
		$values = $submission['values'];
		$id     = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $values['name'],
			)
		);
		( new StateMachine() )->enter(
			$id,
			PubState::IMPORTED_NOT_PUBLIC,
			array(
				'entity_type' => $values['type'],
				'origin'      => 'customer',
				'source'      => 'invitation',
			),
			'Submitted through an invitation'
		);
		$fields  = new ProfileFields();
		$summary = 'organisation' === $values['type'] ? 'summary' : 'bio';
		$site    = 'organisation' === $values['type'] ? 'website' : 'official_website';
		if ( '' !== $values['summary'] ) {
			$fields->set( $id, $summary, $values['summary'] );
		}
		if ( '' !== $values['website'] ) {
			$fields->set( $id, $site, $values['website'] );
		}
		if ( null !== $submission['image'] ) {
			$this->attach_image( $id, $submission['image'] );
		}
		$this->audit->log(
			'invitation.submit',
			array(
				'type' => self::SUBJECT,
				'id'   => $customer_id,
			),
			null,
			array( 'profile_id' => $id )
		);
		return 'Thank you. Your profile has been sent for review.';
	}

	/**
	 * Keep the checked picture in the media library and make it the profile's image.
	 *
	 * @param int                                                          $profile_id Profile.
	 * @param array{name: string, tmp_name: string, size: int, error: int} $image      Picture.
	 */
	private function attach_image( int $profile_id, array $image ): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment = media_handle_sideload(
			array(
				'name'     => $image['name'],
				'tmp_name' => $image['tmp_name'],
			),
			$profile_id
		);
		if ( is_int( $attachment ) ) {
			set_post_thumbnail( $profile_id, $attachment );
		}
	}

	/**
	 * The transient key of a draft: the hash of the token, never the token.
	 *
	 * @param string $token Token.
	 */
	private static function draft_key( string $token ): string {
		return 'wiw_inv_' . substr( SignedLinks::hash( $token ), 0, 32 );
	}
}
