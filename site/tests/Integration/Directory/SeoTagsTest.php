<?php
/**
 * SEO tags for profiles, lists and noindex pages (REQ-006, REQ-044, REQ-217). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Directory;

use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Seo\Output;
use WhoIsWho\Core\Directory\Seo\Tags;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Platform\Links\LinkRoute;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class SeoTagsTest extends TestCase {

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
		remove_all_filters( 'wiw_seo_list_context' );
		remove_all_filters( 'wiw_seo_noindex_request' );
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

	public function test_REQ_006_profile_has_unique_title_description_canonical_og(): void {
		$a    = $this->profile(
			'Ada Seo Qwv',
			'individual',
			true,
			array(
				'current_title'        => 'Chief Executive',
				'current_organisation' => 'Qwv Holdings',
				'bio'                  => 'Ada leads Qwv Holdings. She joined in 2010.',
			)
		);
		$b    = $this->profile(
			'Bola Seo Qwv',
			'individual',
			true,
			array(
				'current_title'        => 'Chair',
				'current_organisation' => 'Qwv Bank',
			)
		);
		$tags = new Tags();
		$one  = $tags->for_profile( $a );
		$two  = $tags->for_profile( $b );

		$this->assertSame( 'Ada Seo Qwv, Chief Executive | WHOISWHO', $one['title'] );
		$this->assertSame( 'Ada leads Qwv Holdings.', $one['description'], 'The first sentence of the biography.' );
		$this->assertStringContainsString( 'Chair at Qwv Bank', $two['description'], 'Else a line built from title and organisation.' );
		$this->assertNotSame( $one['title'], $two['title'] );
		$this->assertNotSame( $one['canonical'], $two['canonical'] );
		$this->assertSame( $a->url, $one['canonical'] );
		$this->assertSame( 'Ada Seo Qwv', $one['og']['og:title'] );
		$this->assertArrayHasKey( 'og:url', $one['og'] );
		$this->assertNull( $one['robots'] );

		$html = $tags->render( $one );
		$this->assertStringContainsString( '<link rel="canonical" href="' . esc_url( $a->url ) . '">', $html );
		$this->assertStringContainsString( 'property="og:title"', $html );
		add_filter( 'wiw_brand_name', static fn (): string => 'Brand From Theme' );
		$this->assertStringContainsString( '| Brand From Theme', ( new Tags() )->for_profile( $a )['title'] );
		remove_all_filters( 'wiw_brand_name' );
	}

	public function test_REQ_217_pending_profile_has_robots_meta_noindex(): void {
		$pending = $this->profile( 'Pending Seo Qwv', 'organisation', false, array( 'org_type' => 'Bank' ) );
		$tags    = new Tags();
		$built   = $tags->for_profile( $pending );
		$this->assertSame( 'noindex', $built['robots'] );
		$this->assertStringContainsString( '<meta name="robots" content="noindex">', $tags->render( $built ) );
		$this->assertStringContainsString( $pending->label, $built['description'], 'The label is in the card text.' );
		$this->assertStringContainsString( $pending->label, $built['og']['og:description'] );
	}

	public function test_REQ_006_share_card_has_no_trust_or_sponsored_label(): void {
		$reviewed = $this->profile( 'Card Seo Qwv', 'individual', true, array( 'current_title' => 'Founder' ) );
		$built    = ( new Tags() )->for_profile( $reviewed );
		$card     = strtolower( implode( ' ', $built['og'] ) );
		foreach ( array( 'sponsored', 'verified', 'featured', 'trusted' ) as $word ) {
			$this->assertStringNotContainsString( $word, $card );
		}
		$this->assertArrayNotHasKey( 'og:image', $built['og'], 'No image and no fallback means no image tag.' );
		add_filter( 'wiw_share_fallback_image', static fn (): string => 'https://example.org/brand-card.png' );
		$this->assertSame( 'https://example.org/brand-card.png', ( new Tags() )->for_profile( $reviewed )['og']['og:image'] );
		remove_all_filters( 'wiw_share_fallback_image' );
	}

	public function test_REQ_044_filtered_list_canonical_points_to_unfiltered(): void {
		$tags     = new Tags();
		$filtered = $tags->for_list( 'people', 'https://example.org/people/', 1 );
		$this->assertSame( 'https://example.org/people/', $filtered['canonical'], 'The filter lives in the address, the canonical does not.' );
		$this->assertSame( 'https://example.org/people/page/3/', $tags->for_list( 'people', 'https://example.org/people', 3 )['canonical'], 'A later page carries its own.' );
		$this->assertStringContainsString( 'People', $filtered['title'] );
	}

	public function test_REQ_217_form_and_token_pages_are_noindex(): void {
		global $wp_query;
		$output = new Output();
		$this->assertNull( $output->current(), 'An ordinary page is left to the theme.' );

		$wp_query->set( LinkRoute::QUERY_VAR, 'abcdefghijklmnopqrstuv' );
		$tags = $output->current();
		$this->assertNotNull( $tags );
		$this->assertSame( 'noindex, nofollow', $tags['robots'] );
		$wp_query->set( LinkRoute::QUERY_VAR, '' );

		add_filter( 'wiw_seo_noindex_request', '__return_true' );
		$this->assertSame( 'noindex, nofollow', $output->current()['robots'] );
		remove_all_filters( 'wiw_seo_noindex_request' );

		$wp_query->set( ProfileRoute::QUERY_VAR, 'not-public-qwv' );
		$this->assertSame( 'noindex, nofollow', $output->current()['robots'], 'A profile address that does not resolve says nothing about a profile.' );
	}

	public function test_REQ_006_profile_page_tags_come_from_the_read_model(): void {
		global $wp_query;
		$profile = $this->profile( 'Route Seo Qwv', 'individual', true, array( 'current_title' => 'Founder' ) );
		$wp_query->set( ProfileRoute::ID_VAR, $profile->id );
		$tags = ( new Output() )->current();
		$this->assertNotNull( $tags );
		$this->assertSame( $profile->url, $tags['canonical'] );
		( new StateMachine() )->transition( $profile->id, PubState::UNPUBLISHED, 'Takedown' );
		$after = ( new Output() )->current();
		$this->assertSame( 'noindex, nofollow', $after['robots'], 'Once not public, nothing describes the profile.' );
		$this->assertStringNotContainsString( 'Route Seo Qwv', (string) $after['title'] );
	}
}
