<?php
/**
 * List fields keep accents, quotes and backslashes through Edit profile, Add profile and the import, and the repair migration
 * mends values an earlier release stored without their backslashes (issue 806; REQ-139, REQ-143, REQ-125, REQ-209, REQ-237).
 * Needs the WordPress test suite and the real tables, so it is a plain TestCase that cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\Admin\AddProfileScreen;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\Fields\ListMetaRepair;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ListFieldBackslashTest extends TestCase {

	/**
	 * Awards with an accent, inner quotes, a backslash and a character outside the BMP.
	 */
	private const AWARDS = array(
		'Prix littéraire de test',
		'Ordre du Mérite "Grand"',
		'Path C:\\Awards\\2026',
		'Médaille d’or 🎖',
	);

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
	 * @var array<int, array{0: int, 1: string}>
	 */
	private array $terms = array();

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
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( RecordMap::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		foreach ( $this->terms as list( $term, $taxonomy ) ) {
			wp_delete_term( $term, $taxonomy );
		}
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->posts = array();
		$this->users = array();
		$this->terms = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'slash_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	private function person(): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Slash test person',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'individual' ) );
		return $id;
	}

	/**
	 * The stored list, decoded.
	 *
	 * @param int $id Profile.
	 * @return mixed
	 */
	private function awards( int $id ) {
		return json_decode( (string) get_post_meta( $id, 'wiw_published_awards', true ), true );
	}

	public function test_REQ_125_edit_profile_keeps_accents_quotes_and_backslashes_in_a_list(): void {
		$id     = $this->person();
		$result = ( new ProfileEditor() )->edit( $id, array( 'published_awards' => self::AWARDS ), 'Add awards', $this->user( 'wiw_editor' ) );
		$this->assertTrue( $result['applied'] );
		$this->assertSame( self::AWARDS, $this->awards( $id ) );
		$this->assertSame( self::AWARDS, ( new ProfileEditor() )->current_values( $id )['published_awards'] );
	}

	public function test_REQ_125_a_name_with_a_backslash_and_quotes_is_kept(): void {
		$id = $this->person();
		( new ProfileEditor() )->edit( $id, array( 'name' => 'Zoë "Zed" Back\\slash' ), 'Rename', $this->user( 'wiw_editor' ) );
		$this->assertSame( 'Zoë "Zed" Back\\slash', get_post( $id )->post_title );
	}

	public function test_REQ_209_add_profile_keeps_accents_quotes_and_backslashes_in_a_list(): void {
		$editor = $this->user( 'wiw_editor' );
		wp_set_current_user( $editor );
		$country = wp_insert_term( 'Slashland ' . wp_generate_password( 6, false ), Taxonomies::PLACE );
		$this->assertIsArray( $country );
		update_term_meta( (int) $country['term_id'], Taxonomies::META_KIND, Taxonomies::KIND_COUNTRY );
		$this->terms[] = array( (int) $country['term_id'], Taxonomies::PLACE );
		$result        = ( new AddProfileScreen() )->process(
			new Request(
				array(
					'entity_type'        => 'individual',
					'name'               => 'Amélie Slashperson ' . wp_generate_password( 6, false, false ),
					'country'            => (string) $country['term_id'],
					'f_published_awards' => implode( "\n", self::AWARDS ),
					'sources'            => 'https://example.org/slash-source',
				)
			)
		);
		$this->assertNotEmpty( $result['profile_id'] ?? null, (string) wp_json_encode( $result['errors'] ?? array() ) );
		$id            = (int) $result['profile_id'];
		$this->posts[] = $id;
		$this->assertSame( self::AWARDS, $this->awards( $id ) );
		$this->assertStringStartsWith( 'Amélie', get_the_title( $id ) );
	}

	public function test_REQ_237_the_import_keeps_accents_quotes_and_backslashes_in_a_list(): void {
		$record                     = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/import/worked-example.json' ), true );
		$provenance                 = $record['fields']['name']['provenance'];
		$record['record_id']        = 'rec_slash_' . bin2hex( random_bytes( 4 ) );
		$record['entity_type']      = 'individual';
		$record['external_ids']     = array();
		$record['contract_version'] = '1.1';
		$record['images']           = array();
		$record['fields']           = array(
			'name'             => array(
				'value'      => 'Kwamé "K" Importé',
				'provenance' => $provenance,
			),
			'published_awards' => array(
				'value'      => self::AWARDS,
				'provenance' => $provenance,
			),
		);
		$result                     = ( new ProfileApplier( new Suppression( AuditLogger::for_site() ) ) )->apply( $record, 'B-slash', $this->user( 'wiw_editor' ), true );
		$this->assertSame( ProfileApplier::CREATED, $result['code'] );
		$id            = (int) $result['profile_id'];
		$this->posts[] = $id;
		$this->assertSame( self::AWARDS, $this->awards( $id ) );
		$this->assertSame( 'Kwamé "K" Importé', get_post( $id )->post_title );
	}

	public function test_REQ_139_the_repair_mends_the_exact_corrupt_example(): void {
		$result = ListMetaRepair::repair( '["Prix littu00e9raire de test","Ordre du Mu00e9rite "Grand""]' );
		$this->assertSame( ListMetaRepair::REPAIRED, $result['status'] );
		$this->assertSame( array( 'Prix littéraire de test', 'Ordre du Mérite "Grand"' ), json_decode( $result['value'], true ) );
		$this->assertSame( '["Prix littéraire de test","Ordre du Mérite \"Grand\""]', wp_json_encode( json_decode( $result['value'], true ), JSON_UNESCAPED_UNICODE ) );
	}

	public function test_REQ_139_the_repair_mends_surrogate_pairs_and_surviving_backslashes(): void {
		// What 0.1.0 stored for array( 'Path C:\Awards', 'Médaille 🎖' ): the backslash pair became one, every other escape lost its backslash.
		$result = ListMetaRepair::repair( '["Path C:\\Awards","Mu00e9daille ud83cudf96"]' );
		$this->assertSame( ListMetaRepair::REPAIRED, $result['status'] );
		$this->assertSame( array( 'Path C:\\Awards', 'Médaille 🎖' ), json_decode( $result['value'], true ) );
	}

	public function test_REQ_139_the_repair_leaves_healthy_and_unrecoverable_values_alone(): void {
		$healthy = (string) wp_json_encode( self::AWARDS );
		$this->assertSame(
			array(
				'status' => ListMetaRepair::OK,
				'value'  => $healthy,
			),
			ListMetaRepair::repair( $healthy )
		);
		$this->assertSame( ListMetaRepair::OK, ListMetaRepair::repair( '["Plain award","Another"]' )['status'] );
		$this->assertSame( ListMetaRepair::OK, ListMetaRepair::repair( '[]' )['status'] );
		$this->assertSame( ListMetaRepair::OK, ListMetaRepair::repair( '["Cafu0041 is ASCII and stays"]' )['status'], 'an ASCII code is not an escape that lost its backslash' );
		$this->assertSame( ListMetaRepair::OK, ListMetaRepair::repair( '["lone ud83d surrogate"]' )['status'], 'a lone surrogate is not text' );
		foreach ( array( '{"a":1}', 'not json', '["unterminated' ) as $bad ) {
			$this->assertSame(
				array(
					'status' => ListMetaRepair::UNRECOVERABLE,
					'value'  => $bad,
				),
				ListMetaRepair::repair( $bad ),
				$bad
			);
		}
	}

	public function test_REQ_139_the_migration_repairs_stored_values_once_and_counts_them(): void {
		global $wpdb;
		$corrupt = $this->person();
		$healthy = $this->person();
		$broken  = $this->person();
		// Write the stored text directly: this is what the database holds after the bug, so no slashing layer may touch it.
		$rows = array(
			$corrupt => '["Prix littu00e9raire de test","Ordre du Mu00e9rite "Grand""]',
			$healthy => (string) wp_json_encode( array( 'Prix littéraire' ) ),
			$broken  => '["unterminated',
		);
		foreach ( $rows as $id => $value ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->postmeta,
				array(
					'post_id'    => $id,
					'meta_key'   => 'wiw_published_awards', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			wp_cache_delete( $id, 'post_meta' );
		}
		$migration = require dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations/2026112101_repair_list_meta.php';
		$repair    = new ListMetaRepair();
		$first     = $repair->run();
		$this->assertGreaterThanOrEqual( 3, $first['checked'] );
		$this->assertGreaterThanOrEqual( 1, $first['repaired'] );
		$this->assertGreaterThanOrEqual( 1, $first['unrecoverable'] );
		$this->assertSame( array( 'Prix littéraire de test', 'Ordre du Mérite "Grand"' ), $this->awards( $corrupt ) );
		$this->assertSame( array( 'Prix littéraire' ), $this->awards( $healthy ) );
		$this->assertSame( '["unterminated', get_post_meta( $broken, 'wiw_published_awards', true ), 'an unrecoverable value is left as it is' );

		$migration->up(); // Idempotent: a second run (here, the migration itself) repairs nothing more.
		$second = $repair->run();
		$this->assertSame( 0, $second['repaired'] );
		$this->assertSame( array( 'Prix littéraire de test', 'Ordre du Mérite "Grand"' ), $this->awards( $corrupt ) );
		$this->assertSame( '2026112101', $migration->version() );
	}
}
