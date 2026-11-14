<?php
/**
 * The dry run speaks plainly about values not yet in the lists (REQ-237, REQ-244, #810): "SCHEMA (1000)" alarmed staff, so in a
 * dry run with such values it reads "New list values needed (1000)" with what to do. Other codes, and the codes sent back to the
 * acquisition side, stay as the contract names them. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Import\Admin\ImportScreen;
use WhoIsWho\Tests\WpTestCase;

final class ImportCountLabelTest extends WpTestCase {

	public function test_REQ_009_a_schema_count_is_always_a_real_record_error(): void {
		// A value missing from the lists no longer refuses a record (REQ-009 as amended 2026-11-14), so SCHEMA keeps its code.
		$this->assertSame( 'SCHEMA (1000)', ImportScreen::count_label( 'SCHEMA', 1000, true ) );
		$this->assertSame( 'SCHEMA (3)', ImportScreen::count_label( 'SCHEMA', 3 ) );
		$this->assertSame( 'CREATED (12)', ImportScreen::count_label( 'CREATED', 12, true ) );
	}
}
