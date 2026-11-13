<?php
/**
 * Public nomination submission and email verification (REQ-030, REQ-196, REQ-233, REQ-259). Needs the WordPress test suite and the
 * real awards and link tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Awards\NominationIntake;
use WhoIsWho\Core\Awards\NominationDrafts;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Compliance\Forms\TurnstileChallenge;
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
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\Support\MemoryStore;
use WhoIsWho\Tests\TestCase;

final class NominationIntakeTest extends TestCase {

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

	private bool $open = true;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		$this->sent = array();
		$this->open = true;
		wp_set_current_user( 0 );
		NominationIntake::register();
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		parent::tearDown();
	}

	private function category(): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => 'Digital Pioneer',
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function intake(): NominationIntake {
		$queue         = new MemoryQueue();
		$options       = new \ArrayObject();
		$runner        = new JobRunner(
			$queue,
			static fn (): int => 1800000000,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$queue->runner = $runner;
		$this->runner  = $runner;
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
		$links         = SignedLinks::for_site();
		$notifier      = new Notifier(
			$runner,
			$log,
			static fn (): array => array(),
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
					'ip'      => array( 5, 600 ),
					'subject' => array( 5, 600 ),
				),
			),
			$clock
		);
		$submission    = new PublicSubmission( new TurnstileChallenge( static fn ( string $response ): bool => 'good' === $response ), new FormGate( new FormToken( 'secret', $clock ), $limiter ) );
		return new NominationIntake( $notifier, $submission, $links, CategoryService::for_site(), new NominationDrafts(), fn (): bool => $this->open );
	}

	/**
	 * Form fields with a fresh token for a form.
	 *
	 * @param string                $form Form type.
	 * @param array<string, string> $over Overrides.
	 * @return array<string, string>
	 */
	private function fields( string $form, array $over = array() ): array {
		$token      = ( new FormToken( 'secret', fn (): int => $this->now ) )->issue( $form );
		$this->now += 10;
		return array_merge(
			array(
				NullChallenge::TOKEN_FIELD         => $token,
				TurnstileChallenge::RESPONSE_FIELD => 'good',
			),
			$over
		);
	}

	private function start_fields( array $over = array() ): array {
		return $this->fields(
			'nomination_start',
			array_merge(
				array(
					'nominator_name'  => 'Nora Nominator',
					'nominator_email' => 'nora.nominator@example.test',
				),
				$over
			)
		);
	}

	private function nomination_fields( string $draft, int $category, array $over = array() ): array {
		return $this->fields(
			'nomination',
			array_merge(
				array(
					'draft'             => $draft,
					'nominee_name'      => 'Ada Obi',
					'nominee_org'       => 'Obi Labs',
					'nominee_email'     => 'ada.contact@example.test',
					'category_id'       => (string) $category,
					'evidence'          => 'Built the national payments switch and trained two hundred engineers.',
					'relationship'      => 'none',
					'relationship_note' => '',
				),
				$over
			)
		);
	}

	/**
	 * Open the emailed link the way the route does: a POST consumes it.
	 */
	private function open_emailed_link(): ?string {
		$this->runner->run_due( 60 );
		$this->assertNotEmpty( $this->sent );
		$mail = end( $this->sent );
		$this->assertSame( 1, preg_match( '#/link/([A-Za-z0-9_-]+)#', $mail['text'], $m ), 'the email carries a link' );
		return SignedLinks::for_site()->use_link( $m[1] );
	}

	private function nominations(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public function test_REQ_196_nominator_email_verified_by_single_use_link(): void {
		$cat    = $this->category();
		$intake = $this->intake();
		$begin  = $intake->begin( new FormInput( $this->start_fields() ), '203.0.113.1' );
		$this->assertSame( PublicSubmission::RECEIVED, $begin['status'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $begin['draft'] );
		$this->runner->run_due( 60 );
		$this->assertSame( 'nora.nominator@example.test', $this->sent[0]['to'] );

		$early = $intake->submit( new FormInput( $this->nomination_fields( $begin['draft'], $cat ) ), '203.0.113.1' );
		$this->assertSame( NominationIntake::UNVERIFIED, $early['status'], 'cannot submit before the link is opened' );
		$this->assertSame( array(), $this->nominations() );

		$this->assertStringContainsString( 'verified', (string) $this->open_emailed_link() );
		$mail = end( $this->sent );
		preg_match( '#/link/([A-Za-z0-9_-]+)#', $mail['text'], $m );
		$this->assertNull( SignedLinks::for_site()->use_link( $m[1] ), 'the link works once' );

		$done = $intake->submit( new FormInput( $this->nomination_fields( $begin['draft'], $cat ) ), '203.0.113.2' );
		$this->assertSame( PublicSubmission::RECEIVED, $done['status'] );
		$this->assertMatchesRegularExpression( '/^NOM-[0-9A-F]{6}$/', $done['reference'] );
		$rows = $this->nominations();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'nominated', $rows[0]['stage'] );
		$this->assertSame( 'not_live', $rows[0]['status'] );
		$this->assertSame( 'public_form', $rows[0]['source'] );
		$this->assertNotEmpty( $rows[0]['nominator_verified_at'] );
		$this->assertSame( 'nora.nominator@example.test', $rows[0]['nominator_email'] );
		$this->assertSame( 'ada.contact@example.test', $rows[0]['contact_email'], 'nominee email is kept as the staff-only Contact email' );

		$again = $intake->submit( new FormInput( $this->nomination_fields( $begin['draft'], $cat ) ), '203.0.113.3' );
		$this->assertSame( NominationIntake::BAD_DRAFT, $again['status'], 'a draft is used once' );
		$this->assertCount( 1, $this->nominations() );
	}

	public function test_REQ_233_expired_or_used_nominator_link_shows_expired_state(): void {
		$intake = $this->intake();
		$intake->begin( new FormInput( $this->start_fields() ), '203.0.113.4' );
		$this->open_emailed_link();
		$mail = end( $this->sent );
		preg_match( '#/link/([A-Za-z0-9_-]+)#', $mail['text'], $m );
		$this->assertNull( SignedLinks::for_site()->use_link( $m[1] ), 'a used link gives the expired state (null)' );
		$this->assertNull( SignedLinks::for_site()->use_link( 'not-a-real-token' ) );
		$this->assertNull( ( new NominationDrafts() )->get( 'zz' ), 'a malformed draft token resolves to nothing' );
	}

	public function test_REQ_259_submission_refused_while_nominations_closed(): void {
		$cat        = $this->category();
		$this->open = false;
		$intake     = $this->intake();
		$begin      = $intake->begin( new FormInput( $this->start_fields() ), '203.0.113.5' );
		$this->assertSame( NominationIntake::CLOSED, $begin['status'] );
		$this->assertSame( array(), $this->sent );
		$draft = ( new NominationDrafts() )->create( 'Nora Nominator', 'nora.nominator@example.test' );
		( new NominationDrafts() )->verify( $draft );
		$out = $intake->submit( new FormInput( $this->nomination_fields( $draft, $cat ) ), '203.0.113.5' );
		$this->assertSame( NominationIntake::CLOSED, $out['status'] );
		$this->assertSame( array(), $this->nominations() );
	}

	public function test_REQ_030_submission_stores_nominee_category_evidence_and_declaration(): void {
		$cat    = $this->category();
		$intake = $this->intake();
		$draft  = ( new NominationDrafts() )->create( 'Nora Nominator', 'nora.nominator@example.test' );
		( new NominationDrafts() )->verify( $draft );

		$bad = $intake->submit( new FormInput( $this->nomination_fields( $draft, 999999, array( 'relationship' => 'yes' ) ) ), '203.0.113.6' );
		$this->assertSame( NominationIntake::INVALID, $bad['status'] );
		$this->assertSame( 'invalid', $bad['errors']['category_id'] );
		$this->assertSame( 'required', $bad['errors']['relationship_note'], 'a declared relationship needs a note' );
		$this->assertSame( $draft, $bad['draft'], 'the draft survives a validation error' );

		$ok = $intake->submit(
			new FormInput(
				$this->nomination_fields(
					$draft,
					$cat,
					array(
						'relationship'      => 'yes',
						'relationship_note' => 'We worked together in 2019.',
					)
				)
			),
			'203.0.113.7'
		);
		$this->assertSame( PublicSubmission::RECEIVED, $ok['status'] );
		$row = $this->nominations()[0];
		$this->assertSame( 'Ada Obi', $row['nominee_name'] );
		$this->assertSame( 'Obi Labs', $row['nominee_org'] );
		$this->assertSame( (string) $cat, (string) $row['category_id'] );
		$this->assertStringContainsString( 'payments switch', (string) $row['evidence'] );
		$this->assertSame( '1', (string) $row['relationship_declared'] );
		$this->assertSame( 'We worked together in 2019.', $row['relationship_note'] );
		$this->assertSame( $ok['reference'], $row['reference'] );
		$this->runner->run_due( 60 );
		$this->assertSame( 'nora.nominator@example.test', end( $this->sent )['to'], 'the nominator gets the received email' );
		$this->assertStringContainsString( $ok['reference'], end( $this->sent )['text'] );
	}

	public function test_REQ_196_no_account_is_created_and_nothing_is_public(): void {
		$cat    = $this->category();
		$before = count_users()['total_users'];
		$intake = $this->intake();
		$begin  = $intake->begin( new FormInput( $this->start_fields() ), '203.0.113.8' );
		$this->open_emailed_link();
		$out = $intake->submit( new FormInput( $this->nomination_fields( $begin['draft'], $cat ) ), '203.0.113.9' );
		$this->assertSame( PublicSubmission::RECEIVED, $out['status'] );
		$this->assertSame( $before, count_users()['total_users'], 'no account for the nominator' );
		$this->assertSame( 'not_live', $this->nominations()[0]['status'], 'a new nomination is Not live, so no public page shows it' );
	}

	public function test_REQ_196_validators_reject_bad_input_and_a_bot_challenge_failure(): void {
		$intake = $this->intake();
		$bad    = $intake->begin( new FormInput( $this->start_fields( array( 'nominator_email' => 'not-an-email' ) ) ), '203.0.113.10' );
		$this->assertSame( NominationIntake::INVALID, $bad['status'] );
		$this->assertArrayHasKey( 'nominator_email', $bad['errors'] );
		$this->assertSame( array(), $this->sent );
		$bot = $intake->begin( new FormInput( $this->start_fields( array( TurnstileChallenge::RESPONSE_FIELD => 'bad' ) ) ), '203.0.113.10' );
		$this->assertSame( PublicSubmission::EXPIRED, $bot['status'] );
		$this->assertSame( array(), $this->sent );
	}
}
