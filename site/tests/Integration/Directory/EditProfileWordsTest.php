<?php
/**
 * The Edit profile screen shows words, not codes: the entity type, the state, the origin, the field names in the sources table,
 * the revision "via" column and the list names (issue 806; REQ-084, REQ-125, REQ-244). Needs the WordPress test suite and the
 * real tables, so it is a plain TestCase that cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Admin\ProfileEditScreen;
use WhoIsWho\Core\Directory\Edit\ProfileEditor;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Admin\Labels;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class EditProfileWordsTest extends TestCase {

	private int $profile = 0;

	private int $user = 0;

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
		if ( $this->profile > 0 ) {
			wp_delete_post( $this->profile, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $this->profile ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $this->profile ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( $this->user > 0 ) {
			wp_delete_user( $this->user );
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_REQ_084_labels_name_origins_and_methods_in_words(): void {
		$this->assertSame( 'Imported', Labels::origin( 'import' ) );
		$this->assertSame( 'Added by hand', Labels::origin( 'manual' ) );
		$this->assertSame( 'Some origin', Labels::origin( 'some_origin' ) );
		$this->assertSame( '', Labels::origin( '' ) );
		$this->assertSame( 'Staff edit', Labels::method( 'edit' ) );
		$this->assertSame( 'AI draft', Labels::method( 'ai' ) );
	}

	public function test_REQ_125_edit_profile_shows_words_not_codes(): void {
		$this->user = (int) wp_insert_user(
			array(
				'user_login' => 'words_' . bin2hex( random_bytes( 4 ) ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $this->user );
		$this->profile = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Words test person',
			)
		);
		( new StateMachine() )->enter(
			$this->profile,
			PubState::IMPORTED_NOT_PUBLIC,
			array(
				'entity_type' => 'individual',
				'origin'      => 'import',
			)
		);
		( new ProvenanceRepository() )->record(
			$this->profile,
			array(
				'field'        => 'published_awards',
				'source_url'   => 'https://example.org/words-source',
				'retrieved_at' => '2026-11-10T09:00:00Z',
				'confidence'   => 'high',
				'method'       => 'import',
				'source_hash'  => '',
			)
		);
		( new ProfileEditor() )->edit( $this->profile, array( 'current_title' => 'Composer' ), 'Fix', $this->user );

		$html = ( new ProfileEditScreen() )->html( new Request( array( 'profile_id' => (string) $this->profile ) ) );
		$this->assertStringContainsString( '<p>' . Labels::entity_type( 'individual' ) . ' · Imported, not public</p>', $html );
		$this->assertStringContainsString( '<td>Imported</td>', $html, 'the origin in words' );
		$this->assertStringContainsString( '<th scope="row">Published awards</th>', $html, 'the field label in the sources table' );
		$this->assertStringContainsString( '<td>Staff edit</td>', $html, 'the revision history says how' );
		foreach ( array( 'individual /', 'imported_not_public', 'published_reviewed', '<td>import</td>', '>published_awards<', '<td>edit</td>', '>wiw_place' ) as $code ) {
			$this->assertStringNotContainsString( $code, $html, $code );
		}
	}
}
