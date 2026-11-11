<?php
/**
 * The state response policy (REQ-208, REQ-209, REQ-217, REQ-218). Pure logic, no WordPress needed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Review;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Review\StatePolicy;
use WhoIsWho\Tests\TestCase;

final class StatePolicyTest extends TestCase {

	public function test_REQ_208_state_policy_matches_state_table(): void {
		// status, noindex, lists, search, sitemap, schema, share, label.
		$table = array(
			PubState::PUBLISHED_REVIEWED  => array( 200, false, true, true, true, true, true, false ),
			PubState::PUBLISHED_PENDING   => array( 200, true, false, true, false, false, true, true ),
			PubState::UNPUBLISHED         => array( 410, true, false, false, false, false, false, false ),
			PubState::REMOVED_SUPPRESSED  => array( 410, true, false, false, false, false, false, false ),
			PubState::REJECTED            => array( 404, true, false, false, false, false, false, false ),
			PubState::IMPORTED_NOT_PUBLIC => array( 404, true, false, false, false, false, false, false ),
		);
		$this->assertEqualsCanonicalizing( PubState::all(), array_keys( $table ), 'every state is covered' );
		foreach ( $table as $state => $expected ) {
			$p = StatePolicy::for_state( $state );
			$this->assertSame( $expected, array( $p->status, $p->noindex, $p->in_lists, $p->in_search, $p->in_sitemap, $p->has_schema, $p->share_card, $p->label ), $state );
		}
		$unknown = StatePolicy::for_state( null );
		$this->assertSame( 404, $unknown->status );
		$this->assertFalse( $unknown->in_search );
		$this->assertSame( 404, StatePolicy::for_state( 'made_up' )->status );
	}

	public function test_REQ_209_unpublished_and_removed_return_410(): void {
		$this->assertSame( 410, StatePolicy::for_state( PubState::UNPUBLISHED )->status );
		$this->assertSame( 410, StatePolicy::for_state( PubState::REMOVED_SUPPRESSED )->status );
		$this->assertTrue( StatePolicy::for_state( PubState::UNPUBLISHED )->noindex );
	}

	public function test_REQ_217_pending_is_200_noindex_not_in_sitemap(): void {
		$p = StatePolicy::for_state( PubState::PUBLISHED_PENDING );
		$this->assertSame( 200, $p->status );
		$this->assertTrue( $p->noindex );
		$this->assertFalse( $p->in_sitemap );
		$this->assertFalse( $p->has_schema );
		$this->assertTrue( $p->label );
	}

	public function test_REQ_218_pending_not_in_lists_but_in_search(): void {
		$p = StatePolicy::for_state( PubState::PUBLISHED_PENDING );
		$this->assertFalse( $p->in_lists );
		$this->assertTrue( $p->in_search );
	}

	public function test_REQ_208_only_published_states_are_visible_and_match_public_status(): void {
		foreach ( PubState::all() as $state ) {
			$this->assertSame( PubState::is_public( $state ), StatePolicy::for_state( $state )->is_visible(), $state );
		}
	}
}
