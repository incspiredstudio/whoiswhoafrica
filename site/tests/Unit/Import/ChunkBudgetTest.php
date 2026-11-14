<?php
/**
 * The import step's time budget (REQ-237, REQ-092, #810): it stops before a record that, as slow as the slowest one so far,
 * would run past the budget, so a step with slow images no longer overruns (20.9 s was seen against 10 s).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use WhoIsWho\Core\Import\Apply\ChunkApplier;

final class ChunkBudgetTest extends TestCase {

	public function test_REQ_237_the_budget_stops_before_a_record_that_would_overrun(): void {
		$budget = ChunkApplier::budget( 10 );
		$this->assertFalse( $budget(), 'time is left' );
		$this->assertFalse( $budget( 2.0 ), 'a 2 s record still fits' );
		$this->assertTrue( $budget( 10.5 ), 'a record as slow as a 10.5 s one would overrun: stop now' );
		$this->assertTrue( ChunkApplier::budget( 0 )(), 'a spent budget stops' );
	}
}
