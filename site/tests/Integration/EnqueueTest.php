<?php
/**
 * Asset pipeline in the parent theme: manifest, deferred scripts, no jQuery, header-state script with nonce, image sizes (REQ-041, REQ-042, REQ-043, REQ-046). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Tests\TestCase;

final class EnqueueTest extends TestCase {

	public function test_REQ_041_manifest_names_resolve_to_hashed_urls(): void {
		$this->requireWordPress();
		add_filter(
			'wiw_manifest',
			static fn () => array( 'base.css' => 'base.abc123.css' )
		);
		$this->assertStringEndsWith( '/dist/base.abc123.css', (string) wiw_asset_url( 'base.css' ) );
		$this->assertNull( wiw_asset_url( 'missing.css' ) );
		remove_all_filters( 'wiw_manifest' );
	}

	public function test_REQ_041_styles_enqueue_in_order_tokens_base_components(): void {
		$this->requireWordPress();
		add_filter(
			'wiw_manifest',
			static fn () => array(
				'base.css'       => 'base.1.css',
				'components.css' => 'components.1.css',
			)
		);
		wp_deregister_style( 'wiw-tokens-base' );
		wp_register_style( 'wiw-tokens-base', 'https://example.test/t.css', array(), '1' );
		do_action( 'wp_enqueue_scripts' );
		$base       = wp_styles()->registered['wiw-base'] ?? null;
		$components = wp_styles()->registered['wiw-components'] ?? null;
		$this->assertNotNull( $base );
		$this->assertNotNull( $components );
		$this->assertContains( 'wiw-tokens-base', $base->deps );
		$this->assertContains( 'wiw-base', $components->deps );
		remove_all_filters( 'wiw_manifest' );
	}

	public function test_REQ_046_front_end_loads_no_jquery(): void {
		$this->requireWordPress();
		do_action( 'wp_enqueue_scripts' );
		$this->assertFalse( wp_script_is( 'jquery', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'jquery', 'registered' ) );
	}

	public function test_REQ_046_header_state_script_carries_the_nonce(): void {
		$this->requireWordPress();
		ob_start();
		wiw_print_header_state_script();
		$out = (string) ob_get_clean();
		$this->assertStringContainsString( '<script nonce="' . wiw_csp_nonce() . '"', $out );
		$this->assertStringContainsString( 'at-top', $out );
		$this->assertStringContainsString( 'is-home', $out );
	}

	public function test_REQ_043_motion_base_gets_the_smooth_scroll_url_and_page_scripts_use_the_js_suffix(): void {
		$this->requireWordPress();
		add_filter(
			'wiw_manifest',
			static fn () => array(
				'main.js'    => 'main.1.js',
				'lenis.js'   => 'lenis.1.js',
				'home-js.js' => 'home-js.1.js',
			)
		);
		wp_dequeue_script( 'wiw-main' );
		wp_deregister_script( 'wiw-main' );
		do_action( 'wp_enqueue_scripts' );
		$before = implode( '', (array) wp_scripts()->get_data( 'wiw-main', 'before' ) );
		$this->assertStringContainsString( 'window.wiwMotion=', $before );
		$this->assertStringContainsString( 'lenis.1.js', $before );
		remove_all_filters( 'wiw_manifest' );
	}

	public function test_REQ_042_card_and_poster_image_sizes_are_registered(): void {
		$this->requireWordPress();
		wiw_register_image_sizes();
		$sizes = wp_get_additional_image_sizes();
		$this->assertArrayHasKey( 'wiw-card', $sizes );
		$this->assertArrayHasKey( 'wiw-poster', $sizes );
	}
}
