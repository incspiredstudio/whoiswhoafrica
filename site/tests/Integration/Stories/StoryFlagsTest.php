<?php
/**
 * Featured and Sponsored on stories (REQ-155, REQ-182, REQ-184, REQ-188, REQ-257). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Stories;

use WhoIsWho\Core\Content\Stories\StoryFlags;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class StoryFlagsTest extends TestCase {

	private const PROBE = 'wiw_storyflags_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		update_option( 'sticky_posts', array() );
		StoryFlags::hooks();
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function user( string $role ): int {
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

	private function flags( int $actor ): StoryFlags {
		wp_set_current_user( 0 );
		wp_set_current_user( $actor );
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor );
		return new StoryFlags( $audit );
	}

	private function story(): int {
		$id = wp_insert_post(
			array(
				'post_title'  => 'A story',
				'post_status' => 'publish',
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_257_featured_requires_edit_others_posts(): void {
		$story    = $this->story();
		$reviewer = $this->user( 'wiw_reviewer' );
		try {
			$this->flags( $reviewer )->set_featured( $story, true );
			$this->fail( 'A reviewer featured a story.' );
		} catch ( \DomainException $e ) {
			$this->assertFalse( is_sticky( $story ) );
		}
		$this->flags( $this->user( 'wiw_editor' ) )->set_featured( $story, true );
		$this->assertTrue( is_sticky( $story ) );
		$this->flags( $this->user( 'wiw_editor' ) )->set_featured( $story, false );
		$this->assertFalse( is_sticky( $story ) );
	}

	public function test_REQ_182_sponsored_requires_wiw_set_sponsored(): void {
		$story    = $this->story();
		$reviewer = $this->user( 'wiw_reviewer' );
		try {
			$this->flags( $reviewer )->set_sponsored( $story, 'Acme', 'home' );
			$this->fail( 'A reviewer sponsored a story.' );
		} catch ( \DomainException $e ) {
			$this->assertSame( '', (string) get_post_meta( $story, 'wiw_sponsor_name', true ) );
		}
		$this->flags( $this->user( 'wiw_editor' ) )->set_sponsored( $story, 'Acme', 'both' );
		$this->assertSame( 'Acme', get_post_meta( $story, 'wiw_sponsor_name', true ) );
		$this->assertSame( 'both', get_post_meta( $story, 'wiw_sponsor_placement', true ) );
		$this->flags( $this->user( 'wiw_editor' ) )->set_sponsored( $story, '', 'home' );
		$this->assertSame( '', (string) get_post_meta( $story, 'wiw_sponsor_name', true ) );
	}

	public function test_REQ_182_sponsor_fields_cannot_be_written_around_the_service(): void {
		$story = $this->story();
		wp_set_current_user( $this->user( 'wiw_editor' ) );
		update_post_meta( $story, 'wiw_sponsor_name', 'Sneaky' );
		$this->assertSame( '', (string) get_post_meta( $story, 'wiw_sponsor_name', true ) );
		$this->flags( $this->user( 'wiw_editor' ) )->commerce_write( $story, 'Acme', 'stories', '2026-11-01', '2026-12-01' );
		$this->assertSame( 'Acme', get_post_meta( $story, 'wiw_sponsor_name', true ) );
		$this->assertSame( '2026-12-01', get_post_meta( $story, 'wiw_sponsor_end', true ) );
	}

	public function test_REQ_184_arrangement_does_not_change_featured(): void {
		$story = $this->story();
		$flags = $this->flags( $this->user( 'wiw_editor' ) );
		$flags->commerce_write( $story, 'Acme', 'home', '2026-11-01', '2026-12-01' );
		$this->assertFalse( is_sticky( $story ), 'A paid arrangement does not feature a story.' );
		$flags->set_featured( $story, true );
		$flags->commerce_write( $story, '', 'home', '', '' );
		$this->assertTrue( is_sticky( $story ), 'Ending an arrangement does not unfeature a story.' );
	}

	public function test_REQ_155_profile_has_no_sponsored_or_featured_field(): void {
		( new ProfileType() )->register();
		$this->assertFalse( post_type_supports( ProfileType::POST_TYPE, 'sticky' ) );
		foreach ( array_keys( get_registered_meta_keys( 'post', ProfileType::POST_TYPE ) ) as $key ) {
			$this->assertDoesNotMatchRegularExpression( '/sponsor|featured|sticky/i', (string) $key );
		}
		$profile = wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Someone',
			)
		);
		$this->assertIsInt( $profile );
		$this->expectException( \DomainException::class );
		$this->flags( $this->user( 'wiw_editor' ) )->set_featured( $profile, true );
	}

	public function test_REQ_188_featured_and_sponsored_changes_are_audited(): void {
		$story  = $this->story();
		$editor = $this->user( 'wiw_editor' );
		$flags  = $this->flags( $editor );
		$flags->set_featured( $story, true );
		$flags->set_sponsored( $story, 'Acme', 'home' );
		$flags->set_featured( $story, true );
		$rows = $this->rows();
		$this->assertCount( 2, $rows, 'A change that changes nothing writes no row.' );
		$this->assertSame( 'story.featured', $rows[0]['action'] );
		$this->assertSame( 'story.sponsored', $rows[1]['action'] );
		$this->assertSame( (string) $story, $rows[0]['subject_id'] );
		$this->assertSame( (string) $editor, (string) $rows[0]['actor_id'] );
	}
}
