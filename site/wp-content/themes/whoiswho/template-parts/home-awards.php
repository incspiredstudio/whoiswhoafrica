<?php
/**
 * Home awards marquee (REQ-198, REQ-001): a slow, endless strip of the award category names as text. A strip only: no heading,
 * no statement, no numbered list, no button. It draws nothing when there are no categories. By default it is a still, wrapped
 * line of words; the scrolling is added only when the visitor has not asked for reduced motion, and the second copy that makes
 * the loop seamless is hidden from assistive technology.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\CategoryService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( CategoryService::class ) ) {
	return;
}
$wiw_titles = array();
foreach ( CategoryService::for_site()->grouped() as $wiw_group ) {
	foreach ( (array) $wiw_group['categories'] as $wiw_cat ) {
		$wiw_titles[] = (string) $wiw_cat['title'];
	}
}
if ( array() === $wiw_titles ) {
	return;
}
?>
<section class="h-marquee" aria-label="<?php esc_attr_e( 'Award categories', 'whoiswho' ); ?>">
	<div class="h-marquee-track">
		<ul class="h-marquee-list">
			<?php foreach ( $wiw_titles as $wiw_title ) : ?>
				<li><?php echo esc_html( $wiw_title ); ?></li>
			<?php endforeach; ?>
		</ul>
		<ul class="h-marquee-list h-marquee-copy" aria-hidden="true">
			<?php foreach ( $wiw_titles as $wiw_title ) : ?>
				<li><?php echo esc_html( $wiw_title ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
