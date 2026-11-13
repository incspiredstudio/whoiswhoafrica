<?php
/**
 * No Sponsored output on profiles or events (M3-COM-14; REQ-155, REQ-182). The Sponsored badge, notice and slot belong to stories
 * only. This scans the profile and event templates, so a later change cannot add one without this test failing.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Tests\TestCase;

final class SponsoredMarkupAbsentTest extends TestCase {

	private function theme_dir(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho';
	}

	/**
	 * The profile and event template files.
	 *
	 * @return string[]
	 */
	private function files( string $pattern ): array {
		$found = glob( $this->theme_dir() . '/' . $pattern );
		return is_array( $found ) ? $found : array();
	}

	/**
	 * Code of a template without its comments, so a comment that explains the rule is not mistaken for markup.
	 */
	private function code( string $file ): string {
		$source = (string) file_get_contents( $file );
		return (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $source );
	}

	public function test_REQ_155_sponsored_markup_absent_from_profile_templates(): void {
		$files = array_merge(
			$this->files( 'templates/profile.php' ),
			$this->files( 'template-parts/profile-*.php' ),
			$this->files( 'template-parts/claim-button.php' ),
			$this->files( 'template-parts/enquiry-panel.php' )
		);
		$this->assertNotEmpty( $files, 'the profile templates exist' );
		foreach ( $files as $file ) {
			$code = $this->code( $file );
			$this->assertDoesNotMatchRegularExpression( '/badge--sponsored|notice--sponsored|stories-slot|is-sponsored/', $code, basename( $file ) );
			$this->assertDoesNotMatchRegularExpression( '/[\'"]sponsor[\'"]|\bSponsored\b/', $this->without_removal( $code ), basename( $file ) );
		}
	}

	public function test_REQ_155_the_related_stories_card_strips_the_sponsor_fields(): void {
		$code = $this->code( $this->theme_dir() . '/template-parts/profile-related-stories.php' );
		$this->assertMatchesRegularExpression( '/unset\(\s*\$wiw_story\[\s*\'sponsor\'\s*\]/', $code );
	}

	public function test_REQ_182_sponsored_markup_absent_from_event_templates(): void {
		$files = array_merge(
			$this->files( 'single-wiw_event.php' ),
			$this->files( 'archive-wiw_event.php' ),
			$this->files( 'template-parts/event-*.php' ),
			$this->files( 'template-parts/home-events.php' )
		);
		$this->assertNotEmpty( $files, 'the event templates exist' );
		foreach ( $files as $file ) {
			$code = $this->code( $file );
			$this->assertDoesNotMatchRegularExpression( '/badge--sponsored|notice--sponsored|stories-slot|is-sponsored|[\'"]sponsor[\'"]|\bSponsored\b/', $code, basename( $file ) );
		}
	}

	/**
	 * The code with the allowed removal of the sponsor fields taken out.
	 */
	private function without_removal( string $code ): string {
		return (string) preg_replace( '/unset\([^;]*\);/', '', $code );
	}
}
