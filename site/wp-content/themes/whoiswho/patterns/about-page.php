<?php
/**
 * Title: About page
 * Slug: whoiswho/about-page
 * Categories: whoiswho
 * Description: The whole About page: hero, purpose, the gap, three words, three ways in, how we keep it credible, closing call. Carries a removable draft note.
 * Keywords: about, page, mission
 *
 * @package WhoIsWho
 */

$wiw_pillars = array(
	array( 'blue', 'star', '01', _x( 'Influence', 'pattern text', 'whoiswho' ), _x( 'The people and organisations making a difference and shaping the world around them.', 'pattern text', 'whoiswho' ) ),
	array( 'orange', 'comb', '02', _x( 'Achievement', 'pattern text', 'whoiswho' ), _x( 'The milestones, contributions and innovations that deserve to be recognised.', 'pattern text', 'whoiswho' ) ),
	array( 'green', 'spiral', '03', _x( 'Legacy', 'pattern text', 'whoiswho' ), _x( 'The lasting impact people and institutions leave behind.', 'pattern text', 'whoiswho' ) ),
);
$wiw_ways    = array(
	array( '/directory/', _x( 'The directory', 'pattern text', 'whoiswho' ), _x( 'Profiles of people, businesses and institutions, with the facts and the sources behind each one.', 'pattern text', 'whoiswho' ) ),
	array( '/stories/', _x( 'Stories', 'pattern text', 'whoiswho' ), _x( 'Interviews, features and news about the people and ideas on the record.', 'pattern text', 'whoiswho' ) ),
	array( '/awards/', _x( 'The awards', 'pattern text', 'whoiswho' ), _x( 'Recognition decided by independent judges, under rules published before nominations open.', 'pattern text', 'whoiswho' ) ),
);
$wiw_checks  = array(
	array( _x( 'Sourced', 'pattern text', 'whoiswho' ), _x( 'Every fact shows where it came from, so you can check it yourself.', 'pattern text', 'whoiswho' ) ),
	array( _x( 'Reviewed by people', 'pattern text', 'whoiswho' ), _x( 'Our team reads what we publish. Nothing about a person is added without a source.', 'pattern text', 'whoiswho' ) ),
	array( _x( 'Corrected in the open', 'pattern text', 'whoiswho' ), _x( 'Anyone can ask for a correction or a removal, with no account and no fee.', 'pattern text', 'whoiswho' ) ),
);
?>
<!-- wp:group {"tagName":"header","className":"ab-hero"} -->
<header class="wp-block-group ab-hero"><!-- wp:group {"className":"wrap ab-hero-in"} -->
<div class="wp-block-group wrap ab-hero-in"><!-- wp:group {"className":"ab-hero-text"} -->
<div class="wp-block-group ab-hero-text"><!-- wp:paragraph {"className":"np-kicker"} -->
<p class="np-kicker"><?php echo esc_html_x( 'About us', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"ab-title"} -->
<h1 class="wp-block-heading ab-title"><?php echo esc_html_x( 'Africa’s influence, on the record.', 'pattern text', 'whoiswho' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"ab-lede"} -->
<p class="ab-lede"><?php echo esc_html_x( 'An African discovery, recognition and media platform. We document the people, businesses and institutions shaping the continent, tell the stories behind them, and recognise achievement through independent awards.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"ab-purpose"} -->
<section class="wp-block-group ab-purpose"><!-- wp:group {"className":"wrap"} -->
<div class="wp-block-group wrap"><!-- wp:paragraph {"className":"np-kicker"} -->
<p class="np-kicker"><?php echo esc_html_x( 'Why we exist', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"ab-purpose-text"} -->
<p class="ab-purpose-text"><?php echo esc_html_x( 'To make African influence visible, celebrate meaningful achievement and preserve the stories and legacies that shape the continent.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"section ab-why"} -->
<section class="wp-block-group section ab-why"><!-- wp:group {"className":"wrap ab-why-in"} -->
<div class="wp-block-group wrap ab-why-in"><!-- wp:group -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"np-kicker aw-kick"} -->
<p class="np-kicker aw-kick"><?php echo esc_html_x( 'The gap', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"ab-pull"} -->
<p class="ab-pull"><?php echo esc_html_x( 'Who runs what, who built what and who achieved what is scattered across registries, annual reports and press releases.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"ab-prose"} -->
<div class="wp-block-group ab-prose"><!-- wp:paragraph -->
<p><?php echo esc_html_x( 'It is hard to find, and harder to trust. We bring it together in one place, as structured profiles with a source beside every fact.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Around the record we publish stories that explain it, and an awards programme that recognises what it shows. We are a curated destination, not another social network.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"notice notice--draft"} -->
<p class="notice notice--draft"><?php echo esc_html_x( 'Draft copy for review. The final mission, vision and values come from Brand. Delete this note when they are in.', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"ab-pillars"} -->
<section class="wp-block-group ab-pillars"><!-- wp:group {"className":"wrap"} -->
<div class="wp-block-group wrap"><!-- wp:group {"className":"ab-pillar-grid"} -->
<div class="wp-block-group ab-pillar-grid">
<?php foreach ( $wiw_pillars as $wiw_pillar ) : ?>
<!-- wp:group {"className":"ab-pillar poster poster--<?php echo esc_attr( $wiw_pillar[0] ); ?>","lock":{"move":true,"remove":true}} -->
<div class="wp-block-group ab-pillar poster poster--<?php echo esc_attr( $wiw_pillar[0] ); ?>"><!-- wp:html -->
<span class="poster-pat" aria-hidden="true"></span><span class="poster-mark poster-mark--<?php echo esc_attr( $wiw_pillar[1] ); ?>" aria-hidden="true"></span>
<!-- /wp:html --><!-- wp:group {"className":"ab-pillar-in"} -->
<div class="wp-block-group ab-pillar-in"><!-- wp:paragraph {"className":"ab-num"} -->
<p class="ab-num"><?php echo esc_html( $wiw_pillar[2] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html( $wiw_pillar[3] ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html( $wiw_pillar[4] ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<?php endforeach; ?>
</div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"section ab-do"} -->
<section class="wp-block-group section ab-do"><!-- wp:group {"className":"wrap"} -->
<div class="wp-block-group wrap"><!-- wp:group {"className":"ab-do-head"} -->
<div class="wp-block-group ab-do-head"><!-- wp:paragraph {"className":"np-kicker"} -->
<p class="np-kicker"><?php echo esc_html_x( 'What you will find here', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Three ways into the record', 'pattern text', 'whoiswho' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:list {"ordered":true,"className":"ab-do-list"} -->
<ol class="wp-block-list ab-do-list">
<?php foreach ( $wiw_ways as $wiw_way ) : ?>
<!-- wp:list-item -->
<li><a href="<?php echo esc_url( home_url( $wiw_way[0] ) ); ?>"><?php echo esc_html( $wiw_way[1] ); ?></a> <?php echo esc_html( $wiw_way[2] ); ?></li>
<!-- /wp:list-item -->
<?php endforeach; ?>
</ol>
<!-- /wp:list --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"section ab-how"} -->
<section class="wp-block-group section ab-how"><!-- wp:group {"className":"wrap"} -->
<div class="wp-block-group wrap"><!-- wp:group {"className":"ab-do-head"} -->
<div class="wp-block-group ab-do-head"><!-- wp:paragraph {"className":"np-kicker"} -->
<p class="np-kicker"><?php echo esc_html_x( 'How we keep it credible', 'pattern text', 'whoiswho' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Built to be checked', 'pattern text', 'whoiswho' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"ab-how-list"} -->
<div class="wp-block-group ab-how-list">
<?php foreach ( $wiw_checks as $wiw_check ) : ?>
<!-- wp:group {"className":"ab-how-item"} -->
<div class="wp-block-group ab-how-item"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html( $wiw_check[0] ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html( $wiw_check[1] ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<?php endforeach; ?>
</div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"ab-cta"} -->
<section class="wp-block-group ab-cta"><!-- wp:group {"className":"wrap ab-cta-in"} -->
<div class="wp-block-group wrap ab-cta-in"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'Questions, a correction or a story to tell?', 'pattern text', 'whoiswho' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html_x( 'Contact us', 'pattern text', 'whoiswho' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php echo esc_html_x( 'Read the questions', 'pattern text', 'whoiswho' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
