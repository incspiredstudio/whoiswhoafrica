<?php
/**
 * The parent theme is never activated alone: show a notice if it is. The site keeps working.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

add_action(
	'admin_notices',
	static function (): void {
		if ( get_stylesheet() !== get_template() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'The parent theme is active on its own. Activate the child theme, which holds the brand values.', 'whoiswho' ) . '</p></div>';
	}
);
