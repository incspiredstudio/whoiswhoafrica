<?php
/**
 * Awards views (REQ-033, REQ-198, REQ-064): the nominees list with group tabs and counts, a category page, a nominee page with the
 * On the record extract, and the winners list. Whether a view is public is decided by the plugin (404 or preview before this file
 * runs); the template draws what the read models return. Nominees come only through PublicNominee, so no nominator, evidence or
 * contact can be printed, and a Not live nominee is a 404 for visitors. A staff preview gets a banner and a noindex header.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

use WhoIsWho\Core\Awards\AwardsRoutes;
use WhoIsWho\Core\Awards\AwardsTables;
use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Awards\PublicNominee;
use WhoIsWho\Core\Awards\PublicWinner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wiw_view = wiw_awards_view();
$wiw_arg  = (string) get_query_var( AwardsRoutes::ARG_VAR, '' );

/**
 * Print the empty state for a view.
 *
 * @param string $title Title.
 * @param string $text  Text.
 */
$wiw_empty = static function ( string $title, string $text ): void {
	get_template_part(
		'template-parts/empty-state',
		null,
		array(
			'title'        => $title,
			'text'         => $text,
			'action_label' => __( 'Awards overview', 'whoiswho' ),
			'action_url'   => home_url( '/awards/' ),
		)
	);
};

get_header();

if ( 'nominee' === $wiw_view ) {
	$wiw_nominee = PublicNominee::find( $wiw_arg, true );
	if ( null === $wiw_nominee ) {
		$wiw_empty( __( 'This nominee is not available', 'whoiswho' ), __( 'Nominee pages appear only once the nominee is announced.', 'whoiswho' ) );
		get_footer();
		return;
	}
	$wiw_style = wiw_award_group_style( $wiw_nominee->group_key );
	$wiw_about = '' !== wiw_profile_fact( $wiw_nominee->profile, 'bio' ) ? wiw_profile_fact( $wiw_nominee->profile, 'bio' ) : wiw_profile_fact( $wiw_nominee->profile, 'summary' );
	$wiw_other = array_values(
		array_filter(
			PublicNominee::live( $wiw_nominee->category_id ),
			static fn ( PublicNominee $n ): bool => $n->ref !== $wiw_nominee->ref
		)
	);
	if ( $wiw_nominee->preview ) {
		get_template_part(
			'template-parts/notice',
			null,
			array(
				'kind' => 'preview',
				'text' => __( 'Staff preview. This nominee page is not public until the nomination is made live.', 'whoiswho' ),
			)
		);
	}
	?>
	<header class="nom-hero nom-hero--<?php echo esc_attr( $wiw_style['tone'] ); ?>">
		<div class="wrap nom-hero-in">
			<div class="nom-hero-text">
				<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'whoiswho' ); ?>">
					<a href="<?php echo esc_url( home_url( '/awards/' ) ); ?>"><?php esc_html_e( 'Awards', 'whoiswho' ); ?></a><span aria-hidden="true">/</span><a href="<?php echo esc_url( home_url( '/awards/nominees/' ) ); ?>"><?php esc_html_e( 'Nominees', 'whoiswho' ); ?></a>
				</nav>
				<p class="nom-hero-tag"><?php echo esc_html( ( $wiw_nominee->is_winner ? __( 'Winner', 'whoiswho' ) : __( 'Nominee', 'whoiswho' ) ) . ' · ' . $wiw_nominee->year ); ?></p>
				<h1><?php echo esc_html( $wiw_nominee->name ); ?></h1>
				<p class="nom-hero-cat"><a href="<?php echo esc_url( home_url( '/awards/category/' . $wiw_nominee->category_id . '/' ) ); ?>"><?php echo esc_html( $wiw_nominee->category ); ?></a></p>
				<?php if ( '' !== $wiw_nominee->citation ) : ?>
					<blockquote class="nom-hero-cite"><?php echo esc_html( $wiw_nominee->citation ); ?></blockquote>
				<?php endif; ?>
			</div>
		</div>
	</header>
	<section class="section">
		<div class="wrap split split--rail">
			<div class="stack-l">
				<div class="stack">
					<h2><?php esc_html_e( 'On the record', 'whoiswho' ); ?></h2>
					<?php if ( '' !== $wiw_about ) : ?>
						<p><?php echo esc_html( wp_trim_words( $wiw_about, 60 ) ); ?></p>
					<?php endif; ?>
					<p><a class="more" href="<?php echo esc_url( $wiw_nominee->profile->url ); ?>"><?php esc_html_e( 'Read the full profile', 'whoiswho' ); ?></a></p>
				</div>
				<?php if ( array() !== $wiw_other ) : ?>
					<div>
						<h2>
							<?php
							/* translators: %s: award category */
							echo esc_html( sprintf( __( 'Other nominees in %s', 'whoiswho' ), $wiw_nominee->category ) );
							?>
						</h2>
						<div class="nom-grid">
							<?php foreach ( $wiw_other as $wiw_one ) : ?>
								<?php get_template_part( 'template-parts/nominee-card', null, array( 'nominee' => $wiw_one ) ); ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<aside class="profile-rail" aria-label="<?php esc_attr_e( 'About this nominee', 'whoiswho' ); ?>">
				<div class="panel panel--ruled">
					<h2><?php esc_html_e( 'About this page', 'whoiswho' ); ?></h2>
					<p class="small"><?php esc_html_e( 'Built from the reviewed profile and its sourced facts. The nominator is never named.', 'whoiswho' ); ?></p>
				</div>
				<div class="panel">
					<h2><?php esc_html_e( 'Category', 'whoiswho' ); ?></h2>
					<p class="small"><?php echo esc_html( $wiw_nominee->group ); ?></p>
					<p><a class="more" href="<?php echo esc_url( home_url( '/awards/category/' . $wiw_nominee->category_id . '/' ) ); ?>"><?php esc_html_e( 'All nominees in this category', 'whoiswho' ); ?></a></p>
				</div>
			</aside>
		</div>
	</section>
	<?php
	get_footer();
	return;
}//end if

