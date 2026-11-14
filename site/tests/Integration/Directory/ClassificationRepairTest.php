<?php
/**
 * The upgrade check for 0.2.0 sites (REQ-009, REQ-056): the 0.2.0 demo organisation types ("Public body", "Public university",
 * "University / school", "NGO / social enterprise", "Professional association") and comma-separated raw industries are folded
 * into the right entries of the tree by 2026112103 followed by 2026112104, and the repair migration fixes a site that already
 * ran 2026112103 with the shorter alias lists, without touching what staff or the acquisition side chose. Needs the WordPress
 * test suite and the real tables; it removes what it creates.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\ClassificationSeeder;
use WhoIsWho\Core\Directory\ClassificationUpgrade;
use WhoIsWho\Core\Directory\ProfileClassifier;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ClassificationRepairTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! is_taxonomy_hierarchical( Taxonomies::INDUSTRY ) ) {
			( new Taxonomies() )->register();
		}
		( new ClassificationSeeder() )->seed();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		Classification::flush();
		parent::tearDown();
	}

	/**
	 * A published profile with raw 0.2.0 text meta.
	 *
	 * @param string                $name Name.
	 * @param string                $type Entity type.
	 * @param array<string, string> $raw  Raw text: industry, org_type, field.
	 */
	private function profile( string $name, string $type, array $raw = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => $name,
				'post_status' => ProfileType::STATUS_HIDDEN,
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => $type ) );
		foreach ( $raw as $key => $value ) {
			update_post_meta( $id, 'wiw_' . $key, $value );
		}
		return $id;
	}

	/**
	 * An old flat term of 0.2.0, created when missing.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Name.
	 */
	private function old_term( string $taxonomy, string $name ): int {
		$found = term_exists( $name, $taxonomy, 0 );
		if ( is_array( $found ) ) {
			return (int) $found['term_id'];
		}
		$made = wp_insert_term( $name, $taxonomy );
		$this->assertIsArray( $made );
		return (int) $made['term_id'];
	}

	/**
	 * The slug of a profile's organisation type, or "".
	 *
	 * @param int $id Profile.
	 */
	private function org_slug( int $id ): string {
		$terms = wp_get_object_terms( $id, Taxonomies::ORG_TYPE );
		return is_array( $terms ) && isset( $terms[0] ) ? $terms[0]->slug : '';
	}

	/**
	 * The slug of a profile's primary industry, or "".
	 *
	 * @param int $id Profile.
	 */
	private function industry_slug( int $id ): string {
		$term = get_term( (int) get_post_meta( $id, Classification::PRIMARY_INDUSTRY_META, true ), Taxonomies::INDUSTRY );
		return $term instanceof \WP_Term ? $term->slug : '';
	}

	public function test_REQ_009_old_demo_names_and_comma_lists_match_the_tree(): void {
		$this->assertSame( 'government-body', Classification::match_org_type( 'Public body' ) );
		$this->assertSame( 'university', Classification::match_org_type( 'Public university' ) );
		$this->assertSame( 'university', Classification::match_org_type( 'University / school' ) );
		$this->assertSame( 'ngo-nonprofit', Classification::match_org_type( 'NGO / social enterprise' ) );
		$this->assertSame( 'other', Classification::match_org_type( 'Professional association' ) );
		$this->assertSame( 'banking', Classification::match_industry( 'financial services, financial sector' ) );
		$this->assertSame( 'telecoms', Classification::match_industry( 'telecommunications industry, mobile networks' ) );
		$this->assertSame( 'telecoms', Classification::match_industry( 'Qwv unknown thing; Telecommunications' ), 'The first part that matches wins.' );
		$this->assertSame( 'film-tv', Classification::match_industry( 'Film, theatre and broadcasting' ), 'A whole alias with a comma still matches as a whole.' );
		$this->assertNull( Classification::match_industry( 'Qwv one, Qwv two' ) );
	}

	public function test_REQ_009_a_0_2_0_site_is_folded_into_the_tree_by_both_migrations(): void {
		$public_body = $this->old_term( Taxonomies::ORG_TYPE, 'Public body' );
		$public_uni  = $this->old_term( Taxonomies::ORG_TYPE, 'Public university' );
		$uni_school  = $this->old_term( Taxonomies::ORG_TYPE, 'University / school' );
		$ngo         = $this->old_term( Taxonomies::ORG_TYPE, 'NGO / social enterprise' );
		$assoc       = $this->old_term( Taxonomies::ORG_TYPE, 'Professional association' );
		$public_sec  = $this->old_term( Taxonomies::INDUSTRY, 'Public Sector' );

		$body = $this->profile( 'Repair Body Qwv', 'institution', array( 'org_type' => 'Public body' ) );
		wp_set_object_terms( $body, array( $public_body ), Taxonomies::ORG_TYPE );
		wp_set_object_terms( $body, array( $public_sec ), Taxonomies::INDUSTRY );
		$uni = $this->profile( 'Repair Uni Qwv', 'institution', array( 'org_type' => 'Public university' ) );
		wp_set_object_terms( $uni, array( $public_uni ), Taxonomies::ORG_TYPE );
		$school = $this->profile( 'Repair School Qwv', 'institution', array( 'org_type' => 'University / school' ) );
		wp_set_object_terms( $school, array( $uni_school ), Taxonomies::ORG_TYPE );
		$charity = $this->profile( 'Repair Ngo Qwv', 'institution', array( 'org_type' => 'NGO / social enterprise' ) );
		wp_set_object_terms( $charity, array( $ngo ), Taxonomies::ORG_TYPE );
		$guild = $this->profile( 'Repair Assoc Qwv', 'institution', array( 'org_type' => 'Professional association' ) );
		wp_set_object_terms( $guild, array( $assoc ), Taxonomies::ORG_TYPE );
		$bank  = $this->profile( 'Repair Bank Qwv', 'organisation', array( 'industry' => 'financial services, financial sector' ) );
		$phone = $this->profile( 'Repair Phone Qwv', 'organisation', array( 'industry' => 'telecommunications industry, mobile networks' ) );

		( new ClassificationUpgrade() )->run();
		( new ClassificationUpgrade() )->repair();

		$this->assertSame( 'government-body', $this->org_slug( $body ) );
		$this->assertSame( 'government', $this->industry_slug( $body ) );
		$this->assertSame( 'university', $this->org_slug( $uni ) );
		$this->assertSame( 'university', $this->org_slug( $school ) );
		$this->assertSame( 'ngo-nonprofit', $this->org_slug( $charity ) );
		$this->assertSame( 'other', $this->org_slug( $guild ) );
		$this->assertSame( 'banking', $this->industry_slug( $bank ) );
		$this->assertSame( 'telecoms', $this->industry_slug( $phone ) );
		foreach ( array( $public_body, $public_uni, $uni_school, $ngo ) as $old ) {
			$this->assertNull( term_exists( $old, Taxonomies::ORG_TYPE ), 'Each old type is folded into the tree.' );
		}
		$this->assertContains( 'Public university', Classification::aliases_of( (int) Classification::term_id( Taxonomies::ORG_TYPE, 'university' ) ) );
	}

	public function test_REQ_009_the_repair_migration_fixes_a_site_that_already_upgraded_and_keeps_chosen_values(): void {
		$classifier = new ProfileClassifier();
		$alias      = array(
			'method'         => 'alias',
			'model'          => null,
			'prompt_version' => null,
			'confidence'     => 'low',
		);
		$ng         = (int) Classification::country_id( 'NG' );
		// As 2026112103 left them under the 0.3.0 aliases: Unclassified and "other", with the raw text still stored.
		$broken = $this->profile(
			'Repair Broken Qwv',
			'institution',
			array(
				'org_type' => 'Public university',
				'industry' => 'financial services, financial sector',
			)
		);
		$classifier->assign(
			$broken,
			'institution',
			array(
				'primary_industry' => 'unclassified',
				'industries'       => array( 'research' ),
				'country_id'       => $ng,
				'org_type'         => 'other',
				'provenance'       => $alias,
			)
		);
		$staff = $this->profile( 'Repair Staff Qwv', 'institution', array( 'org_type' => 'Public university' ) );
		$classifier->assign(
			$staff,
			'institution',
			array(
				'primary_industry' => 'unclassified',
				'org_type'         => 'other',
				'provenance'       => array_merge( $alias, array( 'method' => 'manual' ) ),
			)
		);
		$decided = $this->profile( 'Repair Decided Qwv', 'organisation', array( 'industry' => 'Banking' ) );
		$classifier->assign(
			$decided,
			'organisation',
			array(
				'primary_industry' => 'unclassified',
				'org_type'         => 'company',
				'provenance'       => array_merge( $alias, array( 'method' => 'ai' ) ),
			)
		);
		$fine = $this->profile( 'Repair Fine Qwv', 'organisation', array( 'industry' => 'Banking' ) );
		$classifier->assign(
			$fine,
			'organisation',
			array(
				'primary_industry' => 'insurance',
				'org_type'         => 'company',
				'provenance'       => $alias,
			)
		);

		$counts = ( new ClassificationUpgrade() )->repair();

		$this->assertSame( 'banking', $this->industry_slug( $broken ) );
		$this->assertSame( 'university', $this->org_slug( $broken ) );
		$this->assertSame( $ng, (int) get_post_meta( $broken, Classification::PRIMARY_COUNTRY_META, true ), 'The country stays.' );
		$this->assertContains( (int) Classification::term_id( Taxonomies::INDUSTRY, 'research' ), wp_get_object_terms( $broken, Taxonomies::INDUSTRY, array( 'fields' => 'ids' ) ), 'The extra industry stays.' );
		$this->assertSame( 'unclassified', $this->industry_slug( $staff ), 'A staff choice is never overridden.' );
		$this->assertSame( 'other', $this->org_slug( $staff ) );
		$this->assertSame( 'unclassified', $this->industry_slug( $decided ), 'An acquisition decision is kept.' );
		$this->assertSame( 'insurance', $this->industry_slug( $fine ), 'A real industry is never replaced.' );
		$this->assertGreaterThanOrEqual( 1, $counts['industries_repaired'] );
		$this->assertGreaterThanOrEqual( 1, $counts['org_types_repaired'] );
		$this->assertGreaterThanOrEqual( 2, $counts['kept_as_set'] );

		$again = ( new ClassificationUpgrade() )->repair();
		$this->assertSame( 0, $again['industries_repaired'] + $again['org_types_repaired'], 'Running again changes nothing.' );
	}

	public function test_REQ_056_the_repair_migration_is_registered_after_the_upgrade(): void {
		$file = dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations/2026112104_classification_repair.php';
		$this->assertFileExists( $file );
		$migration = require $file;
		$this->assertSame( '2026112104', $migration->version() );
		$this->assertFalse( $migration->is_contract() );
	}
}
