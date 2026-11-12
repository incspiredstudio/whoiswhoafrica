<?php
/**
 * Comments region of a story: approved comments, thank-you note, and the form. Not shown where comments are not offered. Args: post_id.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_post_id  = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
$wiw_open     = $wiw_post_id > 0 && comments_open( $wiw_post_id );
$wiw_comments = $wiw_post_id > 0 ? get_comments(
	array(
		'post_id' => $wiw_post_id,
		'status'  => 'approve',
		'order'   => 'ASC',
	)
) : array();
if ( ! $wiw_open && array() === $wiw_comments ) {
	return;
}
$wiw_count = count( $wiw_comments );
?>
<section class="section comments" id="comments" aria-labelledby="comments-h">
	<div class="wrap wrap--text">
		<p class="eyebrow"><?php esc_html_e( 'Join in', 'whoiswho' ); ?></p>
		<h2 id="comments-h">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of comments. */
					_n( '%s comment', '%s comments', $wiw_count, 'whoiswho' ),
					number_format_i18n( $wiw_count )
				)
			);
			?>
		</h2>
		<?php if ( 'held' === (string) get_query_var( 'wiw_comment' ) ) : ?>
			<p class="notice notice--info" role="status"><?php esc_html_e( 'Thank you. Your comment will appear once it is approved.', 'whoiswho' ); ?></p>
		<?php endif; ?>
		<?php if ( array() !== $wiw_comments ) : ?>
			<ol class="cm-list">
				<?php foreach ( $wiw_comments as $wiw_comment ) : ?>
					<?php echo wiw_comment_item( $wiw_comment ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in wiw_comment_item(). ?>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
		<?php if ( $wiw_open ) : ?>
			<?php
			add_filter( 'comment_form_default_fields', 'wiw_comment_fields' );
			comment_form(
				array(
					'title_reply'          => esc_html__( 'Leave a comment', 'whoiswho' ),
					'title_reply_before'   => '<h3 id="reply-title" class="comment-reply-title">',
					'title_reply_after'    => '</h3>',
					'comment_notes_before' => '',
					'comment_notes_after'  => '',
					'comment_field'        => '<div class="field"><label for="comment">' . esc_html__( 'Your comment', 'whoiswho' ) . ' <span class="visually-hidden">' . esc_html__( '(required)', 'whoiswho' ) . '</span></label><textarea id="comment" name="comment" rows="5" required aria-required="true"></textarea></div>',
					'class_submit'         => 'btn',
					'submit_field'         => '<p class="small muted cm-note">' . esc_html__( 'Comments are checked before they appear. Your email is never shown.', 'whoiswho' ) . '</p><p class="form-submit">%1$s %2$s</p>',
					'cookies'              => '',
					'logged_in_as'         => '',
					'format'               => 'html5',
				),
				$wiw_post_id
			);
			remove_filter( 'comment_form_default_fields', 'wiw_comment_fields' );
			?>
		<?php endif; ?>
	</div>
</section>
