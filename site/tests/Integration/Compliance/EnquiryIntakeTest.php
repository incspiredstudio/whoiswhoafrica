<?php
/**
 * Enquiry intake (REQ-177, REQ-205, REQ-039, REQ-018). Needs the WordPress test suite and the real support table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Support\DueDates;
use WhoIsWho\Core\Compliance\Support\EnquiryIntake;
use WhoIsWho\Core\Compliance\Support\EnquiryValidator;
use WhoIsWho\Core\Compliance\Support\ItemStatus;
use WhoIsWho\Core\Compliance\Support\SupportItems;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class EnquiryIntakeTest extends TestCase {

	/**
	 * Messages sent.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $sent = array();

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
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		delete_option( 'wiw_enquiry_sequence' );
		$this->sent          = array();
		$options             = new \ArrayObject();
		$this->queue         = new MemoryQueue();
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
		EnquiryIntake::register();
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		parent::tearDown();
	}

	private function intake(): EnquiryIntake {
		$log      = new class() implements EmailLogStore {
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
		$notifier = new Notifier(
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
		return new EnquiryIntake( new SupportItems( new DueDates( array() ), 'test-key' ), $notifier );
	}

	/**
	 * Validated values.
	 *
	 * @return array<string, string>
	 */
	private function values(): array {
		$result = ( new EnquiryValidator() )->validate(
			new FormInput(
				array(
					'name'         => 'Ada Obi',
					'organisation' => 'Obi Holdings',
					'interest'     => 'Getting listed',
					'email'        => 'ada@example.test',
					'phone'        => '+234 800 000 0000',
					'message'      => 'Please tell me how to be listed.',
					'source'       => 'get-listed',
				)
			)
		);
		$this->assertTrue( $result->is_valid() );
		return $result->values();
	}

	public function test_REQ_177_enquiry_item_stores_fields_and_reference(): void {
		$intake = $this->intake();
		$first  = $intake->submit( $this->values() );
		$second = $intake->submit( $this->values() );
		$stored = ( new SupportItems() )->get( (int) $first['id'] );
		$this->assertNotNull( $stored );
		$this->assertSame( 'ENQ-0001', $stored['reference'] );
		$this->assertSame( 'ENQ-0002', $second['reference'] );
		$this->assertSame( 'enquiry', $stored['item_type'] );
		$this->assertSame( 'Ada Obi', $stored['requester_name'] );
		$this->assertSame( 'Obi Holdings', $stored['organisation'] );
		$this->assertSame( 'Getting listed', $stored['interest'] );
		$this->assertSame( 'ada@example.test', $stored['requester_email'] );
		$this->assertSame( 'get-listed', $stored['source'] );
		$this->assertSame( ItemStatus::NEW, $stored['status'] );
		$this->assertNotEmpty( $stored['created_at'] );
		$this->assertNull( $stored['due_at'], 'An enquiry has no due date.' );
	}

	public function test_REQ_177_enquiry_sends_acknowledgement(): void {
		$row = $this->intake()->submit( $this->values() );
		foreach ( $this->queue->jobs as $job ) {
			if ( isset( $job['args']['record'] ) ) {
				$this->assertStringNotContainsString( 'ada@example.test', (string) wp_json_encode( $job['args'] ), 'The address is not in the job.' );
			}
		}
		$this->runner->run_due( 60 );
		$to = array_column( $this->sent, 'to' );
		$this->assertContains( 'ada@example.test', $to );
		$this->assertContains( 'inbox@example.org', $to, 'Staff are told too.' );
		$ack = $this->sent[ array_search( 'ada@example.test', $to, true ) ];
		$this->assertStringContainsString( (string) $row['reference'], $ack['text'] );
	}

	public function test_REQ_205_enquiry_has_external_id_and_change_timestamp(): void {
		$row    = $this->intake()->submit( $this->values() );
		$stored = ( new SupportItems() )->get( (int) $row['id'] );
		$this->assertNotNull( $stored );
		$this->assertTrue( wp_is_uuid( $stored['external_id'] ) );
		$this->assertNotEmpty( $stored['updated_at'] );
		$other = $this->intake()->submit( $this->values() );
		$this->assertNotSame( $stored['external_id'], $other['external_id'] );
	}

	public function test_REQ_177_enquiry_has_no_price_or_package_field(): void {
		global $wpdb;
		$columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed name.
		foreach ( $columns as $column ) {
			$this->assertDoesNotMatchRegularExpression( '/price|package|tier|amount|plan/i', (string) $column );
		}
		$result = ( new EnquiryValidator() )->validate(
			new FormInput(
				array(
					'name'    => 'Ada',
					'email'   => 'a@example.test',
					'message' => 'Hello there',
					'source'  => 'elsewhere',
					'price'   => '100',
				)
			)
		);
		$this->assertArrayHasKey( 'source', $result->errors() );
		$this->assertArrayNotHasKey( 'price', $result->values() );
	}

	public function test_REQ_177_a_missing_record_sends_nothing_and_does_not_retry(): void {
		$row = $this->intake()->submit( $this->values() );
		( new SupportItems() )->get( (int) $row['id'] );
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . SupportItems::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$this->runner->run_due( 60 );
		$this->assertNotContains( 'ada@example.test', array_column( $this->sent, 'to' ) );
	}
}
