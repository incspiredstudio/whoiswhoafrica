<?php
/**
 * Story share preview and structured data (REQ-182, REQ-026, REQ-044). A published story gets its title, description and Open
 * Graph tags from the public read model, with the Sponsored label and sponsor name added to the preview text while the placement
 * is active, plus schema.org Article data that matches the visible headline, date, author and image. A draft, scheduled, private
 * or removed story has no public page and gets no markup at all.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

use WhoIsWho\Core\Content\PublicStory;

/**
 * Story SEO.
 */
final class StorySeo {

	/**
	 * Hook the output.
	 */
	public static function hooks(): void {
		add_action( 'wp_head', array( self::class, 'print_markup' ), 2 );
	}

	/**
	 * Print the markup on a single story page.
	 */
	public static function print_markup(): void {
		if ( is_singular( 'post' ) ) {
			echo self::markup_for( (int) get_queried_object_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every attribute is escaped and the JSON uses the hex flags.
		}
	}

	/**
	 * The head markup for a story, or an empty string unless it is public.
	 *
	 * @param int $post_id Story id.
	 */
	public static function markup_for( int $post_id ): string {
		$story = PublicStory::find( $post_id );
		if ( null === $story || true === $story['preview'] ) {
			return '';
		}
		return self::render_tags( self::tags( $story ) ) . self::render_article( $story );
	}

	/**
	 * Description and Open Graph tags for a public story.
	 *
	 * @param array<string, mixed> $story Public story.
	 * @return array{title: string, description: string, og: array<string, string>}
	 */
	public static function tags( array $story ): array {
		$title       = '' !== (string) $story['seo']['title'] ? (string) $story['seo']['title'] : (string) $story['title'];
		$description = '' !== (string) $story['seo']['description'] ? (string) $story['seo']['description'] : (string) $story['lede'];
		$label       = $story['share']['label'] ?? null;
		if ( is_string( $label ) && '' !== $label ) {
			$description = trim( $label . '. ' . $description );
		}
		$og = array(
			'og:type'        => 'article',
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => (string) $story['url'],
		);
		if ( '' !== (string) $story['share']['image'] ) {
			$og['og:image'] = (string) $story['share']['image'];
		}
		return array(
			'title'       => $title,
			'description' => $description,
			'og'          => $og,
		);
	}

	/**
	 * Escaped head tags.
	 *
	 * @param array{title: string, description: string, og: array<string, string>} $tags Tags.
	 */
	public static function render_tags( array $tags ): string {
		$html = '';
		if ( '' !== $tags['description'] ) {
			$html .= '<meta name="description" content="' . esc_attr( $tags['description'] ) . '">' . "\n";
		}
		foreach ( $tags['og'] as $property => $content ) {
			$html .= '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
		}
		return $html . '<meta name="twitter:card" content="' . ( isset( $tags['og']['og:image'] ) ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	}

	/**
	 * The Article data for a public story.
	 *
	 * @param array<string, mixed> $story Public story.
	 * @return array<string, mixed>
	 */
	public static function article( array $story ): array {
		$data = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => (string) $story['title'],
			'datePublished'    => (string) $story['date'],
			'mainEntityOfPage' => (string) $story['url'],
		);
		if ( '' !== (string) $story['author'] ) {
			$data['author'] = array(
				'@type' => empty( $story['author_editorial'] ) ? 'Person' : 'Organization',
				'name'  => (string) $story['author'],
			);
		}
		if ( '' !== (string) $story['cover'] ) {
			$data['image'] = (string) $story['cover'];
		}
		return $data;
	}

	/**
	 * The Article script element.
	 *
	 * @param array<string, mixed> $story Public story.
	 */
	public static function render_article( array $story ): string {
		$json = wp_json_encode( self::article( $story ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		return false === $json ? '' : '<script type="application/ld+json">' . $json . "</script>\n";
	}
}
