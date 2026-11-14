<?php
/**
 * Page band: eyebrow, title (the page's h1), lede, optional tone, search field and tab chips. The title always lines up with the
 * page's wrap, as in the prototype; the lede keeps a reading measure.
 *
 * Args:
 * - eyebrow string  Small label above the title.
 * - title   string  The page's h1.
 * - lede    string  One-line introduction.
 * - tone    string  '' (plain, on the page background), 'light' (surface band with a rule), or a dark band: 'dark', 'blue',
 *                   'purple', 'green' or 'red'. Dark bands carry the approved-marks pattern.
 * - compact bool    A short band: title left, the search field right, tabs below (the directory lists).
 * - search  array   Optional GET search field in the band: action, name, value, label, placeholder, button, hidden (name => value
 *                   pairs kept when it is submitted).
 * - tabs    array   Optional tab chips: label (accessible name), items (list of array: label, url, current).
 * - wide    bool    Kept for older callers; every band now lines up with the wrap.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_band = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'eyebrow' => '',
		'title'   => '',
		'lede'    => '',
		'tone'    => '',
		'compact' => false,
		'search'  => null,
		'tabs'    => null,
		'wide'    => true,
	)
);

$wiw_tone    = sanitize_html_class( (string) $wiw_band['tone'] );
$wiw_classes = array( 'page-band' );
if ( '' !== $wiw_tone ) {
	$wiw_classes[] = 'page-band--' . $wiw_tone;
}
if ( 'light' === $wiw_tone ) {
	$wiw_classes[] = 'title-band';
	$wiw_classes[] = 'title-band--light';
} elseif ( in_array( $wiw_tone, array( 'dark', 'blue', 'purple', 'green', 'red' ), true ) ) {
	$wiw_classes[] = 'title-band';
	$wiw_classes[] = 'on-dark';
	$wiw_classes[] = 'pattern';
	if ( 'dark' !== $wiw_tone ) {
		$wiw_classes[] = 'tone-' . $wiw_tone;
	}
} else {
	$wiw_classes[] = 'section';
	$wiw_classes[] = 'section--tight';
}
if ( ! empty( $wiw_band['compact'] ) ) {
	$wiw_classes[] = 'title-band--compact';
}

$wiw_search = is_array( $wiw_band['search'] ) && ! empty( $wiw_band['search']['name'] ) ? $wiw_band['search'] : null;
$wiw_tabs   = is_array( $wiw_band['tabs'] ) && ! empty( $wiw_band['tabs']['items'] ) && is_array( $wiw_band['tabs']['items'] ) ? $wiw_band['tabs'] : null;
$wiw_sid    = null !== $wiw_search ? wp_unique_id( 'band-q-' ) : '';
?>
<section class="<?php echo esc_attr( implode( ' ', $wiw_classes ) ); ?>">
	<div class="wrap">
		<?php if ( null !== $wiw_search ) : ?>
		<div class="band-row">
			<div class="band-main">
		<?php endif; ?>
		<?php if ( '' !== $wiw_band['eyebrow'] ) : ?>
			<p class="eyebrow"><?php echo esc_html( (string) $wiw_band['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h1><?php echo esc_html( (string) $wiw_band['title'] ); ?></h1>
		<?php if ( '' !== $wiw_band['lede'] ) : ?>
			<p class="lede"><?php echo esc_html( (string) $wiw_band['lede'] ); ?></p>
		<?php endif; ?>
		<?php if ( null !== $wiw_search ) : ?>
			</div>
			<form class="dir-search" role="search" method="get" action="<?php echo esc_url( (string) ( $wiw_search['action'] ?? '' ) ); ?>" aria-label="<?php echo esc_attr( (string) ( $wiw_search['label'] ?? __( 'Search', 'whoiswho' ) ) ); ?>">
				<?php foreach ( (array) ( $wiw_search['hidden'] ?? array() ) as $wiw_hname => $wiw_hval ) : ?>
					<?php if ( '' !== (string) $wiw_hval ) : ?>
						<input type="hidden" name="<?php echo esc_attr( (string) $wiw_hname ); ?>" value="<?php echo esc_attr( (string) $wiw_hval ); ?>">
					<?php endif; ?>
				<?php endforeach; ?>
				<label class="sr" for="<?php echo esc_attr( $wiw_sid ); ?>"><?php echo esc_html( (string) ( $wiw_search['label'] ?? __( 'Search', 'whoiswho' ) ) ); ?></label>
				<input id="<?php echo esc_attr( $wiw_sid ); ?>" type="search" name="<?php echo esc_attr( (string) $wiw_search['name'] ); ?>" value="<?php echo esc_attr( (string) ( $wiw_search['value'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) ( $wiw_search['placeholder'] ?? '' ) ); ?>">
				<button class="btn" type="submit"><?php echo esc_html( (string) ( $wiw_search['button'] ?? __( 'Search', 'whoiswho' ) ) ); ?></button>
			</form>
		</div>
		<?php endif; ?>
		<?php if ( null !== $wiw_tabs ) : ?>
			<nav class="tabs" aria-label="<?php echo esc_attr( (string) ( $wiw_tabs['label'] ?? '' ) ); ?>">
				<?php foreach ( $wiw_tabs['items'] as $wiw_tab ) : ?>
					<a href="<?php echo esc_url( (string) ( $wiw_tab['url'] ?? '' ) ); ?>"<?php echo ! empty( $wiw_tab['current'] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( (string) ( $wiw_tab['label'] ?? '' ) ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>
</section>
