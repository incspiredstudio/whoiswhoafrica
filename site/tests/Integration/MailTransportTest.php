<?php
/**
 * Mail goes through the SMTP account from the secrets file, and fails closed in production (REQ-075, REQ-204).
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use PHPMailer\PHPMailer\PHPMailer;
use WhoIsWho\Core\Platform\Mail\Transport;
use WhoIsWho\Tests\WpTestCase;

final class MailTransportTest extends WpTestCase {

	/**
	 * Build a transport.
	 *
	 * @param array<string, mixed> $override Settings to change.
	 */
	private function transport( array $override = array() ): Transport {
		return new Transport(
			array_merge(
				array(
					'host'       => 'mail.example.org',
					'port'       => 587,
					'user'       => 'sender@example.org',
					'password'   => 'S3cret-Pass-word',
					'from'       => 'hello@example.org',
					'production' => true,
					'hourly_cap' => 100,
				),
				$override
			)
		);
	}

	public function test_REQ_075_wp_mail_uses_smtp_settings_from_secrets(): void {
		$mailer = new PHPMailer( true );
		$this->transport()->configure( $mailer );

		$this->assertSame( 'smtp', $mailer->Mailer );
		$this->assertSame( 'mail.example.org', $mailer->Host );
		$this->assertSame( 587, $mailer->Port );
		$this->assertTrue( $mailer->SMTPAuth );
		$this->assertSame( 'sender@example.org', $mailer->Username );
		$this->assertSame( 'tls', $mailer->SMTPSecure );

		$ssl = new PHPMailer( true );
		$this->transport( array( 'port' => 465 ) )->configure( $ssl );
		$this->assertSame( 'ssl', $ssl->SMTPSecure );
	}

	public function test_REQ_075_missing_smtp_credentials_do_not_fall_back_silently(): void {
		delete_option( Transport::OPTION_ERROR );
		$transport = $this->transport(
			array(
				'host'     => '',
				'user'     => '',
				'password' => '',
			)
		);

		$this->assertFalse( $transport->pre_send( null, array() ), 'wp_mail is stopped in production' );
		$this->assertSame( 'smtp_credentials_missing', get_option( Transport::OPTION_ERROR ), 'a staff-visible error is recorded' );

		$mailer = new PHPMailer( true );
		$transport->configure( $mailer );
		$this->assertSame( 'mail', $mailer->Mailer, 'it did not quietly switch to another transport' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		$transport->notice();
		$this->assertStringContainsString( 'Email is not being sent', (string) ob_get_clean() );
	}

	public function test_REQ_075_outside_production_mail_goes_to_the_local_catcher(): void {
		$transport = $this->transport(
			array(
				'host'       => '',
				'user'       => '',
				'password'   => '',
				'production' => false,
			)
		);
		$this->assertNull( $transport->pre_send( null, array() ), 'local sending is not blocked' );

		$mailer = new PHPMailer( true );
		$transport->configure( $mailer );
		$this->assertSame( 'smtp', $mailer->Mailer );
		$this->assertSame( '127.0.0.1', $mailer->Host );
		$this->assertSame( 1025, $mailer->Port );
		$this->assertFalse( $mailer->SMTPAuth );
	}

	public function test_REQ_204_no_credential_or_address_reaches_the_log(): void {
		$log = sys_get_temp_dir() . '/wiw-mail-' . uniqid() . '.log';
		// phpcs:ignore WordPress.PHP.IniSet.Risky -- sends error_log output to a temp file for this test only.
		$previous = ini_set( 'error_log', $log );

		$transport = $this->transport();
		$transport->log_failure( new \WP_Error( 'wp_mail_failed', 'SMTP connect failed for sender@example.org with S3cret-Pass-word to jane@example.org' ) );

		$written = (string) file_get_contents( $log );
		unlink( $log ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		ini_set( 'error_log', (string) $previous ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		$this->assertStringContainsString( 'send failed', $written );
		$this->assertStringNotContainsString( 'S3cret-Pass-word', $written );
		$this->assertStringNotContainsString( 'sender@example.org', $written );
		$this->assertStringNotContainsString( 'jane@example.org', $written );
	}

	public function test_REQ_075_mailer_names_itself_with_the_sending_domain_and_has_a_bounded_timeout(): void {
		$mailer = new PHPMailer( true );
		$this->transport()->configure( $mailer );
		$this->assertSame( 'example.org', $mailer->Hostname, 'EHLO and Message-ID use the sending domain, not localhost' );
		$this->assertGreaterThan( 0, $mailer->Timeout );
		$this->assertLessThanOrEqual( 60, $mailer->Timeout );
	}

	public function test_REQ_075_from_name_is_the_brand_not_wordpress(): void {
		add_filter( 'wiw_brand_name', static fn (): string => 'Brand Name' );
		$this->assertSame( 'Brand Name', $this->transport()->from_name( 'WordPress' ) );
		remove_all_filters( 'wiw_brand_name' );
	}

	public function test_REQ_075_html_part_links_urls_and_staff_notices_do_not_say_your_reference(): void {
		$notifier = \WhoIsWho\Core\Platform\Mail\Notifier::for_site();
		$public   = $notifier->render(
			'request_acknowledged',
			array(
				'lead'      => 'Confirm here: https://example.org/link/abc?x=1&y=2.',
				'reference' => 'WIW-1',
			)
		);
		$this->assertStringContainsString( '<a href="https://example.org/link/abc?x=1&amp;y=2">https://example.org/link/abc?x=1&amp;y=2</a>.', $public['html'] );
		$this->assertStringContainsString( 'Your reference: WIW-1', $public['text'] );
		$staff = $notifier->render( 'correction_received', array( 'reference' => 'WIW-1' ) );
		$this->assertStringNotContainsString( 'Your reference', $staff['text'] );
		$this->assertStringContainsString( 'Reference: WIW-1', $staff['text'] );
	}

	public function test_REQ_075_from_address_is_on_the_sending_domain_and_the_hourly_cap_is_recorded(): void {
		$this->assertSame( 'hello@example.org', $this->transport()->from_address( 'wordpress@localhost' ) );
		$this->assertStringStartsWith( 'no-reply@', $this->transport( array( 'from' => '' ) )->from_address( 'wordpress@localhost' ) );
		$this->assertSame( 100, $this->transport()->hourly_cap() );
		$this->assertSame( 100, Transport::DEFAULT_HOURLY_CAP );
	}
}
