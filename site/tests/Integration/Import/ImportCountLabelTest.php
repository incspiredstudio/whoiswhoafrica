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

	public function test_REQ_237_new_list_values_are_named_in_words_in_the_dry_run(): void {
		$label = ImportScreen::count_label( 'SCHEMA', 1000, true );
		$this->assertStringContainsString( 'New list values needed (1000)', $label );
		$this->assertStringContainsString( 'Add all as new list entries', $label );
		$this->assertStringNotContainsString( 'SCHEMA', $label );
		$this->assertSame( 'SCHEMA (3)', ImportScreen::count_label( 'SCHEMA', 3 ), 'a real record error keeps its code' );
		$this->assertSame( 'CREATED (12)', ImportScreen::count_label( 'CREATED', 12, true ) );
	}
}
