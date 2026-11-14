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

	public function test_REQ_042_the_brand_pattern_tile_is_webp_and_small_and_the_tokens_point_at_it(): void {
		$tile = $this->child() . '/assets/brand/pattern-marks.webp';
		$this->assertFileExists( $tile );
		$head = (string) file_get_contents( $tile, false, null, 0, 12 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertSame( 'RIFF', substr( $head, 0, 4 ), 'a real RIFF/WebP file' );
		$this->assertSame( 'WEBP', substr( $head, 8, 4 ), 'a real RIFF/WebP file' );
		$this->assertLessThan( 30 * 1024, (int) filesize( $tile ), 'the tile is repeated on many pages, so it stays under 30 KB' );
		$this->assertStringContainsString( 'pattern-marks.webp', (string) file_get_contents( $this->child() . '/assets/css/tokens.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertStringContainsString( 'pattern-marks.webp', (string) file_get_contents( $this->child() . '/inc/assets.php' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public function test_REQ_273_only_the_four_approved_adinkra_marks_and_the_pattern_built_from_them_are_brand_symbols(): void {
		$approved = array( 'mark-comb.webp', 'mark-link.webp', 'mark-spiral.webp', 'mark-star.webp', 'pattern-marks.webp' );
		$found    = array_map( 'basename', (array) glob( $this->child() . '/assets/brand/*' ) );
		sort( $found );
		$this->assertSame( $approved, $found, 'the brand folder holds the four approved marks (Adinkrahene, Dwennimmen, Nkonsonkonson, Sankofa) and the tile made from them, nothing else' );
		$this->assertFileDoesNotExist( $this->child() . '/assets/brand/pattern-tile.webp', 'the brush-glyph tile is not an approved symbol' );
		$this->assertFileExists( dirname( __DIR__, 2 ) . '/build/pattern-tile.py', 'the tile is rebuilt from the four marks by a script' );
		// No theme stylesheet, template or script points at a brand image outside the approved list.
		$themes = dirname( $this->child() );
		$files  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $themes, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( ! preg_match( '/\.(css|php|js|json|html)$/', (string) $file ) ) {
				continue;
			}
			$text = (string) file_get_contents( (string) $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			preg_match_all( '#brand/([a-z0-9-]+\.(?:webp|png|svg|jpe?g|avif))#i', $text, $hits );
			foreach ( $hits[1] as $hit ) {
				$this->assertContains( $hit, $approved, (string) $file . ' uses an unapproved symbol image: ' . $hit );
			}
		}
	}

	public function test_REQ_190_logos_are_assets_not_template_markup(): void {
		foreach ( array( 'wiw-logo.png', 'wiw-icon.png', 'wiw-icon-black.png' ) as $file ) {
			$this->assertFileExists( $this->child() . '/assets/logo/' . $file );
		}
		foreach ( array( 'mark-star.webp', 'mark-comb.webp', 'mark-link.webp', 'mark-spiral.webp', 'pattern-marks.webp' ) as $file ) {
			$this->assertFileExists( $this->child() . '/assets/brand/' . $file );
		}
	}

	public function test_REQ_273_the_header_logo_comes_from_the_child_tokens_not_an_image_inlined_in_the_parent_css(): void {
		foreach ( (array) glob( $this->parent_dir() . '/assets/css/*.css' ) as $file ) {
			$this->assertStringNotContainsString( 'data:image/png', (string) file_get_contents( (string) $file ), basename( (string) $file ) . ' inlines a brand image' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$this->assertFileExists( $this->child() . '/assets/logo/wiw-logo-mask.webp' );
		$this->assertStringContainsString( '--img-logo: url("../logo/wiw-logo-mask.webp")', (string) file_get_contents( $this->child() . '/assets/css/tokens.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->assertStringContainsString( 'var(--img-logo)', (string) file_get_contents( $this->parent_dir() . '/assets/css/components.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
