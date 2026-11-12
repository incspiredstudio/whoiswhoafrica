<?php
/**
 * Site header: sticky translucent bar with five groups, search icon and a Get listed button; a mega menu from 1024 px; an animated
 * drawer on phones; a search panel (results arrive with search). No sign in, sign up or account menu (REQ-229). The Nominate link
 * shows only while nominations are open (REQ-259), decided in inc/navigation.php.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_nav = wiw_nav_model();

/**
 * Print a mega menu panel.
 *
 * @param string               $id    Element id.
 * @param string               $title Heading of the wide column.
 * @param array<string, mixed> $group Group from the model.
 */
$wiw_mega = static function ( string $id, string $title, array $group ): void {
	?>
	<div class="mega" id="<?php echo esc_attr( $id ); ?>">
		<div class="wrap mega-grid">
			<div class="mega-col mega-col--wide">
				<h2><?php echo esc_html( $title ); ?></h2>
				<ul class="grid grid--2">
					<?php foreach ( (array) ( $group['links'] ?? array() ) as $link ) : ?>
						<li><a class="mega-link" href="<?php echo esc_url( $link['url'] ); ?>"><strong><?php echo esc_html( $link['label'] ); ?></strong><span><?php echo esc_html( $link['note'] ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php if ( ! empty( $group['more'] ) ) : ?>
				<div class="mega-col">
					<?php if ( ! empty( $group['more_title'] ) ) : ?>
						<h2><?php echo esc_html( (string) $group['more_title'] ); ?></h2>
					<?php endif; ?>
					<ul>
						<?php foreach ( (array) $group['more'] as $link ) : ?>
							<li><a class="mega-plain" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
};

/**
 * Print a top-level group button that opens a mega menu.
 *
 * @param string               $id    Panel id.
 * @param array<string, mixed> $group Group.
 */
$wiw_mega_button = static function ( string $id, array $group ): void {
	?>
	<li><button type="button" class="nav-link" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( (string) $group['label'] ); ?><?php echo wiw_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></button></li>
	<?php
};

/**
 * Print a plain top-level link.
 *
 * @param array<string, mixed> $group Group with url and label.
 */
$wiw_plain_link = static function ( array $group ): void {
	?>
	<li><a class="nav-link" href="<?php echo esc_url( (string) $group['url'] ); ?>"<?php echo wiw_is_current_path( (string) $group['url'] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( (string) $group['label'] ); ?></a></li>
	<?php
};

/**
 * Print a drawer group.
 *
 * @param array<string, mixed> $group Group.
 */
$wiw_drawer_group = static function ( array $group ): void {
	$links = array_merge( (array) ( $group['links'] ?? array() ), (array) ( $group['more'] ?? array() ) );
	?>
	<details>
		<summary><?php echo esc_html( (string) $group['label'] ); ?><?php echo wiw_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></summary>
		<ul>
			<?php foreach ( $links as $link ) : ?>
				<li><a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</details>
	<?php
};

$wiw_name = wiw_site_name();
?>
<header class="site-head">
	<div class="wrap head-bar">
		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: site name. */ __( '%s home', 'whoiswho' ), $wiw_name ) ); ?>"><span class="wiw-logo" aria-hidden="true"></span></a>
		<nav class="nav-top" aria-label="<?php esc_attr_e( 'Main', 'whoiswho' ); ?>">
			<ul>
				<?php
				$wiw_mega_button( 'mega-directory', $wiw_nav['directory'] );
				$wiw_mega_button( 'mega-awards', $wiw_nav['awards'] );
				$wiw_plain_link( $wiw_nav['stories'] );
				$wiw_plain_link( $wiw_nav['events'] );
				$wiw_mega_button( 'mega-about', $wiw_nav['about'] );
				?>
			</ul>
		</nav>
		<div class="head-tools">
			<button type="button" class="icon-btn search-link" aria-haspopup="dialog" aria-controls="searchOv" aria-expanded="false" aria-label="<?php esc_attr_e( 'Search the directory', 'whoiswho' ); ?>"><?php echo wiw_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></button>
			<a class="btn sm head-cta" href="<?php echo esc_url( $wiw_nav['cta']['url'] ); ?>"><?php echo esc_html( $wiw_nav['cta']['label'] ); ?></a>
			<button type="button" class="icon-btn menu-btn" aria-expanded="false" aria-controls="drawer"><?php echo wiw_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><?php esc_html_e( 'Menu', 'whoiswho' ); ?></button>
		</div>
	</div>
	<?php
	$wiw_mega( 'mega-directory', __( 'The directory', 'whoiswho' ), $wiw_nav['directory'] );
	$wiw_mega( 'mega-awards', wiw_brand( 'edition' ), $wiw_nav['awards'] );
	$wiw_mega( 'mega-about', $wiw_name, $wiw_nav['about'] );
	?>
</header>

<div class="drawer" id="drawer" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'whoiswho' ); ?>">
	<div class="drawer-head">
		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $wiw_name ); ?>"><span class="wiw-logo" aria-hidden="true"></span></a>
		<button type="button" class="icon-btn drawer-close"><?php echo wiw_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><?php esc_html_e( 'Close', 'whoiswho' ); ?></button>
	</div>
	<form role="search" method="get" action="<?php echo esc_url( home_url( '/search/' ) ); ?>">
		<label class="sr" for="hq2"><?php esc_html_e( 'Search', 'whoiswho' ); ?></label>
		<input id="hq2" name="q" type="search" placeholder="<?php esc_attr_e( 'Search the directory', 'whoiswho' ); ?>">
	</form>
	<?php $wiw_drawer_group( $wiw_nav['directory'] ); ?>
	<?php $wiw_drawer_group( $wiw_nav['awards'] ); ?>
	<a class="drawer-link" href="<?php echo esc_url( $wiw_nav['stories']['url'] ); ?>"><?php echo esc_html( $wiw_nav['stories']['label'] ); ?></a>
	<a class="drawer-link" href="<?php echo esc_url( $wiw_nav['events']['url'] ); ?>"><?php echo esc_html( $wiw_nav['events']['label'] ); ?></a>
	<?php $wiw_drawer_group( $wiw_nav['about'] ); ?>
	<a class="drawer-link" href="<?php echo esc_url( $wiw_nav['cta']['url'] ); ?>"><?php echo esc_html( $wiw_nav['cta']['label'] ); ?></a>
</div>

<div class="sov" id="searchOv" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Search', 'whoiswho' ); ?>" hidden>
	<div class="wrap sov-in">
		<div class="sov-top">
			<span class="sov-kicker"><?php esc_html_e( 'Search', 'whoiswho' ); ?></span>
			<button type="button" class="icon-btn sov-close"><?php echo wiw_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><?php esc_html_e( 'Close', 'whoiswho' ); ?></button>
		</div>
		<form class="sov-form" role="search" method="get" action="<?php echo esc_url( home_url( '/search/' ) ); ?>">
			<label class="sr" for="sovq"><?php esc_html_e( 'Search people, businesses, institutions and stories', 'whoiswho' ); ?></label>
			<input id="sovq" name="q" type="search" autocomplete="off" placeholder="<?php esc_attr_e( 'Search a name or company', 'whoiswho' ); ?>">
			<button class="sov-go" type="submit" aria-label="<?php esc_attr_e( 'See all results', 'whoiswho' ); ?>"><?php echo wiw_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></button>
		</form>
	</div>
</div>
