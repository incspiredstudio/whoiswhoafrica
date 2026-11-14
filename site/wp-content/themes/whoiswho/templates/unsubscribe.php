<?php
/**
 * Unsubscribe page at /unsubscribe (REQ-249, REQ-061, REQ-058): how to stop the newsletter, without an account. Every email we
 * send carries its own one-click link, which opens the confirmation page and stops the emails when the button is pressed; this
 * page explains that and gives the way out for someone who has no email to hand. It holds no form and no personal data, and is
 * not indexed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
wiw_page_band(
	array(
		'eyebrow' => __( 'Newsletter', 'whoiswho' ),
		'title'   => __( 'Unsubscribe', 'whoiswho' ),
		'tone'    => 'dark',
		'lede'    => __( 'You can stop the newsletter at any time. No account is needed, and it takes one click.', 'whoiswho' ),
	)
);
$wiw_steps = array(
	__( 'Open any email we sent you.', 'whoiswho' ),
	__( 'Press the Unsubscribe link at the bottom of it.', 'whoiswho' ),
	__( 'Press the button on the page that opens. Your email address is then taken off the list.', 'whoiswho' ),
);
?>
<section class="section section--tight">
	<div class="wrap form-grid">
		<aside class="form-aside" aria-labelledby="unsub-steps-h">
			<h2 id="unsub-steps-h"><?php esc_html_e( 'How to unsubscribe', 'whoiswho' ); ?></h2>
			<ol class="ticks unsub-steps">
				<?php foreach ( $wiw_steps as $wiw_step ) : ?>
					<li><span><?php echo esc_html( $wiw_step ); ?></span></li>
				<?php endforeach; ?>
			</ol>
		</aside>
		<div class="form">
			<h2><?php esc_html_e( 'No email to hand?', 'whoiswho' ); ?></h2>
			<p><?php esc_html_e( 'Write to us from the address that receives the newsletter and say you want to stop. A person removes it. We keep a note that you withdrew, so you are not added again.', 'whoiswho' ); ?></p>
			<p class="row">
				<a class="btn" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact us', 'whoiswho' ); ?></a>
				<a class="btn alt" href="<?php echo esc_url( home_url( '/data-rights/' ) ); ?>"><?php esc_html_e( 'Your data rights', 'whoiswho' ); ?></a>
			</p>
		</div>
	</div>
</section>
<?php
get_footer();
