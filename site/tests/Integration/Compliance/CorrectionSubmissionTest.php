<?php
/**
 * The public correction form handler (REQ-157, REQ-158, REQ-232, REQ-233). Needs the WordPress test suite and the real profile and
 * support tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Confirm\RequesterConfirmation;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Compliance\Forms\TurnstileChallenge;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\RequestIntake;
use WhoIsWho\Core\Compliance\Support\RequestSubmission;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
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
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class CorrectionSubmissionTest extends TestCase {

	/**
	 * Messages sent.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $sent = array();

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

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
		$this->sent = array();
		wp_set_current_user( 0 );
		EnquiryIntake::register();
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		parent::tearDown();
	}

	/**
	 * A profile in a given state.
	 *
	 * @param string $name  Name.
	 * @param bool   $is_public Whether it is reviewed and public; otherwise it stays imported and not public.
	 * @return array{0: string, 1: int} Slug and id.
	 */
	private function profile( string $name, bool $is_public ): array {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => $is_public ? 'publish' : 'draft',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		if ( $is_public ) {
			$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		} else {
			$machine->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'organisation' ) );
		}
		return array( (string) get_post_field( 'post_name', $id ), $id );
	}

	private function handler(): RequestSubmission {
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
		$links         = new SignedLinks( new MemoryLinkStore(), static fn (): int => 1800000000 );
		$notifier      = new Notifier(
			$this->runner,
			$log,
			static fn (): array => array( 'inbox@example.org' ),
			function ( string $to, string $subject, string $text, string $html ): bool {
				unset( $html );
				$this->sent[] = compact( 'to', 'subject', 'text' );
				return true;
			},
			'hash-key',
			$links
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
		$items         = new SupportItems( new DueDates( array() ), 'test-key' );
		return new RequestSubmission( new RequestIntake( $items, $notifier, new RequesterConfirmation( $items, $links ) ), $submission );
	}

	/**
	 * Fields with a fresh token.
	 *
	 * @param array<string, string> $over Overrides.
	 * @return array<string, string>
	 */
	private function fields( array $over = array() ): array {
		$token      = ( new FormToken( 'secret', fn (): int => $this->now ) )->issue( 'correction' );
		$this->now += 10;
		return array_merge(
			array(
				'name'                             => 'Ada Obi',
				'relationship'                     => 'person',
				'need'                             => 'correct_fact',
				'field'                            => 'Title',
				'new_value'                        => 'Chief Executive',
				'evidence'                         => 'https://example.test/proof',
				'email'                            => 'ada@example.test',
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

	public function test_REQ_157_correction_submission_creates_item_due_in_seven_days(): void {
		list( $slug, $id ) = $this->profile( 'Obi Holdings', true );
		$result            = $this->handler()->correction( $slug, new FormInput( $this->fields() ), '203.0.113.5' );
		$this->assertSame( PublicSubmission::RECEIVED, $result['status'] );
		$stored = ( new SupportItems() )->find_by_reference( $result['reference'] );
		$this->assertNotNull( $stored );
		$this->assertSame( 'correction', $stored['item_type'] );
		$this->assertSame( $id, (int) $stored['profile_id'] );
		$this->assertSame( 'person', $stored['requester_relationship'] );
		$this->assertStringContainsString( 'Chief Executive', (string) $stored['message'] );
		$created = new \DateTimeImmutable( (string) $stored['created_at'], new \DateTimeZone( 'UTC' ) );
		$this->assertSame( $created->modify( '+7 days' )->format( 'Y-m-d H:i:s' ), $stored['due_at'] );
	}

	public function test_REQ_232_subject_request_sends_verification_link(): void {
		list( $slug )   = $this->profile( 'Obi Holdings', true );
		$handler        = $this->handler();
		$subject_result = $handler->correction( $slug, new FormInput( $this->fields() ), '203.0.113.5' );
		$this->runner->run_due( 60 );
		$to_requester = array_values( array_filter( $this->sent, static fn ( array $m ): bool => 'ada@example.test' === $m['to'] ) );
		$this->assertCount( 1, $to_requester );
		$this->assertStringContainsString( $subject_result['reference'], $to_requester[0]['text'] );
		$this->assertStringContainsString( '/link/', $to_requester[0]['text'], 'a link to confirm the email' );
		$this->assertContains( 'inbox@example.org', array_column( $this->sent, 'to' ), 'staff are told' );

		$this->sent = array();
		$handler->correction( $slug, new FormInput( $this->fields( array( 'relationship' => 'someone_else' ) ) ), '203.0.113.6' );
		$this->runner->run_due( 60 );
		$other = array_values( array_filter( $this->sent, static fn ( array $m ): bool => 'ada@example.test' === $m['to'] ) );
		$this->assertCount( 1, $other );
		$this->assertStringNotContainsString( '/link/', $other[0]['text'], 'someone else goes in directly, without a link' );
	}

	public function test_REQ_157_correction_does_not_change_profile(): void {
		list( $slug, $id ) = $this->profile( 'Obi Holdings', true );
		$title             = get_the_title( $id );
		$before            = get_post_meta( $id );
		$this->handler()->correction( $slug, new FormInput( $this->fields() ), '203.0.113.5' );
		$this->assertSame( $title, get_the_title( $id ) );
		$this->assertSame( $before, get_post_meta( $id ) );
		$this->assertSame( PubState::PUBLISHED_REVIEWED, ( new StateRepository() )->get( $id )['pub_state'] ?? '' );
	}

	public function test_REQ_122_a_non_public_or_unknown_profile_gets_the_same_refusal(): void {
		list( $hidden ) = $this->profile( 'Hidden Holdings', false );
		$handler        = $this->handler();
		$a              = $handler->correction( $hidden, new FormInput( $this->fields() ), '203.0.113.5' );
		$b              = $handler->correction( 'no-such-profile', new FormInput( $this->fields() ), '203.0.113.5' );
		$this->assertSame( $a, $b );
		$this->assertSame( RequestSubmission::NOT_FOUND, $a['status'] );
		$this->assertSame( 0, $this->item_count() );
	}

	public function test_REQ_157_the_form_refuses_invalid_input_and_over_the_rate_limit(): void {
		list( $slug ) = $this->profile( 'Obi Holdings', true );
		$handler      = $this->handler();
		foreach ( array(
			'name'         => array( 'name' => '' ),
			'email'        => array( 'email' => 'not-an-email' ),
			'relationship' => array( 'relationship' => 'stranger' ),
			'need'         => array( 'need' => 'win_a_prize' ),
			'new_value'    => array( 'new_value' => '' ),
		) as $field => $over ) {
			$result = $handler->correction( $slug, new FormInput( $this->fields( $over ) ), '203.0.113.9' );
			$this->assertSame( RequestSubmission::INVALID, $result['status'], $field );
			$this->assertArrayHasKey( $field, $result['errors'] );
		}
		$this->assertSame( 0, $this->item_count() );
		$this->assertSame( PublicSubmission::RECEIVED, $handler->correction( $slug, new FormInput( $this->fields() ), '203.0.113.7' )['status'] );
		$this->assertSame( PublicSubmission::RECEIVED, $handler->correction( $slug, new FormInput( $this->fields() ), '203.0.113.7' )['status'] );
		$this->assertSame( PublicSubmission::TRY_LATER, $handler->correction( $slug, new FormInput( $this->fields() ), '203.0.113.7' )['status'] );
	}

	public function test_REQ_157_the_correct_route_reaches_the_correction_form(): void {
		$routes = new \WhoIsWho\Core\Compliance\Forms\FormRoutes( array() );
		$this->assertSame(
			array(
				'form' => 'correction',
				'slug' => 'obi-holdings',
			),
			$routes->match( '/correct/obi-holdings/' )
		);
	}
}
