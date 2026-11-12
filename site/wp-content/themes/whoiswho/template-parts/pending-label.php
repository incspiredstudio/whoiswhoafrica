<?php
/**
 * The pending-review label (REQ-216): a badge on cards and lists, or a notice with correction links on a page.
 * The text comes from PublicProfile::$label; an empty text renders nothing, so it cannot appear on a reviewed profile.
 * Args: label (string|null), variant (badge|notice), correct_url, remove_url.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_args  = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'label'       => null,
		'variant'     => 'badge',
		'correct_url' => '',
		'remove_url'  => '',
	)
);
$wiw_label = is_string( $wiw_args['label'] ) ? trim( $wiw_args['label'] ) : '';
if ( '' === $wiw_label ) {
	return;
}
if ( 'notice' === $wiw_args['variant'] ) :
	?>
<div class="notice notice--pending" role="note">
	<?php echo wiw_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
	<div>
		<strong><?php echo esc_html( $wiw_label ); ?></strong>
		<?php if ( '' !== (string) $wiw_args['correct_url'] && '' !== (string) $wiw_args['remove_url'] ) : ?>
			<a href="<?php echo esc_url( (string) $wiw_args['correct_url'] ); ?>"><?php esc_html_e( 'Correct', 'whoiswho' ); ?></a>
			<?php esc_html_e( 'or', 'whoiswho' ); ?>
			<a href="<?php echo esc_url( (string) $wiw_args['remove_url'] ); ?>"><?php esc_html_e( 'remove', 'whoiswho' ); ?></a>
			<?php esc_html_e( 'this profile.', 'whoiswho' ); ?>
		<?php endif; ?>
	</div>
</div>
<?php else : ?>
<span class="badge badge--pending"><?php echo wiw_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?><?php echo esc_html( $wiw_label ); ?></span>
<?php endif; ?>
