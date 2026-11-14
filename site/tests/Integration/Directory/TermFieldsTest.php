<?php
/**
 * Term screen fields and rules for places, industries and organisation types (REQ-009): place kind, a unique two-letter country
 * code, a country as a city's parent, sectors above industries, "Also known as" aliases, a fixed Unclassified term, old slugs kept
 * with a 301 from old directory addresses, clear admin columns, and an audit row per save. Plain TestCase; cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Admin\TermFields;
use WhoIsWho\Core\Directory\Edit\ProfileClassifier;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Directory\Url\OldTermSlugRedirect;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class TermFieldsTest extends TestCase {

	/**
	 * Terms to remove: [term id, taxonomy].
	 *
	 * @var array<int, array{0: int, 1: string}>
	 */
	private array $terms = array();

	/**
	 * Users to remove.
	 *
	 * @var int[]
	 */
	private array $users = array();

	/**
	 * Request globals before the test.
	 *
	 * @var array<string, mixed>
	 */
	private array $post_before = array();

	private int $audit_floor = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
		( new Taxonomies() )->register();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		$this->post_before = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- test restores the globals.
		global $wpdb;
		$this->audit_floor = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wiw_audit_log" ); // phpcs:ignore WordPress.DB
	}

	protected function tearDown(): void {
		$_POST = $this->post_before;
		foreach ( array_reverse( $this->terms ) as list( $term, $taxonomy ) ) {
			wp_delete_term( $term, $taxonomy );
		}
		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->terms = array();
		$this->users = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function term( string $name, string $taxonomy, int $parent_id = 0, string $kind = '', string $iso = '', string $slug = '' ): int {
		$args = array( 'parent' => $parent_id );
		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}
		$made = wp_insert_term( $name, $taxonomy, $args );
		$this->assertIsArray( $made, $name );
		$id = (int) $made['term_id'];
		if ( '' !== $kind ) {
			update_term_meta( $id, Taxonomies::META_KIND, $kind );
		}
		if ( '' !== $iso ) {
			update_term_meta( $id, Taxonomies::META_ISO, $iso );
		}
		$this->terms[] = array( $id, $taxonomy );
		return $id;
	}

	private function editor(): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'term_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'wiw_editor',
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		wp_set_current_user( $id );
		return $id;
	}

	private function unclassified(): int {
		$term = get_term_by( 'slug', ProfileClassifier::UNCLASSIFIED, Taxonomies::INDUSTRY );
		return $term instanceof \WP_Term ? (int) $term->term_id : $this->term( 'Unclassified', Taxonomies::INDUSTRY, 0, '', '', ProfileClassifier::UNCLASSIFIED );
	}

	/**
	 * Pretend the term screen posted these values with a valid nonce.
	 *
	 * @param array<string, string> $values Values.
	 */
	private function post( array $values ): void {
		$_POST = array_merge( array( TermFields::NONCE_FIELD => wp_create_nonce( TermFields::NONCE ) ), $values );
	}

	public function test_REQ_009_place_rules_country_code_and_city_parent(): void {
		$fields  = new TermFields();
		$code    = 'Q' . chr( 65 + random_int( 0, 25 ) );
		$country = $this->term( 'Testcountry ' . $code, Taxonomies::PLACE, 0, Taxonomies::KIND_COUNTRY, $code );
		$city    = $this->term( 'Testcity ' . $code, Taxonomies::PLACE, $country, Taxonomies::KIND_CITY );
		$this->assertSame( 'kind', $fields->refusal( Taxonomies::PLACE, array( 'kind' => '' ) ) );
		$this->assertSame(
			'code',
			$fields->refusal(
				Taxonomies::PLACE,
				array(
					'kind' => 'country',
					'code' => 'NGA',
				)
			)
		);
		$this->assertSame(
			'code',
			$fields->refusal(
				Taxonomies::PLACE,
				array(
					'kind' => 'country',
					'code' => '',
				)
			)
		);
		$this->assertSame(
			'code_taken',
			$fields->refusal(
				Taxonomies::PLACE,
				array(
					'kind' => 'country',
					'code' => $code,
				)
			)
		);
		$this->assertNull(
			$fields->refusal(
				Taxonomies::PLACE,
				array(
					'kind' => 'country',
					'code' => $code,
				),
				$country
			),
			'its own code is not taken'
		);
		$this->assertSame(
			'country_parent',
			$fields->refusal(
				Taxonomies::PLACE,
				array(
					'kind'   => 'country',
					'code'   => 'ZQ',
					'parent' => $country,
				)
			)
		);
		$this->assertSame( 'city_parent', $fields->refusal( Taxonomies::PLACE, array( 'kind' => 'city' ) ) );
		$this->assertSame(
			'city_parent',
			$fields->refusal(
				Taxonomies::PLACE,
				array(
					'kind'   => 'city',
					'parent' => $city,
				)
			),
			'a city is not a parent'
		);
		$this->assertNull(
			$fields->refusal(
				Taxonomies::PLACE,
				array(
					'kind'   => 'city',
					'parent' => $country,
				)
			)
		);
		$this->assertNull(
			$fields->refusal(
				Taxonomies::PLACE,
				array(
					'kind' => 'body',
					'code' => 'PAN',
				)
			)
		);
		$this->assertNotSame( '', TermFields::message( 'code' ) );
	}

	public function test_REQ_009_industry_rules_sectors_above_industries_and_a_fixed_unclassified(): void {
		$fields   = new TermFields();
		$sector   = $this->term( 'Testsector ' . wp_generate_password( 4, false ), Taxonomies::INDUSTRY );
		$industry = $this->term( 'Testindustry ' . wp_generate_password( 4, false ), Taxonomies::INDUSTRY, $sector );
		$other    = $this->term( 'Othersector ' . wp_generate_password( 4, false ), Taxonomies::INDUSTRY );
		$unc      = $this->unclassified();
		$this->assertNull( $fields->refusal( Taxonomies::INDUSTRY, array( 'parent' => 0 ) ), 'a new top-level term is a sector' );
		$this->assertNull( $fields->refusal( Taxonomies::INDUSTRY, array( 'parent' => $sector ) ) );
		$this->assertSame( 'sector_parent', $fields->refusal( Taxonomies::INDUSTRY, array( 'parent' => $industry ) ), 'no third level' );
		$this->assertSame( 'sector_parent', $fields->refusal( Taxonomies::INDUSTRY, array( 'parent' => $unc ) ) );
		$this->assertSame( 'sector_moved', $fields->refusal( Taxonomies::INDUSTRY, array( 'parent' => $other ), $sector ) );
		$this->assertSame( 'unclassified', $fields->refusal( Taxonomies::INDUSTRY, array( 'name' => 'Misc' ), $unc ) );
		$this->assertSame(
			'unclassified',
			$fields->refusal(
				Taxonomies::INDUSTRY,
				array(
					'name' => get_term( $unc )->name,
					'slug' => 'misc',
				),
				$unc
			)
		);

		$this->editor();
		$this->assertFalse( current_user_can( 'delete_term', $unc ), 'Unclassified cannot be deleted' );
		$this->assertTrue( current_user_can( 'delete_term', $other ) );
		wp_update_term(
			$unc,
			Taxonomies::INDUSTRY,
			array(
				'name' => 'Renamed',
				'slug' => 'renamed',
			)
		);
		$this->assertSame( ProfileClassifier::UNCLASSIFIED, get_term( $unc )->slug, 'a quick edit cannot rename it either' );
		$this->assertArrayNotHasKey(
			'delete',
			( new TermFields() )->row_actions(
				array(
					'delete' => 'x',
					'edit'   => 'y',
				),
				get_term( $unc )
			)
		);
	}

	public function test_REQ_009_a_screen_insert_that_breaks_a_rule_is_refused_but_code_inserts_are_not(): void {
		$fields = new TermFields();
		$this->assertSame( 'Somewhere', $fields->check_insert( 'Somewhere', Taxonomies::PLACE, array() ), 'without the screen nonce nothing is checked' );
		$this->editor();
		$this->post(
			array(
				'wiw_place_kind' => 'city',
				'wiw_iso_code'   => '',
			)
		);
		$refused = $fields->check_insert( 'Orphan city', Taxonomies::PLACE, array( 'parent' => 0 ) );
		$this->assertInstanceOf( \WP_Error::class, $refused );
		$this->assertSame( TermFields::message( 'city_parent' ), $refused->get_error_message() );
	}

	public function test_REQ_009_saving_the_screen_stores_kind_code_and_aliases_and_audits(): void {
		$this->editor();
		$this->post(
			array(
				'wiw_place_kind' => 'country',
				'wiw_iso_code'   => 'zx',
			)
		);
		$place = $this->term( 'Testzedland', Taxonomies::PLACE );
		( new TermFields() )->saved( $place );
		$this->assertSame( 'country', get_term_meta( $place, Taxonomies::META_KIND, true ) );
		$this->assertSame( 'ZX', get_term_meta( $place, Taxonomies::META_ISO, true ) );

		$this->post( array( 'wiw_alias' => ' Petroleum , Oil and gas,Petroleum,, ' ) );
		$industry = $this->term( 'Testoil ' . wp_generate_password( 4, false ), Taxonomies::INDUSTRY );
		( new TermFields() )->saved( $industry );
		$this->assertSame( array( 'Petroleum', 'Oil and gas' ), TermFields::aliases( $industry ) );
		$this->assertSame( array( 'Petroleum', 'Oil and gas' ), get_term_meta( $industry, TermFields::META_ALIAS, true ) );

		global $wpdb;
		$rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE id > %d AND action = 'term.save'", $this->audit_floor ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( 2, $rows );

		$_POST = array( 'wiw_alias' => 'No nonce' );
		( new TermFields() )->saved( $industry );
		$this->assertSame( array( 'Petroleum', 'Oil and gas' ), TermFields::aliases( $industry ), 'no nonce, no save' );
	}

	public function test_REQ_009_a_slug_change_keeps_the_old_slug_and_old_addresses_redirect(): void {
		$this->editor();
		$sector   = $this->term( 'Testenergy', Taxonomies::INDUSTRY, 0, '', '', 'test-energy-old' );
		$industry = $this->term( 'Testpower', Taxonomies::INDUSTRY, $sector, '', '', 'test-power-old' );
		wp_update_term( $industry, Taxonomies::INDUSTRY, array( 'slug' => 'test-power-new' ) );
		$this->assertSame( array( 'test-power-old' ), get_term_meta( $industry, TermFields::META_OLD_SLUGS, false ) );
		$this->assertSame( 'test-power-new', OldTermSlugRedirect::current_slug( 'test-power-old', Taxonomies::INDUSTRY ) );

		$redirect = new OldTermSlugRedirect();
		$this->assertSame(
			'/directory/?country=ng&industry=test-power-new',
			$redirect->target(
				new Request(
					array(
						'industry' => 'test-power-old',
						'country'  => 'ng',
					)
				),
				'/directory/?country=ng&industry=test-power-old'
			)
		);
		$this->assertNull( $redirect->target( new Request( array( 'industry' => 'test-power-new' ) ), '/directory/?industry=test-power-new' ), 'a current slug is not redirected' );
		$this->assertNull( $redirect->target( new Request( array( 'industry' => 'never-was' ) ), '/directory/?industry=never-was' ) );

		wp_update_term( $industry, Taxonomies::INDUSTRY, array( 'slug' => 'test-power-old' ) );
		$this->assertSame( array( 'test-power-new' ), get_term_meta( $industry, TermFields::META_OLD_SLUGS, false ), 'a slug taken back is no longer old' );
		global $wpdb;
		$rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wiw_audit_log WHERE id > %d AND action = 'term.slug_change'", $this->audit_floor ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( 2, $rows );
	}

	public function test_REQ_009_admin_columns_have_clear_labels(): void {
		$fields  = new TermFields();
		$columns = $fields->columns(
			array(
				'cb'          => '',
				'name'        => 'Name',
				'description' => 'Description',
				'slug'        => 'Slug',
				'posts'       => 'Count',
			),
			Taxonomies::PLACE
		);
		$this->assertSame( array( 'cb', 'name', 'slug', 'wiw_kind', 'wiw_code', 'wiw_country', 'posts' ), array_keys( $columns ) );
		$this->assertSame( 'Country code', $columns['wiw_code'] );
		$this->assertSame( 'Profiles', $columns['posts'] );
		$this->assertArrayHasKey( 'wiw_sector', $fields->columns( array(), Taxonomies::INDUSTRY ) );
		$this->assertArrayHasKey( 'wiw_alias', $fields->columns( array(), Taxonomies::ORG_TYPE ) );

		$country = $this->term( 'Testcolumnland', Taxonomies::PLACE, 0, Taxonomies::KIND_COUNTRY, 'QC' );
		$city    = $this->term( 'Testcolumncity', Taxonomies::PLACE, $country, Taxonomies::KIND_CITY );
		$this->assertSame( 'City', $fields->column( '', 'wiw_kind', $city ) );
		$this->assertSame( 'QC', $fields->column( '', 'wiw_code', $country ) );
		$this->assertSame( 'Testcolumnland', $fields->column( '', 'wiw_country', $city ) );
	}

	public function test_REQ_009_the_forms_carry_the_nonce_help_and_fields(): void {
		$fields = new TermFields();
		ob_start();
		$fields->add_fields( Taxonomies::PLACE );
		$place = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="' . TermFields::NONCE_FIELD . '"', $place );
		$this->assertStringContainsString( 'name="wiw_place_kind"', $place );
		$this->assertStringContainsString( 'name="wiw_iso_code"', $place );
		$this->assertStringContainsString( 'two-letter code, for example NG for Nigeria', $place );
		ob_start();
		$fields->add_fields( Taxonomies::INDUSTRY );
		$industry = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="wiw_alias"', $industry );
		$this->assertStringContainsString( 'choose its sector as Parent', $industry );
	}
}
