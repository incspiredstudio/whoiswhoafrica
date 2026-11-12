<?php
/**
 * Block patterns for the editable pages (REQ-260): the theme supplies the structure, staff edit the words. The pattern files live
 * in patterns/ and WordPress registers them; this file adds their category. Forms, labels, header, footer and the hero videos are
 * not blocks and have no pattern.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

add_action(
	'init',
	static function (): void {
		register_block_pattern_category(
			'whoiswho',
			array( 'label' => __( 'Page sections', 'whoiswho' ) )
		);
	}
);
