<?php
/**
 * The holding page shown while the core plugin is not running (REQ-046, REQ-056). It uses WordPress functions only.
 * It answers 503 with Retry-After so search engines keep the old pages and come back later.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

status_header( 503 );
if ( ! headers_sent() ) {
	header( 'Retry-After: 3600' );
	nocache_headers();
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
	<style>body{margin:0;padding:4rem 1.5rem;font-family:system-ui,sans-serif;line-height:1.5;text-align:center}main{max-width:36rem;margin:0 auto}</style>
</head>
<body>
<main>
	<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
	<p><?php esc_html_e( 'We are getting this site ready. Please check back soon.', 'whoiswho' ); ?></p>
	<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
		<p><a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Site owner: open Plugins to finish the setup.', 'whoiswho' ); ?></a></p>
	<?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
