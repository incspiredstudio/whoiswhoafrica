<?php
/**
 * The two-factor reminder (REQ-047, REQ-235, issue 814): two-factor stays optional, and every staff member who has not turned it
 * on sees a warning notice on every admin screen and a dashboard line, can hide it for a day, and gets one email the first time
 * they sign in. It stops as soon as they have a method on, never blocks anything, and shows nothing without the Two Factor plugin.
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\TwoFactor\Reminder;
use WhoIsWho\Core\Platform\TwoFactor\SecondFactorStatus;
use WhoIsWho\Core\Platform\TwoFactor\StaffStatus;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\WpTestCase;

final class TwoFactorReminderTest extends WpTestCase {

	private const NOW = 1800000000;

	/**
	 * Messages the fake mailer "sent".
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
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
		$this->sent          = array();
		$this->queue         = new MemoryQueue();
		$options             = new \ArrayObject();
		$this->runner        = new JobRunner(
			$this->queue,
			static fn (): int => self::NOW,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$this->queue->runner = $this->runner;
		Reminder::register_mail_resolvers();
		delete_transient( Reminder::hour_key( self::NOW ) );
		delete_transient( Reminder::hour_key( self::NOW + HOUR_IN_SECONDS ) );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		remove_all_filters( 'wiw_mail_name_resolvers' );
		parent::tearDown();
	}

	/**
	 * A status source: the plugin present (or not) and the given users enrolled.
	 *
	 * @param int[] $enrolled  Enrolled user ids.
	 * @param bool  $available Whether the plugin is loaded.
	 */
	private function status( array $enrolled, bool $available = true ): SecondFactorStatus {
		return new class( $enrolled, $available ) implements SecondFactorStatus {
			/**
			 * Constructor.
			 *
			 * @param int[] $enrolled  Ids.
			 * @param bool  $available Present.
			 */
			public function __construct( private array $enrolled, private bool $available ) {}
			public function available(): bool {
				return $this->available;
			}
			public function is_enrolled( int $user_id ): bool {
				return $this->available && in_array( $user_id, $this->enrolled, true );
			}
		};
	}

	private function notifier(): Notifier {
		$log = new class() implements EmailLogStore {
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
				unset( $message_id, $event, $recipient_ref, $type, $result );
			}
		};
		return new Notifier(
			$this->runner,
			$log,
			static fn (): array => array(),
			function ( string $to, string $subject, string $text, string $html ): bool {
				$this->sent[] = array(
					'to'      => $to,
					'subject' => $subject,
					'text'    => $text,
					'html'    => $html,
				);
				return true;
			},
			'hash-key'
		);
	}

	/**
	 * Reminder with a fake status and mail path.
	 *
	 * @param int[] $enrolled  Enrolled user ids.
	 * @param bool  $available Whether the plugin is loaded.
	 * @param int   $cap       Hourly cap.
	 */
	private function reminder( array $enrolled = array(), bool $available = true, int $cap = 100 ): Reminder {
		return new Reminder( $this->status( $enrolled, $available ), $this->notifier(), $cap );
	}

	private function user( string $role, string $email = '' ): int {
		$args = array( 'role' => $role );
		if ( '' !== $email ) {
			$args['user_email'] = $email;
		}
		return (int) self::factory()->user->create( $args );
	}

	public function test_REQ_047_every_staff_role_without_two_factor_sees_a_warning_notice_with_both_actions(): void {
		$reminder = $this->reminder();
		foreach ( array( 'administrator', 'wiw_editor', 'wiw_reviewer' ) as $role ) {
			$id   = $this->user( $role );
			$html = $reminder->notice_html( $id, self::NOW );
			$this->assertStringContainsString( 'notice-warning', $html, $role );
			$this->assertStringNotContainsString( 'notice-error', $html, $role );
			$this->assertStringContainsString( 'id="' . Reminder::NOTICE_ID . '"', $html, $role );
			$this->assertStringContainsString( 'Protect your account: turn on two-factor sign-in.', $html, $role );
			$this->assertStringContainsString( 'It takes two minutes with an authenticator app.', $html, $role );
			$this->assertStringContainsString( 'Turn it on now', $html, $role );
			$this->assertStringContainsString( esc_url( admin_url( 'profile.php' ) ) . '#two-factor-options', $html, $role );
			$this->assertStringContainsString( 'Remind me tomorrow', $html, $role );
			$this->assertStringContainsString( 'action=' . Reminder::ACTION, $html, $role );
			$this->assertStringContainsString( '_wpnonce=', $html, $role );
		}
	}

	public function test_REQ_047_a_user_holding_one_staff_capability_counts_as_staff(): void {
		$id   = $this->user( 'subscriber' );
		$user = new \WP_User( $id );
		$this->assertFalse( StaffStatus::is_staff( $id ) );
		$user->add_cap( 'wiw_review_records' );
		$this->assertTrue( StaffStatus::is_staff( $id ) );
		$this->assertNotSame( '', $this->reminder()->notice_html( $id, self::NOW ) );
	}

	public function test_REQ_047_no_notice_for_non_staff_enrolled_staff_or_without_the_plugin(): void {
		$subscriber = $this->user( 'subscriber' );
		$editor     = $this->user( 'wiw_editor' );
		$this->assertSame( '', $this->reminder()->notice_html( $subscriber, self::NOW ), 'not staff' );
		$this->assertSame( '', $this->reminder()->notice_html( 0, self::NOW ), 'signed out' );
		$this->assertSame( '', $this->reminder( array( $editor ) )->notice_html( $editor, self::NOW ), 'two-factor already on' );
		$this->assertSame( '', $this->reminder( array( $editor ) )->dashboard_html( $editor ), 'two-factor already on' );
		$this->assertSame( '', $this->reminder( array(), false )->notice_html( $editor, self::NOW ), 'no Two Factor plugin' );
		$this->assertSame( '', $this->reminder( array(), false )->dashboard_html( $editor ), 'no Two Factor plugin' );
	}

	public function test_REQ_047_the_notice_stops_as_soon_as_a_method_is_on(): void {
		$editor = $this->user( 'wiw_editor' );
		$this->assertNotSame( '', $this->reminder()->notice_html( $editor, self::NOW ) );
		$this->assertSame( '', $this->reminder( array( $editor ) )->notice_html( $editor, self::NOW ) );
		update_user_meta( $editor, Reminder::META_SNOOZE, (string) ( self::NOW - 10 ) );
		$this->assertSame( '', $this->reminder( array( $editor ) )->notice_html( $editor, self::NOW + 2 * DAY_IN_SECONDS ), 'it never comes back' );
	}

	public function test_REQ_047_remind_me_tomorrow_hides_it_for_24_hours_for_that_user_only(): void {
		$editor   = $this->user( 'wiw_editor' );
		$other    = $this->user( 'wiw_editor' );
		$reminder = $this->reminder();
		Reminder::snooze( $editor, self::NOW );

		$this->assertSame( '', $reminder->notice_html( $editor, self::NOW + 60 ) );
		$this->assertSame( '', $reminder->notice_html( $editor, self::NOW + DAY_IN_SECONDS - 1 ) );
		$this->assertNotSame( '', $reminder->notice_html( $editor, self::NOW + DAY_IN_SECONDS + 1 ), 'back after a day' );
		$this->assertNotSame( '', $reminder->notice_html( $other, self::NOW + 60 ), 'only for that user' );
		$this->assertNotSame( '', $reminder->dashboard_html( $editor ), 'the dashboard line stays while two-factor is off' );
	}

	public function test_REQ_047_remind_me_tomorrow_needs_the_users_own_nonce_and_nothing_more(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		wp_set_current_user( $reviewer );
		$good = new Request( array( '_wpnonce' => ActionGuard::nonce_for( Reminder::ACTION ) ) );
		$bad  = new Request( array( '_wpnonce' => 'nope' ) );
		$this->assertNull( ActionGuard::check( Reminder::ACTION, Reminder::CAP, $good ), 'any signed-in staff member may hide their own reminder' );
		$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( Reminder::ACTION, Reminder::CAP, $bad ) );
		wp_set_current_user( 0 );
		$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( Reminder::ACTION, Reminder::CAP, $good ), 'signed out' );
	}

	public function test_REQ_047_the_dashboard_line_has_the_same_message_and_button(): void {
		$admin = $this->user( 'administrator' );
		$html  = $this->reminder()->dashboard_html( $admin );
		$this->assertStringContainsString( 'Protect your account: turn on two-factor sign-in.', $html );
		$this->assertStringContainsString( 'Turn it on now', $html );
		$this->assertStringContainsString( '#two-factor-options', $html );
		$this->assertSame( '', $this->reminder()->dashboard_html( $this->user( 'subscriber' ) ) );
	}

	public function test_REQ_235_the_first_sign_in_without_two_factor_sends_one_email_once_only(): void {
		$editor   = $this->user( 'wiw_editor', 'editor.example@example.org' );
		$reminder = $this->reminder();

		$this->assertTrue( $reminder->maybe_email( $editor, self::NOW ) );
		$this->runner->run_due( 60 );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'editor.example@example.org', $this->sent[0]['to'] );
		$this->assertSame( Events::all()[ Events::TWO_FACTOR_REMINDER ]['subject'], $this->sent[0]['subject'] );
		$this->assertStringContainsString( 'two-factor', strtolower( $this->sent[0]['text'] ) );
		$this->assertStringContainsString( admin_url( 'profile.php' ) . '#two-factor-options', $this->sent[0]['text'] );
		$this->assertStringContainsString( 'max-width:600px', $this->sent[0]['html'], 'the shared mail layout' );
		$this->assertNotSame( '', (string) get_user_meta( $editor, Reminder::META_EMAILED, true ) );

		// The second and later sign-ins send nothing more.
		$this->assertFalse( $reminder->maybe_email( $editor, self::NOW + 3600 ) );
		$this->assertFalse( $this->reminder()->maybe_email( $editor, self::NOW + 7 * DAY_IN_SECONDS ) );
		$this->runner->run_due( 60 );
		$this->assertCount( 1, $this->sent, 'sent once only' );
	}

	public function test_REQ_235_the_sign_in_hook_sends_the_email(): void {
		$admin = $this->user( 'administrator', 'admin.example@example.org' );
		$this->reminder()->on_login( 'someone', new \WP_User( $admin ) );
		$this->runner->run_due( 60 );
		$this->assertSame( array( 'admin.example@example.org' ), array_column( $this->sent, 'to' ) );
	}

	public function test_REQ_235_no_email_for_enrolled_staff_non_staff_or_without_the_plugin(): void {
		$editor     = $this->user( 'wiw_editor', 'on.example@example.org' );
		$subscriber = $this->user( 'subscriber', 'sub.example@example.org' );
		$this->assertFalse( $this->reminder( array( $editor ) )->maybe_email( $editor, self::NOW ) );
		$this->assertFalse( $this->reminder()->maybe_email( $subscriber, self::NOW ) );
		$this->assertFalse( $this->reminder( array(), false )->maybe_email( $editor, self::NOW ) );
		$this->runner->run_due( 60 );
		$this->assertSame( array(), $this->sent );
		$this->assertSame( '', (string) get_user_meta( $editor, Reminder::META_EMAILED, true ), 'still due if they turn it off again' );
	}

	public function test_REQ_235_the_hourly_cap_holds_the_email_until_a_later_sign_in(): void {
		$first  = $this->user( 'wiw_editor', 'first.example@example.org' );
		$second = $this->user( 'wiw_editor', 'second.example@example.org' );
		$this->assertTrue( $this->reminder( array(), true, 1 )->maybe_email( $first, self::NOW ) );
		$this->assertFalse( $this->reminder( array(), true, 1 )->maybe_email( $second, self::NOW ), 'over the hourly cap' );
		$this->assertSame( '', (string) get_user_meta( $second, Reminder::META_EMAILED, true ), 'not marked, so it goes next time' );
		$this->assertTrue( $this->reminder( array(), true, 1 )->maybe_email( $second, self::NOW + HOUR_IN_SECONDS ), 'the next hour' );
		$this->runner->run_due( 60 );
		$this->assertSame( array( 'first.example@example.org', 'second.example@example.org' ), array_column( $this->sent, 'to' ) );
	}
}
