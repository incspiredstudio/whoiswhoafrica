<?php
/**
 * The "Add profile to nominations" staff form (REQ-267, REQ-268). Needs the WordPress test suite and the real profile and awards
 * tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\Admin\AddProfileForm;
use WhoIsWho\Core\Awards\Admin\AddProfileValidator;
use WhoIsWho\Core\Awards\Admin\ProfilePicker;
use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Awards\PublicNominee;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\WpTestCase;

final class AddProfileFormTest extends WpTestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Build the real tables once, before the test suite's temporary-table mode starts (this class sorts early).
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		remove_role( 'wiw_reviewer' );
		remove_role( 'wiw_editor' );
		delete_option( RoleSync::OPTION_SYNCED );
		( new RoleSync( new CapabilityCatalogue() ) )->sync();
		foreach ( array( AwardsTables::NOMINATIONS, AwardsTables::CATEGORIES, AwardsTables::CHANGES ) as $name ) {
			$wpdb->query( 'DELETE FROM ' . AwardsTables::table( $name ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_editor' ) ) );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function profile( string $name, string $state ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		if ( PubState::PUBLISHED_REVIEWED === $state ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		if ( PubState::UNPUBLISHED === $state ) {
			$machine->transition( $id, PubState::UNPUBLISHED, 'test', 1 );
		}
		return $id;
	}

	private function category(): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			AwardsTables::table( AwardsTables::CATEGORIES ),
			array(
				'group_key'  => 'technology-innovation',
				'title'      => 'Digital Pioneer',
				'created_at' => '2026-11-12 10:00:00',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function form(): AddProfileForm {
		return new AddProfileForm( NominationService::for_site() );
	}

	private function nominations(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . AwardsTables::table( AwardsTables::NOMINATIONS ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	public function test_REQ_267_add_profile_offers_only_existing_profiles(): void {
		$this->profile( 'Pickme Reviewed Zq', PubState::PUBLISHED_REVIEWED );
		$this->profile( 'Pickme Pending Zq', PubState::PUBLISHED_PENDING );
		$this->profile( 'Pickme Unpublished Zq', PubState::UNPUBLISHED );
		$names = array_column( ( new ProfilePicker() )->search( 'Pickme' ), 'name' );
		$this->assertSame( array( 'Pickme Pending Zq', 'Pickme Reviewed Zq' ), $names, 'reviewed and pending profiles only' );
		$this->assertSame( array(), ( new ProfilePicker() )->search( 'x' ), 'a search needs two characters' );
		$this->assertSame( array(), ( new ProfilePicker() )->search( 'Nobody Anywhere' ) );

		$this->category();
		$html = $this->form()->html( 'Pickme' );
		$this->assertStringContainsString( 'name="profile_id"', $html );
		$this->assertStringContainsString( 'Pickme Reviewed Zq', $html );
		$this->assertStringNotContainsString( 'Pickme Unpublished Zq', $html );
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]+name="(nominee_name|name|profile_name)"/', $html, 'there is no free-text nominee name field' );
		$this->assertStringContainsString( 'Only existing profiles can be nominated', $this->form()->html( 'Nobody Anywhere' ) );
		$this->assertStringNotContainsString( '<form method="post"', $this->form()->html( '' ), 'nothing to submit until a profile is found' );

		// A made-up or unpublished profile id is refused even when someone posts it by hand.
		$category = $this->category();
		foreach ( array( 99999999, $this->profile( 'Pickme Gone Zq', PubState::UNPUBLISHED ) ) as $bad ) {
			$notice = $this->form()->handle(
				new Request(
					array(
						'profile_id'  => (string) $bad,
						'category_id' => (string) $category,
					)
				)
			);
			$this->assertStringContainsString( 'existing', $notice );
		}
		$this->assertSame( array(), $this->nominations() );
	}

	public function test_REQ_267_a_profile_in_the_review_queue_can_be_nominated_but_not_set_live(): void {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Pickme Queued Zq',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => 'individual' ) );
		$found = ( new ProfilePicker() )->search( 'Pickme Queued' );
		$this->assertSame( array( $id ), array_column( $found, 'id' ) );
		$category = $this->category();
		$this->assertStringContainsString( 'in the review queue, not public', $this->form()->html( 'Pickme Queued' ) );

		$service    = NominationService::for_site();
		$nomination = $service->add_profile( $id, $category );
		$this->assertSame( 'not_live', $service->find( $nomination )['status'] );
		$this->expectException( \DomainException::class );
		$service->set_status( $nomination, 'live' );
	}

	public function test_REQ_267_create_profile_shortcut_opens_add_profile_with_the_name(): void {
		$html = $this->form()->html( 'Nobody Anywhere' );
		$this->assertStringContainsString( 'wiw-create-profile', $html );
		$this->assertStringContainsString( 'page=wiw-profile-add', $html );
		$this->assertStringContainsString( 'return=nominations', $html );
		$this->assertStringContainsString( 'Nobody', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'wiw_reviewer' ) ) );
		$this->assertStringNotContainsString( 'wiw-create-profile', $this->form()->html( 'Nobody Anywhere' ), 'only for a user who may add profiles' );
	}

	public function test_REQ_268_add_profile_stores_the_contact_email_for_staff_only(): void {
		$profile  = $this->profile( 'Staffadd Pending Zq', PubState::PUBLISHED_PENDING );
		$category = $this->category();
		$notice   = $this->form()->handle(
			new Request(
				array(
					'profile_id'    => (string) $profile,
					'category_id'   => (string) $category,
					'note'          => 'Strong record',
					'contact_email' => 'press.contact@example.test',
				)
			)
		);
		$this->assertStringContainsString( 'Not live', $notice );
		$this->assertStringNotContainsString( 'press.contact', $notice, 'the result message never repeats the address' );
		$rows = $this->nominations();
		$this->assertCount( 1, $rows );
		$this->assertSame( (string) $profile, (string) $rows[0]['profile_id'] );
		$this->assertSame( (string) $category, (string) $rows[0]['category_id'] );
		$this->assertSame( 'nominated', $rows[0]['stage'] );
		$this->assertSame( 'not_live', $rows[0]['status'] );
		$this->assertSame( 'press.contact@example.test', $rows[0]['contact_email'] );
		$this->assertStringNotContainsString( 'press.contact', $this->form()->html( 'Staffadd' ), 'the form never shows a stored address' );
		$this->assertNull( PublicNominee::find( PublicNominee::ref_for( (int) $rows[0]['id'] ) ), 'a pending profile is not public' );

		try {
			NominationService::for_site()->set_status( (int) $rows[0]['id'], 'live' );
			$this->fail( 'A pending-review profile was set Live.' );
		} catch ( \DomainException $refusal ) {
			$this->assertStringContainsString( 'reviewed', $refusal->getMessage() );
		}
	}

	public function test_REQ_267_add_profile_form_validates_and_checks_nonce(): void {
		$validator = new AddProfileValidator();
		$bad       = $validator->validate(
			new FormInput(
				array(
					'profile_id'    => 'abc',
					'category_id'   => '',
					'contact_email' => 'not-an-email',
				)
			)
		);
		$this->assertFalse( $bad->is_valid() );
		$this->assertSame( 'invalid', $bad->errors()['profile_id'] );
		$this->assertArrayHasKey( 'category_id', $bad->errors() );
		$this->assertArrayHasKey( 'contact_email', $bad->errors() );
		$good = $validator->validate(
			new FormInput(
				array(
					'profile_id'    => '12',
					'category_id'   => '3',
					'contact_email' => '',
				)
			)
		);
		$this->assertTrue( $good->is_valid() );

		$this->assertStringContainsString( 'Choose a profile', $this->form()->handle( new Request( array( 'profile_id' => 'abc' ) ) ) );
		$this->assertSame( array(), $this->nominations() );

		$this->assertSame( ActionGuard::REFUSED_NONCE, ActionGuard::check( AddProfileForm::ACTION, AddProfileForm::CAP, new Request( array() ) ), 'no nonce, no action' );
		$nonce = ActionGuard::nonce_for( AddProfileForm::ACTION );
		$this->assertNull( ActionGuard::check( AddProfileForm::ACTION, AddProfileForm::CAP, new Request( array( '_wpnonce' => $nonce ) ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( ActionGuard::REFUSED_CAPABILITY, ActionGuard::check( AddProfileForm::ACTION, AddProfileForm::CAP, new Request( array( '_wpnonce' => ActionGuard::nonce_for( AddProfileForm::ACTION ) ) ) ) );
		$this->assertStringContainsString(
			'not allowed',
			$this->form()->handle(
				new Request(
					array(
						'profile_id'  => '1',
						'category_id' => '1',
					)
				)
			)
		);
	}
}
