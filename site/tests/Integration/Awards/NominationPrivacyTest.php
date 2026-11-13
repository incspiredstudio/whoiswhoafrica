<?php
/**
 * Nomination personal data: the Contact email in the PII catalogue, never exported or logged, erased and retained (REQ-268,
 * REQ-064, REQ-059, REQ-063). Needs the WordPress test suite and the real awards and audit tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NominationPrivacy;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\WpTestCase;

final class NominationPrivacyTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( 0 );
	}

	private function nomination( string $created = '2026-11-12 10:00:00', string $nominator = 'nora.nominator@example.test', string $contact = 'press.contact@example.test' ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => 'Digital Pioneer',
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		$category = (int) $wpdb->insert_id;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::NOMINATIONS ),
			array(
				'category_id'       => $category,
				'reference'         => 'NOM-' . strtoupper( substr( md5( $created . $nominator . $contact ), 0, 6 ) ),
				'nominator_name'    => 'Nora Nominator',
				'nominator_email'   => $nominator,
				'contact_email'     => $contact,
				'nominee_name'      => 'Ada Obi',
				'nominee_org'       => 'Obi Labs',
				'relationship_note' => 'Worked together in 2019',
				'evidence'          => 'Built the national payments switch.',
				'stage'             => 'nominated',
				'status'            => 'live',
				'source'            => 'public_form',
				'created_at'        => $created,
				'updated_at'        => $created,
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function row( int $id ): array {
		global $wpdb;
		return (array) $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
	}

	public function test_REQ_268_contact_email_is_in_the_pii_catalogue_and_erased_with_the_nomination(): void {
		$catalogue = new PiiCatalogue();
		$entry     = null;
		foreach ( $catalogue->entries() as $candidate ) {
			if ( 'wiw_nominations.contact_email' === $candidate['name'] ) {
				$entry = $candidate;
			}
		}
		$this->assertNotNull( $entry, 'the Contact email is catalogued' );
		$this->assertSame( 'exclude', $entry['export'] );
		$this->assertSame( 'delete', $entry['erase'] );

		$id    = $this->nomination();
		$other = $this->nomination( '2026-11-12 11:00:00', 'someone.else@example.test', 'other.contact@example.test' );
		$out   = ( new NominationPrivacy( AuditLogger::for_site() ) )->erase( 'press.contact@example.test' );
		$this->assertTrue( $out['items_removed'] );
		$row = $this->row( $id );
		$this->assertNull( $row['contact_email'], 'the Contact email is deleted' );
		$this->assertSame( PiiCatalogue::REDACTED, $row['nominee_name'] );
		$this->assertSame( PiiCatalogue::REDACTED, $row['evidence'] );
		$this->assertSame( 'not_live', $row['status'], 'a nominee who asked for erasure is no longer public' );
		$this->assertSame( 'other.contact@example.test', $this->row( $other )['contact_email'], 'other nominations are untouched' );

		$nominator = ( new NominationPrivacy( AuditLogger::for_site() ) )->erase( 'someone.else@example.test' );
		$this->assertTrue( $nominator['items_removed'] );
		$row = $this->row( $other );
		$this->assertNull( $row['nominator_email'] );
		$this->assertSame( PiiCatalogue::REDACTED, $row['nominator_name'] );
		$this->assertSame( 'Ada Obi', $row['nominee_name'], 'erasing a nominator leaves the nomination about the nominee' );
		$this->assertFalse( ( new NominationPrivacy( AuditLogger::for_site() ) )->erase( 'nobody@example.test' )['items_removed'] );
	}

	public function test_REQ_268_contact_email_is_not_in_public_output_exports_or_logs(): void {
		$id      = $this->nomination();
		$privacy = new NominationPrivacy( AuditLogger::for_site() );
		$byNom   = (string) wp_json_encode( $privacy->export( 'nora.nominator@example.test' ) );
		$this->assertStringContainsString( 'Ada Obi', $byNom );
		$this->assertStringContainsString( 'Worked together in 2019', $byNom, 'the nominator sees their own relationship note' );
		$this->assertStringNotContainsString( 'press.contact', $byNom, 'the Contact email is never exported' );

		$byNominee = (string) wp_json_encode( $privacy->export( 'press.contact@example.test' ) );
		$this->assertStringContainsString( 'Ada Obi', $byNominee );
		$this->assertStringNotContainsString( 'press.contact', $byNominee, 'the nominee sees no Contact email' );
		$this->assertStringNotContainsString( 'Nora', $byNominee, 'and never who nominated them' );
		$this->assertStringNotContainsString( 'nominator', strtolower( $byNominee ) );
		$this->assertSame( array(), $privacy->export( 'nobody@example.test' )['data'] );

		$privacy->erase( 'press.contact@example.test' );
		global $wpdb;
		$audit = (string) wp_json_encode( $wpdb->get_results( $wpdb->prepare( 'SELECT action, subject_id, new_value FROM ' . $wpdb->prefix . 'wiw_audit_log WHERE subject_type = %s AND subject_id = %s', 'nomination', (string) $id ), ARRAY_A ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		$this->assertStringContainsString( 'nomination.erase', $audit );
		foreach ( array( 'press.contact', 'nora.nominator', 'Ada', 'Obi' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $audit, 'the audit entry holds ids only' );
		}
	}

	public function test_REQ_268_retention_removes_the_contact_email(): void {
		$old    = $this->nomination( gmdate( 'Y-m-d H:i:s', time() - 800 * DAY_IN_SECONDS ) );
		$recent = $this->nomination( gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ), 'recent.nominator@example.test', 'recent.contact@example.test' );
		$p      = new NominationPrivacy( AuditLogger::for_site() );
		$this->assertSame( 1, $p->retain( 730, true ), 'a dry run counts and changes nothing' );
		$this->assertSame( 'press.contact@example.test', $this->row( $old )['contact_email'] );

		$this->assertSame( 1, $p->retain( 730 ) );
		$row = $this->row( $old );
		$this->assertNull( $row['contact_email'] );
		$this->assertNull( $row['nominator_email'] );
		$this->assertSame( PiiCatalogue::REDACTED, $row['nominator_name'] );
		$this->assertSame( PiiCatalogue::REDACTED, $row['evidence'] );
		$this->assertSame( 'recent.contact@example.test', $this->row( $recent )['contact_email'], 'a recent nomination is kept' );
		$this->assertSame( 0, $p->retain( 730 ), 'idempotent: a second run finds nothing' );

		add_filter( 'wiw_retention_days_nominations', static fn (): int => 10 );
		$this->assertSame( 10, NominationPrivacy::retention_days() );
		remove_all_filters( 'wiw_retention_days_nominations' );
		$this->assertSame( 730, NominationPrivacy::retention_days() );
	}

	public function test_REQ_059_nomination_exporter_and_eraser_are_registered_with_wordpress(): void {
		NominationPrivacy::hooks();
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$this->assertArrayHasKey( NominationPrivacy::GROUP, $exporters );
		$this->assertArrayHasKey( NominationPrivacy::GROUP, $erasers );
		$this->nomination();
		$result = call_user_func( $exporters[ NominationPrivacy::GROUP ]['callback'], 'nora.nominator@example.test', 1 );
		$this->assertCount( 1, $result['data'] );
		remove_all_filters( 'wp_privacy_personal_data_exporters' );
		remove_all_filters( 'wp_privacy_personal_data_erasers' );
		remove_all_actions( NominationPrivacy::RETENTION_HOOK );
	}
}
