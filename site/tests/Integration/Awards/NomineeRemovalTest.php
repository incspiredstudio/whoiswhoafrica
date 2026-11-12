<?php
/**
 * An upheld removal hides the profile's nominations (REQ-198, REQ-147, REQ-207). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NomineeRemoval;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\WpTestCase;

final class NomineeRemovalTest extends WpTestCase {

	private const PROBE = 'wiw_nominee_removal_audit_probe';

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		foreach ( AwardsTables::definitions() as $name => $definition ) {
			Schema::create_table( $name, $definition );
		}
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function listener(): NomineeRemoval {
		return new NomineeRemoval( new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00' ) );
	}

	private function nomination( ?int $profile, string $status = 'live' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id' => 1,
				'profile_id'  => $profile,
				'status'      => $status,
				'created_at'  => '2026-11-12 10:00:00',
				'updated_at'  => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function status_of( int $id ): string {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	public function test_REQ_198_upheld_removal_of_a_nominee_hides_the_nomination(): void {
		$mine  = $this->nomination( 501 );
		$other = $this->nomination( 502 );
		$staff = $this->nomination( null );

		$hidden = $this->listener()->hide_for_profile( 501, 77 );

		$this->assertSame( 1, $hidden );
		$this->assertSame( 'not_live', $this->status_of( $mine ) );
		$this->assertSame( 'live', $this->status_of( $other ) );
		$this->assertSame( 'live', $this->status_of( $staff ) );
	}

	public function test_REQ_198_hiding_is_audited_with_ids_only_and_logged_as_a_removal_change(): void {
		global $wpdb;
		$id = $this->nomination( 503 );

		$this->listener()->hide_for_profile( 503, 78 );

		$rows = (array) $wpdb->get_results( 'SELECT action, subject_id, new_value FROM ' . $wpdb->prefix . self::PROBE, ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertCount( 1, $rows );
		$this->assertSame( 'nomination.removal_hidden', $rows[0]['action'] );
		$this->assertSame( (string) $id, $rows[0]['subject_id'] );
		$this->assertStringContainsString( '78', (string) $rows[0]['new_value'] );
		$change = $wpdb->get_var( $wpdb->prepare( 'SELECT change_type FROM ' . AwardsTables::table( AwardsTables::CHANGES ) . ' WHERE nomination_id = %d', $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( 'removal', $change );
	}

	public function test_REQ_207_removed_nominee_never_reappears_even_if_hiding_runs_twice(): void {
		$id = $this->nomination( 504 );
		$this->listener()->hide_for_profile( 504, 79 );

		$this->assertSame( 0, $this->listener()->hide_for_profile( 504, 79 ) );
		$this->assertSame( 'not_live', $this->status_of( $id ) );
	}

	public function test_REQ_198_listener_runs_on_the_removal_upheld_event(): void {
		$id = $this->nomination( 505 );
		$this->listener()->register();

		do_action( 'wiw_removal_upheld', 505, 80 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- the plugin's own event.

		$this->assertSame( 'not_live', $this->status_of( $id ) );
		remove_all_actions( 'wiw_removal_upheld' );
	}
}
