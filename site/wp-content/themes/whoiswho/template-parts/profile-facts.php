<?php
/**
 * Key facts table of a profile (REQ-144, REQ-133): each fact with its source link and the date it was retrieved. A fact with no
 * source or no retrieval date is not rendered, and with no such fact the whole section is absent. Reads the profile only through
 * PublicProfile (its facts already carry only sourced values). Args: profile (PublicProfile).
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
$wiw_rows = wiw_profile_key_facts( $wiw_profile );
if ( array() === $wiw_rows ) {
	return;
}
?>
<section id="key-facts" aria-labelledby="key-facts-h">
	<h2 id="key-facts-h"><?php esc_html_e( 'Key facts and sources', 'whoiswho' ); ?></h2>
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
</section>
