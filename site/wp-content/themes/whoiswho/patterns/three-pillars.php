<?php
/**
 * Title: Three pillars
 * Slug: whoiswho/three-pillars
 * Categories: whoiswho
 * Description: Three titled sentences on brand colour blocks. The colours and marks are fixed; only the words are edited.
 * Keywords: pillars, values
 *
 * @package WhoIsWho
 */

$wiw_pillars = array(
	array(
		'tone' => 'blue',
		'mark' => 'star',
		'num'  => '01',
	),
	array(
		'tone' => 'orange',
		'mark' => 'comb',
		'num'  => '02',
	),
	array(
		'tone' => 'green',
		'mark' => 'spiral',
		'num'  => '03',
	),
);
?>
<!-- wp:group {"tagName":"section","className":"ab-pillars"} -->
<section class="wp-block-group ab-pillars"><!-- wp:group {"className":"wrap"} -->
<div class="wp-block-group wrap"><!-- wp:group {"className":"ab-pillar-grid"} -->
<div class="wp-block-group ab-pillar-grid">
<?php foreach ( $wiw_pillars as $wiw_pillar ) : ?>
<!-- wp:group {"className":"ab-pillar poster poster--<?php echo esc_attr( $wiw_pillar['tone'] ); ?>","lock":{"move":true,"remove":true}} -->
<div class="wp-block-group ab-pillar poster poster--<?php echo esc_attr( $wiw_pillar['tone'] ); ?>"><!-- wp:html -->
<span class="poster-pat" aria-hidden="true"></span><span class="poster-mark poster-mark--<?php echo esc_attr( $wiw_pillar['mark'] ); ?>" aria-hidden="true"></span>
<!-- /wp:html --><!-- wp:group {"className":"ab-pillar-in"} -->
<div class="wp-block-group ab-pillar-in"><!-- wp:paragraph {"className":"ab-num"} -->
<p class="ab-num"><?php echo esc_html( $wiw_pillar['num'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'Pillar title', 'pattern text', 'whoiswho' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'One sentence that explains the pillar.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<?php endforeach; ?>
</div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
