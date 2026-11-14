<?php
/**
 * Key facts table of a profile (REQ-144, REQ-133): each fact with its source link and the date it was retrieved. A fact with no
 * source or no retrieval date is not rendered, and with no such fact the whole section is absent. Reads the profile only through
 * PublicProfile (its facts already carry only sourced values). Args: profile (PublicProfile).
 *
 * The key facts setting decides how it shows (REQ-144, owner 2026-11-14): show = this section as before; collapsed = a closed
 * "Key facts and sources" disclosure (the page template places it at the foot); hidden (the default) = nothing at all, so there
 * is no empty heading, anchor or link to it.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = isset( $args['profile'] ) ? $args['profile'] : null;
if ( ! $wiw_profile instanceof PublicProfile ) {
	return;
}
$wiw_mode = wiw_profile_key_facts_mode();
if ( 'hidden' === $wiw_mode ) {
	return;
}
$wiw_rows = wiw_profile_key_facts( $wiw_profile );
if ( array() === $wiw_rows ) {
	return;
}
?>
<?php if ( 'collapsed' === $wiw_mode ) : ?>
<details id="key-facts" class="facts-fold">
	<summary><span class="facts-fold-h"><?php esc_html_e( 'Key facts and sources', 'whoiswho' ); ?></span> <?php echo wiw_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?></summary>
<?php else : ?>
<section id="key-facts" aria-labelledby="key-facts-h">
	<h2 id="key-facts-h"><?php esc_html_e( 'Key facts and sources', 'whoiswho' ); ?></h2>
<?php endif; ?>
	<table class="facts">
		<caption class="sr"><?php esc_html_e( 'Facts about this profile, each with its source and the date it was retrieved', 'whoiswho' ); ?></caption>
		<tbody>
			<?php foreach ( $wiw_rows as $wiw_row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $wiw_row['label'] ); ?></th>
					<td>
						<?php echo esc_html( $wiw_row['value'] ); ?>
						<span class="src">
							<?php esc_html_e( 'Source:', 'whoiswho' ); ?>
							<a href="<?php echo esc_url( $wiw_row['source_url'] ); ?>" rel="nofollow noopener"><?php echo esc_html( $wiw_row['source_host'] ); ?></a>
							<?php esc_html_e( 'Retrieved', 'whoiswho' ); ?>
							<time datetime="<?php echo esc_attr( $wiw_row['retrieved_iso'] ); ?>"><?php echo esc_html( $wiw_row['retrieved'] ); ?></time>
						</span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php if ( 'collapsed' === $wiw_mode ) : ?>
</details>
<?php else : ?>
</section>
<?php endif; ?>
