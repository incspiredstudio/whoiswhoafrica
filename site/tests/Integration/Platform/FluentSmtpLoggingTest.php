<?php
/**
 * FluentSMTP's email log stays off (REQ-233, REQ-046): its log would store every email body, signed links included. Needs the
 * WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Mail\FluentSmtpLogging;
use WhoIsWho\Tests\WpTestCase;

final class FluentSmtpLoggingTest extends WpTestCase {

	protected function tearDown(): void {
		delete_option( FluentSmtpLogging::OPTION );
		parent::tearDown();
	}

	public function test_REQ_233_fluentsmtp_email_logging_is_forced_off_whatever_was_saved(): void {
		update_option(
			FluentSmtpLogging::OPTION,
			array(
				'connections' => array( 'x' => array( 'provider_settings' => array( 'sender_email' => 'site@example.org' ) ) ),
				'misc'        => array(
					'log_emails'              => 'yes',
					'log_saved_interval_days' => '14',
				),
			)
		);
		$settings = get_option( FluentSmtpLogging::OPTION );
		$this->assertSame( 'no', $settings['misc']['log_emails'] );
		$this->assertSame( '14', $settings['misc']['log_saved_interval_days'], 'other settings are kept' );
		$this->assertArrayHasKey( 'x', $settings['connections'] );
	}

	public function test_REQ_233_settings_without_a_misc_section_get_logging_off(): void {
		update_option( FluentSmtpLogging::OPTION, array( 'connections' => array() ) );
		$this->assertSame( 'no', get_option( FluentSmtpLogging::OPTION )['misc']['log_emails'] );
	}

	public function test_REQ_233_no_fluentsmtp_settings_means_nothing_is_invented(): void {
		$this->assertFalse( get_option( FluentSmtpLogging::OPTION ) );
		$this->assertSame( 'text', FluentSmtpLogging::force( 'text' ) );
	}
}
