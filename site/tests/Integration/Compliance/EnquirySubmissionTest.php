<?php
/**
 * The Get listed and contact handlers (REQ-177, REQ-039, REQ-081, REQ-122, REQ-058). Needs the WordPress test suite and the real
 * support table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Compliance\Forms\TurnstileChallenge;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\EnquirySubmission;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Forms\FormGate;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\NullChallenge;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class EnquirySubmissionTest extends TestCase {

	/**
	 * Messages sent.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $sent = array();

	private int $now = 1800000000;

	/**
	 * Runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $runner;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		delete_option( 'wiw_enquiry_sequence' );
		$this->sent = array();
		EnquiryIntake::register();
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		parent::tearDown();
	}

	private function handler(): EnquirySubmission {
		$queue         = new MemoryQueue();
		$options       = new \ArrayObject();
		$this->runner  = new JobRunner(
			$queue,
			static fn (): int => 1800000000,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$queue->runner = $this->runner;
		$log           = new class() implements EmailLogStore {
			/**
			 * Ignore.
			 *
			 * @param string $message_id    Id.
			 * @param string $event         Event.
			 * @param string $recipient_ref Ref.
			 * @param string $type          Type.
			 * @param string $result        Result.
			 */
			public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {
				unset( $message_id, $event, $recipient_ref, $type, $result );
			}
		};
		$notifier      = new Notifier(
			$this->runner,
			$log,
			static fn (): array => array( 'inbox@example.org' ),
			function ( string $to, string $subject, string $text, string $html ): bool {
				unset( $html );
				$this->sent[] = compact( 'to', 'subject', 'text' );
				return true;
			},
			'hash-key'
		);
		$clock         = fn (): int => $this->now;
		$limiter       = new RateLimiter(
			new MemoryStore(),
			'secret',
			array(
				'default' => array(
					'ip'      => array( 2, 600 ),
					'subject' => array( 5, 600 ),
				),
			),
			$clock
		);
		$submission    = new PublicSubmission( new TurnstileChallenge( static fn ( string $response ): bool => 'good' === $response ), new FormGate( new FormToken( 'secret', $clock ), $limiter ) );
		$settings      = new SettingsService( SettingsService::default_registry() );
		return new EnquirySubmission( new EnquiryIntake( new SupportItems( new DueDates( array() ), 'test-key' ), $notifier ), $submission, new ConsentRecorder( null, $settings ) );
	}

	/**
	 * Fields with a fresh token for a form.
	 *
	 * @param string                $form Form type the token is for.
	 * @param array<string, string> $over Overrides.
	 * @return array<string, string>
	 */
	private function fields( string $form, array $over = array() ): array {
		$token      = ( new FormToken( 'secret', fn (): int => $this->now ) )->issue( $form );
		$this->now += 10;
		return array_merge(
			array(
				'name'                             => 'Ada Obi',
				'organisation'                     => 'Obi Holdings',
				'interest'                         => 'listing_a_profile',
				'email'                            => 'ada@example.test',
				'phone'                            => '',
				'message'                          => 'Please tell me how to be listed.',
				'consent'                          => 'on',
				NullChallenge::TOKEN_FIELD         => $token,
				TurnstileChallenge::RESPONSE_FIELD => 'good',
			),
			$over
		);
	}

	private function item_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}

	public function test_REQ_177_get_listed_creates_enquiry_with_reference(): void {
		$handler = $this->handler();
		$result  = $handler->get_listed( new FormInput( $this->fields( 'get_listed' ) ), '203.0.113.5' );
		$this->assertSame( PublicSubmission::RECEIVED, $result['status'] );
		$this->assertMatchesRegularExpression( '/^ENQ-\d{4}$/', $result['reference'] );
		$stored = ( new SupportItems() )->find_by_reference( $result['reference'] );
		$this->assertNotNull( $stored );
		$this->assertSame( 'enquiry', $stored['item_type'] );
		$this->assertSame( 'get-listed', $stored['source'] );
		$this->assertSame( 'Ada Obi', $stored['requester_name'] );
		$this->assertSame( 'listing_a_profile', $stored['interest'] );
		$this->runner->run_due( 60 );
		$this->assertContains( 'ada@example.test', array_column( $this->sent, 'to' ), 'an acknowledgement is sent' );
		$this->assertTrue( ( new ConsentRecorder( null, new SettingsService( SettingsService::default_registry() ) ) )->has_consent( 'ada@example.test', ConsentRecorder::ENQUIRY ), 'consent stored' );
		foreach ( $this->sent as $mail ) {
			$this->assertDoesNotMatchRegularExpression( '/\b(price|prices|pricing|package|packages|checkout)\b/i', $mail['text'] );
		}
	}

	public function test_REQ_177_get_listed_accepts_a_phone_number_instead_of_an_email(): void {
		$result = $this->handler()->get_listed(
			new FormInput(
				$this->fields(
					'get_listed',
					array(
						'email' => '',
						'phone' => '+234 800 000 0000',
					)
				)
			),
			'203.0.113.5'
		);
		$this->assertSame( PublicSubmission::RECEIVED, $result['status'] );
		$this->assertSame( 1, $this->item_count() );
	}

	public function test_REQ_177_get_listed_refuses_missing_name_contact_or_consent(): void {
		$handler = $this->handler();
		$cases   = array(
			'name'     => array( 'name' => '' ),
			'contact'  => array(
				'email' => '',
				'phone' => '',
			),
			'consent'  => array( 'consent' => '' ),
			'interest' => array( 'interest' => 'a_free_tablet' ),
		);
		foreach ( $cases as $field => $over ) {
			$result = $handler->get_listed( new FormInput( $this->fields( 'get_listed', $over ) ), '203.0.113.6' );
			$this->assertSame( 'invalid', $result['status'], $field );
			$this->assertArrayHasKey( $field, $result['errors'], $field );
		}
		$this->assertSame( 0, $this->item_count() );
	}

	public function test_REQ_039_contact_creates_general_enquiry(): void {
		$result = $this->handler()->contact( new FormInput( $this->fields( 'enquiry', array( 'source' => 'get-listed' ) ) ), '203.0.113.7' );
		$this->assertSame( PublicSubmission::RECEIVED, $result['status'] );
		$this->assertStringContainsString( 'Message sent', $result['message'] );
		$stored = ( new SupportItems() )->find_by_reference( $result['reference'] );
		$this->assertNotNull( $stored );
		$this->assertSame( 'contact', $stored['source'], 'a posted source cannot turn a contact message into something else' );
		$this->assertSame( 'enquiry', $stored['item_type'] );
		$refused = $this->handler()->contact( new FormInput( $this->fields( 'enquiry', array( 'message' => '' ) ) ), '203.0.113.7' );
		$this->assertSame( 'invalid', $refused['status'] );
	}

	public function test_REQ_122_forms_refuse_after_rate_limit(): void {
		$handler = $this->handler();
		$handler->get_listed( new FormInput( $this->fields( 'get_listed', array( 'email' => 'a1@example.test' ) ) ), '203.0.113.10' );
		$handler->get_listed( new FormInput( $this->fields( 'get_listed', array( 'email' => 'a2@example.test' ) ) ), '203.0.113.10' );
		$third = $handler->get_listed( new FormInput( $this->fields( 'get_listed', array( 'email' => 'a3@example.test' ) ) ), '203.0.113.10' );
		$this->assertSame( PublicSubmission::TRY_LATER, $third['status'] );
		$this->assertSame( 2, $this->item_count() );
	}

	public function test_REQ_081_forms_require_bot_challenge(): void {
		$handler = $this->handler();
		$result  = $handler->get_listed( new FormInput( $this->fields( 'get_listed', array( TurnstileChallenge::RESPONSE_FIELD => 'bad' ) ) ), '203.0.113.11' );
		$this->assertSame( PublicSubmission::EXPIRED, $result['status'] );
		$result = $handler->contact( new FormInput( $this->fields( 'enquiry', array( NullChallenge::TOKEN_FIELD => '' ) ) ), '203.0.113.11' );
		$this->assertSame( PublicSubmission::EXPIRED, $result['status'] );
		$this->assertSame( 0, $this->item_count() );
	}
}
