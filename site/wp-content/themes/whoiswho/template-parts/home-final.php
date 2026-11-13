<?php
/**
 * Home closing call (REQ-001, REQ-002, REQ-259): "Who should the continent know?" with one button. The button is Nominate while
 * nominations are open and Get listed otherwise. Args: home (the home model).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_home = isset( $args['home'] ) && is_array( $args['home'] ) ? $args['home'] : array();
$wiw_cta  = isset( $wiw_home['ctas']['primary'] ) && is_array( $wiw_home['ctas']['primary'] ) ? $wiw_home['ctas']['primary'] : array();
?>
<section class="h-final" aria-labelledby="home-final-title">
	<div class="wrap">
		<p class="h-kicker"><?php esc_html_e( 'Starting in Nigeria. Built for the continent.', 'whoiswho' ); ?></p>
		<h2 class="h-final-title" id="home-final-title"><?php esc_html_e( 'Who should the continent know?', 'whoiswho' ); ?></h2>
		<?php if ( ! empty( $wiw_cta['url'] ) ) : ?>
			<div class="h-final-cta">
				<a class="btn h-btn" href="<?php echo esc_url( (string) $wiw_cta['url'] ); ?>"><?php echo esc_html( (string) $wiw_cta['label'] ); ?></a>
			</div>
		<?php endif; ?>
	</div>
</section>
