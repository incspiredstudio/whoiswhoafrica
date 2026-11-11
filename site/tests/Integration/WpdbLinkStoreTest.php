<?php
/**
 * The signed-link table: single use is atomic (REQ-233). Needs WordPress and a database.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Platform\Links\LinkStore;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Links\WpdbLinkStore;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class WpdbLinkStoreTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wiw_signed_links' ); // phpcs:ignore WordPress.DB
		Schema::create_table( 'wiw_signed_links', LinkStore::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wiw_signed_links' ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	public function test_REQ_233_table_enforces_single_use_and_stores_only_the_hash(): void {
		global $wpdb;
		$links = new SignedLinks( new WpdbLinkStore() );
		$links->register( 'claim', static fn ( array $link ): string => 'ok ' . $link['subject_id'] );
		$token = $links->issue( 'claim', 'profile', '99', 'jane@example.org' );

		$stored = (string) $wpdb->get_var( 'SELECT token_hash FROM ' . $wpdb->prefix . 'wiw_signed_links' ); // phpcs:ignore WordPress.DB
		$this->assertSame( SignedLinks::hash( $token ), $stored );
		$this->assertNotSame( $token, $stored );

		$this->assertNotNull( $links->peek( $token ) );
		$this->assertNotNull( $links->peek( $token ), 'Peeking does not consume.' );

		$store = new WpdbLinkStore();
		$now   = gmdate( 'Y-m-d H:i:s' );
		$this->assertTrue( $store->consume( $stored, $now ) );
		$this->assertFalse( $store->consume( $stored, $now ), 'Only one consume wins.' );
		$this->assertNull( $links->use_link( $token ) );
	}

	public function test_REQ_233_spent_links_are_purged(): void {
		$links = new SignedLinks( new WpdbLinkStore(), static fn (): int => time() - 100 * 86400 );
		$links->issue( 'claim', 'profile', '1' );
		$this->assertSame( 1, ( new SignedLinks( new WpdbLinkStore() ) )->purge_spent( 30 ) );
	}
}
