<?php
/**
 * The Nominate page at /nominate (REQ-259, REQ-250, REQ-194, REQ-030). The Nominations switch alone decides the state, read through
 * wiw_awards_stage(). Closed: "Nominations are closed", with links to the winners or nominees when they are public, the rules and the
 * overview; no form and no sign-up. Open: the mount point of the six-step form (template-parts/nominate-form.php, built with the
 * form). The guidance (how it works, who can be nominated, the criteria, the categories, what to have ready, short answers) shows in
 * both states and collects no data. The plugin answers the form's POST at this same address.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_stage = wiw_awards_stage();
$wiw_open  = $wiw_stage['open'];

get_header();

if ( $wiw_open ) {
	wiw_page_band(
		array(
			'eyebrow' => wiw_brand( 'edition' ),
			'title'   => __( 'Nominate someone', 'whoiswho' ),
			'lede'    => __( 'It takes a few minutes. Your name is never shown, and nothing is public until screening is complete.', 'whoiswho' ),
			'tone'    => 'purple',
		)
	);
	?>
	<section class="section section--tight" aria-label="<?php esc_attr_e( 'Nomination form', 'whoiswho' ); ?>">
		<div class="wrap wrap--text" id="nominate-form">
			<?php get_template_part( 'template-parts/nominate-form' ); ?>
		</div>
	</section>
	<?php
} else {
	$wiw_after = $wiw_stage['stage'];
	if ( 'winners' === $wiw_after ) {
		$wiw_lede = __( 'The winners are announced.', 'whoiswho' );
	} elseif ( 'nominees' === $wiw_after ) {
		$wiw_lede = __( 'The nominees are announced, and the judges are scoring them.', 'whoiswho' );
	} else {
		$wiw_lede = __( 'We are not taking nominations at the moment. The rules and the criteria stay here.', 'whoiswho' );
	}
	wiw_page_band(
		array(
			'eyebrow' => wiw_brand( 'edition' ),
			'title'   => __( 'Nominations are closed', 'whoiswho' ),
			'lede'    => $wiw_lede,
			'tone'    => 'purple',
		)
	);
	?>
	<section class="section section--tight" aria-label="<?php esc_attr_e( 'Where to go next', 'whoiswho' ); ?>">
		<div class="wrap">
			<div class="nm-closed">
				<?php if ( 'winners' === $wiw_after ) : ?>
					<a class="btn" href="<?php echo esc_url( home_url( '/awards/winners/' ) ); ?>"><?php esc_html_e( 'See the winners', 'whoiswho' ); ?></a>
				<?php elseif ( 'nominees' === $wiw_after ) : ?>
					<a class="btn" href="<?php echo esc_url( home_url( '/awards/nominees/' ) ); ?>"><?php esc_html_e( 'See the nominees', 'whoiswho' ); ?></a>
				<?php endif; ?>
				<a class="btn alt" href="<?php echo esc_url( home_url( '/awards/rules/' ) ); ?>"><?php esc_html_e( 'Read the rules', 'whoiswho' ); ?></a>
				<a class="btn alt" href="<?php echo esc_url( home_url( '/awards/' ) ); ?>"><?php esc_html_e( 'Awards overview', 'whoiswho' ); ?></a>
			</div>
		</div>
	</section>
	<?php
}//end if

get_template_part( 'template-parts/nominate-guidance' );
get_footer();
