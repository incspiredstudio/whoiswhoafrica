<?php
/**
 * The Profile owner account pages (REQ-274, REQ-261): sign in, My profiles, edit one profile (sent for review), account details,
 * lost password and choosing a new password. Args from inc/account.php: view, errors, notice and data (decided by the plugin).
 * Every value is escaped here; password fields are never refilled.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_view   = isset( $args['view'] ) ? (string) $args['view'] : 'signin';
$wiw_errors = isset( $args['errors'] ) && is_array( $args['errors'] ) ? $args['errors'] : array();
$wiw_notice = isset( $args['notice'] ) ? (string) $args['notice'] : '';
$wiw_data   = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$wiw_d      = static function ( string $key, string $fallback = '' ) use ( $wiw_data ): string {
	return isset( $wiw_data[ $key ] ) && is_scalar( $wiw_data[ $key ] ) ? (string) $wiw_data[ $key ] : $fallback;
};
$wiw_titles = array(
	'signin'        => __( 'Profile owner sign in', 'whoiswho' ),
	'dashboard'     => __( 'My account', 'whoiswho' ),
	'edit'          => __( 'Edit your profile', 'whoiswho' ),
	'not_found'     => __( 'Profile not found', 'whoiswho' ),
	'details'       => __( 'Account details', 'whoiswho' ),
	'lost'          => __( 'Reset your password', 'whoiswho' ),
	'lost_sent'     => __( 'Check your email', 'whoiswho' ),
	'reset'         => __( 'Choose a new password', 'whoiswho' ),
	'reset_expired' => __( 'Link expired', 'whoiswho' ),
);
$wiw_ledes  = array(
	'signin'    => __( 'For people and organisations whose profile claim we have approved. Keep your profile up to date; our team checks every change before it goes live.', 'whoiswho' ),
	'dashboard' => __( 'The profiles you look after, and your account.', 'whoiswho' ),
	'edit'      => __( 'Your changes are checked by our team before they appear on the public profile.', 'whoiswho' ),
);
wiw_page_band(
	array(
		'eyebrow' => __( 'Your account', 'whoiswho' ),
		'title'   => 'edit' === $wiw_view ? $wiw_d( 'name' ) : ( $wiw_titles[ $wiw_view ] ?? $wiw_titles['signin'] ),
		'tone'    => 'dark',
		'lede'    => $wiw_ledes[ $wiw_view ] ?? '',
	)
);
$wiw_messages = static function () use ( $wiw_errors, $wiw_notice ): void {
	if ( array() !== $wiw_errors ) {
		echo '<div class="errbox" role="alert">';
		foreach ( $wiw_errors as $wiw_error ) {
			echo '<p>' . esc_html( (string) $wiw_error ) . '</p>';
		}
		echo '</div>';
	}
	if ( '' !== $wiw_notice ) {
		echo '<div class="okbox" role="status"><p>' . esc_html( $wiw_notice ) . '</p></div>';
	}
};
?>
<section class="section section--tight account">
	<div class="wrap wrap--text account__wrap">
		<?php if ( 'signin' === $wiw_view ) : ?>
			<form class="form" method="post" action="<?php echo esc_url( home_url( '/account/' ) ); ?>" novalidate>
				<h2 class="sr"><?php esc_html_e( 'Sign in', 'whoiswho' ); ?></h2>
				<?php $wiw_messages(); ?>
				<?php
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'         => 'log',
						'type'         => 'email',
						'label'        => __( 'Email', 'whoiswho' ),
						'required'     => true,
						'value'        => $wiw_d( 'login' ),
						'autocomplete' => 'username',
					)
				);
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'         => 'pwd',
						'type'         => 'password',
						'label'        => __( 'Password', 'whoiswho' ),
						'required'     => true,
						'autocomplete' => 'current-password',
					)
				);
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'  => 'rememberme',
						'type'  => 'checkbox',
						'label' => __( 'Keep me signed in on this device', 'whoiswho' ),
					)
				);
				get_template_part( 'template-parts/forms/bot-check', null, array( 'form' => 'account_signin' ) );
				wp_nonce_field( 'wiw_account_signin', 'wiw_account_nonce', false );
				?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wiw_d( 'redirect_to', home_url( '/account/' ) ) ); ?>">
				<button class="btn" type="submit"><?php esc_html_e( 'Sign in', 'whoiswho' ); ?></button>
				<p class="meta"><a href="<?php echo esc_url( $wiw_d( 'lost_url', home_url( '/account/lost-password/' ) ) ); ?>"><?php esc_html_e( 'Forgotten your password?', 'whoiswho' ); ?></a></p>
				<p class="meta"><?php esc_html_e( 'There is no sign-up: we make your account when we approve your claim to a profile.', 'whoiswho' ); ?> <a href="<?php echo esc_url( home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Find your profile to claim it', 'whoiswho' ); ?></a></p>
			</form>

		<?php elseif ( 'dashboard' === $wiw_view ) : ?>
			<?php $wiw_messages(); ?>
			<?php /* translators: %s: the account holder's name. */ ?>
			<p class="account__hello"><?php echo esc_html( sprintf( __( 'Signed in as %s.', 'whoiswho' ), $wiw_d( 'name' ) ) ); ?></p>
			<h2 class="account__h"><?php esc_html_e( 'My profiles', 'whoiswho' ); ?></h2>
			<?php $wiw_profiles = isset( $wiw_data['profiles'] ) && is_array( $wiw_data['profiles'] ) ? $wiw_data['profiles'] : array(); ?>
			<?php if ( array() === $wiw_profiles ) : ?>
				<p><?php esc_html_e( 'No profiles are linked to your account yet. If you think this is wrong, contact us.', 'whoiswho' ); ?></p>
			<?php else : ?>
				<ul class="account__list">
					<?php foreach ( $wiw_profiles as $wiw_p ) : ?>
						<li class="account__item">
							<div>
								<strong class="account__name"><?php echo esc_html( (string) $wiw_p['name'] ); ?></strong>
								<span class="account__status<?php echo ! empty( $wiw_p['pending'] ) ? ' account__status--pending' : ''; ?>"><?php echo esc_html( (string) $wiw_p['status'] ); ?></span>
								<?php if ( '' !== (string) $wiw_p['note'] ) : ?>
									<?php /* translators: %s: the reviewer's note. */ ?>
									<p class="meta"><?php echo esc_html( sprintf( __( 'Your last change was returned: %s', 'whoiswho' ), (string) $wiw_p['note'] ) ); ?></p>
								<?php endif; ?>
							</div>
							<div class="account__actions">
								<a class="btn sm" href="<?php echo esc_url( (string) $wiw_p['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'whoiswho' ); ?><span class="sr"> <?php echo esc_html( (string) $wiw_p['name'] ); ?></span></a>
								<a class="btn sm alt" href="<?php echo esc_url( (string) $wiw_p['view_url'] ); ?>"><?php esc_html_e( 'View', 'whoiswho' ); ?><span class="sr"> <?php echo esc_html( (string) $wiw_p['name'] ); ?></span></a>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<h2 class="account__h"><?php esc_html_e( 'Your account', 'whoiswho' ); ?></h2>
			<ul class="link-rows">
				<li><a href="<?php echo esc_url( $wiw_d( 'details_url' ) ); ?>"><strong><?php esc_html_e( 'Account details', 'whoiswho' ); ?></strong><span class="meta"><?php esc_html_e( 'Your name and password.', 'whoiswho' ); ?></span></a></li>
				<li><a href="<?php echo esc_url( $wiw_d( 'signout_url' ) ); ?>"><strong><?php esc_html_e( 'Sign out', 'whoiswho' ); ?></strong><span class="meta"><?php esc_html_e( 'End this session on this device.', 'whoiswho' ); ?></span></a></li>
			</ul>

		<?php elseif ( 'edit' === $wiw_view ) : ?>
			<p><a href="<?php echo esc_url( $wiw_d( 'home_url' ) ); ?>"><?php esc_html_e( 'Back to My account', 'whoiswho' ); ?></a> · <a href="<?php echo esc_url( $wiw_d( 'view_url' ) ); ?>"><?php esc_html_e( 'View the live profile', 'whoiswho' ); ?></a></p>
			<?php $wiw_messages(); ?>
			<?php if ( ! empty( $wiw_data['pending'] ) ) : ?>
				<div class="infobox account__waiting" role="status">
					<p><strong><?php esc_html_e( 'Waiting for review', 'whoiswho' ); ?></strong></p>
					<p><?php esc_html_e( 'Your last change is with our team. You can send another change once it has been approved or returned; we will email you the outcome.', 'whoiswho' ); ?></p>
				</div>
			<?php else : ?>
				<?php if ( '' !== $wiw_d( 'note' ) ) : ?>
					<?php /* translators: %s: the reviewer's note. */ ?>
					<div class="warnbox"><p><?php echo esc_html( sprintf( __( 'Your last change was returned: %s', 'whoiswho' ), $wiw_d( 'note' ) ) ); ?></p></div>
				<?php endif; ?>
				<form class="form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( $wiw_d( 'action' ) ); ?>" novalidate>
					<h2 class="sr"><?php esc_html_e( 'Propose changes', 'whoiswho' ); ?></h2>
					<?php
					foreach ( (array) ( $wiw_data['fields'] ?? array() ) as $wiw_key => $wiw_field ) {
						get_template_part(
							'template-parts/forms/field',
							null,
							array(
								'name'      => (string) $wiw_key,
								'type'      => ! empty( $wiw_field['long'] ) ? 'textarea' : 'url',
								'label'     => (string) $wiw_field['label'],
								'value'     => (string) $wiw_field['value'],
								'maxlength' => (int) $wiw_field['max'],
								'hint'      => ! empty( $wiw_field['long'] ) ? '' : __( 'A full web address, starting with https://', 'whoiswho' ),
							)
						);
					}
					foreach ( (array) ( $wiw_data['contact'] ?? array() ) as $wiw_key => $wiw_field ) {
						get_template_part(
							'template-parts/forms/field',
							null,
							array(
								'name'      => (string) $wiw_key,
								'type'      => (string) $wiw_field['type'],
								'label'     => (string) $wiw_field['label'],
								'value'     => (string) $wiw_field['value'],
								'maxlength' => 300,
								'hint'      => __( 'Our team decides whether this is shown publicly.', 'whoiswho' ),
							)
						);
					}
					?>
					<div class="field">
						<label for="f-image"><?php esc_html_e( 'Photograph', 'whoiswho' ); ?></label>
						<?php if ( '' !== $wiw_d( 'image_html' ) ) : ?>
							<div class="account__photo"><?php echo wp_kses_post( $wiw_d( 'image_html' ) ); ?></div>
						<?php endif; ?>
						<input id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" aria-describedby="f-image-hint">
						<span class="hint" id="f-image-hint"><?php esc_html_e( 'JPEG, PNG or WebP, up to 2 MB. The current photograph stays until a reviewer approves the new one.', 'whoiswho' ); ?></span>
					</div>
					<?php wp_nonce_field( 'wiw_account_edit', 'wiw_account_nonce', false ); ?>
					<button class="btn" type="submit"><?php esc_html_e( 'Send for review', 'whoiswho' ); ?></button>
				</form>
			<?php endif; ?>

		<?php elseif ( 'details' === $wiw_view ) : ?>
			<p><a href="<?php echo esc_url( $wiw_d( 'home_url' ) ); ?>"><?php esc_html_e( 'Back to My account', 'whoiswho' ); ?></a></p>
			<form class="form" method="post" action="<?php echo esc_url( $wiw_d( 'action' ) ); ?>" novalidate>
				<h2 class="sr"><?php esc_html_e( 'Change your details', 'whoiswho' ); ?></h2>
				<?php $wiw_messages(); ?>
				<?php
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'         => 'display_name',
						'label'        => __( 'Your name', 'whoiswho' ),
						'required'     => true,
						'value'        => $wiw_d( 'name' ),
						'autocomplete' => 'name',
						'maxlength'    => 120,
					)
				);
				?>
				<fieldset>
					<legend><?php esc_html_e( 'Change your password', 'whoiswho' ); ?></legend>
					<p class="meta"><?php esc_html_e( 'Leave these empty to keep your password. Use at least 12 characters with a mix of letters, digits and symbols. To change your email address, contact us.', 'whoiswho' ); ?></p>
					<?php
					get_template_part(
						'template-parts/forms/field',
						null,
						array(
							'name'         => 'current_password',
							'type'         => 'password',
							'label'        => __( 'Current password', 'whoiswho' ),
							'autocomplete' => 'current-password',
						)
					);
					get_template_part(
						'template-parts/forms/field',
						null,
						array(
							'name'         => 'pass1',
							'type'         => 'password',
							'label'        => __( 'New password', 'whoiswho' ),
							'autocomplete' => 'new-password',
						)
					);
					get_template_part(
						'template-parts/forms/field',
						null,
						array(
							'name'         => 'pass2',
							'type'         => 'password',
							'label'        => __( 'New password again', 'whoiswho' ),
							'autocomplete' => 'new-password',
						)
					);
					?>
				</fieldset>
				<?php wp_nonce_field( 'wiw_account_details', 'wiw_account_nonce', false ); ?>
				<button class="btn" type="submit"><?php esc_html_e( 'Save details', 'whoiswho' ); ?></button>
			</form>

		<?php elseif ( 'lost' === $wiw_view || 'lost_sent' === $wiw_view ) : ?>
			<form class="form" method="post" action="<?php echo esc_url( $wiw_d( 'action' ) ); ?>" novalidate>
				<h2 class="sr"><?php esc_html_e( 'Ask for a reset link', 'whoiswho' ); ?></h2>
				<?php $wiw_messages(); ?>
				<p><?php esc_html_e( 'Enter the email address of your profile owner account and we will send you a link to choose a new password.', 'whoiswho' ); ?></p>
				<?php
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'         => 'user_email',
						'type'         => 'email',
						'label'        => __( 'Email', 'whoiswho' ),
						'required'     => true,
						'autocomplete' => 'email',
					)
				);
				get_template_part( 'template-parts/forms/bot-check', null, array( 'form' => 'account_lost' ) );
				wp_nonce_field( 'wiw_account_lost', 'wiw_account_nonce', false );
				?>
				<button class="btn" type="submit"><?php esc_html_e( 'Send the link', 'whoiswho' ); ?></button>
				<p class="meta"><a href="<?php echo esc_url( $wiw_d( 'signin_url' ) ); ?>"><?php esc_html_e( 'Back to sign in', 'whoiswho' ); ?></a></p>
			</form>

		<?php elseif ( 'reset' === $wiw_view ) : ?>
			<form class="form" method="post" action="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'key' => rawurlencode( $wiw_d( 'key' ) ),
						'id'  => (int) $wiw_d( 'id', '0' ),
					),
					$wiw_d( 'action' )
				)
			);
			?>
														" novalidate>
				<h2 class="sr"><?php esc_html_e( 'Choose a new password', 'whoiswho' ); ?></h2>
				<?php $wiw_messages(); ?>
				<p class="meta"><?php esc_html_e( 'Use at least 12 characters with a mix of letters, digits and symbols.', 'whoiswho' ); ?></p>
				<?php
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'         => 'pass1',
						'type'         => 'password',
						'label'        => __( 'New password', 'whoiswho' ),
						'required'     => true,
						'autocomplete' => 'new-password',
					)
				);
				get_template_part(
					'template-parts/forms/field',
					null,
					array(
						'name'         => 'pass2',
						'type'         => 'password',
						'label'        => __( 'New password again', 'whoiswho' ),
						'required'     => true,
						'autocomplete' => 'new-password',
					)
				);
				wp_nonce_field( 'wiw_account_reset', 'wiw_account_nonce', false );
				?>
				<button class="btn" type="submit"><?php esc_html_e( 'Save password', 'whoiswho' ); ?></button>
			</form>

		<?php elseif ( 'reset_expired' === $wiw_view ) : ?>
			<div class="warnbox"><p><?php esc_html_e( 'This link has expired or has already been used.', 'whoiswho' ); ?></p></div>
			<p><a class="btn" href="<?php echo esc_url( $wiw_d( 'action' ) ); ?>"><?php esc_html_e( 'Ask for a new link', 'whoiswho' ); ?></a></p>

		<?php else : ?>
			<p><?php esc_html_e( 'We could not find that profile in your account.', 'whoiswho' ); ?></p>
			<p><a class="btn" href="<?php echo esc_url( $wiw_d( 'home_url', home_url( '/account/' ) ) ); ?>"><?php esc_html_e( 'Back to My account', 'whoiswho' ); ?></a></p>
		<?php endif; ?>
	</div>
</section>
