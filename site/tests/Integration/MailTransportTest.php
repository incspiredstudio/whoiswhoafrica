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
	private function transport( array $override = array(), bool $mail_plugin = false ): Transport {
		return new Transport(
			array_merge(
				array(
					'host'       => 'mail.example.org',
					'port'       => 587,
					'user'       => 'sender@example.org',
					'password'   => 'S3cret-Pass-word',
					'from'       => 'hello@example.org',
					'hourly_cap' => 100,
				),
				$override
			),
			static fn (): bool => $mail_plugin
		);
	}

	/**
	 * A transport with no WIW_SMTP_* constants.
	 *
	 * @param bool $mail_plugin Whether a mail plugin is active.
	 */
	private function bare( bool $mail_plugin = false ): Transport {
		return $this->transport(
			array(
				'host'     => '',
				'user'     => '',
				'password' => '',
				'from'     => '',
			),
			$mail_plugin
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

	public function test_REQ_075_with_no_constants_and_no_mail_plugin_wordpress_sends_with_plain_wp_mail(): void {
		$transport = $this->bare();
		$transport->register();
		$this->assertTrue( wp_mail( 'someone@example.org', 'Subject', 'Body' ), 'nothing stops wp_mail, in any environment' );

		$mailer = new PHPMailer( true );
		$transport->configure( $mailer );
		$this->assertSame( 'mail', $mailer->Mailer, 'WordPress\'s own sending is used' );
		$this->assertNotSame( '127.0.0.1', $mailer->Host, 'no forced local mail catcher' );
		$this->assertNotSame( 1025, $mailer->Port );
	}

	public function test_REQ_075_a_local_mail_catcher_is_used_only_when_smtp_host_names_it(): void {
		$transport = $this->transport(
			array(
				'host'     => '127.0.0.1',
				'port'     => 1025,
				'user'     => '',
				'password' => '',
			)
		);
		$mailer    = new PHPMailer( true );
		$transport->configure( $mailer );
		$this->assertSame( 'smtp', $mailer->Mailer );
		$this->assertSame( '127.0.0.1', $mailer->Host );
		$this->assertSame( 1025, $mailer->Port );
		$this->assertFalse( $mailer->SMTPAuth );
		$this->assertFalse( $mailer->SMTPAutoTLS );
	}

	public function test_REQ_075_an_active_smtp_plugin_owns_the_connection_and_the_from_address(): void {
		$mailer       = new PHPMailer( true );
		$mailer->Host = 'set-by-the-plugin';
		$this->transport( array(), true )->configure( $mailer );
		$this->assertSame( 'mail', $mailer->Mailer, 'our constants are ignored while a mail plugin is active' );
		$this->assertSame( 'set-by-the-plugin', $mailer->Host );
		$this->assertSame( 'plugin@example.org', $this->transport( array(), true )->mail_from( 'plugin@example.org' ) );
		$this->assertSame( 'hello@example.org', $this->transport()->mail_from( 'plugin@example.org' ) );
	}

	public function test_REQ_075_fluent_smtp_is_detected_from_the_active_plugins_and_its_settings(): void {
		$before = get_option( 'active_plugins' );
		delete_option( Transport::FLUENT_SMTP_OPTION );
		update_option( 'active_plugins', array( 'akismet/akismet.php' ) );
		$this->assertFalse( Transport::smtp_plugin_active() );
		$this->assertFalse( Transport::fluent_smtp_active() );

		update_option( 'active_plugins', array( Transport::FLUENT_SMTP ) );
		$this->assertTrue( Transport::smtp_plugin_active() );
		$this->assertTrue( Transport::fluent_smtp_active() );
		$this->assertFalse( Transport::fluent_smtp_configured(), 'active but with no connection yet' );
		update_option( Transport::FLUENT_SMTP_OPTION, array( 'connections' => array( 'abc' => array( 'provider_settings' => array() ) ) ) );
		$this->assertTrue( Transport::fluent_smtp_configured() );
		$this->assertStringContainsString( 'page=fluent-mail', Transport::fluent_smtp_url() );

		update_option( 'active_plugins', array( 'wp-mail-smtp/wp_mail_smtp.php' ) );
		$this->assertTrue( Transport::smtp_plugin_active(), 'other SMTP plugins are respected too' );

		update_option( 'active_plugins', $before );
		delete_option( Transport::FLUENT_SMTP_OPTION );
	}

	public function test_REQ_075_a_stale_credentials_missing_flag_from_the_old_rule_is_cleared(): void {
		update_option( Transport::OPTION_ERROR, 'smtp_credentials_missing', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		$this->bare()->notice();
		$this->assertSame( '', (string) ob_get_clean() );
		$this->assertFalse( get_option( Transport::OPTION_ERROR ) );

		update_option( Transport::OPTION_ERROR, 'send_failed', false );
		ob_start();
		$this->bare()->notice();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'could not be sent', $html );
		$this->assertStringContainsString( 'FluentSMTP', $html );
		$this->assertStringNotContainsString( 'secrets file', $html );
		delete_option( Transport::OPTION_ERROR );
		wp_set_current_user( 0 );
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
