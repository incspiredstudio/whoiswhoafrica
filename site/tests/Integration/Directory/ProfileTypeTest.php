<?php
/**
 * The profile type, the five controlled-list taxonomies and the hidden status (REQ-003, REQ-009). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\PlaceSeeder;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\WpTestCase;

final class ProfileTypeTest extends WpTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		wp_set_current_user( 0 );
	}

	public function test_REQ_003_profile_type_has_no_archive_feed_or_rest(): void {
		$type = get_post_type_object( ProfileType::POST_TYPE );
		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->has_archive );
		$this->assertFalse( $type->show_in_rest );
		$this->assertFalse( $type->publicly_queryable );
		$this->assertTrue( $type->exclude_from_search );
		$this->assertFalse( $type->rewrite );
		$this->assertArrayNotHasKey( '/wp/v2/wiw_profile', rest_get_server()->get_routes() );

		$id = self::factory()->post->create(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Findable Person Zxq',
			)
		);
		$this->assertFalse( is_post_type_viewable( $type ) );

		$this->go_to( home_url( '/?s=Zxq' ) );
		$found = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertNotContains( $id, $found, 'Core search must not return profiles.' );

		$this->assertFalse( is_post_type_archive( ProfileType::POST_TYPE ) );
	}

	public function test_REQ_003_hidden_status_is_never_returned_by_a_public_query(): void {
		$status = get_post_status_object( ProfileType::STATUS_HIDDEN );
		$this->assertNotNull( $status );
		$this->assertFalse( $status->public );
		$this->assertTrue( $status->exclude_from_search );

		$id = self::factory()->post->create(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_status' => ProfileType::STATUS_HIDDEN,
				'post_title'  => 'Hidden Person',
			)
		);
		$this->assertSame( ProfileType::STATUS_HIDDEN, get_post_status( $id ) );

		foreach ( array( 'any', 'publish' ) as $post_status ) {
			$query = new \WP_Query(
				array(
					'post_type'   => ProfileType::POST_TYPE,
					'post_status' => $post_status,
					'fields'      => 'ids',
				)
			);
			$this->assertNotContains( $id, $query->posts, 'An anonymous query must never return a hidden profile.' );
		}
		$this->go_to( home_url( '/?p=' . $id ) );
		$this->assertFalse( is_singular( ProfileType::POST_TYPE ) );
		$this->assertNotContains( $id, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	public function test_REQ_009_taxonomies_registered_with_native_term_screens(): void {
		foreach ( Taxonomies::all() as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );
			$this->assertNotFalse( $object, $taxonomy );
			$this->assertTrue( $object->show_ui, "{$taxonomy} is managed on the native term screens" );
			$this->assertFalse( $object->public );
			$this->assertFalse( $object->show_in_rest );
			$this->assertContains( ProfileType::POST_TYPE, $object->object_type );
		}
		$this->assertTrue( get_taxonomy( Taxonomies::PLACE )->hierarchical );
		$this->assertFalse( get_taxonomy( Taxonomies::INDUSTRY )->hierarchical );
	}

	public function test_REQ_009_reviewer_assigns_but_cannot_manage_terms(): void {
		$reviewer = self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) );
		$editor   = self::factory()->user->create( array( 'role' => 'wiw_editor' ) );
		$term_id  = self::factory()->term->create( array( 'taxonomy' => Taxonomies::INDUSTRY ) );

		wp_set_current_user( $reviewer );
		$this->assertTrue( current_user_can( get_taxonomy( Taxonomies::INDUSTRY )->cap->assign_terms ) );
		$this->assertTrue( current_user_can( 'assign_term', $term_id ) );
		$this->assertFalse( current_user_can( 'edit_term', $term_id ) );
		$this->assertFalse( current_user_can( 'delete_term', $term_id ) );
		$this->assertFalse( current_user_can( get_taxonomy( Taxonomies::INDUSTRY )->cap->manage_terms ) );
		$this->assertFalse( current_user_can( get_taxonomy( Taxonomies::PLACE )->cap->edit_terms ) );

		wp_set_current_user( $editor );
		$this->assertTrue( current_user_can( get_taxonomy( Taxonomies::INDUSTRY )->cap->manage_terms ) );
		$this->assertTrue( current_user_can( get_taxonomy( Taxonomies::SECTOR )->cap->edit_terms ) );
		$this->assertTrue( current_user_can( 'edit_term', $term_id ) );
	}

	public function test_REQ_009_place_taxonomy_countries_with_iso_code(): void {
		( new PlaceSeeder() )->seed();
		$countries = get_terms(
			array(
				'taxonomy'   => Taxonomies::PLACE,
				'hide_empty' => false,
				'parent'     => 0,
				'meta_key'   => Taxonomies::META_KIND, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test lookup.
				'meta_value' => Taxonomies::KIND_COUNTRY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- test lookup.
				'orderby'    => 'term_id',
			)
		);
		$this->assertCount( 54, $countries );
		$this->assertSame( 'Nigeria', $countries[0]->name, 'Nigeria is first.' );
		foreach ( $countries as $country ) {
			$this->assertMatchesRegularExpression( '/^[A-Z]{2}$/', (string) get_term_meta( $country->term_id, Taxonomies::META_ISO, true ) );
		}
		$this->assertNotFalse( term_exists( 'Pan-African', Taxonomies::PLACE ) );

		$ng   = (int) ( new PlaceSeeder() )->find_country( 'NG' );
		$city = wp_insert_term( 'Lagos', Taxonomies::PLACE, array( 'parent' => $ng ) );
		$this->assertIsArray( $city );
		$this->assertSame( $ng, (int) get_term( $city['term_id'], Taxonomies::PLACE )->parent );

		$this->assertSame( 0, ( new PlaceSeeder() )->seed(), 'Seeding is idempotent.' );
	}
}
