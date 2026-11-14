<?php
/**
 * Logs, errors and audit details of the M3 flows hold identifiers and never names, email addresses, phone numbers or IP addresses
 * (REQ-063, REQ-058). The fixtures submit an enquiry, a claim, a removal request and a customer with distinctive values, make the
 * mail sender fail with a message that names the address, and then search everything the flows wrote: the audit log, the job
 * failure list, the structured log lines, the PHP error log and the email log. Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Privacy;

use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\CustomerService;
use WhoIsWho\Core\Compliance\Confirm\RequesterConfirmation;
use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\EnquiryValidator;
use WhoIsWho\Core\Compliance\Support\RequestIntake;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Log\Logger;
use WhoIsWho\Core\Platform\Log\LogSink;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Trust\ClaimDecision;
use WhoIsWho\Core\Trust\ClaimIntake;
use WhoIsWho\Core\Trust\ClaimRepository;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class M3LogsHoldNoPersonalDataTest extends TestCase {

	private const PROBE = 'wiw_m3_logs_audit_probe';

	private const NAME  = 'Zanele Qwertyuiop';
	private const EMAIL = 'zanele.qwertyuiop@example.test';
	private const PHONE = '+234 811 555 0199';
	private const IP    = '203.0.113.77';

	/**
	 * Structured log lines.
	 *
	 * @var string[]
	 */
	private array $lines = array();

	/**
	 * Email log rows written.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $email_log = array();

	/**
	 * PHP error log file.
	 *
	 * @var string
	 */
	private string $error_file = '';

	/**
	 * Previous error_log setting.
	 *
	 * @var string
	 */
	private string $previous_log = '';

	/**
	 * Runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $runner;

	/**
	 * Profiles to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Clock for the job runner.
	 *
	 * @var int
	 */
	private int $now = 1800000000;

	/**
	 * Run the jobs, moving the clock past each retry delay, until none is left (a failing sender ends on the failure list).
	 */
	private function drain(): void {
		for ( $i = 0; $i < 8; $i++ ) {
			$this->runner->run_due( 5 );
			$this->now += 5000;
		}
	}

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		foreach ( array( SupportItems::table(), CustomerRepository::table(), ClaimRepository::table() ) as $table ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names.
		}
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		delete_option( ClaimRepository::SEQUENCE_OPTION );
		delete_option( 'wiw_enquiry_sequence' );
		$this->lines        = array();
		$this->email_log    = array();
		$this->error_file   = (string) tempnam( sys_get_temp_dir(), 'wiwlog' );
		$this->previous_log = (string) ini_get( 'error_log' );
		ini_set( 'error_log', $this->error_file ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- capture PHP errors for the assertions.
		EnquiryIntake::register();
	}

	protected function tearDown(): void {
		global $wpdb;
		ini_set( 'error_log', $this->previous_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- restore.
		if ( is_file( $this->error_file ) ) {
			unlink( $this->error_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- a temporary test file.
		}
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		remove_all_actions( 'wiw_job_failed' );
		remove_all_actions( 'wiw_form_failure' );
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A notifier whose sender fails with a message that names the address, the worst case for a leak.
	 */
	private function notifier(): Notifier {
		$queue         = new MemoryQueue();
		$options       = new \ArrayObject();
		$this->runner  = new JobRunner(
			$queue,
			fn (): int => $this->now,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$queue->runner = $this->runner;
		$store         = $this;
		$log           = new class( $store ) implements EmailLogStore {
			/**
			 * Test.
			 *
			 * @var M3LogsHoldNoPersonalDataTest
			 */
			private M3LogsHoldNoPersonalDataTest $test;

			/**
			 * Constructor.
			 *
			 * @param M3LogsHoldNoPersonalDataTest $test Test.
			 */
			public function __construct( M3LogsHoldNoPersonalDataTest $test ) {
				$this->test = $test;
			}

			/**
			 * Record.
			 *
			 * @param string $message_id    Id.
			 * @param string $event         Event.
			 * @param string $recipient_ref Ref.
			 * @param string $type          Type.
			 * @param string $result        Result.
			 */
			public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {
				$this->test->record_email( compact( 'message_id', 'event', 'recipient_ref', 'type', 'result' ) );
			}
		};
		return new Notifier(
			$this->runner,
			$log,
			static fn (): array => array( 'inbox@example.org' ),
			static function ( string $to, string $subject, string $text, string $html ): bool {
				unset( $subject, $text, $html );
				throw new \RuntimeException( 'SMTP refused ' . $to ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- a test double that fails on purpose.
			},
			'hash-key',
			new SignedLinks( new MemoryLinkStore(), static fn (): int => 1800000000 )
		);
	}

	/**
	 * Record an email log row (called by the log store).
	 *
	 * @param array<string, string> $row Row.
	 */
	public function record_email( array $row ): void {
		$this->email_log[] = $row;
	}

	/**
	 * An audit logger writing to the probe table.
	 */
	private function audit( int $user ): AuditLogger {
		return new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-13 10:00:00', static fn (): int => $user );
	}

	/**
	 * A user with a role.
	 *
	 * @param string $role Role.
	 */
	private function user( string $role ): int {
		$user = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user );
		return $user;
	}

	/**
	 * A published, reviewed profile.
	 */
	private function profile(): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Public Holdings',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		return $id;
	}

	/**
	 * Everything the flows wrote, as one string.
	 */
	private function everything(): string {
		global $wpdb;
		$audit = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE, ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		return (string) wp_json_encode(
			array(
				'audit'  => $audit,
				'failed' => $this->runner->failed_jobs(),
				'lines'  => $this->lines,
				'php'    => (string) file_get_contents( $this->error_file ),
				'email'  => $this->email_log,
			)
		);
	}

	/**
	 * Assert none of the fixture values is in a haystack.
	 *
	 * @param string $haystack Text.
	 * @param string $what     Where it was searched.
	 */
	private function assertNoPersonalData( string $haystack, string $what ): void {
		foreach ( array( self::NAME, self::EMAIL, self::PHONE, self::IP, 'Qwertyuiop', 'zanele' ) as $needle ) {
			$this->assertStringNotContainsStringIgnoringCase( $needle, $haystack, $what . ' holds personal data: ' . $needle );
		}
	}

	public function test_REQ_063_logs_and_errors_contain_ids_not_names_or_emails(): void {
		// Structured logging for failed jobs, as the site registers it, writing to an in-memory sink.
		$sink = new class( $this ) implements LogSink {
			/**
			 * Test.
			 *
			 * @var M3LogsHoldNoPersonalDataTest
			 */
			private M3LogsHoldNoPersonalDataTest $test;

			/**
			 * Constructor.
			 *
			 * @param M3LogsHoldNoPersonalDataTest $test Test.
			 */
			public function __construct( M3LogsHoldNoPersonalDataTest $test ) {
				$this->test = $test;
			}

			/**
			 * Write.
			 *
			 * @param string $line Line.
			 */
			public function write( string $line ): void {
				$this->test->record_line( $line );
			}
		};
		( new Logger( $sink, new PiiCatalogue(), 'req-1' ) )->register();

		$notifier = $this->notifier();
		$items    = new SupportItems( new DueDates( array() ), 'test-key' );

		// 1. An enquiry, with the mail sender failing on the address.
		$values = ( new EnquiryValidator() )->validate(
			new FormInput(
				array(
					'name'         => self::NAME,
					'organisation' => 'Qwertyuiop Holdings',
					'interest'     => 'Getting listed',
					'email'        => self::EMAIL,
					'phone'        => self::PHONE,
					'message'      => 'Please tell me how to be listed.',
					'source'       => 'get-listed',
				)
			)
		);
		$this->assertTrue( $values->is_valid() );
		$enquiry = ( new EnquiryIntake( $items, $notifier ) )->submit( $values->values() );
		$this->drain();

		// 2. A removal request with the confirmation link, the same failing sender.
		$profile = $this->profile();
		( new RequestIntake( $items, $notifier, new RequesterConfirmation( $items, new SignedLinks( new MemoryLinkStore(), static fn (): int => 1800000000 ) ) ) )->submit(
			'removal',
			array(
				'name'         => self::NAME,
				'relationship' => 'person',
				'email'        => self::EMAIL,
				'message'      => 'Remove it.',
			),
			$profile,
			self::IP,
			true
		);
		$this->drain();

		// 3. A claim, decided by a staff user (approve, then another claim rejected).
		$staff    = $this->user( 'wiw_editor' );
		$claims   = new ClaimRepository();
		$claim    = $claims->create(
			array(
				'profile_id'   => $profile,
				'relationship' => 'self',
				'name'         => self::NAME,
				'role'         => 'Founder',
				'work_email'   => self::EMAIL,
				'phone'        => self::PHONE,
				'evidence'     => 'Registry entry.',
				'consent'      => true,
			)
		);
		$other    = $claims->create(
			array(
				'profile_id'   => $profile,
				'relationship' => 'self',
				'name'         => self::NAME,
				'work_email'   => self::EMAIL,
				'consent'      => true,
			)
		);
		$audit    = $this->audit( $staff );
		$decision = new ClaimDecision( $claims, new StateRepository(), $notifier, $audit );
		$decision->approve( $claim->id, $staff );
		$decision->reject( $other->id, $staff, 'We could not verify the evidence.' );
		$this->drain();

		// 4. A customer made from the enquiry, then edited (audit holds field names only). Customers are Administrator only.
		wp_set_current_user( $this->user( 'administrator' ) );
		$customers = new CustomerService( new CustomerRepository(), $audit, $items );
		$customer  = $customers->create_from_enquiry( (int) $enquiry['id'] );
		$customers->update(
			$customer,
			array(
				'contact_phone' => self::PHONE,
				'notes'         => 'Spoke to ' . self::NAME,
			)
		);

		// 5. The logger is given personal data by mistake: it removes it.
		( new Logger( $sink, new PiiCatalogue(), 'req-2' ) )->log(
			'warning',
			'Could not reach ' . self::EMAIL,
			array(
				'id'             => $customer,
				'requester_name' => self::NAME,
				'phone'          => self::PHONE,
				'ref'            => (string) $enquiry['reference'],
			)
		);

		// 6. A bad submission: the validation error does not echo the value back.
		$bad = ( new EnquiryValidator() )->validate(
			new FormInput(
				array(
					'name'    => self::NAME,
					'email'   => 'not-an-email-' . self::NAME,
					'message' => 'x',
					'source'  => 'get-listed',
				)
			)
		);
		$this->assertFalse( $bad->is_valid() );

		$all = $this->everything();
		$this->assertNotSame( array(), $this->runner->failed_jobs(), 'The failing sender produced failed jobs to inspect.' );
		$this->assertNotSame( array(), $this->lines, 'The logger wrote lines to inspect.' );
		$this->assertNoPersonalData( $all, 'The audit log, job failures, log lines, PHP error log and email log' );
		$this->assertNoPersonalData( (string) wp_json_encode( $bad->errors() ), 'The validation errors' );
		$this->assertStringContainsString( (string) $enquiry['reference'], implode( ' ', $this->lines ), 'Logs hold the reference.' );
	}

	/**
	 * Record a log line (called by the sink).
	 *
	 * @param string $line Line.
	 */
	public function record_line( string $line ): void {
		$this->lines[] = $line;
	}
}
