<?php
/**
 * The legal pages are seeded and the policy version follows their wording (REQ-100, REQ-120, REQ-058). Needs the WordPress
 * test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\Legal\LegalPages;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\TestCase;

final class LegalPagesSeedTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		$this->clean();
	}

	protected function tearDown(): void {
		$this->clean();
		parent::tearDown();
	}

	/**
	 * Remove the legal pages and the version setting, so each test starts from nothing.
	 */
	private function clean(): void {
		// The fixed page routes can be deleted only by an Administrator (REQ-260), so clean up as one, then act as nobody again.
		wp_set_current_user(
			(int) wp_insert_user(
				array(
					'user_login' => 'legal_seed_cleaner_' . wp_generate_password( 6, false ),
					'user_pass'  => wp_generate_password( 20 ),
					'role'       => 'administrator',
				)
			)
		);
		foreach ( array_merge( LegalPages::SLUGS, array( 'about-us' ) ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page instanceof \WP_Post ) {
				wp_delete_post( $page->ID, true );
			}
		}
		wp_set_current_user( 0 );
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::POLICY_VERSION );
	}

	public function test_REQ_100_terms_and_privacy_pages_exist_and_are_published(): void {
		( new LegalPages() )->ensure();
		foreach ( array( 'privacy', 'terms', 'cookies', 'data-rights', 'complaints' ) as $slug ) {
			$page = get_page_by_path( $slug );
			$this->assertInstanceOf( \WP_Post::class, $page, $slug );
			$this->assertSame( 'publish', $page->post_status, $slug );
			$this->assertNotSame( '', trim( wp_strip_all_tags( $page->post_content ) ), $slug );
		}
	}

	public function test_REQ_100_seeding_twice_creates_nothing_new_and_keeps_edits(): void {
		$seed = new LegalPages();
		$seed->ensure();
		$page = get_page_by_path( 'privacy' );
		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => 'Edited by Legal.',
			)
		);
		$seed->ensure();
		$all = get_posts(
			array(
				'post_type'   => 'page',
				'name'        => 'privacy',
				'post_status' => 'any',
			)
		);
		$this->assertCount( 1, $all );
		$this->assertSame( 'Edited by Legal.', get_post_field( 'post_content', $page->ID ) );
	}

	public function test_REQ_120_no_commercial_terms_page(): void {
		( new LegalPages() )->ensure();
		foreach ( array( 'pricing', 'prices', 'packages', 'commercial-terms', 'rates' ) as $slug ) {
			$this->assertNull( get_page_by_path( $slug ), $slug );
		}
		foreach ( LegalPages::SLUGS as $slug ) {
			$text = strtolower( wp_strip_all_tags( get_post_field( 'post_content', get_page_by_path( $slug ) ) ) );
			$this->assertDoesNotMatchRegularExpression( '/\b(price|prices|pricing|package|packages|fee|fees|naira|kobo)\b|[$\x{20A6}]\s?\d/u', $text, $slug );
		}
	}

	public function test_REQ_058_policy_version_is_bumped_when_a_legal_page_changes(): void {
		$settings = SettingsService::for_site();
		( new LegalPages() )->ensure();
		$before = (string) $settings->get( SettingsService::POLICY_VERSION );
		$page   = get_page_by_path( 'terms' );

		wp_update_post(
			array(
				'ID'         => $page->ID,
				'post_title' => 'Terms of use',
			)
		);
		$this->assertSame( $before, (string) $settings->get( SettingsService::POLICY_VERSION ), 'a title-only change keeps the version' );

		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => 'New wording from Legal.',
			)
		);
		$after = (string) $settings->get( SettingsService::POLICY_VERSION );
		$this->assertNotSame( $before, $after );
		$this->assertSame( (string) ( (int) $before + 1 ), $after );

		$other = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'About us',
				'post_name'    => 'about-us',
				'post_content' => 'one',
			)
		);
		wp_update_post(
			array(
				'ID'           => $other,
				'post_content' => 'two',
			)
		);
		$this->assertSame( $after, (string) $settings->get( SettingsService::POLICY_VERSION ), 'other pages do not bump it' );
	}
}
