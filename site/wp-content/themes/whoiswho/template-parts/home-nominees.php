<?php
/**
 * Home nominees grid (REQ-198, REQ-033, REQ-001): up to eight live nominees as posters, with group filters that are hidden on phones.
 * The block is absent, not empty, until nominees are public: it reads only PublicNominee, which returns nothing for a nomination that
 * is not live or whose profile is not reviewed and public. There is no awards band and no awards button on the home page; the
 * link goes to the nominees page. The filters need script (assets/js/home-nominees.js shows them); without it the grid is complete.
 * Args: home (the home model, unused).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\PublicNominee;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( PublicNominee::class ) ) {
	return;
}
$wiw_nominees = PublicNominee::live( 0, 8 );
if ( array() === $wiw_nominees ) {
	return;
}
$wiw_groups = array();
foreach ( $wiw_nominees as $wiw_one ) {
	$wiw_groups[ $wiw_one->group_key ] = $wiw_one->group;
}
?>
<section class="h-nominees" id="home-nominees" aria-labelledby="home-nominees-title" data-filter-scope>
	<div class="wrap h-sec-head">
		<h2 class="h-kicker" id="home-nominees-title"><?php esc_html_e( 'The nominees', 'whoiswho' ); ?></h2>
		<a class="h-link" href="<?php echo esc_url( home_url( '/awards/nominees/' ) ); ?>"><?php esc_html_e( 'All nominees', 'whoiswho' ); ?><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></a>
	</div>
	<div class="wrap">
		<?php if ( count( $wiw_groups ) > 1 ) : ?>
			<div class="h-nominees-filter" role="group" aria-label="<?php esc_attr_e( 'Filter nominees by group', 'whoiswho' ); ?>" hidden>
				<button class="h-nominees-btn" type="button" data-group="all" aria-pressed="true"><?php esc_html_e( 'All', 'whoiswho' ); ?></button>
				<?php foreach ( $wiw_groups as $wiw_key => $wiw_label ) : ?>
					<button class="h-nominees-btn" type="button" data-group="<?php echo esc_attr( (string) $wiw_key ); ?>" aria-pressed="false"><?php echo esc_html( (string) $wiw_label ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="nom-grid">
			<?php foreach ( $wiw_nominees as $wiw_one ) : ?>
				<?php get_template_part( 'template-parts/nominee-card', null, array( 'nominee' => $wiw_one ) ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
