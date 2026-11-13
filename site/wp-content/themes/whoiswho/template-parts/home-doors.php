<?php
/**
 * Home four doors (REQ-001, REQ-002): large colour blocks that link to the four directory lists. On desktop they sit in one row and the
 * one under the pointer grows wider; on phones they form a 2 by 2 grid. Args: home (the home model).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_home  = isset( $args['home'] ) && is_array( $args['home'] ) ? $args['home'] : array();
$wiw_doors = isset( $wiw_home['doors'] ) && is_array( $wiw_home['doors'] ) ? $wiw_home['doors'] : array();
if ( array() === $wiw_doors ) {
	return;
}
?>
<section class="h-doors" aria-label="<?php esc_attr_e( 'The directory', 'whoiswho' ); ?>">
	<?php foreach ( $wiw_doors as $wiw_door ) : ?>
		<a class="h-door h-door--<?php echo esc_attr( (string) $wiw_door['tone'] ); ?>" href="<?php echo esc_url( (string) $wiw_door['url'] ); ?>">
			<span class="h-mark h-door-mark h-mark--<?php echo esc_attr( (string) $wiw_door['mark'] ); ?>" aria-hidden="true"></span>
			<span class="h-door-text"><strong><?php echo esc_html( (string) $wiw_door['title'] ); ?></strong><span><?php echo esc_html( (string) $wiw_door['text'] ); ?></span></span>
		</a>
	<?php endforeach; ?>
</section>
