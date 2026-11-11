<?php
/**
 * Document head, skip link and the header landmark. The header's navigation arrives with the header issue.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#main"><?php esc_html_e( 'Skip to main content', 'whoiswho' ); ?></a>
<?php get_template_part( 'template-parts/site-header' ); ?>
<main id="main" tabindex="-1">
