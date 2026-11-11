<?php
/**
 * The child theme's brand tokens are the only source of brand values (REQ-052, REQ-190, REQ-273).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Tests\TestCase;

final class ChildBrandTokensTest extends TestCase {

	private function child(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho-child';
	}

	private function parent_dir(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/whoiswho';
	}

	/**
	 * Custom properties in the :root block.
	 *
	 * @param string $css CSS.
	 * @return array<string, string>
	 */
	private function tokens( string $css ): array {
		preg_match( '/:root\s*\{(.*?)\n\}/s', $css, $block );
		preg_match_all( '/(--[a-z0-9-]+)\s*:\s*([^;]+);/', $block[1], $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $row ) {
			$out[ $row[1] ] = trim( $row[2] );
		}
		return $out;
	}

	/**
	 * Resolve var() references.
	 *
	 * @param string               $value  Value.
	 * @param array<string, string> $tokens Tokens.
	 */
	private function resolve( string $value, array $tokens ): string {
		return (string) preg_replace_callback(
			'/var\((--[a-z0-9-]+)\)/',
			fn ( array $m ): string => $this->resolve( $tokens[ $m[1] ], $tokens ),
			$value
		);
	}

	public function test_REQ_190_child_tokens_define_every_name_the_parent_declares(): void {
		$child  = $this->tokens( (string) file_get_contents( $this->child() . '/assets/css/tokens.css' ) );
		$parent = $this->tokens( (string) file_get_contents( $this->parent_dir() . '/assets/css/tokens.base.css' ) );
		$this->assertSame( array(), array_values( array_diff( array_keys( $parent ), array_keys( $child ) ) ), 'the child overrides every parent token' );
		$this->assertSame( '#ffa600', $this->resolve( $child['--c-cta'], $child ), 'orange is the primary colour' );
	}

	public function test_REQ_052_fonts_are_self_hosted_with_swap_and_no_italics(): void {
		$css = (string) file_get_contents( $this->child() . '/assets/css/tokens.css' );
		preg_match_all( '/@font-face\s*\{([^}]*)\}/', $css, $faces );
		$this->assertCount( 5, $faces[1], 'Repline at 400 and Montreal at 300, 400, 500 and 700' );
		foreach ( $faces[1] as $face ) {
			$this->assertStringContainsString( 'font-display: swap', $face );
			$this->assertStringNotContainsString( 'italic', $face );
			preg_match( '/url\(["\']?\.\.\/fonts\/([^"\')]+)["\']?\)/', $face, $file );
			$this->assertFileExists( $this->child() . '/assets/fonts/' . $file[1] );
		}
	}

	public function test_REQ_190_child_tokens_file_is_the_only_brand_value_source(): void {
		$allowed = array( 'tokens.css', 'theme.json' );
		$files   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->child(), \FilesystemIterator::SKIP_DOTS ) );
		$checked = 0;
		foreach ( $files as $file ) {
			if ( ! in_array( $file->getExtension(), array( 'php', 'css', 'js', 'json', 'html' ), true ) || in_array( $file->getFilename(), $allowed, true ) ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}\b/', $code, $file->getFilename() . ' has a colour literal' );
			$this->assertDoesNotMatchRegularExpression( '/Repline|Montreal/', $code, $file->getFilename() . ' names a brand typeface' );
			++$checked;
		}
		$this->assertGreaterThan( 0, $checked );
	}

	public function test_REQ_273_generated_theme_json_matches_the_tokens(): void {
		$tokens = $this->tokens( (string) file_get_contents( $this->child() . '/assets/css/tokens.css' ) );
		$json   = json_decode( (string) file_get_contents( $this->child() . '/theme.json' ), true );
		$this->assertIsArray( $json );
		$palette = array();
		foreach ( $json['settings']['color']['palette'] as $entry ) {
			$palette[ $entry['slug'] ] = $entry['color'];
		}
		$expect = array(
			'bg'     => '--c-bg',
			'text'   => '--c-text',
			'cta'    => '--c-cta',
			'link'   => '--c-link',
			'accent' => '--c-accent',
			'dark'   => '--c-dark',
		);
		foreach ( $expect as $slug => $token ) {
			$this->assertSame( $this->resolve( $tokens[ $token ], $tokens ), $palette[ $slug ], "palette {$slug}" );
		}
		$families = array_column( $json['settings']['typography']['fontFamilies'], 'fontFamily', 'slug' );
		$this->assertStringStartsWith( '"Repline"', $families['display'] );
		$this->assertStringStartsWith( '"Montreal"', $families['body'] );
		$this->assertSame( 'swap', $json['settings']['typography']['fontFamilies'][0]['fontFace'][0]['fontDisplay'] );
	}

	public function test_REQ_190_logos_are_assets_not_template_markup(): void {
		foreach ( array( 'wiw-logo.png', 'wiw-icon.png', 'wiw-icon-black.png', 'logo-mark-dark.png', 'logo-mark-light.png' ) as $file ) {
			$this->assertFileExists( $this->child() . '/assets/logo/' . $file );
		}
		foreach ( array( 'mark-star.webp', 'mark-comb.webp', 'mark-link.webp', 'mark-spiral.webp', 'pattern-tile.png' ) as $file ) {
			$this->assertFileExists( $this->child() . '/assets/brand/' . $file );
		}
	}
}
