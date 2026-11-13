<?php
/**
 * Awards overview (/awards) and rules (/awards/rules) (REQ-032, REQ-033, REQ-200, REQ-266). The overview draws the hero with the
 * stage button and counts, the five-step public timeline with the current step marked Now, and the categories in their five groups
 * (read from the Award categories screen, each linking to its page with a nominee count once public). How judging works, eligibility
 * and the rules are page copy edited in the page editor: the template only prints the page content. The rules page always states
 * that there is no public voting and that payment never affects outcomes.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\AwardsStage;
use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Awards\PublicNominee;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_kind = wiw_awards_page_kind();
get_header();
while ( have_posts() ) {
	the_post();
	if ( 'rules' === $wiw_kind ) {
		wiw_page_band(
			array(
				'eyebrow' => wiw_brand( 'edition' ),
				'title'   => get_the_title(),
			)
		);
		?>
		<article <?php post_class( 'wrap wrap--text section prose' ); ?>>
			<?php the_content(); ?>
			<aside class="panel panel--ruled" aria-labelledby="rules-fixed-h">
				<h2 id="rules-fixed-h"><?php esc_html_e( 'Two things that never change', 'whoiswho' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'There is no public voting. Independent judges decide.', 'whoiswho' ); ?></li>
					<li><?php esc_html_e( 'Payment never affects who is nominated, who wins or how anything is scored.', 'whoiswho' ); ?></li>
				</ul>
			</aside>
		</article>
		<?php
		continue;
	}//end if

	$wiw_stage  = AwardsStage::for_site();
	$wiw_button = $wiw_stage->button();
	$wiw_groups = CategoryService::for_site()->grouped();
	$wiw_live   = PublicNominee::live();
	$wiw_counts = array();
	foreach ( $wiw_live as $wiw_one ) {
		$wiw_counts[ $wiw_one->category_id ] = ( $wiw_counts[ $wiw_one->category_id ] ?? 0 ) + 1;
	}
	$wiw_cat_total = 0;
	foreach ( $wiw_groups as $wiw_group ) {
		$wiw_cat_total += count( $wiw_group['categories'] );
	}
	$wiw_now_map = array(
		AwardsStage::CLOSED   => -1,
		AwardsStage::OPEN     => 0,
		AwardsStage::NOMINEES => 1,
		AwardsStage::WINNERS  => 3,
	);
	$wiw_now     = $wiw_now_map[ $wiw_button['stage'] ] ?? -1;
	$wiw_steps   = array(
		__( 'Nominations open', 'whoiswho' ),
		__( 'Nominees announced', 'whoiswho' ),
		__( 'Judging', 'whoiswho' ),
		__( 'Winners announced', 'whoiswho' ),
		__( 'Awards presentation', 'whoiswho' ),
	);
	?>
	<header class="np-hero aw-hero">
		<div class="wrap">
			<p class="np-kicker"><?php echo esc_html( wiw_brand( 'edition' ) ); ?></p>
			<h1 class="np-title"><span><?php esc_html_e( 'The', 'whoiswho' ); ?></span> <span><?php esc_html_e( 'awards', 'whoiswho' ); ?></span></h1>
			<div class="np-intro">
				<div class="aw-intro">
					<p><?php esc_html_e( 'Recognising the people and organisations shaping the continent. Independent judges decide, against criteria published before nominations open.', 'whoiswho' ); ?></p>
					<div class="row">
						<a class="btn" href="<?php echo esc_url( $wiw_button['url'] ); ?>"><?php echo esc_html( $wiw_button['label'] ); ?></a>
						<a class="btn alt aw-btn-alt" href="<?php echo esc_url( home_url( '/awards/rules/' ) ); ?>"><?php esc_html_e( 'Read the rules', 'whoiswho' ); ?></a>
					</div>
				</div>
				<dl class="np-facts">
					<div><dt><?php esc_html_e( 'Groups', 'whoiswho' ); ?></dt><dd><?php echo esc_html( (string) count( $wiw_groups ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Categories', 'whoiswho' ); ?></dt><dd><?php echo esc_html( (string) $wiw_cat_total ); ?></dd></div>
					<?php if ( array() !== $wiw_live ) : ?>
						<div><dt><?php esc_html_e( 'Nominees', 'whoiswho' ); ?></dt><dd><?php echo esc_html( (string) count( $wiw_live ) ); ?></dd></div>
					<?php endif; ?>
				</dl>
			</div>
		</div>
	</header>
	<section class="section aw-stage" aria-labelledby="awStage">
		<div class="wrap">
			<div class="aw-head">
				<p class="np-kicker aw-kick"><?php esc_html_e( 'Where we are', 'whoiswho' ); ?></p>
				<h2 id="awStage"><?php echo esc_html( $wiw_button['status'] ); ?></h2>
			</div>
			<ol class="aw-steps">
				<?php foreach ( $wiw_steps as $wiw_i => $wiw_step ) : ?>
					<li class="<?php echo $wiw_i < $wiw_now ? 'is-done' : ( $wiw_i === $wiw_now ? 'is-now' : '' ); ?>"<?php echo $wiw_i === $wiw_now ? ' aria-current="step"' : ''; ?>>
						<span class="aw-step-n"><?php echo esc_html( '0' . ( $wiw_i + 1 ) ); ?></span>
						<span class="aw-step-t"><?php echo esc_html( $wiw_step ); ?></span>
						<?php if ( $wiw_i === $wiw_now ) : ?>
							<span class="aw-step-here"><?php esc_html_e( 'Now', 'whoiswho' ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>
	<section class="section aw-cats" id="categories" aria-labelledby="awCats">
		<div class="wrap">
			<div class="aw-head">
				<p class="np-kicker aw-kick"><?php esc_html_e( 'Categories', 'whoiswho' ); ?></p>
				<h2 id="awCats">
					<?php
					/* translators: 1: number of groups, 2: number of categories */
					echo esc_html( sprintf( __( '%1$d groups, %2$d categories', 'whoiswho' ), count( $wiw_groups ), $wiw_cat_total ) );
					?>
				</h2>
			</div>
			<div class="aw-groups">
				<?php foreach ( $wiw_groups as $wiw_key => $wiw_group ) : ?>
					<?php
					if ( array() === $wiw_group['categories'] ) {
						continue;
					}
					$wiw_style = wiw_award_group_style( (string) $wiw_key );
					?>
					<section class="aw-group poster poster--<?php echo esc_attr( $wiw_style['tone'] ); ?>" id="<?php echo esc_attr( (string) $wiw_key ); ?>" aria-labelledby="awg-<?php echo esc_attr( (string) $wiw_key ); ?>">
						<span class="poster-pat" aria-hidden="true"></span><span class="poster-mark poster-mark--<?php echo esc_attr( $wiw_style['mark'] ); ?>" aria-hidden="true"></span>
						<div class="aw-group-in">
							<h3 id="awg-<?php echo esc_attr( (string) $wiw_key ); ?>"><?php echo esc_html( (string) $wiw_group['label'] ); ?></h3>
							<p><?php echo esc_html( wiw_award_group_text( (string) $wiw_key ) ); ?></p>
							<ul>
								<?php foreach ( $wiw_group['categories'] as $wiw_cat ) : ?>
									<?php $wiw_count = $wiw_counts[ (int) $wiw_cat['id'] ] ?? 0; ?>
									<li>
										<a href="<?php echo esc_url( home_url( '/awards/category/' . (int) $wiw_cat['id'] . '/' ) ); ?>">
											<span><?php echo esc_html( (string) $wiw_cat['title'] ); ?></span>
											<?php if ( $wiw_count > 0 ) : ?>
												<span class="aw-count"><?php echo esc_html( (string) $wiw_count ); ?><span class="sr"> <?php esc_html_e( 'nominees', 'whoiswho' ); ?></span></span>
											<?php endif; ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<section class="section aw-judge" aria-labelledby="awJudge">
		<div class="wrap aw-judge-in">
			<div class="prose">
				<p class="np-kicker aw-kick"><?php esc_html_e( 'Judging', 'whoiswho' ); ?></p>
				<h2 id="awJudge"><?php esc_html_e( 'How judging works', 'whoiswho' ); ?></h2>
				<?php the_content(); ?>
				<a class="more" href="<?php echo esc_url( home_url( '/awards/rules/' ) ); ?>"><?php esc_html_e( 'Full awards rules', 'whoiswho' ); ?></a>
			</div>
		</div>
		<?php do_action( 'wiw_awards_overview_after_judging' ); ?>
	</section>
	<?php
}//end while
get_footer();
