<?php
/**
 * Fallback template. Real templates arrive with the theme issues; this keeps the parent valid and the site working.
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
<main id="main">
	<?php
	while ( have_posts() ) {
		the_post();
		echo '<article>';
		the_title( '<h1>', '</h1>' );
		the_content();
		echo '</article>';
	}
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
