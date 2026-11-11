<?php
/**
 * State on import (REQ-210, REQ-216, REQ-148). Needs the WordPress test suite. Plain TestCase because it needs the real state
 * table; its posts and rows are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\StateAssigner;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StatePolicy;
use WhoIsWho\Tests\TestCase;

final class StateAssignerTest extends TestCase {

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
		delete_option( SettingsService::OPTION_PREFIX . StateAssigner::SETTING );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		delete_option( SettingsService::OPTION_PREFIX . StateAssigner::SETTING );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A new profile post with no state yet.
	 *
	 * @param string $name Name.
	 */
	private function post( string $name ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * A validated record, as arrays.
	 *
	 * @param string $type      Entity type.
	 * @param bool   $duplicate Probable duplicate flag.
	 * @return array<string, mixed>
	 */
	private function record( string $type, bool $duplicate = false ): array {
		return array(
			'entity_type'  => $type,
			'tier'         => 'T1',
			'content_hash' => 'sha256:' . str_repeat( 'a', 64 ),
			'flags'        => array(
				'probable_duplicate' => $duplicate,
				'duplicate_of'       => array(),
				'flag_reasons'       => array(),
			),
		);
	}

	public function test_REQ_216_organisation_lands_as_pending_with_label(): void {
		$id    = $this->post( 'Import Org Qwv' );
		$state = ( new StateAssigner() )->assign( $id, $this->record( 'organisation' ), false, 7, 'B1' );
		$this->assertSame( PubState::PUBLISHED_PENDING, $state );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile, 'The page exists for a visitor.' );
		$this->assertSame( StatePolicy::PENDING_LABEL, $profile->label );
		$this->assertTrue( $profile->noindex );
	}

	public function test_REQ_210_individual_lands_as_imported_not_public(): void {
		foreach ( array( 'individual', 'entrepreneur' ) as $type ) {
			$id = $this->post( 'Import ' . $type . ' Qwv' );
			$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateAssigner() )->assign( $id, $this->record( $type ), false, 7, 'B1' ) );
			$this->assertNull( PublicProfile::find_by_id( $id ) );
		}
		$id = $this->post( 'Import Dup Org Qwv' );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateAssigner() )->assign( $id, $this->record( 'organisation', true ), false, 7, 'B1' ), 'A probable duplicate is not public.' );
	}

	public function test_REQ_210_setting_on_makes_nothing_public(): void {
		$this->assertTrue( StateAssigner::import_all_is_on( SettingsService::for_site() ), 'On by default until the launch safeguards pass.' );
		$id = $this->post( 'Import All Qwv' );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateAssigner() )->assign( $id, $this->record( 'organisation' ), true, 7, 'B1' ) );
		$this->assertNull( PublicProfile::find_by_id( $id ) );
	}

	public function test_REQ_210_only_an_administrator_changes_the_setting(): void {
		$denied = new SettingsService( SettingsService::default_registry(), null, static fn ( string $cap ): bool => 'manage_options' === $cap );
		$this->expectException( \DomainException::class );
		$denied->set( StateAssigner::SETTING, false );
	}

	public function test_REQ_210_a_record_cannot_choose_its_state(): void {
		$this->assertSame( PubState::PUBLISHED_PENDING, StateAssigner::decide( array_merge( $this->record( 'organisation' ), array( 'state' => 'published_reviewed' ) ), false ), 'Any state in a record is ignored here (the validator refuses it earlier).' );
	}

	public function test_REQ_148_importer_recorded_on_state_row(): void {
		$id = $this->post( 'Import Row Qwv' );
		( new StateAssigner() )->assign( $id, $this->record( 'organisation' ), false, 42, 'B-QWV', 'src_regulator' );
		$row = ( new StateRepository() )->get( $id );
		$this->assertNotNull( $row );
		$this->assertSame( 42, (int) $row['importer_id'] );
		$this->assertSame( 'B-QWV', $row['import_batch'] );
		$this->assertSame( 'import', $row['origin'] );
		$this->assertSame( 'src_regulator', $row['source'] );
		$this->assertSame( 'T1', $row['tier'] );
		$this->assertSame( str_repeat( 'a', 64 ), $row['content_hash'], 'The sha256: prefix is removed to fit the column.' );
	}
}
