<?php
/**
 * Stories that link to a profile (REQ-028): up to three cards, newest first. The block is absent, not empty, when none is
 * published. Args: profile (PublicProfile).
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
$wiw_stories = $wiw_profile->related_stories( 3 );
if ( array() === $wiw_stories ) {
	return;
}
?>
<section id="stories" aria-labelledby="stories-h">
	<h2 id="stories-h"><?php esc_html_e( 'Related stories', 'whoiswho' ); ?></h2>
	<div class="grid grid--2">
		<?php foreach ( $wiw_stories as $wiw_story ) : ?>
			<div><?php get_template_part( 'template-parts/story-card', null, array( 'story' => $wiw_story ) ); ?></div>
		<?php endforeach; ?>
	</div>
</section>
