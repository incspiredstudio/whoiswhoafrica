<?php
/**
 * Approval flips noindex, markup and the sitemap together (M1-SEO-12; REQ-217, REQ-006, REQ-121): a pending profile is 200 with noindex,
 * no structured data, no sitemap entry and a share card that carries the pending label; one approval removes the noindex, adds the
 * markup and the sitemap entry, drops the label, and fires the purge hook in the same step. robots.txt allows pending addresses.
 * Needs the WordPress test suite and the real tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Search\SearchIndexer;
use WhoIsWho\Core\Directory\Seo\StructuredData;
use WhoIsWho\Core\Directory\Seo\Tags;
use WhoIsWho\Core\Directory\Sitemap\Sitemap;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Url\ProfileRoute;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Guard\Seo\Robots;
use WhoIsWho\Tests\TestCase;

final class ApprovalFlipTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete(
				SearchIndexer::table(),
				array(
					'item_type' => SearchIndexer::ITEM_TYPE,
					'item_id'   => $id,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		( new Sitemap() )->purge();
		parent::tearDown();
	}

	/**
	 * A pending organisation profile.
	 *
	 * @return array{id: int, slug: string}
	 */
	private function pending_profile(): array {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Approval Flip Qwv',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		return array(
			'id'   => $id,
			'slug' => (string) get_post_field( 'post_name', $id ),
		);
	}

	/**
	 * What a visitor and a crawler see for the profile now.
	 *
	 * @param array{id: int, slug: string} $p Profile.
	 * @return array{status: int, noindex_header: bool, noindex_meta: bool, schema: bool, sitemap: bool, label_in_card: bool}
	 */
	private function seen( array $p ): array {
		$decision = ( new ProfileRoute() )->resolve( $p['slug'] );
		$headers  = ProfileRoute::headers_for( $decision );
		$public   = PublicProfile::find_by_id( $p['id'] );
		$this->assertNotNull( $public, 'The profile is public in both states.' );
		$tags    = ( new Tags() )->for_profile( $public );
		$sitemap = ( new Sitemap() )->respond( 'sitemap-profiles-1.xml' );
		$card    = (string) wp_json_encode( $tags['og'] );
		return array(
			'status'         => (int) $decision['status'],
			'noindex_header' => isset( $headers['X-Robots-Tag'] ),
			'noindex_meta'   => 'noindex' === $tags['robots'],
			'schema'         => null !== ( new StructuredData() )->for_profile( $public ),
			'sitemap'        => null !== $sitemap && false !== strpos( $sitemap['body'], '/profile/' . $p['slug'] . '/' ),
			'label_in_card'  => false !== strpos( $card, 'Human review pending' ),
		);
	}

	public function test_REQ_217_approval_flips_noindex_markup_sitemap(): void {
		$p      = $this->pending_profile();
		$before = $this->seen( $p );
		$this->assertSame( 200, $before['status'] );
		$this->assertTrue( $before['noindex_header'], 'Pending: X-Robots-Tag noindex.' );
		$this->assertTrue( $before['noindex_meta'], 'Pending: robots meta noindex.' );
		$this->assertFalse( $before['schema'], 'Pending: no structured data.' );
		$this->assertFalse( $before['sitemap'], 'Pending: no sitemap entry.' );
		$this->assertTrue( $before['label_in_card'], 'Pending: the share card carries the label (REQ-216).' );

		$fired = 0;
		$count = static function () use ( &$fired ): void {
			++$fired;
		};
		add_action( 'wiw_profile_state_changed', $count );
		( new StateMachine() )->transition( $p['id'], PubState::PUBLISHED_REVIEWED, '', 5 );
		remove_action( 'wiw_profile_state_changed', $count );

		$after = $this->seen( $p );
		$this->assertSame( 200, $after['status'] );
		$this->assertFalse( $after['noindex_header'], 'Approved: noindex header removed.' );
		$this->assertFalse( $after['noindex_meta'], 'Approved: noindex meta removed.' );
		$this->assertTrue( $after['schema'], 'Approved: structured data added.' );
		$this->assertTrue( $after['sitemap'], 'Approved: sitemap entry added without a manual purge.' );
		$this->assertFalse( $after['label_in_card'], 'Approved: the pending label is gone.' );
		$this->assertSame( 1, $fired, 'One approval fires one state-change event, which drives the purge and the sitemap cache.' );
	}

	public function test_REQ_217_robots_txt_allows_pending_addresses(): void {
		$text = Robots::text( 'https://example.org/sitemap.xml' );
		$this->assertStringContainsString( "Allow: /\n", $text );
		$this->assertStringNotContainsString( 'Disallow', $text, 'A Disallow would stop crawlers from seeing the noindex.' );
		$this->assertStringContainsString( 'Sitemap: https://example.org/sitemap.xml', $text );
	}
}