if ( 'category' === $wiw_view ) {
	$wiw_service  = CategoryService::for_site();
	$wiw_category = $wiw_service->find( (int) $wiw_arg );
	if ( null === $wiw_category || ! empty( $wiw_category['archived'] ) ) {
		$wiw_empty( __( 'This category is not available', 'whoiswho' ), __( 'Check the awards overview for the current categories.', 'whoiswho' ) );
		get_footer();
		return;
	}
	$wiw_group_key = (string) $wiw_category['group_key'];
	$wiw_nominees  = PublicNominee::live( (int) $wiw_category['id'] );
	$wiw_winner    = null;
	$wiw_rest      = array();
	foreach ( $wiw_nominees as $wiw_one ) {
		if ( null === $wiw_winner && $wiw_one->is_winner ) {
			$wiw_winner = $wiw_one;
		} else {
			$wiw_rest[] = $wiw_one;
		}
	}
	$wiw_siblings = array_filter(
		$wiw_service->in_group( $wiw_group_key ),
		static fn ( array $c ): bool => (int) $c['id'] !== (int) $wiw_category['id']
	);
	wiw_page_band(
		array(
			'eyebrow' => AwardsTables::GROUPS[ $wiw_group_key ] ?? '',
			'title'   => (string) $wiw_category['title'],
			'lede'    => wiw_award_group_text( $wiw_group_key ),
		)
	);
	?>
	<section class="section">
		<div class="wrap split split--rail">
			<div class="stack-l">
				<?php if ( null !== $wiw_winner ) : ?>
					<div>
						<h2><?php esc_html_e( 'Winner', 'whoiswho' ); ?></h2>
						<div class="nom-grid">
						<?php
						get_template_part(
							'template-parts/nominee-card',
							null,
							array(
								'nominee' => $wiw_winner,
								'won'     => true,
							)
						);
						?>
												</div>
					</div>
				<?php endif; ?>
				<div>
					<h2><?php esc_html_e( 'Nominees', 'whoiswho' ); ?></h2>
					<?php if ( array() !== $wiw_rest ) : ?>
						<div class="nom-grid">
							<?php foreach ( $wiw_rest as $wiw_one ) : ?>
								<?php get_template_part( 'template-parts/nominee-card', null, array( 'nominee' => $wiw_one ) ); ?>
							<?php endforeach; ?>
						</div>
					<?php elseif ( null === $wiw_winner ) : ?>
						<?php
						get_template_part(
							'template-parts/empty-state',
							null,
							array(
								'title' => __( 'No nominees yet', 'whoiswho' ),
								'text'  => __( 'Nominee pages appear only once nominees are announced, after screening.', 'whoiswho' ),
							)
						);
						?>
					<?php endif; ?>
				</div>
			</div>
			<aside class="profile-rail" aria-label="<?php esc_attr_e( 'About this category', 'whoiswho' ); ?>">
				<div class="panel panel--ruled">
					<h2><?php esc_html_e( 'How it is judged', 'whoiswho' ); ?></h2>
					<p class="small"><?php esc_html_e( 'Independent judges score every nominee against the published criteria. There is no public voting.', 'whoiswho' ); ?></p>
					<p><a class="more" href="<?php echo esc_url( home_url( '/awards/rules/' ) ); ?>"><?php esc_html_e( 'Awards rules', 'whoiswho' ); ?></a></p>
				</div>
				<?php if ( array() !== $wiw_siblings ) : ?>
					<div class="panel">
						<h2><?php esc_html_e( 'In this group', 'whoiswho' ); ?></h2>
						<ul class="link-stack">
							<?php foreach ( $wiw_siblings as $wiw_sibling ) : ?>
								<li><a class="small" href="<?php echo esc_url( home_url( '/awards/category/' . (int) $wiw_sibling['id'] . '/' ) ); ?>"><?php echo esc_html( (string) $wiw_sibling['title'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	</section>
	<?php
	get_footer();
	return;
}//end if

if ( 'winners' === $wiw_view ) {
	$wiw_by_year = PublicWinner::by_year();
	wiw_page_band(
		array(
			'title' => __( 'Winners', 'whoiswho' ),
			'lede'  => __( 'Every winner was chosen by independent judges against the published criteria.', 'whoiswho' ),
		)
	);
	?>
	<section class="section">
		<div class="wrap">
			<?php if ( array() === $wiw_by_year ) : ?>
				<?php $wiw_empty( __( 'Winners are not published yet', 'whoiswho' ), __( 'They appear here once the team makes them live.', 'whoiswho' ) ); ?>
			<?php endif; ?>
			<?php foreach ( $wiw_by_year as $wiw_year => $wiw_winners ) : ?>
				<h2><?php echo esc_html( (string) $wiw_year ); ?></h2>
				<div class="nom-grid">
					<?php foreach ( $wiw_winners as $wiw_one ) : ?>
						<?php
						get_template_part(
							'template-parts/nominee-card',
							null,
							array(
								'nominee' => $wiw_one,
								'won'     => true,
							)
						);
						?>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	get_footer();
	return;
}//end if

// The nominees list: public nominees grouped by award group, with group tabs and counts.
$wiw_live   = PublicNominee::live();
$wiw_groups = array();
foreach ( $wiw_live as $wiw_one ) {
	$wiw_groups[ $wiw_one->group_key ][] = $wiw_one;
}
$wiw_people = count( array_filter( $wiw_live, static fn ( PublicNominee $n ): bool => 'individual' === $n->profile->entity_type ) );
?>
<header class="np-hero">
	<div class="wrap">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'whoiswho' ); ?>">
			<a href="<?php echo esc_url( home_url( '/awards/' ) ); ?>"><?php esc_html_e( 'Awards', 'whoiswho' ); ?></a><span aria-hidden="true">/</span><span><?php esc_html_e( 'Nominees', 'whoiswho' ); ?></span>
		</nav>
		<p class="np-kicker"><?php echo esc_html( wiw_brand( 'edition' ) ); ?></p>
		<h1 class="np-title"><span><?php esc_html_e( 'The', 'whoiswho' ); ?></span> <span><?php esc_html_e( 'nominees', 'whoiswho' ); ?></span></h1>
		<div class="np-intro">
			<p><?php esc_html_e( 'Every nominee here was put forward and confirmed by our team.', 'whoiswho' ); ?></p>
			<?php if ( array() !== $wiw_live ) : ?>
				<dl class="np-facts">
					<div><dt><?php esc_html_e( 'Nominees', 'whoiswho' ); ?></dt><dd><?php echo esc_html( (string) count( $wiw_live ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'People', 'whoiswho' ); ?></dt><dd><?php echo esc_html( (string) $wiw_people ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Organisations', 'whoiswho' ); ?></dt><dd><?php echo esc_html( (string) ( count( $wiw_live ) - $wiw_people ) ); ?></dd></div>
				</dl>
			<?php endif; ?>
		</div>
	</div>
</header>
<section class="section nom-page">
	<div class="wrap">
		<?php if ( array() === $wiw_live ) : ?>
			<?php $wiw_empty( __( 'Nominees are not published yet', 'whoiswho' ), __( 'They appear here once our team makes nominees live. Nobody is listed without staff approval.', 'whoiswho' ) ); ?>
		<?php else : ?>
			<nav class="np-tabs" aria-label="<?php esc_attr_e( 'Jump to a group', 'whoiswho' ); ?>">
				<?php foreach ( AwardsTables::GROUPS as $wiw_key => $wiw_label ) : ?>
					<?php if ( isset( $wiw_groups[ $wiw_key ] ) ) : ?>
						<a class="np-tab" href="#group-<?php echo esc_attr( $wiw_key ); ?>"><?php echo esc_html( $wiw_label ); ?> <span><?php echo esc_html( (string) count( $wiw_groups[ $wiw_key ] ) ); ?></span></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</nav>
			<?php foreach ( AwardsTables::GROUPS as $wiw_key => $wiw_label ) : ?>
				<?php
				if ( ! isset( $wiw_groups[ $wiw_key ] ) ) {
					continue;
				}
				$wiw_style = wiw_award_group_style( $wiw_key );
				?>
				<section class="np-group np-tone--<?php echo esc_attr( $wiw_style['tone'] ); ?> nom-group" id="group-<?php echo esc_attr( $wiw_key ); ?>" data-group="<?php echo esc_attr( $wiw_key ); ?>" aria-labelledby="ng-<?php echo esc_attr( $wiw_key ); ?>">
					<header class="np-group-head">
						<div>
							<h2 id="ng-<?php echo esc_attr( $wiw_key ); ?>"><?php echo esc_html( $wiw_label ); ?></h2>
							<p><?php echo esc_html( wiw_award_group_text( $wiw_key ) ); ?></p>
						</div>
					</header>
					<div class="np-grid">
						<?php foreach ( $wiw_groups[ $wiw_key ] as $wiw_one ) : ?>
							<?php get_template_part( 'template-parts/nominee-card', null, array( 'nominee' => $wiw_one ) ); ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		<?php endif; ?>
		<div class="nom-how">
			<div>
				<h2><?php esc_html_e( 'How nominees are chosen', 'whoiswho' ); ?></h2>
				<p><?php esc_html_e( 'Anyone can put someone forward, with evidence. Our team checks eligibility against the published rules and lists only the nominees it has made live. Independent judges decide, outside this site. There is no public voting.', 'whoiswho' ); ?></p>
			</div>
			<div class="nom-how-side"><a class="btn" href="<?php echo esc_url( home_url( '/awards/rules/' ) ); ?>"><?php esc_html_e( 'Read the rules', 'whoiswho' ); ?></a></div>
		</div>
	</div>
</section>
<?php
get_footer();
