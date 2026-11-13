<?php
/**
 * Single-use link page: the theme supplies the markup for the plugin's /link/{token} route through the wiw_link_page filter.
 * A GET only shows a confirm button; the POST consumes the link. The theme never decides the state, it renders what the plugin sends (REQ-121, REQ-233).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

/**
 * Whether the link is the newsletter unsubscribe link. A usable link is looked up (without using it) for its purpose; once it has
 * been used the plugin answers with its one fixed unsubscribe sentence, which is how the done page is told apart.
 *
 * @param string $kind    State: confirm, done, expired or limited.
 * @param string $message The plugin's message.
 * @param string $token   The link token.
 */
function wiw_link_is_unsubscribe( string $kind, string $message, string $token ): bool {
	if ( 'done' === $kind ) {
		return 'You have been unsubscribed.' === $message;
	}
	if ( 'confirm' !== $kind || '' === $token || ! class_exists( '\WhoIsWho\Core\Platform\Links\SignedLinks' ) ) {
		return false;
	}
	$row = \WhoIsWho\Core\Platform\Links\SignedLinks::for_site()->peek( $token );
	return is_array( $row ) && 'unsubscribe' === (string) ( $row['purpose'] ?? '' );
}

/**
 * Which answer to the complimentary-listing consent request a link carries: accept, decline, or an empty string for any other link
 * (REQ-175, REQ-233). A usable link is looked up without being used; after use the plugin's fixed sentences tell the two apart.
 *
 * @param string $kind    State: confirm, done, expired or limited.
 * @param string $message The plugin's message.
 * @param string $token   The link token.
 */
function wiw_link_consent_choice( string $kind, string $message, string $token ): string {
	if ( 'done' === $kind ) {
		$done = array(
			'Thank you. Your consent is recorded.' => 'accept',
			'Your choice is recorded. The profile will not be published.' => 'decline',
		);
		return $done[ $message ] ?? '';
	}
	if ( 'confirm' !== $kind || '' === $token || ! class_exists( '\WhoIsWho\Core\Platform\Links\SignedLinks' ) ) {
		return '';
	}
	$row     = \WhoIsWho\Core\Platform\Links\SignedLinks::for_site()->peek( $token );
	$purpose = is_array( $row ) ? (string) ( $row['purpose'] ?? '' ) : '';
	$map     = array(
		'complimentary_listing'         => 'accept',
		'complimentary_listing_decline' => 'decline',
	);
	return $map[ $purpose ] ?? '';
}

/**
 * Render the link page inside the site header and footer.
 *
 * @param string                                  $html  The plugin's bare default markup (ignored).
 * @param array{state?: string, message?: string} $state State and message from the plugin.
 */
function wiw_link_page( string $html, array $state = array() ): string {
	unset( $html );
	$kind    = isset( $state['state'] ) ? (string) $state['state'] : 'expired';
	$message = isset( $state['message'] ) ? (string) $state['message'] : '';
	$token   = (string) get_query_var( 'wiw_link' );
	$unsub   = wiw_link_is_unsubscribe( $kind, $message, $token );
	$consent = $unsub ? '' : wiw_link_consent_choice( $kind, $message, $token );

	ob_start();
	get_header();
	?>
	<div class="wrap wrap--text section link-page">
		<?php if ( 'confirm' === $kind && '' !== $consent ) : ?>
			<h1><?php echo 'accept' === $consent ? esc_html__( 'Agree to a complimentary listing', 'whoiswho' ) : esc_html__( 'Decline a complimentary listing', 'whoiswho' ); ?></h1>
			<p><?php esc_html_e( 'We would like to publish a profile about you in the directory at no charge: a complimentary listing. Nothing is public until you agree and a reviewer has approved the profile. Opening this page does nothing until you press the button.', 'whoiswho' ); ?></p>
			<form method="post" data-personal-data="none" action="<?php echo esc_url( home_url( '/link/' . rawurlencode( $token ) ) ); ?>">
				<button type="submit" class="btn"><?php echo 'accept' === $consent ? esc_html__( 'I agree', 'whoiswho' ) : esc_html__( 'I decline', 'whoiswho' ); ?></button>
			</form>
		<?php elseif ( 'done' === $kind && '' !== $consent ) : ?>
			<h1 role="status"><?php echo 'accept' === $consent ? esc_html__( 'Consent recorded', 'whoiswho' ) : esc_html__( 'Choice recorded', 'whoiswho' ); ?></h1>
			<p>
				<?php
				echo 'accept' === $consent
					? esc_html__( 'Thank you. The profile is not public until a reviewer approves it.', 'whoiswho' )
					: esc_html__( 'We have noted your choice. The profile will not be published.', 'whoiswho' );
				?>
			</p>
		<?php elseif ( 'confirm' === $kind && $unsub ) : ?>
			<h1><?php esc_html_e( 'Unsubscribe from the newsletter', 'whoiswho' ); ?></h1>
			<p><?php esc_html_e( 'Press the button and we take your email address off the list. Opening this page does nothing until you do. No account is needed.', 'whoiswho' ); ?></p>
			<form method="post" data-personal-data="none" action="<?php echo esc_url( home_url( '/link/' . rawurlencode( $token ) ) ); ?>">
				<button type="submit" class="btn"><?php esc_html_e( 'Unsubscribe', 'whoiswho' ); ?></button>
			</form>
		<?php elseif ( 'done' === $kind && $unsub ) : ?>
			<h1 role="status"><?php esc_html_e( 'You are unsubscribed', 'whoiswho' ); ?></h1>
			<p><?php esc_html_e( 'We will not send you the newsletter any more. We keep a note that you withdrew, so you are not added again. You can sign up again at any time from the Stories page.', 'whoiswho' ); ?></p>
		<?php elseif ( 'confirm' === $kind ) : ?>
			<h1><?php esc_html_e( 'Please confirm', 'whoiswho' ); ?></h1>
			<p><?php esc_html_e( 'Press the button to continue. Opening this page does nothing until you do.', 'whoiswho' ); ?></p>
			<form method="post" data-personal-data="none" action="<?php echo esc_url( home_url( '/link/' . rawurlencode( $token ) ) ); ?>">
				<button type="submit" class="btn"><?php esc_html_e( 'Confirm', 'whoiswho' ); ?></button>
			</form>
		<?php elseif ( 'done' === $kind ) : ?>
			<h1 role="status"><?php esc_html_e( 'Done', 'whoiswho' ); ?></h1>
			<p><?php echo esc_html( $message ); ?></p>
		<?php elseif ( 'limited' === $kind ) : ?>
			<h1><?php esc_html_e( 'Please try again later', 'whoiswho' ); ?></h1>
			<p><?php echo esc_html( $message ); ?></p>
		<?php else : ?>
			<h1><?php esc_html_e( 'This link has expired or was already used', 'whoiswho' ); ?></h1>
			<p><?php esc_html_e( 'If you still need to do this, start again from the page you came from.', 'whoiswho' ); ?></p>
		<?php endif; ?>
		<p><a class="btn alt" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the home page', 'whoiswho' ); ?></a></p>
	</div>
	<?php
	get_footer();
	return (string) ob_get_clean();
}
add_filter( 'wiw_link_page', 'wiw_link_page', 10, 2 );
