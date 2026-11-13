<?php
/**
 * The draft legal and privacy wording (REQ-100, REQ-120, REQ-058, REQ-059, REQ-062, REQ-063, REQ-158): each page is a real draft
 * that reflects what the site does, keeps its visible draft note and draft marker, and never overwrites an edited page. Needs the
 * WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Legal\LegalDrafts;
use WhoIsWho\Core\Compliance\Legal\LegalPages;
use WhoIsWho\Core\Content\Pages\StarterPages;
use WhoIsWho\Tests\TestCase;

final class LegalDraftsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		wp_cache_flush();
		$this->clean();
	}

	protected function tearDown(): void {
		$this->clean();
		parent::tearDown();
	}

	/**
	 * Remove the legal pages so each test starts from nothing.
	 */
	private function clean(): void {
		// The fixed page routes cannot be deleted by anyone but an Administrator (REQ-260), so clean up as one.
		wp_set_current_user(
			(int) wp_insert_user(
				array(
					'user_login' => 'legal_drafts_cleaner_' . wp_generate_password( 6, false ),
					'user_pass'  => wp_generate_password( 20 ),
					'role'       => 'administrator',
				)
			)
		);
		// The starter-pages seed makes the other starter pages too; remove them so the starter-pages tests start clean.
		foreach ( array_merge( LegalPages::SLUGS, array( 'about', 'faq', 'awards', 'rules', 'nominate' ) ) as $slug ) {
			foreach ( get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'any',
					'name'           => $slug,
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			) as $id ) {
				wp_delete_post( (int) $id, true );
			}
		}
		wp_set_current_user( 0 );
	}

	/**
	 * Plain text of a draft.
	 *
	 * @param string $slug Slug.
	 */
	private function text( string $slug ): string {
		return strtolower( wp_strip_all_tags( LegalDrafts::for_slug( $slug )['body'] ) );
	}

	public function test_REQ_100_every_draft_is_full_and_keeps_the_visible_draft_note(): void {
		foreach ( LegalPages::SLUGS as $slug ) {
			$draft = LegalDrafts::for_slug( $slug );
			$this->assertNotSame( '', $draft['title'], $slug );
			$this->assertStringContainsString( 'notice--draft', $draft['body'], $slug );
			$this->assertStringContainsString( 'Draft, pending legal review', $draft['body'], $slug );
			$this->assertGreaterThanOrEqual( 4, substr_count( $draft['body'], '<h2 ' ), $slug . ' has headings' );
			$this->assertGreaterThan( 250, str_word_count( $this->text( $slug ) ), $slug . ' is more than a stub' );
			$this->assertStringContainsString( '[contact email to be confirmed]', $draft['body'], $slug );
			$this->assertStringContainsString( '[effective date to be confirmed]', $draft['body'], $slug );
			$this->assertStringNotContainsString( '<h1', $draft['body'], 'the template draws the one h1' );
		}
	}

	public function test_REQ_120_drafts_state_no_prices_packages_or_fees(): void {
		foreach ( LegalPages::SLUGS as $slug ) {
			$this->assertDoesNotMatchRegularExpression( '/\b(price|prices|pricing|package|packages|fee|fees|naira|kobo)\b|[$\x{20A6}]\s?\d/u', $this->text( $slug ), $slug );
		}
	}

	public function test_REQ_058_privacy_describes_what_the_site_collects_and_the_basis(): void {
		$text = $this->text( 'privacy' );
		foreach ( array( 'legitimate interests', 'nominat', 'newsletter', 'comment', 'sponsored', 'right to object', 'under 18', 'single-use link', 'to be confirmed' ) as $needle ) {
			$this->assertStringContainsString( $needle, $text, $needle );
		}
		$this->assertStringContainsString( 'href="/data-rights/"', LegalDrafts::for_slug( 'privacy' )['body'] );
		$this->assertStringContainsString( 'nominator', $text );
		$this->assertStringContainsString( 'never shown', $text );
	}

	public function test_REQ_063_retention_periods_are_the_documented_defaults_marked_to_be_confirmed(): void {
		$text = $this->text( 'privacy' );
		$this->assertStringContainsString( '2 years', $text );
		$this->assertStringContainsString( '14 days', $text );
		$this->assertStringContainsString( '48 hours', $text );
		$this->assertStringContainsString( '30 days', $text );
		$this->assertStringContainsString( 'all are to be confirmed by legal', $text );
	}

	public function test_REQ_062_cookie_page_lists_the_consent_cookie_and_analytics_after_accept(): void {
		$text = $this->text( 'cookies' );
		$this->assertStringContainsString( 'wiw_consent', $text );
		$this->assertStringContainsString( 'only after you press accept', $text );
		$this->assertStringContainsString( '180 days', $text );
	}

	public function test_REQ_059_data_rights_page_names_the_rights_the_confirmation_link_and_the_period(): void {
		$text = $this->text( 'data-rights' );
		foreach ( array( 'access', 'correction', 'erasure', 'objection', 'portability', 'single-use link', '30 calendar days', 'do not need an account' ) as $needle ) {
			$this->assertStringContainsString( $needle, $text, $needle );
		}
	}

	public function test_REQ_158_complaints_page_states_the_removal_commitments(): void {
		$text = $this->text( 'complaints' );
		foreach ( array( 'one business day', 'two business days', 'seven days', 'suppression list', 'suggest a correction', 'request removal', 'report this profile' ) as $needle ) {
			$this->assertStringContainsString( $needle, $text, $needle );
		}
	}

	public function test_REQ_100_ensure_creates_published_pages_with_the_draft_marker(): void {
		( new LegalPages() )->ensure();
		foreach ( LegalPages::SLUGS as $slug ) {
			$page = get_page_by_path( $slug );
			$this->assertInstanceOf( \WP_Post::class, $page, $slug );
			$this->assertSame( 'publish', $page->post_status, $slug );
			$this->assertSame( '1', get_post_meta( $page->ID, LegalDrafts::DRAFT_META, true ), $slug );
			$this->assertStringContainsString( 'notice--draft', $page->post_content, $slug );
			$this->assertStringContainsString( 'Draft, pending legal review', $page->post_content, $slug );
		}
	}

	public function test_REQ_100_the_starter_pages_seed_makes_the_same_legal_pages_and_keeps_edits(): void {
		( new StarterPages() )->seed();
		foreach ( LegalPages::SLUGS as $slug ) {
			$page = get_page_by_path( $slug );
			$this->assertInstanceOf( \WP_Post::class, $page, $slug );
			$this->assertSame( '1', get_post_meta( $page->ID, LegalDrafts::DRAFT_META, true ), $slug );
			$this->assertStringContainsString( 'Draft, pending legal review', $page->post_content, $slug );
		}
		$page = get_page_by_path( 'privacy' );
		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => 'Edited by Legal.',
			)
		);
		( new StarterPages() )->seed();
		( new LegalPages() )->ensure();
		( new LegalPages() )->upgrade_untouched();
		$this->assertSame( 'Edited by Legal.', get_post_field( 'post_content', $page->ID ) );
	}

	public function test_REQ_100_upgrade_replaces_only_the_untouched_first_draft(): void {
		$first = '<p>This is draft wording. You may ask to see, correct, export, or remove the personal information we hold about you. Use the request form and we will reply within the legal time limit.</p>';
		$id    = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => 'data-rights',
				'post_title'   => 'Your data rights',
				'post_content' => $first,
			)
		);
		$this->assertSame( 1, ( new LegalPages() )->upgrade_untouched() );
		$this->assertStringContainsString( 'Draft, pending legal review', get_post_field( 'post_content', $id ) );
		$this->assertSame( 0, ( new LegalPages() )->upgrade_untouched(), 'a second run changes nothing' );

		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => $first . ' Legal added a line.',
			)
		);
		$this->assertSame( 0, ( new LegalPages() )->upgrade_untouched() );
		$this->assertStringContainsString( 'Legal added a line.', get_post_field( 'post_content', $id ) );
	}
}
