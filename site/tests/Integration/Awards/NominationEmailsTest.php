<?php
/**
 * The nomination email and the winner email, sent by staff to a nomination's Contact email (REQ-268, REQ-038): offered only when a
 * Contact email exists, recorded with who and when, audited without the address, and plain, with no link. Needs the WordPress test
 * suite and the real awards tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NominationEmails;
use WhoIsWho\Core\Awards\NominationsScreen;
use WhoIsWho\Core\Awards\NominationsSwitch;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\WpTestCase;

final class NominationEmailsTest extends WpTestCase {

	/**
	 * Messages the mailer was given.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $sent = array();

	/**
	 * Job runner of the notifier under test.
	 *
	 * @var JobRunner|null
	 */
	private ?JobRunner $runner = null;

	/**
	 * Email log rows: event and recipient reference.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $log = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		$this->sent = array();
		$this->log  = array();
		wp_set_current_user( 0 );
		NominationEmails::register();
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		remove_all_filters( 'wiw_mail_templates' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function emails(): NominationEmails {
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
		$test          = $this;
		$log           = new class( $test ) implements EmailLogStore {
			/**
			 * Test.
			 *
			 * @var NominationEmailsTest
			 */
			private NominationEmailsTest $test;

			/**
			 * Constructor.
			 *
			 * @param NominationEmailsTest $test Test.
			 */
			public function __construct( NominationEmailsTest $test ) {
				$this->test = $test;
			}

			/**
			 * Keep the row.
			 *
			 * @param string $message_id    Id.
			 * @param string $event         Event.
			 * @param string $recipient_ref Ref.
			 * @param string $type          Type.
			 * @param string $result        Result.
			 */
			public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {
				$this->test->log_row( compact( 'message_id', 'event', 'recipient_ref', 'type', 'result' ) );
			}
		};
		$notifier      = new Notifier(
			$runner,
			$log,
			static fn (): array => array(),
			function ( string $to, string $subject, string $text, string $html ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- the values are read through compact().
				$this->sent[] = compact( 'to', 'subject', 'text', 'html' );
				return true;
			},
			'hash-key'
		);
		return new NominationEmails( $notifier, NominationService::for_site(), AuditLogger::for_site() );
	}

	/**
	 * Called by the log double.
	 *
	 * @param array<string, string> $row Row.
	 */
	public function log_row( array $row ): void {
		$this->log[] = $row;
	}

	private function as_role( string $role, string $name = '' ): int {
		$args = array( 'role' => $role );
		if ( '' !== $name ) {
			$args['display_name'] = $name;
		}
		$id = self::factory()->user->create( $args );
		wp_set_current_user( $id );
		return $id;
	}

	private function nomination( string $contact = 'press.contact@example.test', string $stage = 'nominated' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => 'Digital Pioneer',
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'     => (int) $wpdb->insert_id,
				'nominator_name'  => 'Nora Nominator',
				'nominator_email' => 'nora.nominator@example.test',
				'nominee_name'    => 'Ada Obi',
				'contact_email'   => '' === $contact ? null : $contact,
				'stage'           => $stage,
				'created_at'      => '2026-11-12 10:00:00',
				'updated_at'      => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function audit_rows( int $id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE action = 'nomination.email_sent' AND subject_id = %s", (string) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public function test_REQ_268_send_nomination_email_requires_a_contact_email_and_records_sender_and_time(): void {
		$none = $this->nomination( '' );
		$some = $this->nomination();
		$user = $this->as_role( 'wiw_editor' );
		$mail = $this->emails();

		$this->assertFalse( $mail->can_send( $none ) );
		$this->assertTrue( $mail->can_send( $some ) );
		try {
			$mail->send( $none, NominationEmails::KIND_NOMINATION );
			$this->fail( 'a nomination with no Contact email cannot be emailed' );
		} catch ( \DomainException $refusal ) {
			$this->assertStringContainsString( 'Contact email', $refusal->getMessage() );
		}

		$mail->send( $some, NominationEmails::KIND_NOMINATION );
		$row = NominationService::for_site()->find( $some );
		$this->assertSame( $user, (int) $row['nomination_sent_by'] );
		$this->assertNotEmpty( $row['nomination_sent_at'] );
		$this->assertNull( $row['winner_sent_at'] );
		$this->assertNull( NominationService::for_site()->find( $none )['nomination_sent_at'] );

		$this->runner->run_due( 60 );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'press.contact@example.test', $this->sent[0]['to'] );
	}

	public function test_REQ_268_audit_entry_for_nomination_email_holds_no_address(): void {
		$id = $this->nomination();
		$this->as_role( 'wiw_editor' );
		$mail = $this->emails();
		$mail->send( $id, NominationEmails::KIND_NOMINATION );
		$this->runner->run_due( 60 );

		$rows = $this->audit_rows( $id );
		$this->assertCount( 1, $rows );
		$text = (string) wp_json_encode( $rows[0] );
		$this->assertStringNotContainsString( 'press.contact', $text );
		$this->assertStringNotContainsString( '@', $text );
		$this->assertSame( array( 'kind' => 'nomination' ), json_decode( (string) $rows[0]['new_value'], true ) );
		$this->assertStringNotContainsString( 'press.contact', (string) wp_json_encode( $this->log ), 'the email log holds a keyed hash, not the address' );
	}

	public function test_REQ_268_winner_email_uses_the_contact_email(): void {
		$loser  = $this->nomination( 'press.contact@example.test', 'nominated' );
		$winner = $this->nomination( 'winner.contact@example.test', 'winner' );
		$user   = $this->as_role( 'wiw_editor' );
		$mail   = $this->emails();

		$this->assertFalse( $mail->can_send( $loser, NominationEmails::KIND_WINNER ), 'the winner email is for a Winner row only' );
		$this->assertTrue( $mail->can_send( $winner, NominationEmails::KIND_WINNER ) );
		$mail->send( $winner, NominationEmails::KIND_WINNER );
		$this->runner->run_due( 60 );

		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'winner.contact@example.test', $this->sent[0]['to'] );
		$row = NominationService::for_site()->find( $winner );
		$this->assertSame( $user, (int) $row['winner_sent_by'] );
		$this->assertNotEmpty( $row['winner_sent_at'] );
		$this->assertNull( $row['nomination_sent_at'] );
		$rows = $this->audit_rows( $winner );
		$this->assertSame( array( 'kind' => 'winner' ), json_decode( (string) $rows[0]['new_value'], true ) );
	}

	public function test_REQ_038_winner_email_contains_no_link(): void {
		$id = $this->nomination( 'winner.contact@example.test', 'winner' );
		$this->as_role( 'wiw_editor' );
		$mail = $this->emails();
		$mail->send( $id, NominationEmails::KIND_WINNER );
		$mail->send( $id, NominationEmails::KIND_NOMINATION );
		$this->runner->run_due( 60 );

		$this->assertCount( 2, $this->sent );
		foreach ( $this->sent as $message ) {
			$all = $message['text'] . $message['html'];
			$this->assertDoesNotMatchRegularExpression( '#https?://|www\.|<a\s|href=#i', $all );
			$this->assertStringContainsString( 'Digital Pioneer', $all );
			$this->assertStringNotContainsString( 'Nora', $all, 'the nominator is never named to the nominee' );
		}
	}

	public function test_REQ_268_sending_requires_wiw_manage_awards(): void {
		$id = $this->nomination();
		$this->as_role( 'wiw_reviewer' );
		$this->expectException( \DomainException::class );
		$this->emails()->send( $id, NominationEmails::KIND_NOMINATION );
	}

	public function test_REQ_268_screen_offers_the_buttons_only_with_a_contact_email(): void {
		$none = $this->nomination( '' );
		$some = $this->nomination( 'press.contact@example.test', 'winner' );
		$this->as_role( 'wiw_editor' );
		$screen = new NominationsScreen( new NominationsSwitch( SettingsService::for_site() ), NominationService::for_site(), $this->emails() );
		$html   = $screen->html();
		$this->assertStringContainsString( 'name="op" value="email_nomination"', $html );
		$this->assertStringContainsString( 'name="op" value="email_winner"', $html );
		$this->assertSame( 1, substr_count( $html, 'name="op" value="email_nomination"' ), 'only the nomination with a Contact email has the button' );
		$this->assertStringNotContainsString( 'press.contact', $html, 'the address is never printed in the list' );

		$out = $screen->handle(
			new Request(
				array(
					'op'            => 'email_nomination',
					'nomination_id' => $some,
				)
			)
		);
		$this->assertSame( 'The nomination email is queued.', $out['notice'] );
		$out = $screen->handle(
			new Request(
				array(
					'op'            => 'email_nomination',
					'nomination_id' => $none,
				)
			)
		);
		$this->assertStringContainsString( 'Contact email', $out['notice'] );
	}
}
