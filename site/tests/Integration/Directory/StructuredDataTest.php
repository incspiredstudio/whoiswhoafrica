<?php
/**
 * Schema.org structured data for profiles (REQ-006, REQ-139, REQ-140, REQ-217). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Seo\StructuredData;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class StructuredDataTest extends TestCase {

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
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb, $wp_query;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts          = array();
		$wp_query->query_vars = array();
		parent::tearDown();
	}

	/**
	 * Make a profile with sourced fields.
	 *
	 * @param string               $name   Name.
	 * @param string               $type   Entity type.
	 * @param bool                 $review Whether it is reviewed.
	 * @param array<string, mixed> $fields Fields.
	 */
	private function profile( string $name, string $type, bool $review, array $fields = array() ): PublicProfile {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => $type ) );
		$store = new ProfileFields();
		$prov  = new ProvenanceRepository();
		foreach ( $fields as $key => $value ) {
			$store->set( $id, $key, $value );
			$prov->record(
				$id,
				array(
					'field'        => $key,
					'source_url'   => 'https://example.org/src',
					'retrieved_at' => '2026-11-10T09:00:00Z',
					'confidence'   => 'high',
					'method'       => 'import',
				)
			);
		}
		if ( $review ) {
			$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		}
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		return $profile;
	}

	public function test_REQ_006_reviewed_profile_outputs_valid_person_or_organization(): void {
		$person = $this->profile(
			'Ada Schema Qwv',
			'individual',
			true,
			array(
				'current_title'        => 'Chief Economist',
				'current_organisation' => 'Qwv Bank',
				'industry'             => 'Banking',
				'official_website'     => 'https://ada.example.org',
				'published_awards'     => array( 'Award One' ),
				'public_record_roles'  => array( 'Board member' ),
			)
		);
		$data   = ( new StructuredData() )->for_profile( $person );
		$this->assertIsArray( $data );
		$this->assertSame( 'https://schema.org', $data['@context'] );
		$this->assertSame( 'Person', $data['@type'] );
		$this->assertSame( 'Ada Schema Qwv', $data['name'] );
		$this->assertSame( 'Chief Economist', $data['jobTitle'] );
		$this->assertSame( 'Qwv Bank', $data['worksFor']['name'] );
		$this->assertSame( array( 'Award One' ), $data['award'] );
		$this->assertSame( array( 'https://ada.example.org' ), $data['sameAs'] );
		$this->assertSame( $person->url, $data['url'] );

		$org  = $this->profile(
			'Qwv Holdings',
			'organisation',
			true,
			array(
				'website'      => 'https://qwv.example.org',
				'year_founded' => 1999,
				'summary'      => 'A holding company.',
			)
		);
		$data = ( new StructuredData() )->for_profile( $org );
		$this->assertIsArray( $data );
		$this->assertSame( 'Organization', $data['@type'] );
		$this->assertSame( '1999', (string) $data['foundingDate'] );
		$this->assertSame( 'A holding company.', $data['description'] );

		$html = ( new StructuredData() )->render( $person );
		$this->assertStringStartsWith( '<script type="application/ld+json">', $html );
		$body = substr( trim( $html ), strlen( '<script type="application/ld+json">' ), -strlen( '</script>' ) );
		$this->assertSame( 'Person', json_decode( $body, true )['@type'], 'the script body is valid JSON' );
		$this->assertSame( 1, substr_count( $html, '</script>' ), 'a value cannot close the script tag' );
	}

	public function test_REQ_217_pending_profile_outputs_no_structured_data(): void {
		$pending = $this->profile( 'Pending Schema Qwv', 'individual', false, array( 'current_title' => 'Director' ) );
		$this->assertNull( ( new StructuredData() )->for_profile( $pending ) );
		$this->assertSame( '', ( new StructuredData() )->render( $pending ) );
	}

	public function test_REQ_217_other_plugins_cannot_add_schema_to_a_page_without_it(): void {
		global $wp_query;
		$pending = $this->profile( 'Pending Foreign Qwv', 'individual', false );
		$service = new StructuredData();
		$wp_query->set( ProfileRoute::ID_VAR, $pending->id );
		$this->assertFalse( $service->block_foreign_output( true ), 'a Yoast-style on/off filter is switched off' );
		$this->assertSame( array(), $service->block_foreign_graph( array( array( '@type' => 'WebPage' ) ) ), 'a graph is emptied' );

		$reviewed = $this->profile( 'Reviewed Foreign Qwv', 'individual', true );
		$wp_query->set( ProfileRoute::ID_VAR, $reviewed->id );
		$this->assertTrue( $service->block_foreign_output( true ), 'a page that carries schema is left alone' );
	}

	public function test_REQ_140_structured_data_has_no_forbidden_field(): void {
		$person = $this->profile(
			'Eve Forbidden Qwv',
			'individual',
			true,
			array( 'current_title' => 'Director' )
		);
		update_post_meta( $person->id, 'wiw_email', 'eve@example.org' );
		update_post_meta( $person->id, 'wiw_phone', '0800' );
		update_post_meta( $person->id, 'wiw_date_of_birth', '1980-01-01' );
		$json = (string) wp_json_encode( ( new StructuredData() )->for_profile( PublicProfile::find_by_id( $person->id ) ) );
		foreach ( array( 'eve@example.org', '0800', '1980', 'email', 'telephone', 'birthDate', 'address' ) as $forbidden ) {
			$this->assertStringNotContainsString( $forbidden, $json );
		}
	}
}
