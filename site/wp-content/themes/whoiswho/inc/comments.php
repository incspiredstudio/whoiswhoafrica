<?php
/**
 * Comments on a story (REQ-258): the approved list with an initial in place of a picture, the name, the time and the text, then the
 * form (name, email, consent tick) with the note that comments are checked first, and the thank-you after posting. Whether a story
 * takes comments, and holding every comment for a moderator, is decided by the plugin; this file only draws them.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

/**
 * Draw the comments region where the story template reserves it.
 */
function wiw_story_comments_region(): void {
	$post_id = (int) get_queried_object_id();
	if ( $post_id < 1 || ! is_singular( 'post' ) ) {
		return;
	}
	get_template_part( 'template-parts/comments', null, array( 'post_id' => $post_id ) );
}
add_action( 'wiw_story_comments', 'wiw_story_comments_region' );

/**
 * The query variable that marks the page shown after a comment is posted.
 *
 * @param array<int, string> $vars Public query variables.
 * @return array<int, string>
 */
function wiw_comment_query_vars( $vars ): array {
	$vars   = (array) $vars;
	$vars[] = 'wiw_comment';
	return $vars;
}
add_filter( 'query_vars', 'wiw_comment_query_vars' );

/**
 * After a visitor posts a comment, come back to the comments region with a thank-you marker. A comment approved straight away
 * (staff) just shows in the list.
 *
 * @param string          $location Redirect target.
 * @param WP_Comment|null $comment  The comment.
 */
function wiw_comment_redirect( $location, $comment = null ): string {
	if ( $comment instanceof WP_Comment && '1' !== (string) $comment->comment_approved ) {
		return add_query_arg( 'wiw_comment', 'held', (string) get_permalink( (int) $comment->comment_post_ID ) ) . '#comments';
	}
	return (string) $location;
}
add_filter( 'comment_post_redirect', 'wiw_comment_redirect', 10, 2 );

/**
 * The initial shown in place of a picture (no Gravatar, REQ-258).
 *
 * @param WP_Comment $comment Comment.
 */
function wiw_comment_initial( WP_Comment $comment ): string {
	$name = trim( (string) $comment->comment_author );
	return '' === $name ? '?' : mb_strtoupper( mb_substr( $name, 0, 1 ) );
}

/**
 * Markup for one comment. The email is never printed.
 *
 * @param WP_Comment $comment Comment.
 */
function wiw_comment_item( WP_Comment $comment ): string {
	$name = '' !== trim( (string) $comment->comment_author ) ? (string) $comment->comment_author : __( 'A reader', 'whoiswho' );
	$time = (int) mysql2date( 'U', (string) $comment->comment_date_gmt, false );
	/* translators: %s: time since the comment was posted, for example "2 days". */
	$ago = sprintf( __( '%s ago', 'whoiswho' ), human_time_diff( $time ) );
	ob_start();
	?>
	<li id="comment-<?php echo esc_attr( (string) $comment->comment_ID ); ?>" class="cm-item">
		<span class="cm-av" aria-hidden="true"><?php echo esc_html( wiw_comment_initial( $comment ) ); ?></span>
		<div class="cm-body">
			<p class="cm-meta"><strong class="cm-name"><?php echo esc_html( $name ); ?></strong> <time datetime="<?php echo esc_attr( gmdate( 'c', $time ) ); ?>"><?php echo esc_html( $ago ); ?></time></p>
			<div class="cm-text">
			<?php
			echo wp_kses(
				wpautop( (string) $comment->comment_content ),
				array(
					'p'  => array(),
					'br' => array(),
				)
			);
			?>
									</div>
		</div>
	</li>
	<?php
	return (string) ob_get_clean();
}

/**
 * Form fields as the design system draws them, through the shared field part.
 *
 * @param array<string, string> $fields Core fields (replaced).
 * @return array<string, string>
 */
function wiw_comment_fields( $fields ): array {
	unset( $fields );
	$defs = array(
		'author' => array( __( 'Your name', 'whoiswho' ), 'text', '' ),
		'email'  => array( __( 'Your email', 'whoiswho' ), 'email', __( 'Never shown. Used only if we need to reach you about your comment.', 'whoiswho' ) ),
	);
	$out  = array();
	foreach ( $defs as $name => $def ) {
		ob_start();
		get_template_part(
			'template-parts/forms/field',
			null,
			array(
				'name'     => $name,
				'id'       => $name,
				'label'    => $def[0],
				'type'     => $def[1],
				'hint'     => $def[2],
				'required' => true,
			)
		);
		$out[ $name ] = (string) ob_get_clean();
	}
	ob_start();
	get_template_part(
		'template-parts/forms/consent-tick',
		null,
		array(
			'name'    => 'wiw_comment_consent',
			'label'   => __( 'I agree that you may keep my name and comment with this story, as set out in the', 'whoiswho' ),
			'purpose' => 'comment',
		)
	);
	$out['wiw_consent'] = (string) ob_get_clean();
	return $out;
}
