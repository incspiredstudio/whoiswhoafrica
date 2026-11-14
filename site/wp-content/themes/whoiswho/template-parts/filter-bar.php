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
 * - controls array   each: name, label, blank (text of the "all" option), options (value => label, or a group key =>
 *                    array( label, options ) printed as an optgroup), value.
 * - sort     array   name, value, options (value => label) (optional).
 * - view     array   Grid or list switch (optional): value (grid|list), grid and list (addresses that keep the filters).
 * - count    string  Result count text, for example "12 stories".
 * - total    int     The result count as a number (optional): labels the phone sheet's "Show N results" button.
 * - tabs     array   Type tabs shown as chips inside the phone sheet (optional): title (heading, default "Type"), items (label,
 *                    url, current).
 * - clear    bool    Whether any filter is on.
 *
 * Phones (REQ-044, REQ-007): with script, below 48rem the selects move into a bottom sheet (a native modal dialog) opened by one
 * "Filters (n)" button, and the filters that are on show as chips, each a link that removes that one filter. The sheet (inside the form)
 * has a footer that shows "Show N results" (refreshed from the same list address as the choices change) and "Clear all". Without script
 * nothing of this shows and the form is the inline bar above; the addresses and parameters are the same either way.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_fb       = wp_parse_args(
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
		'total'    => null,
		'tabs'     => null,
		'clear'    => false,
	)
);
$wiw_fb_state = wiw_filter_bar_state( $wiw_fb );
$wiw_fb_on    = count( $wiw_fb_state['chips'] );
$wiw_fb_id    = wp_unique_id( 'fbar-' );
$wiw_fb_tabs  = is_array( $wiw_fb['tabs'] ) && ! empty( $wiw_fb['tabs']['items'] ) && is_array( $wiw_fb['tabs']['items'] ) ? $wiw_fb['tabs'] : null;
$wiw_fb_total = null !== $wiw_fb['total'] ? (int) $wiw_fb['total'] : null;
/* translators: %d: number of results. */
$wiw_fb_one = __( 'Show %d result', 'whoiswho' );
/* translators: %d: number of results. */
$wiw_fb_many = __( 'Show %d results', 'whoiswho' );

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
				<?php if ( is_array( $option_label ) ) : ?>
					<optgroup label="<?php echo esc_attr( (string) ( $option_label['label'] ?? '' ) ); ?>">
						<?php foreach ( (array) ( $option_label['options'] ?? array() ) as $group_value => $group_label ) : ?>
							<option value="<?php echo esc_attr( (string) $group_value ); ?>"<?php selected( $value, (string) $group_value ); ?>><?php echo esc_html( (string) $group_label ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				<?php else : ?>
					<option value="<?php echo esc_attr( (string) $option_value ); ?>"<?php selected( $value, (string) $option_value ); ?>><?php echo esc_html( (string) $option_label ); ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
};
?>
<div class="fbar-box" data-fbar-box>
<form class="fbar" id="<?php echo esc_attr( $wiw_fb_id ); ?>" method="get" action="<?php echo esc_url( (string) $wiw_fb['action'] ); ?>" data-filter aria-label="<?php echo esc_attr( (string) $wiw_fb['label'] ); ?>">
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
		<?php if ( array() !== $wiw_fb['controls'] || is_array( $wiw_fb['sort'] ) ) : ?>
			<button type="button" class="fbar-open" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $wiw_fb_id ); ?>-sheet" aria-expanded="false" data-fbar-open>
				<?php echo wiw_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
				<span><?php esc_html_e( 'Filters', 'whoiswho' ); ?></span>
				<?php if ( $wiw_fb_on > 0 ) : ?>
					<span class="fbar-open-n">
						<?php
						/* translators: %d: number of filters that are on. */
						echo esc_html( sprintf( __( '(%d)', 'whoiswho' ), $wiw_fb_on ) );
						?>
					</span>
				<?php endif; ?>
			</button>
		<?php endif; ?>
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
	<?php
	// The phone sheet sits inside the form, so its fields belong to it; its button applies them through filter-bar.js (the
	// form keeps one submit button). Closed, it is not shown.
	?>
	<dialog class="fbar-sheet" id="<?php echo esc_attr( $wiw_fb_id ); ?>-sheet" aria-labelledby="<?php echo esc_attr( $wiw_fb_id ); ?>-h" data-fbar-sheet>
		<div class="fbar-sheet-head">
			<h2 id="<?php echo esc_attr( $wiw_fb_id ); ?>-h"><?php esc_html_e( 'Filters', 'whoiswho' ); ?></h2>
			<button type="button" class="icon-btn fbar-sheet-x" data-fbar-close aria-label="<?php esc_attr_e( 'Close filters', 'whoiswho' ); ?>"><?php echo wiw_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></button>
		</div>
		<div class="fbar-sheet-body" data-lenis-prevent>
			<?php if ( null !== $wiw_fb_tabs ) : ?>
				<?php
				// The page's own tabs stay where they are (and carry aria-current="page"); these are the same links as chips, under
				// their own heading, the current one marked aria-current="true".
				?>
				<div class="fbar-sheet-group">
					<p class="fbar-sheet-k" id="<?php echo esc_attr( $wiw_fb_id ); ?>-t"><?php echo esc_html( (string) ( $wiw_fb_tabs['title'] ?? __( 'Type', 'whoiswho' ) ) ); ?></p>
					<ul class="fbar-sheet-tabs" aria-labelledby="<?php echo esc_attr( $wiw_fb_id ); ?>-t">
						<?php foreach ( $wiw_fb_tabs['items'] as $wiw_tab ) : ?>
							<li><a href="<?php echo esc_url( (string) ( $wiw_tab['url'] ?? '' ) ); ?>"<?php echo ! empty( $wiw_tab['current'] ) ? ' aria-current="true"' : ''; ?>><?php echo esc_html( (string) ( $wiw_tab['label'] ?? '' ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<div class="fbar-sheet-fields" data-fbar-fields></div>
		</div>
		<div class="fbar-sheet-foot">
			<a class="btn alt fbar-sheet-clear" href="<?php echo esc_url( (string) $wiw_fb['action'] ); ?>"><?php esc_html_e( 'Clear all', 'whoiswho' ); ?></a>
			<button type="button" class="btn fbar-sheet-go" data-fbar-go data-one="<?php echo esc_attr( $wiw_fb_one ); ?>" data-many="<?php echo esc_attr( $wiw_fb_many ); ?>"><?php echo esc_html( null !== $wiw_fb_total ? sprintf( 1 === $wiw_fb_total ? $wiw_fb_one : $wiw_fb_many, $wiw_fb_total ) : __( 'Show results', 'whoiswho' ) ); ?></button>
		</div>
	</dialog>
</form>
<?php if ( array() !== $wiw_fb_state['chips'] ) : ?>
	<ul class="fbar-chips" aria-label="<?php esc_attr_e( 'Filters on', 'whoiswho' ); ?>">
		<?php foreach ( $wiw_fb_state['chips'] as $wiw_chip ) : ?>
			<li>
				<a class="fbar-chip" href="<?php echo esc_url( $wiw_chip['url'] ); ?>">
					<span class="sr">
						<?php
						/* translators: %s: filter name, for example Industry. */
						echo esc_html( sprintf( __( 'Remove filter %s:', 'whoiswho' ), $wiw_chip['label'] ) );
						?>
					</span>
					<span><?php echo esc_html( $wiw_chip['value'] ); ?></span>
					<?php echo wiw_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
</div>
