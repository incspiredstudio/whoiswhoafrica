<?php
/**
 * The parent theme's base tokens (REQ-052, REQ-190, REQ-273).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Tests\TestCase;

final class ParentTokensTest extends TestCase {

	private function parent_dir(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho';
	}

	public function test_REQ_190_parent_tokens_contain_no_brand_literals(): void {
		$css = (string) file_get_contents( $this->parent_dir() . '/assets/css/tokens.base.css' );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}\b/', $css, 'no hex colour' );
		$this->assertDoesNotMatchRegularExpression( '/Repline|Montreal/i', $css, 'no brand typeface' );
		$this->assertStringContainsString( 'child theme', $css, 'the file says brand values live in the child' );
	}

	public function test_REQ_190_every_prototype_token_has_a_name_in_the_parent(): void {
		$prototype = dirname( __DIR__, 3 ) . '/prototype/theme/tokens.css';
		if ( ! is_readable( $prototype ) ) {
			$this->markTestSkipped( 'prototype/ is not present.' );
		}
		preg_match_all( '/^\s*(--[a-z0-9-]+)\s*:/m', (string) file_get_contents( $prototype ), $proto );
		preg_match_all( '/^\s*(--[a-z0-9-]+)\s*:/m', (string) file_get_contents( $this->parent_dir() . '/assets/css/tokens.base.css' ), $base );

		// The raw palette (named colours) is brand and lives only in the child.
		$raw     = '/^--(charcoal|stone|offwhite|surface|orange|blue|red|green|purple)(-|$)/';
		$missing = array();
		foreach ( array_unique( $proto[1] ) as $name ) {
			if ( ! preg_match( $raw, $name ) && ! in_array( $name, $base[1], true ) ) {
				$missing[] = $name;
			}
		}
		$this->assertSame( array(), $missing, 'prototype tokens missing from tokens.base.css' );
	}

	public function test_REQ_273_theme_json_references_variables_only(): void {
		$json = json_decode( (string) file_get_contents( $this->parent_dir() . '/theme.json' ), true );
		$this->assertIsArray( $json );
		$values = array();
		foreach ( $json['settings']['color']['palette'] as $entry ) {
			$values[] = $entry['color'];
		}
		foreach ( $json['settings']['typography']['fontFamilies'] as $entry ) {
			$values[] = $entry['fontFamily'];
		}
		foreach ( $json['settings']['typography']['fontSizes'] as $entry ) {
			$values[] = $entry['size'];
		}
		foreach ( $json['settings']['spacing']['spacingSizes'] as $entry ) {
			$values[] = $entry['size'];
		}
		$this->assertNotEmpty( $values );
		foreach ( $values as $value ) {
			$this->assertStringStartsWith( 'var(--', $value, 'theme.json holds no literal values' );
		}
	}
}
