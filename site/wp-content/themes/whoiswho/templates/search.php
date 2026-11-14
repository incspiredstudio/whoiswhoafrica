<?php
/**
 * Search page at /search?q= (REQ-008, REQ-254, REQ-182, REQ-208). It reads the SearchService only, the same service the header
 * panel's plain form submits to, so the same words give the same results. Results are grouped under Profiles then Stories with
 * the count, each as the ordinary card (the profile card carries the pending-review label, the story card the Sponsored label).
 * No events. Four states: empty (a prompt and the public-only note), results, no results (with a way to the directory) and
 * rate limited. Not indexed. The cards are built from the read models for the ids the service returned, never from a query here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Content\PublicStory;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\Search\SearchService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_q  = mb_substr( trim( sanitize_text_field( (string) get_query_var( 'q', '' ) ) ), 0, 80 );
$wiw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
$wiw_r  = class_exists( SearchService::class )
	? SearchService::for_site()->search( $wiw_q, $wiw_ip )
	: array(
		'state'    => 'empty',
		'query'    => '',
		'profiles' => array(),
		'stories'  => array(),
		'count'    => 0,
	);
if ( SearchService::STATE_RATE_LIMITED === $wiw_r['state'] ) {
	status_header( 429 );
	nocache_headers();
}

get_header();
?>
<?php
// A dark band with the search field, as the prototype's search page.
?>
<section class="page-band page-band--dark title-band on-dark pattern search-band">
	<div class="wrap">
		<p class="eyebrow"><?php esc_html_e( 'Search', 'whoiswho' ); ?></p>
		<h1><?php echo esc_html( '' === $wiw_q ? __( 'Search the directory and stories', 'whoiswho' ) : sprintf( /* translators: %s: the words searched */ __( 'Results for “%s”', 'whoiswho' ), $wiw_q ) ); ?></h1>
		<form class="search-form dir-search" role="search" method="get" action="<?php echo esc_url( home_url( '/search/' ) ); ?>">
			<label class="sr" for="search-page-q"><?php esc_html_e( 'Search people, businesses, institutions and stories', 'whoiswho' ); ?></label>
			<input id="search-page-q" name="q" type="search" maxlength="80" autocomplete="off" value="<?php echo esc_attr( $wiw_q ); ?>" placeholder="<?php esc_attr_e( 'Type a name, organisation or keyword', 'whoiswho' ); ?>">
			<button class="btn" type="submit"><?php esc_html_e( 'Search', 'whoiswho' ); ?></button>
		</form>
		<?php if ( SearchService::STATE_RESULTS === $wiw_r['state'] ) : ?>
			<p class="meta search-count" role="status">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of results */
						_n( '%d result', '%d results', (int) $wiw_r['count'], 'whoiswho' ),
						(int) $wiw_r['count']
					)
				);
				?>
			</p>
		<?php endif; ?>
	</div>
</section>
<section class="section section--tight search-results">
	<div class="wrap">
		<?php if ( SearchService::STATE_EMPTY === $wiw_r['state'] ) : ?>
			<div class="empty">
				<h2><?php esc_html_e( 'Type a name, organisation or keyword', 'whoiswho' ); ?></h2>
				<p class="muted"><?php esc_html_e( 'Search covers public, reviewed profiles and published stories only. Nothing that is pending removal or not yet public appears here.', 'whoiswho' ); ?></p>
			</div>
		<?php elseif ( SearchService::STATE_RATE_LIMITED === $wiw_r['state'] ) : ?>
			<div class="empty" role="alert">
				<h2><?php esc_html_e( 'Please try again in a few minutes', 'whoiswho' ); ?></h2>
				<p class="muted"><?php esc_html_e( 'There have been a lot of searches from this connection. You can still browse the directory.', 'whoiswho' ); ?></p>
				<a class="btn" href="<?php echo esc_url( home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Browse the directory', 'whoiswho' ); ?></a>
			</div>
		<?php elseif ( SearchService::STATE_NONE === $wiw_r['state'] ) : ?>
			<div class="empty">
				<h2><?php esc_html_e( 'Nothing matches that yet', 'whoiswho' ); ?></h2>
				<p class="muted"><?php esc_html_e( 'Check the spelling, try fewer words, or browse the directory.', 'whoiswho' ); ?></p>
				<a class="btn" href="<?php echo esc_url( home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'Browse the directory', 'whoiswho' ); ?></a>
			</div>
		<?php else : ?>
			<?php if ( array() !== $wiw_r['profiles'] ) : ?>
				<section class="search-group" aria-labelledby="search-profiles-h">
					<h2 id="search-profiles-h"><?php esc_html_e( 'Profiles', 'whoiswho' ); ?> <span class="count"><?php echo esc_html( (string) count( $wiw_r['profiles'] ) ); ?></span></h2>
					<div class="grid grid--4 results">
						<?php
						foreach ( $wiw_r['profiles'] as $wiw_row ) {
							$wiw_profile = PublicProfile::find_by_id( (int) $wiw_row['id'] );
							if ( null !== $wiw_profile ) {
								echo '<div>';
								get_template_part( 'template-parts/profile-card', null, array( 'profile' => $wiw_profile ) );
								echo '</div>';
							}
						}
						?>
					</div>
				</section>
			<?php endif; ?>
			<?php if ( array() !== $wiw_r['stories'] ) : ?>
				<section class="search-group" aria-labelledby="search-stories-h">
					<h2 id="search-stories-h"><?php esc_html_e( 'Stories', 'whoiswho' ); ?> <span class="count"><?php echo esc_html( (string) count( $wiw_r['stories'] ) ); ?></span></h2>
					<div class="stories-grid">
						<?php
						foreach ( $wiw_r['stories'] as $wiw_row ) {
							$wiw_story = PublicStory::find( (int) $wiw_row['id'] );
							if ( null !== $wiw_story ) {
								get_template_part( 'template-parts/story-card', null, array( 'story' => $wiw_story ) );
							}
						}
						?>
					</div>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
