<?php
/**
 * Profile field storage: meta registered only from the allowlist, and forbidden writes refused (REQ-139, REQ-140).
 * Needs the WordPress test suite. Plain TestCase because it needs the real state table; its posts are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileFieldsTest extends TestCase {

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
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		parent::tearDown();
	}

	private function profile( string $type ): int {
		$id            = wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Field test',
			)
		);
		$this->posts[] = (int) $id;
		( new StateMachine() )->enter( (int) $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => $type ) );
		return (int) $id;
	}

	public function test_REQ_140_forbidden_attributes_have_no_storage(): void {
		$registered = array_keys( get_registered_meta_keys( 'post', ProfileType::POST_TYPE ) );
		$allow      = new FieldAllowlist();
		foreach ( $allow->forbidden() as $key ) {
			$this->assertNotContains( 'wiw_' . $key, $registered, "no meta key is registered for {$key}" );
		}
		foreach ( $allow->all_keys() as $key ) {
			if ( 'name' !== $key ) {
				$this->assertContains( 'wiw_' . $key, $registered );
			}
		}

		$id = $this->profile( 'individual' );
		foreach ( array( 'wiw_email', 'wiw_phone', 'wiw_date_of_birth', 'wiw_age', 'wiw_religion' ) as $meta_key ) {
			$this->assertFalse( add_post_meta( $id, $meta_key, 'x' ), "{$meta_key} cannot be added" );
			$this->assertFalse( update_post_meta( $id, $meta_key, 'x' ), "{$meta_key} cannot be updated" );
			$this->assertSame( '', get_post_meta( $id, $meta_key, true ) );
		}
		$fields = new ProfileFields();
		$this->expectException( \InvalidArgumentException::class );
		$fields->set( $id, 'email', 'a@b.c' );
	}

	public function test_REQ_139_allowlist_depends_on_the_profiles_entity_type(): void {
		$person = $this->profile( 'individual' );
		$org    = $this->profile( 'organisation' );
		$fields = new ProfileFields();
		$fields->set( $person, 'current_title', 'Chief Executive' );
		$fields->set( $person, 'public_record_roles', array( 'Director' ) );
		$fields->set( $org, 'year_founded', 1999 );
		$fields->set( $org, 'name', 'Renamed Org' );
		$this->assertSame( 'Chief Executive', get_post_meta( $person, 'wiw_current_title', true ) );
		$this->assertSame( '["Director"]', get_post_meta( $person, 'wiw_public_record_roles', true ) );
		$this->assertSame( 'Renamed Org', get_the_title( $org ) );

		$this->assertFalse( update_post_meta( $person, 'wiw_year_founded', '1999' ), 'A person cannot hold an organisation field.' );
		$this->assertFalse( add_post_meta( $org, 'wiw_current_title', 'x' ), 'An organisation cannot hold a person field.' );
		try {
			$fields->set( $person, 'year_founded', 1999 );
			$this->fail( 'Refused' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( '', get_post_meta( $person, 'wiw_year_founded', true ) );
		}
	}
}
