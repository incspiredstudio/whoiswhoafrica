<?php
/**
 * The registry of personal-data exporters and erasers (REQ-059, REQ-063, REQ-064). Needs the WordPress test suite and the real
 * tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Privacy\PersonalData;
use WhoIsWho\Core\Compliance\Privacy\PrivacyHandler;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\WpTestCase;

final class PersonalDataTest extends WpTestCase {

	private const EMAIL = 'requester.zq@example.test';
	private const OTHER = 'other.zq@example.test';

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		foreach ( array( 'wiw_support_items', 'wiw_profile_claims', 'wiw_signed_links', 'wiw_email_events' ) as $table ) {
			$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( 0 );
		PersonalData::hooks();
	}

	private function seed( string $email ): void {
		global $wpdb;
		$now = '2026-11-12 10:00:00';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wiw_support_items',
			array(
				'reference'       => 'SUP-' . substr( md5( $email ), 0, 6 ),
				'item_type'       => 'enquiry',
				'requester_name'  => 'Reggie Requester',
				'requester_email' => $email,
				'requester_phone' => '+2348031234567',
				'organisation'    => 'Requester Ltd',
				'message'         => 'Please list my company.',
				'ip_hash'         => str_repeat( 'a', 64 ),
				'ack_due_at'      => $now,
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wiw_profile_claims',
			array(
				'reference'    => 'CLM-' . substr( md5( $email ), 0, 6 ),
				'profile_id'   => 1,
				'relationship' => 'owner',
				'name'         => 'Reggie Requester',
				'role'         => 'Founder',
				'work_email'   => $email,
				'phone'        => '+2348031234567',
				'evidence'     => 'I run the company.',
				'received_at'  => $now,
				'decide_by_at' => $now,
			)
		);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wiw_signed_links',
			array(
				'token_hash'   => hash( 'sha256', $email ),
				'purpose'      => 'verify_requester',
				'subject_type' => 'support_item',
				'subject_id'   => '1',
				'email'        => $email,
				'created_at'   => $now,
				'expires_at'   => $now,
			)
		);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wiw_email_events',
			array(
				'message_id'    => 'm-' . substr( md5( $email ), 0, 6 ),
				'event'         => 'enquiry_received',
				'recipient_ref' => Notifier::for_site()->recipient_ref( $email ),
				'type'          => 'sent',
				'result'        => 'sent',
				'created_at'    => $now,
			)
		);
	}

	/**
	 * Run every registered exporter for an address.
	 *
	 * @param string $email Address.
	 * @return array<string, array<int, array<string, mixed>>> Exporter key => exported items.
	 */
	private function export_all( string $email ): array {
		$out = array();
		foreach ( apply_filters( 'wp_privacy_personal_data_exporters', array() ) as $key => $exporter ) {
			$result = call_user_func( $exporter['callback'], $email, 1 );
			if ( array() !== $result['data'] ) {
				$out[ $key ] = $result['data'];
			}
		}
		return $out;
	}

	private function rows( string $table, string $where = '1=1' ): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . $table . ' WHERE ' . $where ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	public function test_REQ_059_data_request_export_lists_all_pii_for_email(): void {
		$this->seed( self::EMAIL );
		$this->seed( self::OTHER );
		$export = $this->export_all( self::EMAIL );
		foreach ( array( 'wiw-support-items', 'wiw-claims', 'wiw-signed-links', 'wiw-email-log' ) as $key ) {
			$this->assertArrayHasKey( $key, $export, $key . ' is exported' );
			$this->assertCount( 1, $export[ $key ], 'one row for the address, not the other one' );
		}
		$json = (string) wp_json_encode( $export );
		$this->assertStringContainsString( 'Please list my company.', $json );
		$this->assertStringContainsString( 'I run the company.', $json );
		$this->assertStringContainsString( self::EMAIL, $json );
		$this->assertStringNotContainsString( self::OTHER, $json, 'only the requester\'s own rows' );
		$this->assertStringNotContainsString( str_repeat( 'a', 64 ), $json, 'hashes are never exported' );
		$this->assertStringNotContainsString( Notifier::for_site()->recipient_ref( self::EMAIL ), $json );
		$this->assertSame( array(), $this->export_all( 'nobody@example.test' ) );
	}

	public function test_REQ_059_erase_removes_or_anonymises_records(): void {
		$this->seed( self::EMAIL );
		$this->seed( self::OTHER );
		$removed = array();
		foreach ( apply_filters( 'wp_privacy_personal_data_erasers', array() ) as $key => $eraser ) {
			$result = call_user_func( $eraser['callback'], self::EMAIL, 1 );
			if ( $result['items_removed'] ) {
				$removed[] = $key;
			}
		}
		foreach ( array( 'wiw-support-items', 'wiw-claims', 'wiw-signed-links', 'wiw-email-log' ) as $key ) {
			$this->assertContains( $key, $removed );
		}
		$this->assertSame( 1, $this->rows( 'wiw_support_items', "requester_email = '[redacted]' AND message = '[redacted]' AND ip_hash IS NULL" ), 'kept for the case, with the person removed' );
		$this->assertSame( 1, $this->rows( 'wiw_support_items', "requester_email = '" . self::OTHER . "'" ) );
		$this->assertSame( 1, $this->rows( 'wiw_profile_claims', "work_email = '[redacted]' AND evidence = '[redacted]'" ) );
		$this->assertSame( 1, $this->rows( 'wiw_profile_claims', "work_email = '" . self::OTHER . "'" ) );
		$this->assertSame( 0, $this->rows( 'wiw_signed_links', "email = '" . self::EMAIL . "'" ) );
		$this->assertSame( 1, $this->rows( 'wiw_signed_links', "email = '" . self::OTHER . "'" ) );
		$this->assertSame( 1, $this->rows( 'wiw_email_events' ), 'only the other address stays in the send log' );

		$again = 0;
		foreach ( apply_filters( 'wp_privacy_personal_data_erasers', array() ) as $key => $eraser ) {
			if ( 0 === strpos( $key, 'wiw-' ) && ! in_array( $key, array( 'wiw-nominations' ), true ) ) {
				$again += (int) call_user_func( $eraser['callback'], self::EMAIL, 1 )['items_removed'];
			}
		}
		$this->assertSame( 0, $again, 'a second erase finds nothing' );
	}

	public function test_REQ_059_every_catalogue_table_has_export_and_erase_handler(): void {
		$this->assertSame( array(), PersonalData::coverage_gaps(), 'every personal-data table is handled or explained' );

		// A new table that nobody handles is reported.
		$catalogue = new PiiCatalogue(
			array(
				array(
					'kind'      => 'column',
					'name'      => 'wiw_widgets.email',
					'class'     => 'pii',
					'retention' => 'case_life',
					'export'    => 'include',
					'erase'     => 'delete',
					'note'      => '',
				),
			)
		);
		$this->assertSame( array( 'wiw_widgets' ), PersonalData::coverage_gaps( $catalogue ) );

		// A module's own handler closes the gap through the filter.
		$handler = new class() implements PrivacyHandler {
			/**
			 * Id.
			 */
			public function id(): string {
				return 'widgets';
			}

			/**
			 * Label.
			 */
			public function label(): string {
				return 'Widgets';
			}

			/**
			 * Tables.
			 *
			 * @return string[]
			 */
			public function tables(): array {
				return array( 'wiw_widgets' );
			}

			/**
			 * Export.
			 *
			 * @param string $email Address.
			 * @return array<int, array{item_id: string, data: array<int, array{name: string, value: string}>}>
			 */
			public function export( string $email ): array {
				unset( $email );
				return array();
			}

			/**
			 * Erase.
			 *
			 * @param string $email Address.
			 */
			public function erase( string $email ): int {
				unset( $email );
				return 0;
			}
		};
		add_filter( 'wiw_privacy_handlers', static fn ( $handlers ) => array_merge( (array) $handlers, array( $handler ) ) );
		$this->assertSame( array(), PersonalData::coverage_gaps( $catalogue ) );
		$this->assertArrayHasKey( 'wiw-widgets', apply_filters( 'wp_privacy_personal_data_exporters', array() ) );
	}
}
