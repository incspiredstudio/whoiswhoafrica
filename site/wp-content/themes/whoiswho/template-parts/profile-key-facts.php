<?php
/**
 * Key facts of a profile (REQ-144, REQ-133): a table where each fact carries its source link and the date it was retrieved.
 * A fact without a source is never rendered, and the whole block is absent when no fact has one. Args: profile (PublicProfile).
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
<section id="facts" aria-labelledby="facts-h">
	<h2 id="facts-h"><?php esc_html_e( 'Key facts', 'whoiswho' ); ?></h2>
	<table class="facts">
		<caption class="sr"><?php esc_html_e( 'Key facts, each with its source and the date it was retrieved', 'whoiswho' ); ?></caption>
		<tbody>
			<?php foreach ( $wiw_rows as $wiw_row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $wiw_row['label'] ); ?></th>
					<td>
						<?php echo esc_html( $wiw_row['value'] ); ?>
						<span class="src">
							<?php esc_html_e( 'Source:', 'whoiswho' ); ?>
							<a href="<?php echo esc_url( $wiw_row['source_url'] ); ?>" rel="nofollow noopener"><?php echo esc_html( $wiw_row['source_host'] ); ?></a>
							<?php if ( '' !== $wiw_row['retrieved'] ) : ?>
								<span aria-hidden="true">&middot;</span>
								<?php esc_html_e( 'Retrieved', 'whoiswho' ); ?>
								<time datetime="<?php echo esc_attr( $wiw_row['retrieved'] ); ?>"><?php echo esc_html( (string) wp_date( (string) get_option( 'date_format' ), (int) strtotime( $wiw_row['retrieved'] . ' UTC' ) ) ); ?></time>
							<?php endif; ?>
						</span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>
