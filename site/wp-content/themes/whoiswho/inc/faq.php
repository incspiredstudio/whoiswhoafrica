<?php
/**
 * The FAQ blocks (REQ-260): a group (heading and questions) and a question (title and rich answer), edited in the page editor.
 * Output is accessible disclosure markup (details and summary, which work without script and by keyboard): the first question of
 * the page is open and the rest are collapsed. FAQ structured data is off by default and is printed only on the FAQ page.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_faq_opened' ) ) {
	/**
	 * Whether the first question of the page has been opened, per post; pass true to reset (tests).
	 *
	 * @param int|null $post_id Post to mark as done, or null to read the post's flag.
	 * @param bool     $reset   Forget every mark.
	 */
	function wiw_faq_opened( ?int $post_id = null, bool $reset = false ): bool {
		static $done = array();
		if ( $reset ) {
			$done = array();
			return false;
		}
		$key = (int) get_the_ID();
		if ( null !== $post_id ) {
			$done[ $post_id ] = true;
			return true;
		}
		return isset( $done[ $key ] );
	}
}//end if

if ( ! function_exists( 'wiw_faq_render_group' ) ) {
	/**
	 * Render a group: heading and the questions already rendered in $content. The first question on the page gets "open".
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Rendered questions.
	 */
	function wiw_faq_render_group( array $attributes, string $content ): string {
		if ( ! wiw_faq_opened() ) {
			$count = 0;
			$tmp   = preg_replace( '/<details class="faq-q"/', '<details class="faq-q" open', $content, 1, $count );
			if ( $count > 0 && is_string( $tmp ) ) {
				$content = $tmp;
				wiw_faq_opened( (int) get_the_ID() );
			}
		}
		$heading = wp_strip_all_tags( (string) ( $attributes['heading'] ?? '' ) );
		return '<section class="faq-group">'
			. ( '' !== $heading ? '<h2 class="faq-group-h">' . esc_html( $heading ) . '</h2>' : '' )
			. '<div class="faq-list">' . $content . '</div></section>';
	}
}//end if

if ( ! function_exists( 'wiw_faq_render_question' ) ) {
	/**
	 * Render a question as a disclosure. The answer is the rendered inner blocks.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Rendered answer.
	 */
	function wiw_faq_render_question( array $attributes, string $content ): string {
		$title = wp_strip_all_tags( (string) ( $attributes['title'] ?? '' ) );
		if ( '' === $title ) {
			return '';
		}
		return '<details class="faq-q"><summary>' . esc_html( $title ) . '</summary><div class="faq-a">' . $content . '</div></details>';
	}
}

add_action(
	'init',
	static function (): void {
		wp_register_script(
			'wiw-faq-editor',
			get_template_directory_uri() . '/blocks/faq-group/editor.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n' ),
			defined( 'WIW_THEME_VERSION' ) ? WIW_THEME_VERSION : '1',
			true
		);
		$registry = WP_Block_Type_Registry::get_instance();
		if ( ! $registry->is_registered( 'whoiswho/faq-group' ) ) {
			register_block_type( get_template_directory() . '/blocks/faq-group', array( 'render_callback' => 'wiw_faq_render_group' ) );
		}
		if ( ! $registry->is_registered( 'whoiswho/faq-question' ) ) {
			register_block_type( get_template_directory() . '/blocks/faq-question', array( 'render_callback' => 'wiw_faq_render_question' ) );
		}
	}
);

if ( ! function_exists( 'wiw_faq_items' ) ) {
	/**
	 * Question and answer text pairs of the FAQ blocks in some content, in order.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array{question: string, answer: string}>
	 */
	function wiw_faq_items( array $blocks ): array {
		$items = array();
		foreach ( $blocks as $block ) {
			if ( 'whoiswho/faq-question' === ( $block['blockName'] ?? '' ) ) {
				$answer = '';
				foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
					$answer .= render_block( $inner );
				}
				$question = wp_strip_all_tags( (string) ( $block['attrs']['title'] ?? '' ) );
				$answer   = trim( wp_strip_all_tags( $answer ) );
				if ( '' !== $question && '' !== $answer ) {
					$items[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
				continue;
			}
			$items = array_merge( $items, wiw_faq_items( (array) ( $block['innerBlocks'] ?? array() ) ) );
		}
		return $items;
	}
}//end if

if ( ! function_exists( 'wiw_faq_schema_enabled' ) ) {
	/**
	 * Whether FAQ structured data is switched on: off by default (the team has not decided), changed by the option or the filter.
	 */
	function wiw_faq_schema_enabled(): bool {
		return (bool) apply_filters( 'wiw_faq_schema_enabled', '1' === (string) get_option( 'wiw_faq_schema', '0' ) );
	}
}

if ( ! function_exists( 'wiw_faq_schema_json' ) ) {
	/**
	 * The FAQPage JSON-LD for some content, or an empty string when it has no questions.
	 *
	 * @param string $content Post content.
	 */
	function wiw_faq_schema_json( string $content ): string {
		$items = wiw_faq_items( parse_blocks( $content ) );
		if ( array() === $items ) {
			return '';
		}
		$entities = array();
		foreach ( $items as $item ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['answer'],
				),
			);
		}
		return (string) wp_json_encode(
			array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $entities,
			),
			JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);
	}
}//end if

add_action(
	'wp_head',
	static function (): void {
		if ( ! is_page( 'faq' ) || ! wiw_faq_schema_enabled() ) {
			return;
		}
		$json = wiw_faq_schema_json( (string) get_post_field( 'post_content', (int) get_queried_object_id() ) );
		if ( '' !== $json ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON encoded with HEX_TAG and HEX_AMP.
		}
	},
	20
);
