<?php
/**
 * Consent for complimentary listings (REQ-175, REQ-233, REQ-058). Needs the WordPress test suite and the real state tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Consent\ListingConsent;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Links\LinkStore;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Links\WpdbLinkStore;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Review\StateException;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ListingConsentTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		Schema::create_table( 'wiw_signed_links', LinkStore::TABLE_DEFINITION );
		Schema::create_table( ListingConsent::TABLE, ListingConsent::TABLE_DEFINITION );
		$wpdb->query( 'TRUNCATE TABLE ' . ListingConsent::table() ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'wiw_signed_links' ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		ListingConsent::register_gate();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		remove_all_filters( 'wiw_profile_transition_refusal' );
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		parent::tearDown();
	}

	/**
	 * A profile in Imported, not public.
	 *
	 * @param string $origin Origin.
	 */
	private function profile( string $origin ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Consent Person',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter(
			$id,
			PubState::IMPORTED_NOT_PUBLIC,
			array(
				'entity_type' => 'individual',
				'origin'      => $origin,
			)
		);
		return $id;
	}

	public function test_REQ_175_complimentary_listing_cannot_publish_without_consent(): void {
		$id      = $this->profile( 'complimentary' );
		$machine = new StateMachine();
		foreach ( array( PubState::PUBLISHED_PENDING, PubState::PUBLISHED_REVIEWED ) as $target ) {
			try {
				$machine->transition( $id, $target, '', 1 );
				$this->fail( 'Published without consent.' );
			} catch ( StateException $e ) {
				$this->assertStringContainsString( 'consent', $e->getMessage() );
			}
		}
		$consent = new ListingConsent();
		$consent->decide( $id, ListingConsent::DECLINED );
		$this->expectException( StateException::class );
		try {
			$machine->transition( $id, PubState::PUBLISHED_PENDING, '', 1 );
		} finally {
			$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->get( $id )['pub_state'], 'A person who declines stays unpublished.' );
		}
	}

	public function test_REQ_175_a_granted_consent_lets_it_publish_and_other_origins_are_unaffected(): void {
		$id = $this->profile( 'complimentary' );
		( new ListingConsent() )->decide( $id, ListingConsent::GRANTED );
		( new StateMachine() )->transition( $id, PubState::PUBLISHED_PENDING, '', 1 );
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->get( $id )['pub_state'] );
		$other = $this->profile( 'import' );
		( new StateMachine() )->transition( $other, PubState::PUBLISHED_PENDING, '', 1 );
		$this->assertSame( PubState::PUBLISHED_PENDING, ( new StateRepository() )->get( $other )['pub_state'] );
	}

	public function test_REQ_175_consent_stored_with_timestamp_and_policy_version(): void {
		$id = $this->profile( 'complimentary' );
		( new ListingConsent( static fn (): string => '2026-11-12 11:00:00' ) )->decide( $id, ListingConsent::GRANTED );
		$row = ( new ListingConsent() )->find( $id );
		$this->assertNotNull( $row );
		$this->assertSame( 'granted', $row['decision'] );
		$this->assertSame( '2026-11-12 11:00:00', $row['decided_at'] );
		$this->assertNotSame( '', $row['policy_version'] );
		$this->expectException( \InvalidArgumentException::class );
		( new ListingConsent() )->decide( $this->profile( 'import' ), ListingConsent::GRANTED );
	}

	public function test_REQ_233_consent_link_is_single_use_and_bound_to_record(): void {
		$a       = $this->profile( 'complimentary' );
		$b       = $this->profile( 'complimentary' );
		$links   = new SignedLinks( new WpdbLinkStore() );
		$consent = new ListingConsent();
		$consent->register_links( $links );
		$tokens = $consent->issue_links( $links, $a, 'person@example.test' );
		$url    = $links->url( $tokens['accept'] );
		$this->assertStringNotContainsString( 'person@example.test', $url );
		$this->assertMatchesRegularExpression( '#/link/[A-Za-z0-9_-]{43}$#', $url, 'The URL carries only an opaque token.' );
		$this->assertNotNull( $links->use_link( $tokens['accept'] ) );
		$this->assertTrue( $consent->has_granted( $a ) );
		$this->assertFalse( $consent->has_granted( $b ), 'A link is bound to its one record.' );
		$this->assertNull( $links->use_link( $tokens['accept'] ), 'A second use is refused.' );
		$this->assertNull( $links->use_link( $tokens['decline'] ), 'The other choice is revoked once one is used.' );
		$this->assertTrue( $consent->has_granted( $a ) );
	}

	public function test_REQ_233_declining_through_the_link_is_recorded(): void {
		$id      = $this->profile( 'complimentary' );
		$links   = new SignedLinks( new WpdbLinkStore() );
		$consent = new ListingConsent();
		$consent->register_links( $links );
		$tokens = $consent->issue_links( $links, $id, 'person@example.test' );
		$this->assertNotNull( $links->use_link( $tokens['decline'] ) );
		$this->assertSame( 'declined', $consent->find( $id )['decision'] );
		$this->assertFalse( $consent->has_granted( $id ) );
		$this->assertNull( $links->use_link( $tokens['accept'] ) );
	}

	public function test_REQ_233_an_expired_link_does_nothing(): void {
		$id      = $this->profile( 'complimentary' );
		$old     = new SignedLinks( new WpdbLinkStore(), static fn (): int => time() - 30 * 86400 );
		$consent = new ListingConsent();
		$token   = $old->issue( ListingConsent::PURPOSE_ACCEPT, ListingConsent::SUBJECT, (string) $id );
		$now     = new SignedLinks( new WpdbLinkStore() );
		$consent->register_links( $now );
		$this->assertNull( $now->use_link( $token ) );
		$this->assertFalse( $consent->has_granted( $id ) );
	}
}
