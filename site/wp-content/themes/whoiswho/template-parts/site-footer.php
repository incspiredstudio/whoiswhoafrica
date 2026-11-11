<?php
/**
 * Site footer: the brand statement, four link columns (the Nominate link only while nominations are open, REQ-259), the legal row
 * with the Cookie preferences button (the notice itself is built with the cookie issue) and the base line.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_nav     = wiw_nav_model();
$wiw_tagline = wiw_brand( 'tagline' );
$wiw_parts   = array_values( array_filter( array_map( 'trim', explode( '.', $wiw_tagline ) ) ) );
?>
<footer class="site-foot on-dark pattern">
	<div class="wrap">
		<?php if ( '' !== $wiw_tagline ) : ?>
			<p class="foot-statement">
				<?php foreach ( $wiw_parts as $wiw_part ) : ?>
					<span aria-hidden="true"><?php echo esc_html( $wiw_part ); ?>.</span>
				<?php endforeach; ?>
				<span class="sr"><?php echo esc_html( $wiw_tagline ); ?></span>
			</p>
		<?php endif; ?>
		<div class="foot-cols">
			<?php foreach ( (array) $wiw_nav['footer'] as $wiw_title => $wiw_links ) : ?>
				<div>
					<h2><?php echo esc_html( (string) $wiw_title ); ?></h2>
					<ul>
						<?php foreach ( (array) $wiw_links as $wiw_link ) : ?>
							<li><a href="<?php echo esc_url( $wiw_link['url'] ); ?>"><?php echo esc_html( $wiw_link['label'] ); ?></a></li>
						<?php endforeach; ?>
						<?php if ( __( 'Legal', 'whoiswho' ) === $wiw_title ) : ?>
							<li><button type="button" class="foot-btn" data-cookie-reopen><?php esc_html_e( 'Cookie preferences', 'whoiswho' ); ?></button></li>
						<?php endif; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="foot-base">
			<span>
			<?php
			/* translators: 1: year, 2: legal name, 3: domain. */
			echo esc_html( sprintf( __( '© %1$s %2$s. %3$s', 'whoiswho' ), gmdate( 'Y' ), wiw_brand( 'legal_name' ), wiw_brand( 'domain' ) ) );
			?>
			</span>
			<span><a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>"><?php esc_html_e( 'Sitemap', 'whoiswho' ); ?></a> · <a href="<?php echo esc_url( home_url( '/unsubscribe/' ) ); ?>"><?php esc_html_e( 'Unsubscribe', 'whoiswho' ); ?></a></span>
		</div>
	</div>
</footer>
