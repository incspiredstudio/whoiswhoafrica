<?php
/**
 * The duplicates matcher (REQ-123, REQ-145, REQ-146). Needs the WordPress test suite. Plain TestCase because it needs the real
 * state and duplicates tables; its posts and rows are removed in tearDown.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Review;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Review\Duplicates\DuplicateRepository;
use WhoIsWho\Core\Review\Duplicates\Matcher;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class DuplicatesTest extends TestCase {

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
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . DuplicateRepository::table() . ' WHERE profile_a = %d OR profile_b = %d', $id, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$this->posts = array();
		parent::tearDown();
	}

	/**
	 * Make a profile.
	 *
	 * @param string               $name  Name.
	 * @param string               $type  Entity type.
	 * @param array<string, mixed> $meta  Post meta (wiw_ keys without the prefix) and "external_ids".
	 */
	private function profile( string $name, string $type, array $meta = array() ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::IMPORTED_NOT_PUBLIC, array( 'entity_type' => $type ) );
		foreach ( $meta as $key => $value ) {
			if ( 'external_ids' === $key ) {
				update_post_meta( $id, Matcher::EXTERNAL_IDS_META, wp_json_encode( $value ) );
			} else {
				update_post_meta( $id, 'wiw_' . $key, $value );
			}
		}
		return $id;
	}

	public function test_REQ_145_match_on_registration_number(): void {
		$a = $this->profile( 'Alpha Qwv Industries', 'organisation', array( 'external_ids' => array( 'rc_number' => 'RC 123-456' ) ) );
		$b = $this->profile( 'Completely Different Name Zzz', 'organisation', array( 'external_ids' => array( 'rc_number' => 'rc123456' ) ) );
		$this->assertSame( 1, ( new Matcher() )->scan( $b ) );
		$pair = ( new DuplicateRepository() )->get( $a, $b );
		$this->assertNotNull( $pair );
		$this->assertSame( 'rc_number', $pair['basis'] );
		$this->assertSame( 100, (int) $pair['score'] );

		$c = $this->profile( 'Wiki Zzq Person', 'individual', array( 'external_ids' => array( 'wikidata_qid' => 'Q42' ) ) );
		$d = $this->profile( 'Other Name Xyy', 'individual', array( 'external_ids' => array( 'wikidata_qid' => 'q42' ) ) );
		( new Matcher() )->scan( $c );
		$this->assertSame( 'wikidata_qid', ( new DuplicateRepository() )->get( $c, $d )['basis'] );
	}

	public function test_REQ_145_match_on_normalised_name_org_location(): void {
		$a = $this->profile( "Chinwe \u{d6}kafor Qwv", 'individual', array( 'current_organisation' => 'Zenith Bank Plc' ) );
		$b = $this->profile( 'Chinwe Okafor Qwv', 'individual', array( 'current_organisation' => 'Zenith Bank' ) );
		$c = $this->profile( 'Chinwe Okafor Qwv', 'individual', array( 'current_organisation' => 'Access Bank' ) );
		$d = $this->profile( 'Chinwe Okafor Qwv', 'individual' );
		$this->assertGreaterThanOrEqual( 1, ( new Matcher() )->scan( $a ) );
		$repo = new DuplicateRepository();
		$pair = $repo->get( $a, $b );
		$this->assertNotNull( $pair, 'Diacritics and legal suffixes are folded.' );
		$this->assertSame( 'name_org_location', $pair['basis'] );
		$this->assertGreaterThanOrEqual( Matcher::MIN_NAME_SCORE, (int) $pair['score'] );
		$this->assertNull( $repo->get( $a, $c ), 'A different organisation is not a match.' );
		$this->assertNull( $repo->get( $a, $d ), 'A name alone, with nothing else known on both sides, is not a match.' );
	}

	public function test_REQ_145_compare_is_pure_and_handles_word_order(): void {
		$one = array(
			'entity_type'  => 'individual',
			'name'         => 'Okafor Chinwe',
			'organisation' => 'Zenith',
			'location'     => '',
			'external_ids' => array(),
		);
		$two = array(
			'entity_type'  => 'entrepreneur',
			'name'         => 'Chinwe Okafor',
			'organisation' => 'Zenith Bank',
			'location'     => '',
			'external_ids' => array(),
		);
		$this->assertNull( Matcher::compare( $one, $two ), 'Zenith and Zenith Bank are not the same organisation name.' );
		$two['organisation'] = 'Zenith';
		$this->assertSame( 98, Matcher::compare( $one, $two )['score'] );
		$two['entity_type'] = 'organisation';
		$this->assertNull( Matcher::compare( $one, $two ), 'A person is never compared with an organisation by name.' );
	}

	public function test_REQ_146_individuals_are_never_auto_merged(): void {
		$a = $this->profile( 'Twin Person Qwv', 'individual', array( 'current_organisation' => 'Twin Bank' ) );
		$b = $this->profile( 'Twin Person Qwv', 'individual', array( 'current_organisation' => 'Twin Bank' ) );
		( new Matcher() )->scan( $a );
		$repo = new DuplicateRepository();
		$pair = $repo->get( $a, $b );
		$this->assertNotNull( $pair );
		$this->assertSame( 'open', $pair['status'], 'The pair waits for a human.' );
		$this->assertInstanceOf( \WP_Post::class, get_post( $a ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $b ), 'Both profiles are kept.' );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->pub_state( $a ) );
		$this->assertSame( PubState::IMPORTED_NOT_PUBLIC, ( new StateRepository() )->pub_state( $b ) );
		$this->assertFalse( method_exists( Matcher::class, 'merge' ), 'The matcher has no merge operation.' );
	}

	public function test_REQ_123_probable_duplicate_flagged_for_review(): void {
		$a    = $this->profile( 'Flag Org Qwv', 'organisation', array( 'hq_city' => 'Kano' ) );
		$b    = $this->profile( 'Flag Org Qwv Ltd', 'organisation', array( 'hq_city' => 'Kano' ) );
		$repo = new DuplicateRepository();
		$this->assertFalse( $repo->is_flagged( $a ) );
		( new Matcher() )->scan( $a );
		$this->assertTrue( $repo->is_flagged( $a ) );
		$this->assertTrue( $repo->is_flagged( $b ) );
		$this->assertSame( array( $b ), $repo->partners( $a ) );
		$this->assertSame( 0, ( new Matcher() )->scan( $a ), 'Running again does not flag the pair twice.' );

		$repo->decide( $a, $b, 'dismissed', 1 );
		$this->assertFalse( $repo->is_flagged( $a ), 'A decided pair leaves the queue.' );
		( new Matcher() )->scan( $b );
		$this->assertSame( 'dismissed', $repo->get( $a, $b )['status'], 'A decided pair is not reopened.' );
		$this->expectException( \InvalidArgumentException::class );
		$repo->decide( $a, $b, 'deleted', 1 );
	}
}
