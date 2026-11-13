<?php
/**
 * Front page (M2-HOM-08..12): the hero, then the sections in order. Each section is its own part under template-parts/ and draws
 * nothing when it has nothing to show, so the page is complete with scripts off, with reduced motion and with data saver.
 * The one h1 is in the hero. Page data comes from wiw_home_model(); profiles, stories and events are read by their parts through
 * the read models only.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$wiw_home = wiw_home_model();
?>
<div class="home">
	<?php
	foreach ( array( 'home-hero', 'home-acts', 'home-record', 'home-doors', 'home-awards', 'home-stories', 'home-events', 'home-final' ) as $wiw_part ) {
		if ( locate_template( 'template-parts/' . $wiw_part . '.php' ) ) {
			get_template_part( 'template-parts/' . $wiw_part, null, array( 'home' => $wiw_home ) );
		}
	}
	?>
</div>
<?php
get_footer();
