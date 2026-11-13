<?php
/**
 * The catalogue completeness check (REQ-063, REQ-065, REQ-059). PiiCoverageTest catches a personal-looking column name. This test
 * goes further: it scans every column the real migrations create and every registered meta key, and fails when one that can hold
 * personal data (a name or contact column, or free text) is neither in config/pii.php nor on the reviewed allowlist below. A new
 * table therefore cannot ship without someone deciding whether it holds personal data. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;
use WhoIsWho\Tests\TestCase;

final class PiiCatalogueCompletenessTest extends TestCase {

	/**
	 * Tables that hold no personal data at all, each with the reason. A table not here and not in the catalogue fails the test.
	 */
	private const NO_PERSONAL_TABLES = array(
		'wiw_award_categories'            => 'Category titles and rules, set by staff.',
		'wiw_curation_screen_audit_probe' => 'Scratch table made by a test.',
		'wiw_duplicates'                  => 'Pairs of profile ids with a staff reason.',
		'wiw_editions'                    => 'Edition wording set by staff.',
		'wiw_field_provenance'            => 'Source of a field value, by profile id.',
		'wiw_import_batches'              => 'Batch counts and settings, no records.',
		'wiw_import_results'              => 'Row numbers and codes.',
		'wiw_nomination_changes'          => 'Stage and status words with user and nomination ids.',
		'wiw_listing_consents'            => 'Profile id, decision, policy version and time; no contact data.',
		'wiw_profile_state'               => 'Publication state by profile id.',
		'wiw_purge_log'                   => 'Profile ids and cache purge reasons.',
		'wiw_record_map'                  => 'External record ids mapped to profile ids.',
		'wiw_review_decisions'            => 'Decision words and profile ids.',
		'wiw_sources'                     => 'Source labels and URLs of public records.',
		'wiw_verification'                => 'Check results by profile id.',
		'wiw_view_counts_daily'           => 'Counts by day and profile id.',
	);

	/**
	 * Text columns reviewed and judged to hold no personal data beyond what the table's catalogue entries already cover.
	 */
	private const REVIEWED_TEXT_COLUMNS = array(
		'wiw_audit_log.old_value'              => 'Audit values hold ids and field names only (REQ-188); scrubbed by the logger.',
		'wiw_audit_log.new_value'              => 'As above.',
		'wiw_audit_log.reason'                 => 'A staff reason, scrubbed by the logger.',
		'wiw_bulk_actions.filter_json'         => 'A filter on public profile fields.',
		'wiw_bulk_actions.reason'              => 'A staff reason.',
		'wiw_duplicates.reason'                => 'A staff reason.',
		'wiw_editions.wording'                 => 'Edition wording set by staff.',
		'wiw_field_provenance.source_url'      => 'URL of a public source.',
		'wiw_import_batches.counts'            => 'Counts.',
		'wiw_import_batches.settings_snapshot' => 'Settings, no records.',
		'wiw_passages.passage_text'            => 'Public source text kept for search.',
		'wiw_passages.source_url'              => 'URL of a public source.',
		'wiw_profile_search.bio'               => 'Public profile text (class public_after_approval).',
		'wiw_profile_search.industry'          => 'Public profile text.',
		'wiw_profile_search.name'              => 'Public profile text.',
		'wiw_profile_search.name_sort'         => 'Public profile text.',
		'wiw_profile_search.organisation'      => 'Public profile text.',
		'wiw_profile_search.title'             => 'Public profile text.',
		'wiw_profile_search.url'               => 'Public profile URL.',
		'wiw_profile_state.source'             => 'Source label.',
		'wiw_profile_versions.old_value'       => 'Public profile field values (class public_after_approval).',
		'wiw_profile_versions.new_value'       => 'As above.',
		'wiw_profile_versions.reason'          => 'A staff reason.',
		'wiw_sources.label'                    => 'Label of a public source.',
		'wiw_verification.checks'              => 'Check results.',
		'wiw_verification.fields_dropped'      => 'Field names.',
		'wiw_award_categories.title'           => 'Category title.',
		'wiw_nominations.nominee_org'          => 'A public organisation name (the nominee is a public figure under REQ-199).',
		'wiw_nominations.relationship_note'    => 'Declared relationship text; covered with the nomination entries.',
		'wiw_nominations.citation'             => 'Public citation text for a winner.',
		'wiw_nominations.notes'                => 'Staff notes on the nomination; covered with the nomination entries.',
		'wiw_nominations.evidence'             => 'Public evidence links for the nominee; covered with the nomination entries.',
		'wiw_notes.body'                       => 'Internal note body; covered by wiw_notes entry.',
		'wiw_support_items.organisation'       => 'Covered by its own catalogue entry.',
		'wiw_profile_claims.reason'            => 'A staff decision reason.',
	);

	/**
	 * Tables of the plugin as the database reports them, with columns: table (no prefix) => array of "column => data type".
	 *
	 * @return array<string, array<string, array{0: string, 1: int}>>
	 */
	private function schema(): array {
		global $wpdb;
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$like = $wpdb->esc_like( $wpdb->prefix . 'wiw_' ) . '%';
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT TABLE_NAME AS t, COLUMN_NAME AS c, DATA_TYPE AS d, COALESCE(CHARACTER_MAXIMUM_LENGTH, 0) AS l FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s ORDER BY TABLE_NAME, ORDINAL_POSITION', $like ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( $rows as $row ) {
			$table = substr( (string) $row['t'], strlen( $wpdb->prefix ) );
			if ( false !== strpos( $table, 'probe' ) ) {
				continue; // Scratch tables made by other tests.
			}
			$out[ $table ][ (string) $row['c'] ] = array( (string) $row['d'], (int) $row['l'] );
		}
		return $out;
	}

	public function test_REQ_063_missing_pii_column_fails_the_build(): void {
		$entries   = require dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/config/pii.php';
		$without   = array_values( array_filter( $entries, static fn ( array $e ): bool => 'wiw_customers.contact_email' !== $e['name'] ) );
		$catalogue = new PiiCatalogue( $without );
		$this->assertSame( array( 'wiw_customers.contact_email' ), $catalogue->unclassified( array( 'wiw_customers.contact_email' ) ), 'A personal column that is not listed is reported.' );
		$this->assertSame( array( 'wiw_gadgets.owner_phone' ), ( new PiiCatalogue( $entries ) )->unclassified( array( 'wiw_gadgets.owner_phone' ) ), 'A column in a table nobody catalogued is reported.' );
		$this->assertSame( array(), ( new PiiCatalogue( $entries ) )->unclassified( array( 'wiw_customers.contact_email' ) ) );
	}

	public function test_REQ_063_every_m3_personal_column_is_catalogued(): void {
		$this->requireWordPress();
		$catalogue = new PiiCatalogue();
		$expected  = array(
			// Claims.
			'wiw_profile_claims.name',
			'wiw_profile_claims.work_email',
			'wiw_profile_claims.phone',
			'wiw_profile_claims.evidence',
			// Support items (enquiries, corrections, removals, data requests).
			'wiw_support_items.requester_name',
			'wiw_support_items.requester_email',
			'wiw_support_items.requester_phone',
			'wiw_support_items.message',
			'wiw_support_items.ip_hash',
			// Customers.
			'wiw_customers.name',
			'wiw_customers.contact_email',
			'wiw_customers.contact_phone',
			'wiw_customers.notes',
			// Arrangements (financial).
			'wiw_arrangements.notes',
			'wiw_arrangements.reference',
			'wiw_arrangements.amount_minor',
			// Consents and signed links.
			'wiw_consents.email',
			'wiw_consents.email_hash',
			'wiw_consents.token_hash',
			'wiw_signed_links.email',
			// Suppression.
			'wiw_suppression.name',
			'wiw_suppression.name_key',
		);
		$this->assertSame( array(), $catalogue->missing( $expected ), 'an M3 personal column is not in config/pii.php' );
		$schema = $this->schema();
		foreach ( $expected as $name ) {
			list( $table, $column ) = explode( '.', $name );
			$this->assertArrayHasKey( $column, $schema[ $table ] ?? array(), $name . ' is catalogued but the column does not exist: the catalogue is stale.' );
		}
		foreach ( array( 'wiw_arrangements.amount_minor', 'wiw_arrangements.reference' ) as $name ) {
			$this->assertSame( 'financial', ( $catalogue->find( $name ) ?? array() )['class'] ?? '', $name . ' is marked FIN.' );
		}
	}

	public function test_REQ_063_every_table_is_catalogued_or_reviewed_as_holding_no_personal_data(): void {
		$this->requireWordPress();
		$catalogue = new PiiCatalogue();
		$tables    = array();
		foreach ( $catalogue->names( 'column' ) as $name ) {
			$tables[ explode( '.', $name )[0] ] = true;
		}
		$unreviewed = array();
		foreach ( array_keys( $this->schema() ) as $table ) {
			if ( ! isset( $tables[ $table ] ) && ! isset( self::NO_PERSONAL_TABLES[ $table ] ) ) {
				$unreviewed[] = $table;
			}
		}
		$this->assertSame( array(), $unreviewed, 'a table has no catalogue entry and is not reviewed as holding no personal data' );
	}

	public function test_REQ_063_free_text_and_long_text_columns_are_catalogued_or_reviewed(): void {
		$this->requireWordPress();
		$catalogue  = new PiiCatalogue();
		$unreviewed = array();
		foreach ( $this->schema() as $table => $columns ) {
			foreach ( $columns as $column => $type ) {
				$qualified = $table . '.' . $column;
				$free_text = in_array( $type[0], array( 'text', 'mediumtext', 'longtext' ), true ) || ( 'varchar' === $type[0] && $type[1] >= 190 );
				if ( $free_text && ! $catalogue->has( $qualified ) && ! isset( self::REVIEWED_TEXT_COLUMNS[ $qualified ] ) && ! isset( self::NO_PERSONAL_TABLES[ $table ] ) ) {
					$unreviewed[] = $qualified;
				}
			}
		}
		$this->assertSame( array(), $unreviewed, 'a free-text column is neither in config/pii.php nor reviewed in this test' );
	}

	public function test_REQ_063_registered_meta_keys_that_look_personal_are_catalogued(): void {
		$this->requireWordPress();
		$catalogue = new PiiCatalogue();
		$keys      = array();
		foreach ( array( 'post', 'user', 'comment', 'term' ) as $object ) {
			$subtypes = 'post' === $object ? get_post_types() : array( '' );
			foreach ( $subtypes as $subtype ) {
				foreach ( array_keys( get_registered_meta_keys( $object, (string) $subtype ) ) as $key ) {
					$keys[ (string) $key ] = true;
				}
			}
		}
		$missing = array();
		foreach ( array_keys( $keys ) as $key ) {
			$plain = ltrim( $key, '_' );
			if ( ( PiiCatalogue::looks_personal( $plain ) || false !== strpos( $plain, 'contact' ) ) && ! $catalogue->has( $key ) ) {
				$missing[] = $key;
			}
		}
		$this->assertSame( array(), $missing, 'a registered meta key that looks personal is not in config/pii.php' );
		$this->assertTrue( $catalogue->has( '_wiw_contact_details' ), 'The profile contact details meta key is catalogued.' );
	}
}
