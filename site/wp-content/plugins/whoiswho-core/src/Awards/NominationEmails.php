<?php
/**
 * The nomination email and the winner email (REQ-268, REQ-038): staff send a plain message to a nomination's Contact email from the
 * Nominations screen. It is offered only when a Contact email exists (the winner email only on a Winner row). The message carries no
 * link and asks nothing of the nominee, and never names the nominator. Sending records who sent it and when on the nomination, and
 * the audit entry holds the kind only, never the address. The address is looked up when the queued job runs, so it is not kept in the
 * queue. The wording below is a proposal for Brand and Legal to review.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Nomination emails.
 */
final class NominationEmails {

	public const KIND_NOMINATION = 'nomination';
	public const KIND_WINNER     = 'winner';
	public const SOURCE          = 'nomination_contact';
	public const CAP             = 'wiw_manage_awards';

	/**
	 * Notifier.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Nomination service.
	 *
	 * @var NominationService
	 */
	private NominationService $service;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param Notifier          $notifier Notifier.
	 * @param NominationService $service  Nomination service.
	 * @param AuditLogger       $audit    Audit logger.
	 */
	public function __construct( Notifier $notifier, NominationService $service, AuditLogger $audit ) {
		$this->notifier = $notifier;
		$this->service  = $service;
		$this->audit    = $audit;
	}

	/**
	 * Emails for this site.
	 */
	public static function for_site(): self {
		return new self( Notifier::for_site(), NominationService::for_site(), AuditLogger::for_site() );
	}

	/**
	 * Register the recipient lookup and the wording of the two events.
	 */
	public static function register(): void {
		add_filter(
			'wiw_mail_recipient_resolvers',
			static function ( $resolvers ): array {
				$resolvers                 = is_array( $resolvers ) ? $resolvers : array();
				$resolvers[ self::SOURCE ] = static function ( int $id ): string {
					global $wpdb;
					$email = $wpdb->get_var( $wpdb->prepare( 'SELECT contact_email FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
					return is_string( $email ) ? $email : '';
				};
				return $resolvers;
			}
		);
		add_filter(
			'wiw_mail_templates',
			static function ( $templates ): array {
				$templates                              = is_array( $templates ) ? $templates : array();
				$templates[ Events::NOMINATION_EMAIL ]  = static fn ( array $context ): array => self::wording( self::KIND_NOMINATION, (string) ( $context['category'] ?? '' ) );
				$templates[ Events::NOMINATION_WINNER ] = static fn ( array $context ): array => self::wording( self::KIND_WINNER, (string) ( $context['category'] ?? '' ) );
				return $templates;
			}
		);
	}

	/**
	 * The proposed wording: plain text, no link, nothing asked of the reader, no nominator named. For Brand and Legal review.
	 *
	 * @param string $kind     KIND_NOMINATION or KIND_WINNER.
	 * @param string $category Award category title.
	 * @return array{subject: string, lines: string[]}
	 */
	public static function wording( string $kind, string $category ): array {
		$brand = (string) apply_filters( 'wiw_brand_name', (string) get_bloginfo( 'name' ) );
		if ( self::KIND_WINNER === $kind ) {
			return array(
				'subject' => 'Congratulations from ' . $brand,
				'lines'   => array(
					'Congratulations. You have been named a winner in the award category "' . $category . '".',
					'This message is for your information only. You do not need to reply or do anything.',
					$brand,
				),
			);
		}
		return array(
			'subject' => 'You have been nominated',
			'lines'   => array(
				'You have been nominated for the award category "' . $category . '".',
				'This message is for your information only. You do not need to reply or do anything.',
				$brand,
			),
		);
	}

	/**
	 * Whether the email can be sent for a nomination: it has a Contact email, and a winner email also needs the Winner stage.
	 *
	 * @param int    $id   Nomination.
	 * @param string $kind KIND_NOMINATION or KIND_WINNER.
	 */
	public function can_send( int $id, string $kind = self::KIND_NOMINATION ): bool {
		$row = $this->service->find( $id );
		return null !== $row && $this->row_allows( $row, $kind );
	}

	/**
	 * Whether a nomination row allows the email (no database lookup).
	 *
	 * @param array<string, mixed> $row  Nomination row, with contact_email and stage, or has_contact and stage.
	 * @param string               $kind KIND_NOMINATION or KIND_WINNER.
	 */
	public function row_allows( array $row, string $kind ): bool {
		$has_contact = isset( $row['has_contact'] ) ? (bool) (int) $row['has_contact'] : '' !== trim( (string) ( $row['contact_email'] ?? '' ) );
		if ( ! $has_contact ) {
			return false;
		}
		return self::KIND_WINNER !== $kind || 'winner' === (string) ( $row['stage'] ?? '' );
	}

	/**
	 * Queue the email and record who sent it and when.
	 *
	 * @param int    $id   Nomination.
	 * @param string $kind KIND_NOMINATION or KIND_WINNER.
	 * @throws \DomainException When not allowed, unknown, or there is no Contact email (or no Winner stage for the winner email).
	 */
	public function send( int $id, string $kind ): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to manage nominations.' );
		}
		if ( ! in_array( $kind, array( self::KIND_NOMINATION, self::KIND_WINNER ), true ) ) {
			throw new \DomainException( 'That email is not available.' );
		}
		$row = $this->service->find( $id );
		if ( null === $row ) {
			throw new \DomainException( 'That nomination does not exist.' );
		}
		if ( ! $this->row_allows( $row, $kind ) ) {
			throw new \DomainException( self::KIND_WINNER === $kind && '' !== trim( (string) $row['contact_email'] ) ? 'The winner email can only be sent for a Winner.' : 'This nomination has no Contact email.' );
		}
		$this->notifier->send_to_record(
			self::KIND_WINNER === $kind ? Events::NOMINATION_WINNER : Events::NOMINATION_EMAIL,
			self::SOURCE,
			$id,
			array( 'category' => $this->category_title( (int) $row['category_id'] ) )
		);
		global $wpdb;
		$prefix = self::KIND_WINNER === $kind ? 'winner' : 'nomination';
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				$prefix . '_sent_by' => get_current_user_id(),
				$prefix . '_sent_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
		// The audit entry names the kind only: no address.
		$this->audit->log(
			'nomination.email_sent',
			array(
				'type' => 'nomination',
				'id'   => $id,
			),
			null,
			array( 'kind' => $kind )
		);
	}

	/**
	 * Category title.
	 *
	 * @param int $category_id Category.
	 */
	private function category_title( int $category_id ): string {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM ' . AwardsTables::table( AwardsTables::CATEGORIES ) . ' WHERE id = %d', $category_id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}
}
