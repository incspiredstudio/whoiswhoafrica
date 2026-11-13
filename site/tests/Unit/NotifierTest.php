<?php
/**
 * Notification service: queueing, audiences, the send log and links (REQ-075, REQ-094, REQ-050, REQ-233).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class NotifierTest extends TestCase {

	/**
	 * Log rows.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $rows = array();

	/**
	 * Messages "sent".
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $sent = array();

	/**
	 * Whether the fake mailer succeeds.
	 *
	 * @var bool
	 */
	private bool $mail_ok = true;

	/**
	 * Clock.
	 *
	 * @var \stdClass
	 */
	private \stdClass $clock;

	/**
	 * Queue.
	 *
	 * @var MemoryQueue
	 */
	private MemoryQueue $queue;

	/**
	 * Runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $runner;

	protected function setUp(): void {
		parent::setUp();
		$this->rows          = array();
		$this->sent          = array();
		$this->mail_ok       = true;
		$this->clock         = new \stdClass();
		$this->clock->t      = 1800000000;
		$options             = new \ArrayObject();
		$clock               = $this->clock;
		$this->queue         = new MemoryQueue();
		$this->runner        = new JobRunner(
			$this->queue,
			static fn (): int => $clock->t,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$this->queue->runner = $this->runner;
	}

	private function notifier( ?SignedLinks $links = null ): Notifier {
		$log = new class( $this->rows ) implements EmailLogStore {
			/**
			 * Rows, by reference.
			 *
			 * @var array<int, array<string, string>>
			 */
			private array $rows;

			/**
			 * Constructor.
			 *
			 * @param array<int, array<string, string>> $rows Rows.
			 */
			public function __construct( array &$rows ) {
				$this->rows = &$rows;
			}

			/**
			 * Add.
			 *
			 * @param string $message_id    Id.
			 * @param string $event         Event.
			 * @param string $recipient_ref Ref.
			 * @param string $type          Type.
			 * @param string $result        Result.
			 */
			public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {
				$this->rows[] = compact( 'message_id', 'event', 'recipient_ref', 'type', 'result' );
			}
		};

		return new Notifier(
			$this->runner,
			$log,
			static fn ( string $audience ): array => 'administrators' === $audience
				? array( 'admin@example.org' )
				: array( 'reviewer1@example.org', 'reviewer2@example.org' ),
			function ( string $to, string $subject, string $text, string $html ): bool {
				$this->sent[] = array(
					'to'      => $to,
					'subject' => $subject,
					'text'    => $text,
					'html'    => $html,
				);
				return $this->mail_ok;
			},
			'hash-key',
			$links
		);
	}

	public function test_REQ_075_every_send_is_logged_with_event_and_result(): void {
		$notifier = $this->notifier();
		$this->assertSame( 1, $notifier->send( Events::PROFILE_RECEIVED, 'jane@example.org', array( 'reference' => 'WW-123' ) ) );
		$this->runner->run_due( 60 );

		$this->assertCount( 1, $this->sent );
		$this->assertStringContainsString( 'WW-123', $this->sent[0]['text'] );
		$this->assertStringContainsString( '<p>', $this->sent[0]['html'] );
		$this->assertSame( 'profile_received', $this->rows[0]['event'] );
		$this->assertSame( 'sent', $this->rows[0]['result'] );

		// A failed send is logged as failed and retried; the retry succeeds and is logged too.
		$this->mail_ok = false;
		$notifier->send( Events::PROFILE_APPROVED, 'jane@example.org' );
		$this->runner->run_due( 60 );
		$this->assertSame( 'failed', $this->rows[1]['result'] );
		$this->assertCount( 1, $this->queue->jobs, 'A retry is queued.' );
		$this->mail_ok   = true;
		$this->clock->t += 3600;
		$this->runner->run_due( 60 );
		$this->assertSame( 'sent', $this->rows[2]['result'] );
	}

	public function test_REQ_094_staff_notice_goes_to_capability_holders_only(): void {
		$notifier = $this->notifier();
		$queued   = $notifier->send( Events::ENQUIRY_RECEIVED, 'stranger@example.org' );
		$this->runner->run_due( 60 );

		$this->assertSame( 2, $queued );
		$this->assertSame( array( 'reviewer1@example.org', 'reviewer2@example.org' ), array_column( $this->sent, 'to' ) );

		$this->sent = array();
		$notifier->send( Events::RENEWAL_REMINDER, 'stranger@example.org' );
		$notifier->send( Events::EXPIRY_REMINDER );
		$this->runner->run_due( 60 );
		$this->assertSame( array( 'admin@example.org', 'admin@example.org' ), array_column( $this->sent, 'to' ), 'Renewal and expiry reminders go only to Administrators.' );

		foreach ( array( Events::RENEWAL_REMINDER, Events::EXPIRY_REMINDER ) as $event ) {
			$this->assertSame( 'administrators', Events::all()[ $event ]['audience'] );
		}
	}

	public function test_REQ_050_log_holds_recipient_reference_not_address(): void {
		$notifier = $this->notifier();
		$notifier->send( Events::NOMINATION_RECEIVED, 'Jane.Doe@Example.org' );
		$this->runner->run_due( 60 );

		$this->assertSame( $notifier->recipient_ref( 'jane.doe@example.org' ), $this->rows[0]['recipient_ref'], 'Case does not change the reference.' );
		$dump = (string) json_encode( $this->rows ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$this->assertStringNotContainsString( 'example.org', $dump );
		$this->assertStringNotContainsString( 'jane', strtolower( $dump ) );
		$this->assertSame( 64, strlen( $this->rows[0]['recipient_ref'] ) );
	}

	public function test_REQ_233_email_link_contains_no_personal_data(): void {
		$links    = new SignedLinks( new MemoryLinkStore(), fn (): int => $this->clock->t );
		$notifier = $this->notifier( $links );
		$token    = $links->issue( 'verify_correction', 'support_item', '5', 'jane@example.org' );
		$notifier->send( Events::VERIFY_EMAIL, 'jane@example.org', array( 'link_token' => $token ) );
		$this->runner->run_due( 60 );

		$this->assertStringContainsString( '/link/' . $token, $this->sent[0]['text'] );
		preg_match( '#(https?://\S+/link/\S+)#', $this->sent[0]['text'], $m );
		$this->assertStringNotContainsString( '@', $m[1] );
		$this->assertStringNotContainsString( 'jane', $m[1] );
	}

	public function test_REQ_075_unknown_events_and_bad_addresses_are_refused(): void {
		$notifier = $this->notifier();
		$this->assertSame( 0, $notifier->send( Events::PROFILE_RECEIVED, 'not-an-address' ) );
		$this->expectException( \InvalidArgumentException::class );
		$notifier->send( 'made_up_event', 'jane@example.org' );
	}

	public function test_REQ_075_every_event_renders_a_full_layout_with_a_plain_text_twin(): void {
		$notifier = $this->notifier();
		foreach ( array_keys( Events::all() ) as $event ) {
			$mail = $notifier->render( $event, array( 'reference' => 'REF-0001' ) );
			$this->assertStringContainsString( '<!DOCTYPE html>', $mail['html'], $event );
			$this->assertStringContainsString( 'max-width:600px', $mail['html'], $event );
			$this->assertStringContainsString( '<h1', $mail['html'], $event );
			$this->assertSame( trim( $mail['text'] ), trim( strip_tags( $mail['text'] ) ), "{$event}: the text twin has no markup" );
			$this->assertStringContainsString( 'REF-0001', $mail['text'], $event );
			$this->assertStringContainsString( 'REF-0001', $mail['html'], $event );
			$this->assertStringNotContainsString( 'REF-0001', $mail['subject'], "{$event}: no data in a subject" );
		}
	}

	public function test_REQ_075_public_mail_greets_by_first_name_and_a_subject_never_holds_it(): void {
		$notifier = $this->notifier();
		$named    = $notifier->render( Events::ENQUIRY_ACKNOWLEDGED, array( 'name' => 'Amara Okafor' ) );
		$this->assertStringContainsString( 'Hello Amara,', $named['text'] );
		$this->assertStringContainsString( 'Hello Amara,', $named['html'] );
		$this->assertStringNotContainsString( 'Amara', $named['subject'] );
		$this->assertStringNotContainsString( 'Okafor', $named['text'], 'Only the first name is used.' );
		$anonymous = $notifier->render( Events::ENQUIRY_ACKNOWLEDGED, array() );
		$this->assertStringContainsString( 'Hello,', $anonymous['text'] );
		$staff = $notifier->render( Events::ENQUIRY_STAFF_NOTICE, array( 'name' => 'Amara Okafor' ) );
		$this->assertStringNotContainsString( 'Amara', $staff['text'], 'A staff notice carries no requester name.' );
	}

	public function test_REQ_075_enquiry_acknowledgement_says_what_happens_next(): void {
		$mail = $this->notifier()->render( Events::ENQUIRY_ACKNOWLEDGED, array( 'reference' => 'ENQ-0001' ) );
		$this->assertStringContainsString( 'Your reference: ENQ-0001', $mail['text'] );
		$this->assertStringContainsString( 'We will reply as soon as we can.', $mail['text'], 'No window set: the plain promise.' );
		$this->assertStringContainsString( 'You can reply to this email', $mail['text'] );
	}

	public function test_REQ_075_staff_notice_names_what_to_act_on_with_no_personal_data(): void {
		$mail = $this->notifier()->render(
			Events::ENQUIRY_STAFF_NOTICE,
			array(
				'reference' => 'ENQ-0001',
				'details'   => array(
					'Type'          => 'Enquiry about a profile',
					'Interested in' => 'Advertising',
				),
			)
		);
		$this->assertStringContainsString( 'What to do:', $mail['text'] );
		$this->assertStringContainsString( 'Type: Enquiry about a profile', $mail['text'] );
		$this->assertStringContainsString( 'Interested in: Advertising', $mail['text'] );
		$this->assertStringContainsString( 'Reference: ENQ-0001', $mail['text'] );
		$this->assertStringNotContainsString( 'Hello', $mail['text'], 'Staff notices are not greeted.' );
	}

	public function test_REQ_061_marketing_mail_shows_the_unsubscribe_link_and_transactional_does_not(): void {
		$notifier = $this->notifier();
		$news     = $notifier->render( Events::NEWSLETTER_ISSUE, array( 'unsubscribe_url' => 'https://example.test/unsubscribe/x/' ) );
		$this->assertStringContainsString( 'Unsubscribe', $news['html'] );
		$this->assertStringContainsString( 'https://example.test/unsubscribe/x/', $news['text'] );
		$tx = $notifier->render( Events::ENQUIRY_ACKNOWLEDGED, array( 'unsubscribe_url' => 'https://example.test/unsubscribe/x/' ) );
		$this->assertStringNotContainsString( 'Unsubscribe', $tx['html'] );
	}

	public function test_REQ_075_link_emails_have_a_button_and_the_address_in_text(): void {
		$mail = $this->notifier()->render( Events::VERIFY_EMAIL, array( 'cta_url' => 'https://example.test/link/abc/' ) );
		$this->assertStringContainsString( 'Confirm my email', $mail['html'] );
		$this->assertStringContainsString( 'href="https://example.test/link/abc/"', $mail['html'] );
		$this->assertStringContainsString( 'Confirm my email: https://example.test/link/abc/', $mail['text'] );
		$bad = $this->notifier()->render( Events::VERIFY_EMAIL, array( 'cta_url' => 'javascript:alert(1)' ) );
		$this->assertStringNotContainsString( 'javascript:', $bad['html'] );
	}
}
