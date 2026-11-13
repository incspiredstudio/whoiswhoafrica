<?php
/**
 * The shared filter bar (REQ-029, REQ-264): borderless inline lists, an optional search field and sort, the result count (announced),
 * and a Clear link only while a filter is on. It is a plain GET form, so it works without script; filter-bar.js submits it when a value
 * changes and a visually hidden Apply button remains for keyboard and no-script use. Filters write to the address.
 *
 * Args:
 * - action   string  Address the form submits to (the list page).
 * - label    string  Accessible name of the form.
 * - hidden   array   name => value pairs kept when filters change (for example the story type).
 * - search   array   name, value, placeholder, label (optional).
 * - controls array   each: name, label, blank (text of the "all" option), options (value => label), value.
 * - sort     array   name, value, options (value => label) (optional).
 * - view     array   Grid or list switch (optional): value (grid|list), grid and list (addresses that keep the filters).
 * - count    string  Result count text, for example "12 stories".
 * - clear    bool    Whether any filter is on.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_fb = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'action'   => '',
		'label'    => __( 'Filter results', 'whoiswho' ),
		'hidden'   => array(),
		'search'   => null,
		'controls' => array(),
		'sort'     => null,
		'view'     => null,
		'count'    => null,
		'clear'    => false,
	)
);

/**
 * Print one select.
 *
 * @param array<string, mixed> $control Control definition.
 */
$wiw_fb_select = static function ( array $control ): void {
	$name    = (string) ( $control['name'] ?? '' );
	$label   = (string) ( $control['label'] ?? '' );
	$value   = (string) ( $control['value'] ?? '' );
	$options = isset( $control['options'] ) && is_array( $control['options'] ) ? $control['options'] : array();
	if ( '' === $name ) {
		return;
	}
	?>
	<label class="fbar-sel<?php echo '' !== $value ? ' is-on' : ''; ?>">
		<span class="sr"><?php echo esc_html( $label ); ?></span>
		<select name="<?php echo esc_attr( $name ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
			<?php if ( ! empty( $control['blank'] ) ) : ?>
				<option value=""><?php echo esc_html( (string) $control['blank'] ); ?></option>
			<?php endif; ?>
			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( (string) $option_value ); ?>"<?php selected( $value, (string) $option_value ); ?>><?php echo esc_html( (string) $option_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
};
?>
<form class="fbar" method="get" action="<?php echo esc_url( (string) $wiw_fb['action'] ); ?>" data-filter aria-label="<?php echo esc_attr( (string) $wiw_fb['label'] ); ?>">
	<div class="fbar-l">
		<?php if ( is_array( $wiw_fb['view'] ) && 'list' === ( $wiw_fb['view']['value'] ?? '' ) ) : ?>
			<input type="hidden" name="view" value="list">
		<?php endif; ?>
		<?php foreach ( (array) $wiw_fb['hidden'] as $wiw_name => $wiw_val ) : ?>
			<input type="hidden" name="<?php echo esc_attr( (string) $wiw_name ); ?>" value="<?php echo esc_attr( (string) $wiw_val ); ?>">
		<?php endforeach; ?>
		<?php if ( is_array( $wiw_fb['search'] ) && ! empty( $wiw_fb['search']['name'] ) ) : ?>
			<label class="fbar-q">
				<span class="sr"><?php echo esc_html( (string) ( $wiw_fb['search']['label'] ?? __( 'Search', 'whoiswho' ) ) ); ?></span>
				<?php echo wiw_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
				<input type="search" name="<?php echo esc_attr( (string) $wiw_fb['search']['name'] ); ?>" value="<?php echo esc_attr( (string) ( $wiw_fb['search']['value'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) ( $wiw_fb['search']['placeholder'] ?? __( 'Search', 'whoiswho' ) ) ); ?>">
			</label>
		<?php endif; ?>
		<?php
		foreach ( (array) $wiw_fb['controls'] as $wiw_control ) {
			if ( is_array( $wiw_control ) ) {
				$wiw_fb_select( $wiw_control );
			}
		}
		if ( is_array( $wiw_fb['sort'] ) && ! empty( $wiw_fb['sort']['name'] ) ) {
			$wiw_sort_options = array();
			foreach ( (array) ( $wiw_fb['sort']['options'] ?? array() ) as $wiw_sv => $wiw_sl ) {
				/* translators: %s: sort order, for example Newest. */
				$wiw_sort_options[ (string) $wiw_sv ] = sprintf( __( 'Sort: %s', 'whoiswho' ), (string) $wiw_sl );
			}
			$wiw_fb_select(
				array(
					'name'    => $wiw_fb['sort']['name'],
					'label'   => __( 'Sort by', 'whoiswho' ),
					'value'   => $wiw_fb['sort']['value'] ?? '',
					'options' => $wiw_sort_options,
				)
			);
		}
		?>
	</div>
	<div class="fbar-r">
		<?php if ( null !== $wiw_fb['count'] ) : ?>
			<span class="fbar-n" role="status"><?php echo esc_html( (string) $wiw_fb['count'] ); ?></span>
		<?php endif; ?>
		<?php if ( is_array( $wiw_fb['view'] ) && ! empty( $wiw_fb['view']['grid'] ) && ! empty( $wiw_fb['view']['list'] ) ) : ?>
			<?php $wiw_is_list = 'list' === ( $wiw_fb['view']['value'] ?? '' ); ?>
			<nav class="view-toggle" aria-label="<?php esc_attr_e( 'Layout', 'whoiswho' ); ?>">
				<a href="<?php echo esc_url( (string) $wiw_fb['view']['grid'] ); ?>"<?php echo $wiw_is_list ? '' : ' aria-current="true"'; ?>><?php echo wiw_icon( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><span><?php esc_html_e( 'Grid', 'whoiswho' ); ?></span></a>
				<a href="<?php echo esc_url( (string) $wiw_fb['view']['list'] ); ?>"<?php echo $wiw_is_list ? ' aria-current="true"' : ''; ?>><?php echo wiw_icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><span><?php esc_html_e( 'List', 'whoiswho' ); ?></span></a>
			</nav>
		<?php endif; ?>
		<?php if ( ! empty( $wiw_fb['clear'] ) ) : ?>
			<a class="fbar-clear" href="<?php echo esc_url( (string) $wiw_fb['action'] ); ?>"><?php esc_html_e( 'Clear', 'whoiswho' ); ?></a>
		<?php endif; ?>
	</div>
	<button class="sr" type="submit"><?php esc_html_e( 'Apply filters', 'whoiswho' ); ?></button>
</form>
