<?php
/**
 * Data-rights intake (REQ-059, REQ-081, REQ-122, REQ-233). Needs the WordPress test suite and the real support table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Compliance\Forms\TurnstileChallenge;
use WhoIsWho\Core\Compliance\Support\DataRightsIntake;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\ItemType;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Forms\FormGate;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\FormToken;
use WhoIsWho\Core\Platform\Forms\NullChallenge;
use WhoIsWho\Core\Platform\Forms\RateLimiter;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class DataRightsIntakeTest extends TestCase {

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

	/**
	 * Links.
	 *
	 * @var SignedLinks
	 */
	private SignedLinks $links;

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
		remove_all_filters( 'wiw_data_request_due_days' );
		parent::tearDown();
	}

	private function handler(): DataRightsIntake {
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
		$this->links   = new SignedLinks( new MemoryLinkStore(), fn (): int => $this->now );
		$this->links->register( DataRightsIntake::LINK_PURPOSE, array( DataRightsIntake::class, 'confirm_from_link' ) );
		$log        = new class() implements EmailLogStore {
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
		$notifier   = new Notifier(
			$this->runner,
			$log,
			static fn (): array => array( 'inbox@example.org' ),
			function ( string $to, string $subject, string $text, string $html ): bool {
				unset( $html );
				$this->sent[] = compact( 'to', 'subject', 'text' );
				return true;
			},
			'hash-key',
			$this->links
		);
		$clock      = fn (): int => $this->now;
		$limiter    = new RateLimiter(
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
		$submission = new PublicSubmission( new TurnstileChallenge( static fn ( string $response ): bool => 'good' === $response ), new FormGate( new FormToken( 'secret', $clock ), $limiter ) );
		return new DataRightsIntake( new SupportItems( new DueDates( array() ), 'test-key' ), $notifier, $submission, $this->links );
	}

	/**
	 * Fields with a fresh token.
	 *
	 * @param array<string, string> $over Overrides.
	 * @return array<string, string>
	 */
	private function fields( array $over = array() ): array {
		$token      = ( new FormToken( 'secret', fn (): int => $this->now ) )->issue( 'data_rights' );
		$this->now += 10;
		return array_merge(
			array(
				'kind'                             => 'access',
				'name'                             => 'Ada Obi',
				'email'                            => 'ada@example.test',
				'details'                          => 'Please send me a copy of everything you hold about me.',
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

	public function test_REQ_059_data_request_has_due_date_and_confirmation_email(): void {
		$result = $this->handler()->submit( new FormInput( $this->fields() ), '203.0.113.5' );
		$this->assertSame( PublicSubmission::RECEIVED, $result['status'] );
		$item = ( new SupportItems() )->find_by_reference( $result['reference'] );
		$this->assertNotNull( $item );
		$this->assertSame( ItemType::DATA_REQUEST, $item['item_type'] );
		$this->assertSame( 'access', $item['source'] );
		$this->assertNotEmpty( $item['due_at'], 'a due date is set' );
		$received = strtotime( $item['created_at'] . ' UTC' );
		$this->assertSame( gmdate( 'Y-m-d', $received + 30 * 86400 ), substr( (string) $item['due_at'], 0, 10 ), 'thirty days by default' );
		$this->assertNull( $item['confirmed_at'], 'does not count until confirmed' );
		$this->runner->run_due( 60 );
		$this->assertSame( array( 'ada@example.test' ), array_column( $this->sent, 'to' ) );
		$this->assertStringContainsString( $result['reference'], $this->sent[0]['text'] );
		$this->assertStringContainsString( '/link/', $this->sent[0]['text'] );
	}

	public function test_REQ_059_every_kind_is_accepted_and_others_refused(): void {
		$handler = $this->handler();
		foreach ( array( 'access', 'correction', 'erasure', 'objection', 'portability' ) as $kind ) {
			$this->assertSame(
				PublicSubmission::RECEIVED,
				$handler->submit(
					new FormInput(
						$this->fields(
							array(
								'kind'  => $kind,
								'email' => $kind . '@example.test',
							)
						)
					),
					'203.0.113.' . strlen( $kind )
				)['status'],
				$kind
			);
		}
		$bad = $handler->submit( new FormInput( $this->fields( array( 'kind' => 'sell_my_data' ) ) ), '203.0.113.99' );
		$this->assertSame( DataRightsIntake::INVALID, $bad['status'] );
		$this->assertArrayHasKey( 'kind', $bad['errors'] );
		$this->assertSame( 5, $this->item_count() );
	}

	public function test_REQ_059_due_period_can_be_set_by_compliance(): void {
		add_filter( 'wiw_data_request_due_days', static fn (): int => 14 );
		$result = $this->handler()->submit( new FormInput( $this->fields() ), '203.0.113.5' );
		$item   = ( new SupportItems() )->find_by_reference( $result['reference'] );
		$this->assertNotNull( $item );
		$this->assertSame( gmdate( 'Y-m-d', strtotime( $item['created_at'] . ' UTC' ) + 14 * 86400 ), substr( (string) $item['due_at'], 0, 10 ) );
	}

	public function test_REQ_233_data_request_confirm_link_single_use(): void {
		$result = $this->handler()->submit( new FormInput( $this->fields() ), '203.0.113.5' );
		$this->runner->run_due( 60 );
		$this->assertSame( 1, preg_match( '#/link/([A-Za-z0-9_-]{20,64})#', $this->sent[0]['text'], $found ) );
		$token = $found[1];
		$this->assertNotNull( $this->links->peek( $token ), 'a look at the link consumes nothing' );
		$items = new SupportItems();
		$this->assertNull( $items->find_by_reference( $result['reference'] )['confirmed_at'] );
		$this->assertNotNull( $this->links->use_link( $token ), 'first use confirms' );
		$this->assertNotNull( $items->find_by_reference( $result['reference'] )['confirmed_at'], 'the request counts once confirmed' );
		$this->assertNull( $this->links->use_link( $token ), 'second use is refused' );
	}

	public function test_REQ_122_data_request_rate_limited(): void {
		$handler  = $this->handler();
		$statuses = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$statuses[] = $handler->submit( new FormInput( $this->fields( array( 'email' => 'p' . $i . '@example.test' ) ) ), '203.0.113.7' )['status'];
		}
		$this->assertSame( array( PublicSubmission::RECEIVED, PublicSubmission::RECEIVED, PublicSubmission::TRY_LATER ), $statuses );
		$this->assertSame( 2, $this->item_count() );
	}

	public function test_REQ_081_invalid_fields_and_a_failed_challenge_create_nothing(): void {
		$handler = $this->handler();
		foreach ( array(
			'name'    => '',
			'email'   => 'nope',
			'details' => '',
		) as $field => $value ) {
			$result = $handler->submit( new FormInput( $this->fields( array( $field => $value ) ) ), '203.0.113.8' );
			$this->assertSame( DataRightsIntake::INVALID, $result['status'], $field );
			$this->assertArrayHasKey( $field, $result['errors'] );
		}
		$bot = $handler->submit( new FormInput( $this->fields( array( TurnstileChallenge::RESPONSE_FIELD => 'bad' ) ) ), '203.0.113.9' );
		$this->assertSame( PublicSubmission::EXPIRED, $bot['status'] );
		$this->assertSame( 0, $this->item_count() );
	}
}
