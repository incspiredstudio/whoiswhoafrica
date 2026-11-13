<?php
/**
 * The newsletter sign-up handler (REQ-061, REQ-058, REQ-081, REQ-122). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Newsletter;

use WhoIsWho\Core\Compliance\Consent\ConsentRecorder;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Compliance\Forms\TurnstileChallenge;
use WhoIsWho\Core\Content\Newsletter\NewsletterSignup;
use WhoIsWho\Core\Content\Newsletter\SubscriberRepository;
use WhoIsWho\Core\Platform\Forms\FormGate;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\NullChallenge;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class NewsletterSignupTest extends TestCase {

	/**
	 * Messages sent.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $sent = array();

	/**
	 * Jobs queued, as stored.
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

	private int $now = 1800000000;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		Schema::create_table( ConsentRecorder::TABLE, ConsentRecorder::TABLE_DEFINITION );
		$wpdb->query( 'DELETE FROM ' . SubscriberRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->sent = array();
		NewsletterSignup::register();
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		parent::tearDown();
	}

	private function handler(): NewsletterSignup {
		$this->queue         = new MemoryQueue();
		$options             = new \ArrayObject();
		$this->runner        = new JobRunner(
			$this->queue,
			static fn (): int => 1800000000,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$this->queue->runner = $this->runner;
		$log                 = new class() implements EmailLogStore {
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
		$notifier            = new Notifier(
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
		$clock               = fn (): int => $this->now;
		$limiter             = new RateLimiter(
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
		$submission          = new PublicSubmission( new TurnstileChallenge( static fn ( string $response ): bool => 'good' === $response ), new FormGate( new FormToken( 'secret', $clock ), $limiter ) );
		$settings            = new SettingsService( SettingsService::default_registry() );
		return new NewsletterSignup( new SubscriberRepository( static fn (): string => '2026-11-12 10:00:00', $settings ), $notifier, $submission );
	}

	/**
	 * Fields with a fresh token.
	 *
	 * @param array<string, string> $over Overrides.
	 * @return array<string, string>
	 */
	private function fields( array $over = array() ): array {
		$token      = ( new FormToken( 'secret', fn (): int => $this->now ) )->issue( 'newsletter' );
		$this->now += 10;
		return array_merge(
			array(
				'email'                            => 'reader@example.test',
				'consent'                          => 'on',
				NullChallenge::TOKEN_FIELD         => $token,
				TurnstileChallenge::RESPONSE_FIELD => 'good',
			),
			$over
		);
	}

	private function row_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SubscriberRepository::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
	}

	public function test_REQ_058_valid_signup_records_timestamp_and_policy_version(): void {
		$result = $this->handler()->submit( new FormInput( $this->fields() ), '203.0.113.5' );
		$this->assertSame( PublicSubmission::RECEIVED, $result['status'] );
		$row = ( new SubscriberRepository() )->find_by_email_hash( ConsentRecorder::hash_email( 'reader@example.test' ) );
		$this->assertNotNull( $row );
		$this->assertSame( '2026-11-12 10:00:00', $row['granted_at'] );
		$this->assertNotSame( '', (string) $row['policy_version'] );
		$this->assertNull( $row['confirmed_at'], 'pending until the link is used' );
		$this->runner->run_due( 60 );
		$this->assertSame( array( 'reader@example.test' ), array_column( $this->sent, 'to' ), 'the confirmation goes to the new address only' );
		$this->assertStringContainsString( '/confirm/', $this->sent[0]['text'] );
	}

	public function test_REQ_061_invalid_email_creates_no_record(): void {
		$handler = $this->handler();
		foreach ( array(
			'email'   => 'not-an-email',
			'empty'   => '',
			'consent' => 'x',
		) as $case => $value ) {
			$over   = 'consent' === $case ? array( 'consent' => '' ) : array( 'email' => $value );
			$result = $handler->submit( new FormInput( $this->fields( $over ) ), '203.0.113.6' );
			$this->assertSame( NewsletterSignup::INVALID, $result['status'], $case );
			$this->assertNotEmpty( $result['errors'], $case );
			$this->assertStringNotContainsString( 'not-an-email', (string) wp_json_encode( $result ), 'errors do not echo data' );
		}
		$this->assertSame( 0, $this->row_count() );
	}

	public function test_REQ_122_signup_rate_limited_per_ip(): void {
		$handler  = $this->handler();
		$statuses = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$statuses[] = $handler->submit( new FormInput( $this->fields( array( 'email' => 'reader' . $i . '@example.test' ) ) ), '203.0.113.7' )['status'];
		}
		$this->assertSame( array( PublicSubmission::RECEIVED, PublicSubmission::RECEIVED, PublicSubmission::TRY_LATER ), $statuses );
		$this->assertSame( 2, $this->row_count(), 'the refused attempt made no record' );
	}

	public function test_REQ_081_bot_failure_creates_no_record(): void {
		$handler = $this->handler();
		$result  = $handler->submit( new FormInput( $this->fields( array( TurnstileChallenge::RESPONSE_FIELD => 'bad' ) ) ), '203.0.113.8' );
		$this->assertSame( PublicSubmission::EXPIRED, $result['status'] );
		$this->assertSame( 0, $this->row_count() );
	}

	public function test_REQ_122_a_confirmed_subscriber_gets_the_same_reply_and_no_second_row_or_mail(): void {
		$handler = $this->handler();
		$first   = $handler->submit( new FormInput( $this->fields() ), '203.0.113.10' );
		$repo    = new SubscriberRepository( static fn (): string => '2026-11-12 10:00:00', new SettingsService( SettingsService::default_registry() ) );
		$row     = $repo->find_by_email_hash( ConsentRecorder::hash_email( 'reader@example.test' ) );
		$this->assertNotNull( $row );
		global $wpdb;
		$wpdb->update( SubscriberRepository::table(), array( 'confirmed_at' => '2026-11-12 10:05:00' ), array( 'id' => $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->runner->run_due( 60 );
		$mails = count( $this->sent );
		$again = $handler->submit( new FormInput( $this->fields() ), '203.0.113.11' );
		$this->runner->run_due( 60 );
		$this->assertSame( $first, $again, 'the same reply, so the form never reveals who is subscribed' );
		$this->assertSame( 1, $this->row_count() );
		$this->assertCount( $mails, $this->sent );
	}
}
