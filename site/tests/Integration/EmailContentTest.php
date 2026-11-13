<?php
/**
 * Email content and layout inside WordPress with the child theme active: branded layout, footer, dark-mode block, Reply-To,
 * greeting looked up at send time, the preview command (REQ-075, REQ-052, REQ-043, REQ-061, REQ-233). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- test doubles with fixed signatures.

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Cli\MailPreview;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Tests\WpTestCase;

final class EmailContentTest extends WpTestCase {

	/**
	 * Messages the fake mailer received.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $sent = array();

	protected function setUp(): void {
		parent::setUp();
		switch_theme( 'whoiswho-child' );
		$this->sent = array();
		delete_option( 'wiw_setting_mail_reply_to' );
		delete_option( 'wiw_setting_contact_email' );
		delete_option( 'wiw_setting_enquiry_response_window' );
		remove_all_filters( 'wiw_mail_name_resolvers' );
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_mail_name_resolvers' );
		remove_all_filters( 'wiw_mail_recipient_resolvers' );
		parent::tearDown();
	}

	/**
	 * A notifier whose mailer records what it was given.
	 */
	private function notifier(): Notifier {
		return new Notifier(
			JobRunner::for_site(),
			new class() implements EmailLogStore {
				/**
				 * Add.
				 *
				 * @param string $message_id    Id.
				 * @param string $event         Event.
				 * @param string $recipient_ref Ref.
				 * @param string $type          Type.
				 * @param string $result        Result.
				 */
				public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {}
			},
			static fn (): array => array(),
			function ( string $to, string $subject, string $text, string $html, array $headers = array() ): bool {
				$this->sent[] = compact( 'to', 'subject', 'text', 'html', 'headers' );
				return true;
			},
			'test-key'
		);
	}

	public function test_REQ_075_branded_layout_has_footer_with_contact_privacy_and_site_name(): void {
		update_option( 'wiw_setting_contact_email', 'hello@example.org' );
		$mail = $this->notifier()->render( Events::ENQUIRY_ACKNOWLEDGED, array( 'reference' => 'ENQ-0001' ) );
		$this->assertStringContainsString( wiw_brand( 'legal_name' ), $mail['html'] );
		$this->assertStringContainsString( 'hello@example.org', $mail['html'] );
		$this->assertStringContainsString( 'hello@example.org', $mail['text'] );
		$this->assertStringContainsString( '/privacy/', $mail['html'] );
		$this->assertStringContainsString( '/privacy/', $mail['text'] );
		$this->assertStringContainsString( 'max-width:600px', $mail['html'] );
		$this->assertStringContainsString( 'Your reference: ENQ-0001', $mail['text'] );
	}

	public function test_REQ_052_dark_mode_block_uses_tokens_and_no_literals_are_added_to_the_plugin(): void {
		$html = $this->notifier()->render( Events::ENQUIRY_ACKNOWLEDGED, array() )['html'];
		$this->assertStringContainsString( 'prefers-color-scheme: dark', $html );
		$this->assertStringContainsString( 'color-scheme', $html );
		foreach ( array( 'EmailCopy.php', 'MailLayout.php', 'MailSettings.php' ) as $file ) {
			$code = (string) file_get_contents( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/src/Platform/Mail/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{6}\b/', $code, $file . ' has a colour literal' );
		}
	}

	public function test_REQ_043_images_have_alt_text_and_the_page_is_text_first(): void {
		$html = $this->notifier()->render( Events::VERIFY_EMAIL, array( 'cta_url' => 'https://example.test/link/x/' ) )['html'];
		$this->assertMatchesRegularExpression( '/<img [^>]*alt="[^"]+"/', $html );
		$this->assertStringNotContainsString( 'width="1" height="1"', $html, 'No tracking pixel.' );
		$this->assertStringContainsString( 'Confirm my email', $html );
	}

	public function test_REQ_075_response_window_and_staff_admin_link_come_from_settings_and_ids(): void {
		update_option( 'wiw_setting_enquiry_response_window', 'two working days' );
		$notifier = $this->notifier();
		$ack      = $notifier->render( Events::ENQUIRY_ACKNOWLEDGED, array() );
		$this->assertStringContainsString( 'We aim to reply within two working days.', $ack['text'] );

		$staff = $notifier->render( Events::ENQUIRY_STAFF_NOTICE, array( 'id' => 42 ) );
		$this->assertStringContainsString( 'page=wiw-enquiries&item=42', $staff['text'] );
		$this->assertStringContainsString( 'Open it in the admin', $staff['html'] );
		$this->assertStringContainsString( 'What to do:', $staff['text'] );
	}

	public function test_REQ_075_delivery_sets_reply_to_from_settings_else_from_address(): void {
		update_option( 'wiw_setting_mail_reply_to', 'support@example.org' );
		$this->notifier()->deliver(
			array(
				'event'   => Events::PROFILE_RECEIVED,
				'to'      => 'jane@example.org',
				'context' => array(),
			)
		);
		$this->assertContains( 'Reply-To: support@example.org', $this->sent[0]['headers'] );

		delete_option( 'wiw_setting_mail_reply_to' );
		$this->notifier()->deliver(
			array(
				'event'   => Events::PROFILE_RECEIVED,
				'to'      => 'jane@example.org',
				'context' => array(),
			)
		);
		$reply = array_values( array_filter( $this->sent[1]['headers'], static fn ( string $h ): bool => str_starts_with( $h, 'Reply-To: ' ) ) );
		$this->assertCount( 1, $reply, 'The default is the From address.' );
		$this->assertTrue( is_email( substr( $reply[0], 10 ) ) !== false );
	}

	public function test_REQ_075_greeting_name_is_looked_up_when_the_job_runs_not_queued(): void {
		add_filter(
			'wiw_mail_recipient_resolvers',
			static fn ( $r ): array => array_merge( (array) $r, array( 'demo' => static fn ( int $id ): string => 'amara@example.org' ) )
		);
		add_filter(
			'wiw_mail_name_resolvers',
			static fn ( $r ): array => array_merge( (array) $r, array( 'demo' => static fn ( int $id ): string => 'Amara Okafor' ) )
		);
		$this->notifier()->deliver(
			array(
				'event'   => Events::ENQUIRY_ACKNOWLEDGED,
				'record'  => array(
					'source' => 'demo',
					'id'     => 5,
				),
				'context' => array( 'reference' => 'ENQ-0001' ),
			)
		);
		$this->assertStringContainsString( 'Hello Amara,', $this->sent[0]['text'] );
		$this->assertStringNotContainsString( 'Amara', $this->sent[0]['subject'] );
	}

	public function test_REQ_233_marketing_footer_has_unsubscribe_and_transactional_has_none(): void {
		$notifier = $this->notifier();
		$news     = $notifier->render( Events::NEWSLETTER_ISSUE, array( 'unsubscribe_url' => 'https://example.test/unsubscribe/x/' ) );
		$this->assertStringContainsString( 'https://example.test/unsubscribe/x/', $news['html'] );
		$tx = $notifier->render( Events::ENQUIRY_ACKNOWLEDGED, array( 'unsubscribe_url' => 'https://example.test/unsubscribe/x/' ) );
		$this->assertStringNotContainsString( 'example.test/unsubscribe', $tx['html'] );
	}

	public function test_REQ_075_preview_command_renders_every_event_and_refuses_in_production(): void {
		$dir    = sys_get_temp_dir() . '/wiw-preview-' . wp_generate_password( 6, false );
		$output = new Output();
		ob_start();
		$code = ( new MailPreview( $this->notifier(), false ) )->run( array( '--dir=' . $dir ), $output );
		ob_end_clean();
		$this->assertSame( 0, $code );
		foreach ( array_keys( Events::all() ) as $event ) {
			$this->assertFileExists( $dir . '/' . $event . '.html' );
			$this->assertFileExists( $dir . '/' . $event . '.txt' );
		}
		$this->assertFileExists( $dir . '/index.html' );
		$this->assertEmpty( $this->sent, 'Nothing is sent.' );

		ob_start();
		$refused = ( new MailPreview( $this->notifier(), true ) )->run( array( '--dir=' . $dir . '-no' ), $output );
		ob_end_clean();
		$this->assertSame( 1, $refused );
		$this->assertDirectoryDoesNotExist( $dir . '-no' );
	}
}
