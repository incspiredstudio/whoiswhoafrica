<?php
/**
 * The guidance on the Nominate page, shown whether nominations are open or closed (REQ-250, REQ-194): how it works, who can be
 * nominated and the criteria, the categories (read from the Award categories screen, never typed here), what to have ready and short
 * answers. It collects no data. The wording of the editable parts is page copy: when a published page with the slug "nominate" has
 * content, that content replaces the default eligibility text, so staff change it in the page editor and not in code.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_steps    = array(
	array( __( 'Nominate', 'whoiswho' ), __( 'Anyone can put someone forward: a person or an organisation, with a short statement of why.', 'whoiswho' ) ),
	array( __( 'Screening', 'whoiswho' ), __( 'Our team checks the nomination against the published eligibility rules.', 'whoiswho' ) ),
	array( __( 'Confirmed', 'whoiswho' ), __( 'Our team confirms each nominee before they are listed. Nothing is public until then.', 'whoiswho' ) ),
	array( __( 'Independent judging', 'whoiswho' ), __( 'Judges score every nominee against the published criteria.', 'whoiswho' ) ),
	array( __( 'Announced', 'whoiswho' ), __( 'Nominees, then winners, are published on the site.', 'whoiswho' ) ),
);
$wiw_criteria = array(
	__( 'Impact', 'whoiswho' ),
	__( 'Achievement', 'whoiswho' ),
	__( 'Credibility and integrity', 'whoiswho' ),
	__( 'Leadership or innovation', 'whoiswho' ),
);
$wiw_ready    = array(
	__( "The nominee's full name, and whether it is a person or an organisation", 'whoiswho' ),
	__( 'The category that fits best', 'whoiswho' ),
	__( 'Two or three sentences of evidence: what they did, when, and the result', 'whoiswho' ),
	__( 'A way to check it, such as a public report, award or news item (a link helps)', 'whoiswho' ),
	__( 'Any link between you and the nominee, so we can handle it fairly', 'whoiswho' ),
);
$wiw_faq      = array(
	array( __( 'Does it cost anything?', 'whoiswho' ), __( 'No. Nominating is free and needs no account.', 'whoiswho' ) ),
	array( __( 'Will the nominee know it was me?', 'whoiswho' ), __( 'No. Your name and email are never shown to the nominee or the public.', 'whoiswho' ) ),
	array( __( 'Can I nominate myself or my own company?', 'whoiswho' ), __( 'You can, but say so in the conflict declaration. Judges see only the evidence, not who sent it.', 'whoiswho' ) ),
	array( __( 'Can I nominate more than one person?', 'whoiswho' ), __( 'Yes. Send one nomination for each person or organisation, in the category that fits.', 'whoiswho' ) ),
	array( __( 'What if the nominee says no?', 'whoiswho' ), __( 'They are removed and not contacted again about this edition.', 'whoiswho' ) ),
);
$wiw_groups   = function_exists( 'wiw_awards_categories' ) ? wiw_awards_categories() : array();

// The eligibility wording is page copy: use the editor page when it has content.
$wiw_copy = '';
$wiw_page = get_page_by_path( 'nominate' );
if ( $wiw_page instanceof WP_Post && 'publish' === $wiw_page->post_status && '' !== trim( wp_strip_all_tags( $wiw_page->post_content ) ) ) {
	$wiw_copy = (string) apply_filters( 'the_content', $wiw_page->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
}
?>
<section class="nm-sec" aria-labelledby="nmHow">
	<div class="wrap">
		<p class="nm-kick"><?php esc_html_e( 'How it works', 'whoiswho' ); ?></p>
		<h2 id="nmHow"><?php esc_html_e( 'From nomination to announcement', 'whoiswho' ); ?></h2>
		<ol class="nm-steps">
			<?php foreach ( $wiw_steps as $wiw_i => $wiw_step ) : ?>
				<li>
					<span class="nm-n"><?php echo esc_html( sprintf( '%02d', $wiw_i + 1 ) ); ?></span>
					<strong><?php echo esc_html( $wiw_step[0] ); ?></strong>
					<span><?php echo esc_html( $wiw_step[1] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
<section class="nm-sec nm-sec--alt" aria-labelledby="nmWho">
	<div class="wrap nm-two">
		<div>
			<p class="nm-kick"><?php esc_html_e( 'Eligibility', 'whoiswho' ); ?></p>
			<h2 id="nmWho"><?php esc_html_e( 'Who can be nominated', 'whoiswho' ); ?></h2>
			<?php if ( '' !== $wiw_copy ) : ?>
				<div class="prose"><?php echo wp_kses_post( $wiw_copy ); ?></div>
			<?php else : ?>
				<p class="nm-lede"><?php esc_html_e( 'Open to individuals and organisations in Nigeria first, then other African countries, with a professional record in the last five years.', 'whoiswho' ); ?></p>
			<?php endif; ?>
			<p><a class="more" href="<?php echo esc_url( home_url( '/awards/rules/' ) ); ?>"><?php esc_html_e( 'Read the full rules', 'whoiswho' ); ?></a></p>
		</div>
		<div>
			<p class="nm-kick"><?php esc_html_e( 'Judged on', 'whoiswho' ); ?></p>
			<h2><?php esc_html_e( 'The criteria', 'whoiswho' ); ?></h2>
			<ul class="nm-list">
				<?php foreach ( $wiw_criteria as $wiw_criterion ) : ?>
					<li><?php echo esc_html( $wiw_criterion ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
<?php if ( array() !== $wiw_groups ) : ?>
	<section class="nm-sec" aria-labelledby="nmCats">
		<div class="wrap">
			<p class="nm-kick"><?php esc_html_e( 'Categories', 'whoiswho' ); ?></p>
			<h2 id="nmCats"><?php esc_html_e( 'Pick the category that fits', 'whoiswho' ); ?></h2>
			<ul class="nm-cats">
				<?php foreach ( $wiw_groups as $wiw_group ) : ?>
					<li>
						<a href="<?php echo esc_url( home_url( '/awards/' ) ); ?>">
							<strong><?php echo esc_html( (string) $wiw_group['label'] ); ?></strong>
							<span>
								<?php
								/* translators: %d: number of categories in a group. */
								echo esc_html( sprintf( _n( '%d category', '%d categories', count( (array) $wiw_group['categories'] ), 'whoiswho' ), count( (array) $wiw_group['categories'] ) ) );
								?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>
<section class="nm-sec nm-sec--alt" aria-labelledby="nmPrep">
	<div class="wrap nm-two">
		<div>
			<p class="nm-kick"><?php esc_html_e( 'Before you start', 'whoiswho' ); ?></p>
			<h2 id="nmPrep"><?php esc_html_e( 'What to have ready', 'whoiswho' ); ?></h2>
			<ul class="nm-list">
				<?php foreach ( $wiw_ready as $wiw_item ) : ?>
					<li><?php echo esc_html( $wiw_item ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="small muted"><?php esc_html_e( 'Please leave out phone numbers, home addresses and sensitive personal details. We remove them.', 'whoiswho' ); ?></p>
		</div>
		<div>
			<p class="nm-kick"><?php esc_html_e( 'Questions', 'whoiswho' ); ?></p>
			<h2><?php esc_html_e( 'Good to know', 'whoiswho' ); ?></h2>
			<div class="nm-faq">
				<?php foreach ( $wiw_faq as $wiw_qa ) : ?>
					<details>
						<summary><?php echo esc_html( $wiw_qa[0] ); ?></summary>
						<div><p><?php echo esc_html( $wiw_qa[1] ); ?></p></div>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
