<?php
/**
 * The event post type, its fields and its validator (REQ-262, REQ-263, REQ-244). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Events;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Events\EventValidator;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Tests\TestCase;

final class EventsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		EventPostType::register();
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

	/**
	 * A valid form submission.
	 *
	 * @param array<string, string> $override Fields to change.
	 * @return array<string, string>
	 */
	private function form( array $override = array() ): array {
		return array_merge(
			array(
				'start'            => '2027-03-04T10:00',
				'end'              => '2027-03-04T17:00',
				'online'           => '',
				'venue'            => 'Eko Hotel',
				'city'             => 'Lagos',
				'country'          => 'Nigeria',
				'organiser'        => 'Africa Summit Ltd',
				'registration_url' => 'https://example.com/register',
				'price_note'       => 'From 50,000 naira',
			),
			$override
		);
	}

	public function test_REQ_244_wiw_manage_events_is_grantable(): void {
		$reviewer = $this->user_with_role( 'wiw_reviewer' );
		$editor   = $this->user_with_role( 'wiw_editor' );
		$post_id  = wp_insert_post(
			array(
				'post_type'   => EventPostType::TYPE,
				'post_title'  => 'Summit',
				'post_status' => 'draft',
			)
		);
		wp_set_current_user( $reviewer );
		$this->assertFalse( current_user_can( 'edit_post', $post_id ) );
		$this->assertFalse( current_user_can( 'publish_post', $post_id ) );
		wp_set_current_user( 0 );
		( new \WP_User( $reviewer ) )->add_cap( 'wiw_manage_events' );
		wp_set_current_user( $reviewer );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
		wp_set_current_user( $editor );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
		$this->assertTrue( current_user_can( 'delete_post', $post_id ) );
		$this->assertTrue( current_user_can( 'publish_posts' ) );
		$type = get_post_type_object( EventPostType::TYPE );
		$this->assertNotNull( $type );
		$this->assertSame( 'wiw_manage_events', $type->cap->publish_posts );
	}

	public function test_REQ_262_event_type_is_registered_for_events_without_rest_or_feed(): void {
		$type = get_post_type_object( EventPostType::TYPE );
		$this->assertNotNull( $type );
		$this->assertFalse( $type->show_in_rest );
		$this->assertNotEmpty( $type->has_archive );
		$this->assertSame( 'events', $type->rewrite['slug'] );
		$this->assertFalse( $type->rewrite['feeds'] );
		$this->assertTrue( post_type_supports( EventPostType::TYPE, 'revisions' ) );
		$this->assertFalse( post_type_supports( EventPostType::TYPE, 'sticky' ) );
		$this->assertTrue( $type->exclude_from_search );
	}

	public function test_REQ_262_event_requires_start_and_city_unless_online(): void {
		$validator = new EventValidator();
		$ok        = $validator->validate( $this->form() );
		$this->assertSame( array(), $ok['errors'] );
		$this->assertSame( '2027-03-04 10:00', $ok['values']['start'] );

		$no_start = $validator->validate( $this->form( array( 'start' => '' ) ) );
		$this->assertArrayHasKey( 'start', $no_start['errors'] );

		$no_city = $validator->validate( $this->form( array( 'city' => '' ) ) );
		$this->assertArrayHasKey( 'city', $no_city['errors'] );

		$no_country = $validator->validate( $this->form( array( 'country' => '' ) ) );
		$this->assertArrayHasKey( 'country', $no_country['errors'] );

		$online = $validator->validate(
			$this->form(
				array(
					'online'  => '1',
					'city'    => '',
					'country' => '',
					'venue'   => '',
				)
			)
		);
		$this->assertSame( array(), $online['errors'] );
		$this->assertSame( 'Online', $online['values']['venue'] );
	}

	public function test_REQ_262_end_cannot_be_before_start(): void {
		$result = ( new EventValidator() )->validate( $this->form( array( 'end' => '2027-03-03T10:00' ) ) );
		$this->assertArrayHasKey( 'end', $result['errors'] );
	}

	public function test_REQ_262_registration_link_must_be_external_https(): void {
		$validator = new EventValidator();
		foreach ( array( 'http://example.com/r', 'javascript:alert(1)', 'not a url', '/relative' ) as $bad ) {
			$result = $validator->validate( $this->form( array( 'registration_url' => $bad ) ) );
			$this->assertArrayHasKey( 'registration_url', $result['errors'], $bad );
		}
		$own = $validator->validate( $this->form( array( 'registration_url' => home_url( '/x' ) ) ) );
		$this->assertArrayHasKey( 'registration_url', $own['errors'], 'Must be external.' );
		$empty = $validator->validate( $this->form( array( 'registration_url' => '' ) ) );
		$this->assertSame( array(), $empty['errors'], 'The link is optional.' );
	}

	public function test_REQ_262_price_note_is_plain_text(): void {
		$result = ( new EventValidator() )->validate( $this->form( array( 'price_note' => '<b>Free</b> <script>x</script>' ) ) );
		$this->assertStringNotContainsString( '<', $result['values']['price_note'] );
	}

	public function test_REQ_263_event_has_no_sponsor_field(): void {
		$this->assertFalse( post_type_supports( EventPostType::TYPE, 'sticky' ) );
		$fields = ( new EventValidator() )->field_names();
		foreach ( $fields as $name ) {
			$this->assertDoesNotMatchRegularExpression( '/sponsor|featured|sticky/i', $name );
		}
		$this->assertNotContains( 'category', get_object_taxonomies( EventPostType::TYPE ) );
	}

	public function test_REQ_262_industry_taxonomy_is_attached_when_it_exists(): void {
		register_taxonomy( 'wiw_industry', array(), array( 'public' => true ) );
		EventPostType::attach_industry();
		$this->assertContains( 'wiw_industry', get_object_taxonomies( EventPostType::TYPE ) );
	}
}
