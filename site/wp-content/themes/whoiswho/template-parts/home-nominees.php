<?php
/**
 * Home nominees block (REQ-198, REQ-033, REQ-257, REQ-001): a dark band with a grid of nominee posters and a group filter, shown only
 * while at least one nominee is Live (so the block follows the published awards, with no choice to make). It reads nominees only
 * through PublicNominee, which has no nominator, evidence or contact. People with a portrait come first. The filter is added by home.js;
 * without scripts the grid simply shows everyone. The block is absent when there is nothing to show.
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
$wiw_all = PublicNominee::live( 0, 200 );
if ( array() === $wiw_all ) {
	return;
}
$wiw_with = array();
$wiw_none = array();
$wiw_cats = array();
$wiw_wins = false;
foreach ( $wiw_all as $wiw_n ) {
	$wiw_cats[ $wiw_n->category_id ] = true;
	$wiw_wins                        = $wiw_wins || $wiw_n->is_winner;
	if ( null !== $wiw_n->profile->image_id ) {
		$wiw_with[] = $wiw_n;
	} else {
		$wiw_none[] = $wiw_n;
	}
}
$wiw_show   = array_slice( array_merge( $wiw_with, $wiw_none ), 0, 8 );
$wiw_groups = array();
foreach ( $wiw_show as $wiw_n ) {
	$wiw_groups[ $wiw_n->group_key ] = $wiw_n->group;
}
$wiw_count = count( $wiw_all );
$wiw_ncats = count( $wiw_cats );
/* translators: %d: number of nominees. */
$wiw_summary = sprintf( _n( '%d nominee', '%d nominees', $wiw_count, 'whoiswho' ), $wiw_count );
/* translators: %d: number of award categories. */
$wiw_summary .= ' ' . sprintf( _n( 'in %d category.', 'in %d categories.', $wiw_ncats, 'whoiswho' ), $wiw_ncats ) . ' ' . __( 'Independent judges choose the winners.', 'whoiswho' );
?>
<section class="h-noms" aria-labelledby="home-noms-title">
	<div class="wrap" data-nom-scope>
		<div class="h-noms-head">
			<div>
				<p class="h-kicker"><?php echo esc_html( $wiw_wins ? __( 'The winners and nominees', 'whoiswho' ) : __( 'The nominees', 'whoiswho' ) ); ?></p>
				<h2 class="h-big" id="home-noms-title"><span class="h-line"><span><?php esc_html_e( 'Meet the', 'whoiswho' ); ?></span></span> <span class="h-line"><span><?php esc_html_e( 'nominees.', 'whoiswho' ); ?></span></span></h2>
			</div>
			<div class="h-noms-side">
				<p><?php echo esc_html( $wiw_summary ); ?></p>
				<a class="btn" href="<?php echo esc_url( home_url( '/awards/nominees/' ) ); ?>"><?php esc_html_e( 'See all nominees', 'whoiswho' ); ?></a>
			</div>
		</div>
		<?php if ( count( $wiw_groups ) > 1 ) : ?>
			<div class="h-nom-filter" role="group" aria-label="<?php esc_attr_e( 'Show nominees by group', 'whoiswho' ); ?>" hidden>
				<button type="button" class="chip" data-nom-filter="all" aria-pressed="true"><?php esc_html_e( 'All', 'whoiswho' ); ?></button>
				<?php foreach ( $wiw_groups as $wiw_key => $wiw_label ) : ?>
					<button type="button" class="chip" data-nom-filter="<?php echo esc_attr( (string) $wiw_key ); ?>" aria-pressed="false"><?php echo esc_html( $wiw_label ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<ul class="hn-grid">
			<?php
			foreach ( $wiw_show as $wiw_i => $wiw_n ) :
				$wiw_style = wiw_award_group_style( $wiw_n->group_key );
				$wiw_photo = null !== $wiw_n->profile->image_id ? wp_get_attachment_image(
					$wiw_n->profile->image_id,
					'wiw-poster',
					false,
					array_merge( array( 'class' => 'poster-photo' ), wiw_poster_image_attrs( '', 'card' ) )
				) : '';
				$wiw_role  = $wiw_n->profile->facts['current_title']['value'] ?? '';
				?>
				<li class="hn-item" data-group="<?php echo esc_attr( $wiw_n->group_key ); ?>" style="--d:<?php echo (int) $wiw_i; ?>">
					<a class="hn-card" href="<?php echo esc_url( $wiw_n->url ); ?>">
						<span class="nom-media">
							<span class="poster poster--<?php echo esc_attr( $wiw_style['tone'] ); ?><?php echo '' === $wiw_photo ? ' poster--org' : ''; ?>" aria-hidden="true">
								<span class="poster-pat"></span>
								<span class="poster-mark poster-mark--<?php echo esc_attr( $wiw_style['mark'] ); ?>"></span>
								<?php if ( '' !== $wiw_photo ) : ?>
									<?php echo $wiw_photo; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image. ?>
								<?php else : ?>
									<span class="poster-ini"><?php echo esc_html( wiw_initials( $wiw_n->name ) ); ?></span>
								<?php endif; ?>
							</span>
						</span>
						<span class="hn-body">
							<span class="hn-cat"><?php echo esc_html( ( $wiw_n->is_winner ? __( 'Winner', 'whoiswho' ) . ' · ' : '' ) . $wiw_n->category ); ?></span>
							<span class="hn-name"><?php echo esc_html( $wiw_n->name ); ?></span>
							<?php if ( is_string( $wiw_role ) && '' !== $wiw_role ) : ?>
								<span class="hn-role"><?php echo esc_html( $wiw_role ); ?></span>
							<?php endif; ?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
