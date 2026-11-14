<?php
/**
 * The PublicStory read model (REQ-027, REQ-029, REQ-257, REQ-208, REQ-265, REQ-263, REQ-028). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Stories;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Content\Stories\LinkedProfiles;
use WhoIsWho\Core\Content\Stories\StoryFields;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Tests\TestCase;

final class PublicStoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		StoryTypes::register();
		StoryTypes::ensure_terms();
		StoryFields::register_meta();
		EventPostType::register();
		if ( ! taxonomy_exists( 'wiw_industry' ) ) {
			register_taxonomy( 'wiw_industry', array( 'post' ), array( 'public' => true ) );
		}
		register_taxonomy_for_object_type( 'wiw_industry', 'post' );
		// Other tests commit their rows, so start from no posts at all.
		foreach ( get_posts(
			array(
				'post_type'      => array( 'post', EventPostType::TYPE ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $leftover ) {
			wp_delete_post( (int) $leftover, true );
		}
		update_option( 'sticky_posts', array() );
		remove_all_filters( LinkedProfiles::FILTER );
	}

	/**
	 * Create a story.
	 *
	 * @param array<string, mixed> $args Overrides.
	 */
	private function story( array $args = array() ): int {
		$type = isset( $args['type'] ) ? (string) $args['type'] : 'news';
		$ind  = isset( $args['industry'] ) ? (string) $args['industry'] : '';
		unset( $args['type'], $args['industry'] );
		$id = wp_insert_post(
			array_merge(
				array(
					'post_title'   => 'Story ' . wp_generate_password( 5, false ),
					'post_content' => 'Body text.',
					'post_status'  => 'publish',
					'post_type'    => 'post',
				),
				$args
			)
		);
		$this->assertIsInt( $id );
		wp_set_object_terms( $id, $type, StoryTypes::TAXONOMY );
		if ( '' !== $ind ) {
			wp_set_object_terms( $id, $ind, 'wiw_industry' );
		}
		return $id;
	}

	public function test_REQ_208_public_story_hides_draft_and_scheduled(): void {
		$draft  = $this->story( array( 'post_status' => 'draft' ) );
		$future = $this->story(
			array(
				'post_status'   => 'future',
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);
		$trash  = $this->story();
		wp_trash_post( $trash );
		$this->assertSame( 'future', get_post_status( $future ) );
		foreach ( array( $draft, $future, $trash ) as $id ) {
			$this->assertNull( PublicStory::find( $id ), 'Hidden from visitors.' );
		}
		$this->assertNotNull( PublicStory::find( $this->story() ) );

		$preview = PublicStory::find( $draft, true );
		$this->assertNull( $preview, 'The preview flag is ignored for a visitor.' );
		$editor = wp_insert_user(
			array(
				'user_login' => 'admin' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
		$this->assertIsInt( $editor );
		wp_set_current_user( $editor );
		$staff = PublicStory::find( $draft, true );
		$this->assertNotNull( $staff );
		$this->assertTrue( $staff['preview'] );
		$this->assertFalse( PublicStory::find( $this->story() )['preview'] ?? true );
	}

	/**
	 * A staff login name never shows on a public story: when the display name is the login, the byline is the editorial name (#810).
	 */
	public function test_REQ_026_byline_never_shows_the_login_name(): void {
		$login = 'owner' . strtolower( wp_generate_password( 6, false ) );
		$admin = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
		$this->assertIsInt( $admin );
		$this->assertSame( $login, get_the_author_meta( 'display_name', $admin ), 'WordPress defaults the display name to the login.' );
		$brand = static fn (): string => 'Brandname Qwv';
		add_filter( 'wiw_brand_name', $brand );

		$story = PublicStory::find( $this->story( array( 'post_author' => $admin ) ) );
		$this->assertNotNull( $story );
		$this->assertSame( 'Brandname Qwv Editorial', $story['author'] );
		$this->assertStringNotContainsString( $login, (string) wp_json_encode( $story ) );
		$this->assertTrue( PublicStory::byline_needs_name( $admin ) );
		$article = \WhoIsWho\Core\Content\Stories\StorySeo::article( $story );
		$this->assertSame( 'Organization', $article['author']['@type'] );
		$this->assertSame( 'Brandname Qwv Editorial', $article['author']['name'], 'The JSON-LD author matches the byline.' );

		wp_update_user(
			array(
				'ID'           => $admin,
				'display_name' => 'Ada Writer',
			)
		);
		$story = PublicStory::find( $this->story( array( 'post_author' => $admin ) ) );
		$this->assertSame( 'Ada Writer', $story['author'] ?? null, 'A real public name is kept.' );
		$this->assertFalse( PublicStory::byline_needs_name( $admin ) );
		remove_filter( 'wiw_brand_name', $brand );
		wp_delete_user( $admin );
	}

	public function test_REQ_029_list_filters_by_type_and_industry(): void {
		wp_insert_term( 'Finance', 'wiw_industry', array( 'slug' => 'finance' ) );
		wp_insert_term( 'Tech', 'wiw_industry', array( 'slug' => 'tech' ) );
		$a   = $this->story(
			array(
				'type'     => 'interview',
				'industry' => 'finance',
			)
		);
		$b   = $this->story(
			array(
				'type'     => 'news',
				'industry' => 'tech',
			)
		);
		$c   = $this->story(
			array(
				'type'     => 'interview',
				'industry' => 'tech',
			)
		);
		$all = PublicStory::list( array() );
		$this->assertSame( 3, $all['total'] );
		$this->assertFalse( $all['error'] );

		$interviews = wp_list_pluck( PublicStory::list( array( 'type' => 'interview' ) )['items'], 'id' );
		$this->assertEqualsCanonicalizing( array( $a, $c ), $interviews );

		$tech = wp_list_pluck( PublicStory::list( array( 'industry' => 'tech' ) )['items'], 'id' );
		$this->assertEqualsCanonicalizing( array( $b, $c ), $tech );

		$both = wp_list_pluck(
			PublicStory::list(
				array(
					'type'     => 'interview',
					'industry' => 'tech',
				)
			)['items'],
			'id'
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		$this->assertSame( array( $c ), $both );

		$none = PublicStory::list( array( 'type' => 'spotlight' ) );
		$this->assertSame( 0, $none['total'] );
		$this->assertFalse( $none['error'], 'Empty is not an error.' );

		$bad = PublicStory::list( array( 'type' => 'gossip' ) );
		$this->assertTrue( $bad['error'], 'An unknown type is an error, not an empty list.' );
		$this->assertSame( array(), $bad['items'] );
	}

	public function test_REQ_257_featured_stories_come_first(): void {
		$old      = $this->story( array( 'post_date' => '2026-01-01 10:00:00' ) );
		$mid      = $this->story( array( 'post_date' => '2026-02-01 10:00:00' ) );
		$new      = $this->story( array( 'post_date' => '2026-03-01 10:00:00' ) );
		$featured = $old;
		stick_post( $featured );
		$ids = wp_list_pluck( PublicStory::list( array() )['items'], 'id' );
		$this->assertSame( array( $old, $new, $mid ), $ids );
		$this->assertTrue( PublicStory::list( array() )['items'][0]['featured'] );
	}

	public function test_REQ_257_list_pages(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->story( array( 'post_date' => '2026-01-0' . $i . ' 10:00:00' ) );
		}
		$page2 = PublicStory::list(
			array(
				'per_page' => 2,
				'page'     => 2,
			)
		);
		$this->assertCount( 2, $page2['items'] );
		$this->assertSame( 5, $page2['total'] );
		$this->assertSame( 3, $page2['pages'] );
	}

	public function test_REQ_028_non_public_linked_profile_is_omitted(): void {
		add_filter(
			LinkedProfiles::FILTER,
			static function (): array {
				return array( 11 => 'Ada Public' );
			}
		);
		$id = $this->story();
		update_post_meta( $id, LinkedProfiles::META, array( 11, 99 ) );
		$story = PublicStory::find( $id );
		$this->assertNotNull( $story );
		$this->assertSame( array( 11 ), wp_list_pluck( $story['profiles'], 'id' ) );
		$this->assertSame( 'Ada Public', $story['profiles'][0]['name'] );
	}

	public function test_REQ_265_related_event_shows_only_while_published(): void {
		$event = wp_insert_post(
			array(
				'post_type'   => EventPostType::TYPE,
				'post_title'  => 'Summit',
				'post_status' => 'draft',
			)
		);
		$id    = $this->story();
		update_post_meta( $id, 'related_event_id', $event );
		$this->assertNull( PublicStory::find( $id )['event'] );
		wp_update_post(
			array(
				'ID'          => $event,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( $event, PublicStory::find( $id )['event']['id'] );
	}

	public function test_REQ_029_story_exposes_type_reading_time_and_other_stories(): void {
		$id = $this->story(
			array(
				'type'         => 'opinion',
				'post_content' => 'Short body.',
			)
		);
		$this->story();
		$this->story();
		$story = PublicStory::find( $id );
		$this->assertSame( 'opinion', $story['type'] );
		$this->assertSame( 2, $story['reading_minutes'] );
		$this->assertCount( 2, $story['others'] );
		$this->assertNotContains( $id, wp_list_pluck( $story['others'], 'id' ) );
		$this->assertArrayNotHasKey( 'post_password', $story );
		$this->assertArrayNotHasKey( 'meta', $story );
	}

	public function test_REQ_263_placement_does_not_reorder_unsponsored_stories(): void {
		$a      = $this->story( array( 'post_date' => '2026-01-01 10:00:00' ) );
		$b      = $this->story( array( 'post_date' => '2026-02-01 10:00:00' ) );
		$c      = $this->story( array( 'post_date' => '2026-03-01 10:00:00' ) );
		$before = wp_list_pluck( PublicStory::list( array() )['items'], 'id' );
		foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $filter ) {
			remove_filter( $filter, array( \WhoIsWho\Core\Content\Stories\StoryFlags::class, 'guard_meta' ), 1 ); // This test only needs the fields set; StoryFlagsTest covers the guard.
		}
		update_post_meta( $a, 'wiw_sponsor_name', 'Acme' );
		update_post_meta( $a, 'wiw_sponsor_placement', 'home' );
		$after = wp_list_pluck( PublicStory::list( array() )['items'], 'id' );
		$this->assertSame( $before, $after );
		$this->assertSame( array( $c, $b, $a ), $after );
	}

	public function test_REQ_208_stories_for_a_profile_and_an_industry(): void {
		add_filter(
			LinkedProfiles::FILTER,
			static function (): array {
				return array( 11 => 'Ada' );
			}
		);
		wp_insert_term( 'Finance', 'wiw_industry', array( 'slug' => 'finance' ) );
		$linked = $this->story( array( 'industry' => 'finance' ) );
		$this->story();
		update_post_meta( $linked, LinkedProfiles::META, array( 11 ) );
		$this->assertSame( array( $linked ), wp_list_pluck( PublicStory::for_profile( 11 ), 'id' ) );
		$this->assertSame( array( $linked ), wp_list_pluck( PublicStory::for_industry( 'finance' ), 'id' ) );
	}

	public function test_REQ_208_templates_read_stories_only_through_public_story(): void {
		$root  = dirname( __DIR__, 3 ) . '/wp-content/themes';
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			$this->assertDoesNotMatchRegularExpression( '/new\s+\\\\?WP_Query|get_posts\s*\(|query_posts\s*\(|get_post_meta\s*\(/', $code, $file->getPathname() . ' reads posts directly; use PublicStory.' );
		}
	}
}
