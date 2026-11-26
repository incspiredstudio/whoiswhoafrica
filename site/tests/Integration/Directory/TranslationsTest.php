<?php
/**
 * Translating profiles into English (REQ-276, REQ-053, REQ-125, REQ-188, REQ-143, REQ-006): the translations file, the applier,
 * the batched run, the hidden original name, the English slug with its redirect, duplicate flags and the Translations screen.
 * Needs the WordPress test suite and the real tables, so it is a plain TestCase that cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Admin\ProfileEditScreen;
use WhoIsWho\Core\Directory\Admin\TermFields;
use WhoIsWho\Core\Directory\Admin\TranslationsScreen;
use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Directory\Search\SearchService;
use WhoIsWho\Core\Directory\Seo\StructuredData;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Directory\Translate\OriginalName;
use WhoIsWho\Core\Directory\Translate\TranslationApplier;
use WhoIsWho\Core\Directory\Translate\TranslationFile;
use WhoIsWho\Core\Directory\Translate\TranslationRun;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Directory\Url\ProfileSlugs;
use WhoIsWho\Core\Import\Admin\BundleStore;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Duplicates\DuplicateRepository;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class TranslationsTest extends TestCase {

	/**
	 * Arabic name of the test profile ("Akon", in Arabic script), and the English one.
	 */
	private const ARABIC  = "\u{625}\u{64a}\u{643}\u{648}\u{646} \u{62a}\u{62c}\u{631}\u{628}\u{629}";
	private const ENGLISH = 'Akon Test Qtr';

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	/**
	 * Terms to remove.
	 *
	 * @var int[]
	 */
	private array $terms = array();

	/**
	 * Temporary folder for stored files.
	 *
	 * @var string
	 */
	private string $root = '';

	/**
	 * Highest audit row id before the test acted.
	 *
	 * @var int
	 */
	private int $audit_floor = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		if ( ! taxonomy_exists( Taxonomies::PLACE ) ) {
			( new Taxonomies() )->register();
		}
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		delete_option( TranslationRun::OPTION );
		delete_option( TranslationRun::LOCK );
		$this->root = rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ) . '/wiw-translations-' . bin2hex( random_bytes( 4 ) );
		global $wpdb;
		$this->audit_floor = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wiw_audit_log" ); // phpcs:ignore WordPress.DB
		wp_set_current_user( $this->user( 'administrator' ) );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			( new RecordMap() )->unlink_profile( $id );
			$wpdb->delete( DuplicateRepository::table(), array( 'profile_a' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( DuplicateRepository::table(), array( 'profile_b' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( SearchIndexer::table(), array( 'item_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->terms as $id ) {
			wp_delete_term( $id, Taxonomies::PLACE );
		}
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		( new TranslationRun( $this->root ) )->discard();
		foreach ( (array) glob( $this->root . '/{,.}*', GLOB_BRACE ) as $file ) {
			if ( is_file( (string) $file ) ) {
				unlink( (string) $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test temp folder.
			}
		}
		if ( is_dir( $this->root ) ) {
			rmdir( $this->root ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test temp folder.
		}
		delete_option( TranslationRun::OPTION );
		$this->posts = array();
		$this->users = array();
		$this->terms = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'tr_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * A public organisation with an Arabic name and a sourced bio, linked to a record id.
	 *
	 * @param string $name    Name.
	 * @param string $bio     Bio.
	 * @param string $record  Record id.
	 * @param int    $country Main country term id.
	 */
	private function profile( string $name, string $bio, string $record, int $country = 0 ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		update_post_meta( $id, 'wiw_org_type', 'Company' );
		( new ProfileFields() )->set( $id, 'bio', $bio );
		( new ProvenanceRepository() )->record(
			$id,
			array(
				'field'        => 'bio',
				'source_url'   => 'https://www.example.org/entry',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
			)
		);
		if ( $country > 0 ) {
			update_post_meta( $id, Classification::PRIMARY_COUNTRY_META, (string) $country );
		}
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		( new RecordMap() )->link( $record, $id );
		( new SearchIndexer() )->upsert( $id );
		return $id;
	}

	/**
	 * The item for the standard test profile.
	 *
	 * @param string $record Record id.
	 * @param string $bio_old Current bio.
	 * @return array<string, mixed>
	 */
	private function item( string $record, string $bio_old ): array {
		return array(
			'record_id'     => $record,
			'profile_id'    => 0,
			'original_name' => self::ARABIC,
			'original_lang' => 'ar',
			'fields'        => array(
				'name' => array(
					'old' => self::ARABIC,
					'new' => self::ENGLISH,
				),
				'bio'  => array(
					'old' => $bio_old,
					'new' => self::ENGLISH . ' is a fictional company.',
				),
			),
		);
	}

	private function record(): string {
		return 'wd:Q9' . random_int( 100000, 999999 );
	}

	/**
	 * Audit rows written since the test began.
	 *
	 * @param string $action Action.
	 * @return array<int, array<string, mixed>>
	 */
	private function audits( string $action ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wiw_audit_log WHERE id > %d AND action = %s", $this->audit_floor, $action ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_276_applies_name_and_bio_keeps_the_original_and_writes_a_version_an_audit_and_ai_provenance(): void {
		$record = $this->record();
		$bio    = self::ARABIC . ' is a fictional company.';
		$id     = $this->profile( self::ARABIC, $bio, $record );
		$out    = ( new TranslationApplier() )->apply( $this->item( $record, $bio ), get_current_user_id(), 'test.json' );

		$this->assertSame( TranslationApplier::APPLIED, $out['outcome'] );
		$this->assertSame( array( 'name', 'bio' ), $out['apply'] );
		$this->assertSame( self::ENGLISH, get_post_field( 'post_title', $id, 'raw' ) );
		$this->assertSame( self::ENGLISH . ' is a fictional company.', get_post_meta( $id, 'wiw_bio', true ) );
		$this->assertSame(
			array(
				'name' => self::ARABIC,
				'lang' => 'ar',
			),
			OriginalName::get( $id )
		);
		$reasons = array_map( static fn ( array $row ): string => (string) $row['reason'], ( new VersionRepository() )->for_profile( $id ) );
		$this->assertContains( 'Translated into English from Arabic (issue 885, file test.json)', $reasons, 'a version row names the translation' );
		$this->assertCount( 1, array_filter( $this->audits( 'profile.edit' ), static fn ( array $row ): bool => (int) $row['subject_id'] === $id ) );
		foreach ( array( 'name', 'bio' ) as $field ) {
			$rows = ( new ProvenanceRepository() )->for_profile( $id, $field );
			$row  = end( $rows );
			$this->assertIsArray( $row );
			$this->assertSame( 'ai', $row['method'], "the $field is marked as an AI translation" );
			$this->assertSame( TranslationApplier::PROVENANCE_SOURCE, $row['source_url'] );
		}
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		$this->assertSame( self::ENGLISH . ' is a fictional company.', $profile->facts['bio']['value'] ?? null, 'the translated bio still counts as sourced' );
	}

	public function test_REQ_276_search_finds_the_profile_by_its_original_name_and_shows_the_english_name(): void {
		$record = $this->record();
		$bio    = self::ARABIC . ' is a fictional company.';
		$id     = $this->profile( self::ARABIC, $bio, $record );
		( new TranslationApplier() )->apply( $this->item( $record, $bio ), get_current_user_id(), 'test.json' );

		$search = new SearchService();
		foreach ( array( self::ARABIC, self::ENGLISH ) as $query ) {
			$found = array_values( array_filter( $search->search( $query )['profiles'], static fn ( array $row ): bool => (int) $row['id'] === $id ) );
			$this->assertCount( 1, $found, 'found by ' . ( self::ENGLISH === $query ? 'the English' : 'the original' ) . ' name' );
			$this->assertSame( self::ENGLISH, $found[0]['name'], 'the result shows the English name' );
		}
		global $wpdb;
		$sort = $wpdb->get_var( $wpdb->prepare( 'SELECT name_sort FROM ' . SearchIndexer::table() . ' WHERE item_type = %s AND item_id = %d', SearchIndexer::ITEM_TYPE, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		$this->assertSame( strtolower( self::ENGLISH ), $sort, 'results are ordered by the English name' );
	}

	public function test_REQ_276_a_field_changed_since_the_file_was_made_is_left_alone(): void {
		$record = $this->record();
		$id     = $this->profile( self::ARABIC, 'A bio a reviewer rewrote.', $record );
		$out    = ( new TranslationApplier() )->apply( $this->item( $record, self::ARABIC . ' is a fictional company.' ), get_current_user_id(), 'test.json' );

		$this->assertSame( TranslationApplier::PARTLY, $out['outcome'] );
		$this->assertSame( array( 'name' ), $out['apply'] );
		$this->assertSame( array( 'bio' ), $out['changed'] );
		$this->assertSame( 'A bio a reviewer rewrote.', get_post_meta( $id, 'wiw_bio', true ) );
		$this->assertSame( self::ENGLISH, get_post_field( 'post_title', $id, 'raw' ) );
	}

	public function test_REQ_276_applying_the_same_item_again_changes_nothing(): void {
		$record  = $this->record();
		$bio     = self::ARABIC . ' is a fictional company.';
		$id      = $this->profile( self::ARABIC, $bio, $record );
		$applier = new TranslationApplier();
		$applier->apply( $this->item( $record, $bio ), get_current_user_id(), 'test.json' );
		$before = count( ( new VersionRepository() )->for_profile( $id ) );

		$again = $applier->apply( $this->item( $record, $bio ), get_current_user_id(), 'test.json' );
		$this->assertSame( TranslationApplier::DONE, $again['outcome'] );
		$this->assertSame( $before, count( ( new VersionRepository() )->for_profile( $id ) ), 'no new version' );
		$this->assertSame( TranslationApplier::DONE, $applier->preview( $this->item( $record, $bio ) )['outcome'] );
		$missing = $applier->apply( $this->item( 'wd:Q1nope', $bio ), get_current_user_id(), 'test.json' );
		$this->assertSame( TranslationApplier::NOT_FOUND, $missing['outcome'] );
	}

	public function test_REQ_006_the_arabic_slug_becomes_english_and_the_old_address_redirects(): void {
		$record = $this->record();
		$bio    = self::ARABIC . ' is a fictional company.';
		$id     = $this->profile( self::ARABIC, $bio, $record );
		$old    = (string) get_post_field( 'post_name', $id );
		$this->assertStringStartsWith( '%d8', $old, 'WordPress stored the Arabic slug percent-encoded' );

		( new TranslationApplier() )->apply( $this->item( $record, $bio ), get_current_user_id(), 'test.json' );
		$new = (string) get_post_field( 'post_name', $id );
		$this->assertSame( 'akon-test-qtr', $new );
		$this->assertContains( $old, get_post_meta( $id, ProfileSlugs::OLD_SLUG_META, false ), 'the site keeps the old slug' );

		$route = new ProfileRoute();
		foreach ( array( $old, rawurldecode( $old ) ) as $asked ) {
			$result = $route->resolve( $asked );
			$this->assertSame( 'redirect', $result['action'] );
			$this->assertSame( 301, $result['status'] );
			$this->assertSame( ProfileSlugs::url_for_slug( $new ), $result['location'] );
		}
		$this->assertSame( 'serve', $route->resolve( $new )['action'] );
		$this->assertNotEmpty( $this->audits( 'profile.slug_change' ) );
	}

	public function test_REQ_276_a_list_term_is_renamed_and_keeps_its_old_slug(): void {
		$made = wp_insert_term( "\u{646}\u{627}\u{643}\u{648}\u{631}\u{648} Qtr", Taxonomies::PLACE );
		$this->assertIsArray( $made );
		$this->terms[] = (int) $made['term_id'];
		$old_slug      = get_term( (int) $made['term_id'], Taxonomies::PLACE )->slug;
		$term          = array(
			'taxonomy' => Taxonomies::PLACE,
			'term_id'  => (int) $made['term_id'],
			'old'      => "\u{646}\u{627}\u{643}\u{648}\u{631}\u{648} Qtr",
			'new'      => 'Nakuru Qtr',
		);
		$applier       = new TranslationApplier();
		$this->assertSame( TranslationApplier::APPLIED, $applier->term( $term, false ), 'the preview writes nothing' );
		$this->assertSame( TranslationApplier::APPLIED, $applier->term( $term, true ) );
		$after = get_term( (int) $made['term_id'], Taxonomies::PLACE );
		$this->assertSame( 'Nakuru Qtr', $after->name );
		$this->assertSame( 'nakuru-qtr', $after->slug );
		$this->assertContains( $old_slug, array_map( 'strval', (array) get_term_meta( (int) $made['term_id'], TermFields::META_OLD_SLUGS, false ) ) );
		$this->assertSame( TranslationApplier::DONE, $applier->term( $term, true ) );
		$this->assertNotEmpty( $this->audits( 'term.translate' ) );
	}

	public function test_REQ_276_same_english_name_type_and_country_is_flagged_as_a_possible_duplicate(): void {
		$country = wp_insert_term( 'Dupland Qtr', Taxonomies::PLACE );
		$this->assertIsArray( $country );
		$this->terms[] = (int) $country['term_id'];
		$elsewhere     = wp_insert_term( 'Otherland Qtr', Taxonomies::PLACE );
		$this->assertIsArray( $elsewhere );
		$this->terms[] = (int) $elsewhere['term_id'];
		$twin          = $this->profile( 'AKON TEST QTR', 'Already in English.', $this->record(), (int) $country['term_id'] );
		$far           = $this->profile( self::ENGLISH, 'Same name, other country.', $this->record(), (int) $elsewhere['term_id'] );
		$record        = $this->record();
		$bio           = self::ARABIC . ' is a fictional company.';
		$id            = $this->profile( self::ARABIC, $bio, $record, (int) $country['term_id'] );

		$out = ( new TranslationApplier() )->apply( $this->item( $record, $bio ), get_current_user_id(), 'test.json' );
		$this->assertSame( 1, $out['duplicates'] );
		$pair = ( new DuplicateRepository() )->get( $id, $twin );
		$this->assertNotNull( $pair );
		$this->assertSame( 'open', $pair['status'] );
		$this->assertSame( TranslationApplier::DUPLICATE_BASIS, $pair['basis'] );
		$this->assertNull( ( new DuplicateRepository() )->get( $id, $far ), 'a profile in another country is not flagged' );
	}

	public function test_REQ_276_the_file_is_refused_unless_it_is_exactly_the_translations_format(): void {
		$good = array(
			'format'  => 'wiw-translations',
			'version' => 1,
			'items'   => array( $this->item( 'wd:Q1', 'x' ) ),
		);
		$this->assertCount( 1, TranslationFile::parse( (string) wp_json_encode( $good ) )['items'] );
		$bad = array(
			'not json'         => '{nope',
			'wrong format'     => wp_json_encode( array_merge( $good, array( 'format' => 'other' ) ) ),
			'wrong version'    => wp_json_encode( array_merge( $good, array( 'version' => 2 ) ) ),
			'no items'         => wp_json_encode( array_merge( $good, array( 'items' => array() ) ) ),
			'unknown field'    => wp_json_encode(
				array_merge(
					$good,
					array(
						'items' => array(
							array_merge(
								$this->item( 'wd:Q1', 'x' ),
								array(
									'fields' => array(
										'net_worth' => array(
											'old' => 'a',
											'new' => 'b',
										),
									),
								)
							),
						),
					)
				)
			),
			'list field'       => wp_json_encode(
				array_merge(
					$good,
					array(
						'items' => array(
							array_merge(
								$this->item( 'wd:Q1', 'x' ),
								array(
									'fields' => array(
										'published_awards' => array(
											'old' => 'a',
											'new' => 'b',
										),
									),
								)
							),
						),
					)
				)
			),
			'no id'            => wp_json_encode( array_merge( $good, array( 'items' => array( array_merge( $this->item( '', 'x' ), array( 'profile_id' => 0 ) ) ) ) ) ),
			'empty new name'   => wp_json_encode(
				array_merge(
					$good,
					array(
						'items' => array(
							array_merge(
								$this->item( 'wd:Q1', 'x' ),
								array(
									'fields' => array(
										'name' => array(
											'old' => 'a',
											'new' => ' ',
										),
									),
								)
							),
						),
					)
				)
			),
			'bad language'     => wp_json_encode( array_merge( $good, array( 'items' => array( array_merge( $this->item( 'wd:Q1', 'x' ), array( 'original_lang' => 'Arabic' ) ) ) ) ) ),
			'unknown taxonomy' => wp_json_encode(
				array_merge(
					$good,
					array(
						'terms' => array(
							array(
								'taxonomy' => 'category',
								'term_id'  => 1,
								'old'      => 'a',
								'new'      => 'b',
							),
						),
					)
				)
			),
			'larger than 2 MB' => str_repeat( ' ', TranslationFile::MAX_BYTES + 1 ),
		);
		foreach ( $bad as $why => $json ) {
			try {
				TranslationFile::parse( (string) $json );
				$this->fail( 'Accepted a file with ' . $why );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertNotSame( '', $e->getMessage(), $why );
			}
		}
	}

	public function test_REQ_276_a_run_previews_then_applies_in_batches_with_one_audit_row_and_can_be_rerun(): void {
		$items = array();
		for ( $n = 0; $n < TranslationRun::BATCH + 3; $n++ ) {
			$record = $this->record();
			$name   = self::ARABIC . ' ' . $n;
			$this->profile( $name, 'Plain bio.', $record );
			$items[] = array(
				'record_id'     => $record,
				'profile_id'    => 0,
				'original_name' => $name,
				'original_lang' => 'ar',
				'fields'        => array(
					'name' => array(
						'old' => $name,
						'new' => 'Batch Company Qtr ' . $n,
					),
				),
			);
		}
		$json = (string) wp_json_encode(
			array(
				'format'  => 'wiw-translations',
				'version' => 1,
				'items'   => $items,
			)
		);
		$run  = new TranslationRun( $this->root );
		$plan = $run->start( $json, 'batch.json', get_current_user_id() );
		$this->assertSame( TranslationRun::PREVIEW, $plan['status'] );
		$this->assertSame( TranslationRun::BATCH + 3, $plan['preview'][ TranslationApplier::APPLIED ] );
		$this->assertCount( 20, $plan['sample'] );
		$this->assertFalse( BundleStore::inside_web_root( $this->root ), 'the stored file is outside the web root' );

		$first = $run->step( get_current_user_id() );
		$this->assertSame( TranslationRun::RUNNING, $first['status'] );
		$this->assertSame( TranslationRun::BATCH, $first['offset'] );
		$this->assertSame( array(), $this->audits( 'translations.apply' ), 'no summary row until the end' );
		$last = $run->step( get_current_user_id() );
		$this->assertSame( TranslationRun::DONE, $last['status'] );
		$this->assertSame( TranslationRun::BATCH + 3, $last['counts'][ TranslationApplier::APPLIED ] );
		$this->assertCount( 1, $this->audits( 'translations.apply' ) );

		$again = $run->start( $json, 'batch.json', get_current_user_id() );
		$this->assertSame( TranslationRun::BATCH + 3, $again['preview'][ TranslationApplier::DONE ], 'the same file again changes nothing' );
		$this->assertSame( 0, $again['preview'][ TranslationApplier::APPLIED ] );
	}

	public function test_REQ_276_the_screen_and_its_actions_need_wiw_run_imports(): void {
		$this->assertNull( ActionGuard::check( TranslationsScreen::STEP, TranslationsScreen::CAPABILITY, new Request( array( '_wpnonce' => ActionGuard::nonce_for( TranslationsScreen::STEP ) ) ) ) );
		$html = ( new TranslationsScreen() )->html( new Request( array() ) );
		$this->assertStringContainsString( 'name="translations"', $html );
		$this->assertStringContainsString( 'multipart/form-data', $html );

		wp_set_current_user( $this->user( 'wiw_editor' ) );
		$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( TranslationsScreen::UPLOAD, TranslationsScreen::CAPABILITY, new Request( array( '_wpnonce' => ActionGuard::nonce_for( TranslationsScreen::UPLOAD ) ) ) ) );
		$this->expectException( \DomainException::class );
		( new TranslationsScreen() )->html( new Request( array() ) );
	}

	public function test_REQ_053_the_public_page_shows_only_the_english_name_and_staff_see_the_original_on_edit_profile(): void {
		$record = $this->record();
		$bio    = self::ARABIC . ' is a fictional company.';
		$id     = $this->profile( self::ARABIC, $bio, $record );
		( new TranslationApplier() )->apply( $this->item( $record, $bio ), get_current_user_id(), 'test.json' );

		$edit = ( new ProfileEditScreen() )->html( new Request( array( 'profile_id' => $id ) ) );
		$this->assertStringContainsString( 'Original name (Arabic):', $edit );
		$this->assertStringContainsString( '<span lang="ar" dir="auto">' . esc_html( self::ARABIC ) . '</span>', $edit );

		switch_theme( 'whoiswho-child' );
		wp_set_current_user( 0 );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		ob_start();
		set_query_var( 'wiw_profile_id', $id );
		include get_template_directory() . '/templates/profile.php';
		$page = (string) ob_get_clean();
		set_query_var( 'wiw_profile_id', 0 );
		$page .= ( new StructuredData() )->render( $profile );
		$this->assertStringContainsString( self::ENGLISH, $page );
		foreach ( array( self::ARABIC, rawurlencode( self::ARABIC ), wp_json_encode( self::ARABIC ) ) as $form ) {
			$this->assertStringNotContainsString( trim( (string) $form, '"' ), $page, 'the original name is not on the public page' );
		}
		$this->assertSame( 0, preg_match( '/\p{Arabic}/u', $page ), 'no Arabic text at all on the public page' );
	}
}
