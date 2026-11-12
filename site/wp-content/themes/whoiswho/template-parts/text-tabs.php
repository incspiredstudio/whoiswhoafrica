<?php
/**
 * Plain text tabs with an underline on the current one (story types, REQ-029): links, not buttons.
 * Args: label (accessible name), items (list of array: label, url, current).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_tabs  = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$wiw_label = isset( $args['label'] ) ? (string) $args['label'] : '';
if ( array() === $wiw_tabs ) {
	return;
}
?>
<nav class="bl-pills" aria-label="<?php echo esc_attr( $wiw_label ); ?>">
	<?php foreach ( $wiw_tabs as $wiw_tab ) : ?>
		<a href="<?php echo esc_url( (string) ( $wiw_tab['url'] ?? '' ) ); ?>"<?php echo ! empty( $wiw_tab['current'] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( (string) ( $wiw_tab['label'] ?? '' ) ); ?></a>
	<?php endforeach; ?>
</nav>
