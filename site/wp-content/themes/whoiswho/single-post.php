<?php
/**
 * Single story at /stories/:slug (REQ-026, REQ-027, REQ-028, REQ-182, REQ-265): type, date, industry and reading time, an optional
 * Sponsored badge with the in-story notice, a 16:9 cover, the lede, the body with its pull quotes, the public profiles linked to
 * the story (On the record), the Related event card while the event is published, a reserved comments region and three more
 * stories. Reads stories only through PublicStory. A draft or scheduled story shows a staff banner to staff and nothing to a
 * visitor (the plugin answers 404 for them).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Content\PublicEvent;
use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Directory\PublicProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_story = class_exists( PublicStory::class ) ? PublicStory::find( (int) get_queried_object_id(), true ) : null;
get_header();
if ( null === $wiw_story ) {
	get_template_part(
		'template-parts/notice',
		null,
		array(
			'kind' => 'info',
			'text' => __( 'This story is not available.', 'whoiswho' ),
		)
	);
	get_footer();
	return;
}
$wiw_types    = wiw_story_types();
$wiw_type     = $wiw_types[ (string) ( $wiw_story['type'] ?? '' ) ] ?? '';
$wiw_sponsor  = isset( $wiw_story['sponsor'] ) && is_array( $wiw_story['sponsor'] ) && ! empty( $wiw_story['sponsor']['active'] ) ? $wiw_story['sponsor'] : null;
$wiw_cover    = wiw_story_cover( $wiw_story, '(min-width: 80rem) 72rem, 100vw', true );
$wiw_date     = wiw_story_date( $wiw_story );
$wiw_minutes  = (int) ( $wiw_story['reading_minutes'] ?? 0 );
$wiw_industry = isset( $wiw_story['industries'][0] ) ? (string) $wiw_story['industries'][0] : '';
$wiw_event    = isset( $wiw_story['event']['id'] ) && class_exists( PublicEvent::class ) ? PublicEvent::find( (int) $wiw_story['event']['id'] ) : null;
$wiw_people   = array();
foreach ( (array) ( $wiw_story['profiles'] ?? array() ) as $wiw_linked ) {
	$wiw_person = class_exists( PublicProfile::class ) && isset( $wiw_linked['id'] ) ? PublicProfile::find_by_id( (int) $wiw_linked['id'] ) : null;
	if ( null !== $wiw_person ) {
		$wiw_people[] = $wiw_person;
	}
}
if ( ! empty( $wiw_story['preview'] ) ) {
	get_template_part(
		'template-parts/notice',
		null,
		array(
			'kind' => 'preview',
			'text' => __( 'Staff preview. This story is not public: visitors get a 404 until it is published.', 'whoiswho' ),
		)
	);
}
?>
<article class="story-page">
	<header class="wrap wrap--text article-head">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'whoiswho' ); ?>">
			<a href="<?php echo esc_url( home_url( '/stories/' ) ); ?>"><?php esc_html_e( 'Stories', 'whoiswho' ); ?></a>
			<?php if ( '' !== $wiw_type ) : ?>
				<span aria-hidden="true"> / </span>
				<span><?php echo esc_html( $wiw_type ); ?></span>
			<?php endif; ?>
		</nav>
		<p class="story-meta article-meta">
			<?php if ( null !== $wiw_sponsor ) : ?>
				<span class="badge badge--sponsored"><?php echo esc_html( (string) ( $wiw_sponsor['label'] ?? __( 'Sponsored', 'whoiswho' ) ) ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $wiw_type ) : ?>
				<b><?php echo esc_html( $wiw_type ); ?></b>
			<?php endif; ?>
			<?php if ( '' !== $wiw_date ) : ?>
				<time datetime="<?php echo esc_attr( gmdate( 'Y-m-d', (int) strtotime( (string) $wiw_story['date'] ) ) ); ?>"><?php echo esc_html( $wiw_date ); ?></time>
			<?php endif; ?>
			<?php if ( '' !== $wiw_industry ) : ?>
				<span><?php echo esc_html( $wiw_industry ); ?></span>
			<?php endif; ?>
			<?php if ( $wiw_minutes > 0 ) : ?>
				<span>
					<?php
					/* translators: %d: minutes to read. */
					echo esc_html( sprintf( __( '%d min read', 'whoiswho' ), $wiw_minutes ) );
					?>
				</span>
			<?php endif; ?>
		</p>
		<h1><?php echo esc_html( (string) $wiw_story['title'] ); ?></h1>
		<?php if ( '' !== (string) $wiw_story['lede'] ) : ?>
			<p class="article-lede"><?php echo esc_html( (string) $wiw_story['lede'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== (string) $wiw_story['author'] ) : ?>
			<p class="article-by muted small">
				<?php
				/* translators: %s: author name. */
				echo esc_html( sprintf( __( 'By %s', 'whoiswho' ), (string) $wiw_story['author'] ) );
				?>
			</p>
		<?php endif; ?>
	</header>
	<?php if ( '' !== $wiw_cover ) : ?>
		<div class="wrap">
			<figure class="article-cover">
				<div class="scard-media"><?php echo $wiw_cover; // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup. ?></div>
			</figure>
		</div>
	<?php endif; ?>
	<div class="wrap wrap--text article-body">
		<?php if ( null !== $wiw_sponsor ) : ?>
			<div class="notice notice--sponsored" role="note">
				<?php echo wiw_icon( 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static icon markup. ?>
				<div>
					<strong><?php echo esc_html( (string) ( $wiw_sponsor['label'] ?? '' ) ); ?>.</strong>
					<?php esc_html_e( 'The sponsor paid for this story\'s placement. The sponsor did not write it and has no influence over any label, ranking or award.', 'whoiswho' ); ?>
				</div>
			</div>
		<?php endif; ?>
		<?php echo wp_kses_post( (string) $wiw_story['body'] ); ?>
	</div>
</article>
<?php
get_template_part( 'template-parts/event-related', null, array( 'event' => $wiw_event ?? array() ) );
?>
<?php if ( array() !== $wiw_people ) : ?>
	<section class="section section--alt story-people" aria-labelledby="story-people-h">
		<div class="wrap">
			<div class="section-head">
				<div>
					<p class="eyebrow"><?php esc_html_e( 'On the record', 'whoiswho' ); ?></p>
					<h2 id="story-people-h"><?php esc_html_e( 'Profiles in this story', 'whoiswho' ); ?></h2>
				</div>
			</div>
			<div class="grid grid--4">
				<?php foreach ( $wiw_people as $wiw_person ) : ?>
					<div><?php get_template_part( 'template-parts/profile-card', null, array( 'profile' => $wiw_person ) ); ?></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>
<?php
/**
 * The comments region of a story. Reserved here; the comments feature (M4) adds its block on this hook.
 *
 * @param array<string, mixed> $story The story from PublicStory.
 */
do_action( 'wiw_story_comments', $wiw_story );
?>
<?php if ( ! empty( $wiw_story['others'] ) ) : ?>
	<section class="section story-more" aria-labelledby="story-more-h">
		<div class="wrap">
			<div class="section-head">
				<div>
					<p class="eyebrow"><?php esc_html_e( 'Keep reading', 'whoiswho' ); ?></p>
					<h2 id="story-more-h"><?php esc_html_e( 'More stories', 'whoiswho' ); ?></h2>
				</div>
				<a class="btn alt" href="<?php echo esc_url( home_url( '/stories/' ) ); ?>"><?php esc_html_e( 'All stories', 'whoiswho' ); ?></a>
			</div>
			<div class="stories-grid stories-grid--more">
				<?php foreach ( (array) $wiw_story['others'] as $wiw_other ) : ?>
					<?php get_template_part( 'template-parts/story-card', null, array( 'story' => $wiw_other ) ); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>
<?php
get_footer();
