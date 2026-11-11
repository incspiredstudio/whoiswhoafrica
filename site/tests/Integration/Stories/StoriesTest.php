<?php
/**
 * Story fields on native posts (REQ-027, REQ-028, REQ-029). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Stories;

use WhoIsWho\Core\Content\Stories\LinkedProfiles;
use WhoIsWho\Core\Content\Stories\ReadingTime;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryRules;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\TestCase;

final class StoriesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
	}

	private function user_with_role( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function make_story( string $status, string $type ): int {
		$id = wp_insert_post(
			array(
				'post_title'  => 'A story',
				'post_status' => 'draft',
				'post_type'   => 'post',
			)
		);
		$this->assertIsInt( $id );
		if ( '' !== $type ) {
			wp_set_object_terms( $id, $type, StoryTypes::TAXONOMY );
		}
		if ( 'draft' !== $status ) {
			wp_update_post(
				array(
					'ID'            => $id,
					'post_status'   => $status,
					'post_date'     => 'future' === $status ? gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) : current_time( 'mysql' ),
					'edit_date'     => true,
					'post_date_gmt' => 'future' === $status ? gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) : current_time( 'mysql', true ),
				)
			);
		}
		return $id;
	}

	public function test_REQ_027_editor_can_create_schedule_and_publish_story(): void {
		wp_set_current_user( $this->user_with_role( 'wiw_editor' ) );
		$this->assertTrue( current_user_can( 'edit_posts' ) );
		$this->assertTrue( current_user_can( 'publish_posts' ) );
		$published = $this->make_story( 'publish', 'interview' );
		$this->assertSame( 'publish', get_post_status( $published ) );
		$scheduled = $this->make_story( 'future', 'feature' );
		$this->assertSame( 'future', get_post_status( $scheduled ) );
	}

	public function test_REQ_027_reviewer_cannot_publish_story(): void {
		wp_set_current_user( $this->user_with_role( 'wiw_reviewer' ) );
		$this->assertFalse( current_user_can( 'edit_posts' ) );
		$this->assertFalse( current_user_can( 'publish_posts' ) );
	}

	public function test_REQ_027_story_type_is_one_of_six_and_required_to_publish(): void {
		$this->assertSame( array( 'interview', 'feature', 'spotlight', 'opinion', 'announcement', 'news' ), StoryTypes::TYPES );
		wp_set_current_user( $this->user_with_role( 'wiw_editor' ) );
		$_POST = array(
			StoryFields::NONCE_FIELD => wp_create_nonce( StoryFields::NONCE_ACTION ),
			'wiw_story_type'         => '',
		);
		$id    = $this->make_story( 'publish', '' );
		$_POST = array();
		$this->assertSame( 'draft', get_post_status( $id ), 'A story submitted in the editor without a type cannot be published.' );
		$ok = $this->make_story( 'publish', 'opinion' );
		$this->assertSame( 'publish', get_post_status( $ok ) );
		$this->assertSame( 'opinion', StoryTypes::of( $ok ) );
		$this->assertFalse( StoryTypes::is_valid( 'gossip' ) );
	}

	public function test_REQ_027_unknown_type_term_is_not_a_story_type(): void {
		$id = $this->make_story( 'draft', '' );
		wp_set_object_terms( $id, 'gossip', StoryTypes::TAXONOMY );
		$this->assertSame( '', StoryTypes::of( $id ) );
		$this->assertFalse( StoryRules::has_valid_type( $id ) );
	}

	public function test_REQ_029_reading_time_has_two_minute_minimum(): void {
		$this->assertSame( 2, ReadingTime::minutes( 'Short.' ) );
		$this->assertSame( 2, ReadingTime::minutes( '' ) );
		$this->assertSame( 2, ReadingTime::minutes( str_repeat( 'word ', 360 ) ) );
		$this->assertSame( 3, ReadingTime::minutes( str_repeat( 'word ', 361 ) ) );
		$this->assertSame( 6, ReadingTime::minutes( '<p>' . str_repeat( 'word ', 1000 ) . '</p>' ) );
	}

	public function test_REQ_028_story_links_only_public_profiles_in_picker(): void {
		$filter = static function (): array {
			return array(
				11 => 'Ada Public',
				12 => 'Bola Public',
			);
		};
		add_filter( LinkedProfiles::FILTER, $filter );
		$this->assertSame(
			array(
				11 => 'Ada Public',
				12 => 'Bola Public',
			),
			LinkedProfiles::choices()
		);
		$this->assertSame( array( 11 ), LinkedProfiles::sanitise( array( '11', '99', 'x', '11' ) ) );
		remove_filter( LinkedProfiles::FILTER, $filter );
		$this->assertSame( array(), LinkedProfiles::sanitise( array( 11 ) ), 'With no public profiles nothing can be linked.' );
	}

	public function test_REQ_027_seo_meta_is_registered_and_sanitised(): void {
		$id = $this->make_story( 'draft', 'news' );
		update_post_meta( $id, 'wiw_seo_title', '<b>Title</b> here' );
		$this->assertSame( 'Title here', get_post_meta( $id, 'wiw_seo_title', true ) );
		$this->assertArrayHasKey( 'wiw_seo_description', get_registered_meta_keys( 'post', 'post' ) );
	}

	public function test_REQ_027_industry_taxonomy_is_attached_to_posts_when_it_exists(): void {
		register_taxonomy( 'wiw_industry', array(), array( 'public' => true ) );
		\WhoIsWho\Core\Content\Stories\StoryFields::attach_industry();
		$this->assertContains( 'wiw_industry', get_object_taxonomies( 'post' ) );
	}
}
