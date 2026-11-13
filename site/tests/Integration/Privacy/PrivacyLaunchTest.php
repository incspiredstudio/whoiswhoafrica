<?php
/**
 * Pre-launch verification of the privacy controls (REQ-063, REQ-057, REQ-059, REQ-158, REQ-147; M5-OPS-09). It checks what is
 * built and states plainly what is not: the scheduled retention job does not exist yet, so its test is skipped.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Privacy;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

final class PrivacyLaunchTest extends TestCase {

	public function test_REQ_063_pii_catalogue_is_complete_and_every_entry_has_a_retention_and_erase_rule(): void {
		$catalogue = new PiiCatalogue();
		$this->assertSame( array(), $catalogue->validate(), 'every entry has kind, class, retention, export, erase and (for keep) a reason' );
		$this->assertNotEmpty( $catalogue->entries() );
		foreach ( $catalogue->personal() as $entry ) {
			$this->assertContains( $entry['erase'], array( 'delete', 'anonymise', 'keep' ), $entry['name'] );
			$this->assertNotSame( '', (string) $entry['retention'], $entry['name'] . ' names a retention class' );
		}
	}

	public function test_REQ_063_retention_job_anonymises_expired_data(): void {
		$this->markTestSkipped( 'No scheduled retention job exists yet: the catalogue names retention classes (D-051 defers the periods) and only SignedLinks::purge_spent is scheduled. Needs a follow-up build issue; see the pull request.' );
	}

	public function test_REQ_057_no_field_exists_for_the_never_stored_data_classes(): void {
		$allow = new FieldAllowlist();
		$never = array( 'home_address', 'phone', 'mobile', 'personal_email', 'family', 'spouse', 'children', 'date_of_birth', 'dob', 'birth_date', 'age', 'health', 'religion', 'politics' );
		foreach ( $never as $key ) {
			$this->assertTrue( $allow->is_forbidden( $key ), "$key is on the forbidden list" );
			$this->assertNotContains( $key, $allow->all_keys(), "$key is not an allowed profile field" );
		}
		foreach ( $allow->all_keys() as $key ) {
			$this->assertFalse( $allow->is_forbidden( $key ), "$key is both allowed and forbidden" );
		}
	}

	public function test_REQ_057_profile_tables_have_no_column_for_the_never_stored_classes(): void {
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();

		// Tables that hold profile data. Requester tables (support items, consents, subscribers, claims) hold contact details on
		// purpose and are catalogued instead.
		$like = $wpdb->esc_like( $wpdb->prefix . 'wiw_' ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s', $like ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertNotEmpty( $rows );
		$forbidden = array( 'home_address', 'date_of_birth', 'dob', 'birth_date', 'age', 'religion', 'politics', 'ethnicity', 'health', 'biometric', 'criminal_record', 'spouse', 'children' );
		$found     = array();
		foreach ( $rows as $row ) {
			$table = substr( (string) $row['t'], strlen( $wpdb->prefix ) );
			if ( 1 === preg_match( '/profile|provenance|search|import|duplicate|view_counts|review|purge/', $table ) && in_array( strtolower( (string) $row['c'] ), $forbidden, true ) ) {
				$found[] = $table . '.' . $row['c'];
			}
		}
		$this->assertSame( array(), $found, 'a profile table has a column for data that is never stored' );
	}

	public function test_REQ_158_overdue_removal_is_flagged(): void {
		// The behavioural tests are Compliance\DeadlineTrackerTest::test_REQ_158_overdue_item_shows_breach_flag and the reminder
		// job test beside it; this one keeps the job registered, which is what makes breaches flagged without a person asking.
		$code = (string) file_get_contents( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Compliance/Deadlines/DeadlineTracker.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertStringContainsString( 'recurring(', $code, 'a recurring job runs the deadline check' );
		$this->assertStringContainsString( 'BREACH', $code );
	}

	public function test_REQ_147_suppressed_person_not_reimported(): void {
		// Behavioural coverage: Compliance\SuppressionTest::test_REQ_207_suppression_cannot_be_bypassed_by_import. Here: the apply
		// step still asks the suppression list (a removal of that call fails this test).
		$code = (string) file_get_contents( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Import/Apply/ProfileApplier.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertMatchesRegularExpression( '/Suppression/', $code );
	}
}
