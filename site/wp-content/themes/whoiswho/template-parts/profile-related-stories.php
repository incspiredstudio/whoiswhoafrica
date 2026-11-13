<?php
/**
 * Related stories on a profile page (REQ-028): the stories that link this profile, as compact story cards. Nothing prints when there
 * are none. A profile page never carries a Sponsored or Featured marker, so those fields are removed from each card before it is
 * drawn. Reads only through the PublicProfile read model. Args: profile.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_profile = isset( $args['profile'] ) && $args['profile'] instanceof PublicProfile ? $args['profile'] : null;
if ( null === $wiw_profile ) {
	return;
}
$wiw_stories = $wiw_profile->related_stories( 3 );
if ( array() === $wiw_stories ) {
	return;
}
?>
<section id="stories" class="profile-stories" aria-labelledby="stories-h">
	<h2 id="stories-h"><?php esc_html_e( 'Stories', 'whoiswho' ); ?></h2>
	<div class="profile-stories-list">
		<?php
		foreach ( $wiw_stories as $wiw_story ) {
			unset( $wiw_story['sponsor'], $wiw_story['featured'] );
			get_template_part(
				'template-parts/story-card',
				null,
				array(
					'story'   => $wiw_story,
					'variant' => 'card',
				)
			);
		}
		?>
	</div>
</section>
