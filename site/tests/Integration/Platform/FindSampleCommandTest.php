<?php
/**
 * content:find-sample: sample content is tagged and the command lists what is left (REQ-208, REQ-052).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Cli\Application;
use WhoIsWho\Core\Platform\Cli\FindSampleCommand;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Tests\WpTestCase;

final class FindSampleCommandTest extends WpTestCase {

	private function run_command(): array {
		$output = new Output( false );
		$code   = ( new FindSampleCommand() )->run( array(), $output );
		return array( $code, $output->lines() );
	}

	public function test_REQ_208_find_sample_command_exits_zero_on_a_clean_site(): void {
		self::factory()->post->create(
			array(
				'post_title' => 'Real page',
				'post_type'  => 'page',
			)
		);
		list( $code, $lines ) = $this->run_command();
		$this->assertSame( 0, $code );
		$this->assertStringContainsString( 'No sample content', implode( "\n", $lines ) );
	}

	public function test_REQ_208_find_sample_command_reports_tagged_items(): void {
		$by_meta = self::factory()->post->create(
			array(
				'post_title'  => 'Anything',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $by_meta, FindSampleCommand::META_KEY, '1' );
		$by_title = self::factory()->post->create( array( 'post_title' => '[Sample] Jane Doe' ) );
		$media    = self::factory()->attachment->create( array( 'post_title' => 'prototype photograph' ) );
		update_post_meta( $media, FindSampleCommand::META_KEY, '1' );
		$term = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Real category',
			)
		);
		update_term_meta( $term, FindSampleCommand::META_KEY, '1' );
		$clean = self::factory()->post->create( array( 'post_title' => 'A real story' ) );

		list( $code, $lines ) = $this->run_command();
		$text                 = implode( "\n", $lines );

		$this->assertSame( 1, $code, 'exits non-zero while sample items remain' );
		$this->assertStringContainsString( 'post #' . $by_meta, $text );
		$this->assertStringContainsString( 'post #' . $by_title, $text );
		$this->assertStringContainsString( 'attachment #' . $media, $text );
		$this->assertStringContainsString( 'term #' . $term, $text );
		$this->assertStringNotContainsString( '#' . $clean . ' ', $text . ' ' );
		$this->assertStringNotContainsString( 'Jane Doe', $text, 'only identifiers are printed' );
	}

	public function test_REQ_208_find_sample_command_reports_draft_notes_on_legal_pages(): void {
		$page                 = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Privacy',
				'post_content' => '<p class="notice notice--draft">Draft note: delete this block when Legal has signed off the wording.</p>',
			)
		);
		list( $code, $lines ) = $this->run_command();
		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'draft note on page #' . $page, implode( "\n", $lines ) );
	}

	public function test_REQ_208_find_sample_command_is_registered(): void {
		$this->assertContains( 'content:find-sample', Application::for_site()->names() );
	}
}
