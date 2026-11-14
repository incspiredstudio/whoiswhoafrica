<?php
/**
 * The site's own sitemap (REQ-121, REQ-208, REQ-209, REQ-217): /sitemap.xml is an index; /sitemap-pages.xml lists the home page
 * and published pages; /sitemap-profiles-N.xml lists profiles whose state allows a sitemap entry (StatePolicy::in_sitemap, read
 * from the state table, so only Published - reviewed). Pending, unpublished, removed, suppressed, draft and hidden profiles never
 * appear. Built on request, cached five minutes, and the cache is dropped as soon as a profile changes state or a page changes.
 * Core and user sitemaps stay off (the guard turns them off). Published stories are listed in /sitemap-stories.xml.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Sitemap;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Events\EventUrls;
use WhoIsWho\Core\Content\Stories\StoryUrls;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\Url\ProfileSlugs;
use WhoIsWho\Core\Review\StatePolicy;

/**
 * Sitemap.
 */
final class Sitemap {

	public const PER_PART = 1000;
	public const TTL      = 300;
	public const VERSION  = 'wiw_sitemap_version';

	/**
	 * Hook the request and the cache purges.
	 */
	public function register(): void {
		add_action( 'parse_request', array( $this, 'on_parse_request' ), 0 );
		add_action( 'wiw_profile_state_changed', array( $this, 'purge' ) );
		add_action( 'save_post_page', array( $this, 'purge' ) );
		add_action( 'transition_post_status', array( $this, 'purge_for_story' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'purge' ) );
		add_action( 'trashed_post', array( $this, 'purge' ) );
	}

	/**
	 * Serve a sitemap request.
	 *
	 * @param \WP $wp The request.
	 */
	public function on_parse_request( $wp ): void {
		$path     = (string) $wp->request;
		$response = $this->respond( $path );
		if ( null === $response ) {
			return;
		}
		status_header( $response['status'] );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		echo $response['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by this class with every value escaped.
		exit;
	}

	/**
	 * The response for a request path, or null when the path is not a sitemap.
	 *
	 * @param string $path Path without the leading slash, for example sitemap.xml.
	 * @return array{status: int, body: string}|null
	 */
	public function respond( string $path ): ?array {
		$path = trim( $path, '/' );
		if ( 'sitemap.xml' === $path ) {
			return array(
				'status' => 200,
				'body'   => $this->cached( 'index', fn (): string => $this->index() ),
			);
		}
		if ( 'sitemap-pages.xml' === $path ) {
			return array(
				'status' => 200,
				'body'   => $this->cached( 'pages', fn (): string => $this->pages() ),
			);
		}
		if ( 'sitemap-stories.xml' === $path ) {
			return array(
				'status' => 200,
				'body'   => $this->cached( 'stories', fn (): string => $this->stories() ),
			);
		}
		if ( 'sitemap-events.xml' === $path ) {
			return array(
				'status' => 200,
				'body'   => $this->cached( 'events', fn (): string => $this->events() ),
			);
		}
		if ( 1 === preg_match( '/^sitemap-profiles-([0-9]{1,6})\.xml$/', $path, $m ) ) {
			$part = (int) $m[1];
			if ( $part < 1 || $part > max( 1, $this->part_count() ) ) {
				return array(
					'status' => 404,
					'body'   => $this->wrap( 'urlset', '' ),
				);
			}
			return array(
				'status' => 200,
				'body'   => $this->cached( 'profiles-' . $part, fn (): string => $this->profiles( $part ) ),
			);
		}
		return null;
	}

	/**
	 * A story entered or left the published state: drop the cache.
	 *
	 * @param string   $to    New status.
	 * @param string   $from  Old status.
	 * @param \WP_Post $post Post.
	 */
	public function purge_for_story( string $to, string $from, \WP_Post $post ): void {
		if ( in_array( $post->post_type, array( 'post', EventPostType::TYPE ), true ) && ( 'publish' === $to || 'publish' === $from ) && $to !== $from ) {
			$this->purge();
		}
	}

	/**
	 * Drop every cached sitemap.
	 */
	public function purge(): void {
		update_option( self::VERSION, (int) get_option( self::VERSION, 0 ) + 1, false );
	}

	/**
	 * A cached body, built when missing.
	 *
	 * @param string   $name  Cache name.
	 * @param callable $build Builds the body.
	 */
	private function cached( string $name, callable $build ): string {
		$key = 'wiw_sm_' . (int) get_option( self::VERSION, 0 ) . '_' . $name;
		$hit = get_transient( $key );
		if ( is_string( $hit ) ) {
			return $hit;
		}
		$body = (string) $build();
		set_transient( $key, $body, self::TTL );
		return $body;
	}

	/**
	 * The index.
	 */
	private function index(): string {
		$parts   = array( home_url( '/sitemap-pages.xml' ), home_url( '/sitemap-stories.xml' ), home_url( '/sitemap-events.xml' ) );
		$profile = max( 1, $this->part_count() );
		for ( $i = 1; $i <= $profile; $i++ ) {
			$parts[] = home_url( '/sitemap-profiles-' . $i . '.xml' );
		}
		$xml = '';
		foreach ( $parts as $loc ) {
			$xml .= '<sitemap><loc>' . esc_url( $loc ) . '</loc></sitemap>';
		}
		return $this->wrap( 'sitemapindex', $xml );
	}

	/**
	 * Home page and published pages.
	 */
	private function pages(): string {
		$home = home_url( '/' );
		$xml  = '<url><loc>' . esc_url( $home ) . '</loc></url>';
		// Published pages only, by default. The static front page's address is the home page, already listed.
		$pages = get_pages( array( 'sort_column' => 'ID' ) );
		foreach ( $pages as $page ) {
			$link = (string) get_permalink( $page );
			if ( trailingslashit( $link ) === trailingslashit( $home ) ) {
				continue;
			}
			$xml .= $this->url( $link, $page->post_modified_gmt );
		}
		return $this->wrap( 'urlset', $xml );
	}

	/**
	 * Published stories, from the story read-side list.
	 */
	private function stories(): string {
		$xml = '';
		foreach ( StoryUrls::published( self::PER_PART ) as $row ) {
			$xml .= $this->url( $row['url'], $row['modified'] );
		}
		return $this->wrap( 'urlset', $xml );
	}

	/**
	 * Published events (REQ-264).
	 */
	private function events(): string {
		$xml = '';
		foreach ( EventUrls::published( self::PER_PART ) as $row ) {
			$xml .= $this->url( $row['url'], $row['modified'] );
		}
		return $this->wrap( 'urlset', $xml );
	}

	/**
	 * One part of the profile list.
	 *
	 * @param int $part Part number from 1.
	 */
	private function profiles( int $part ): string {
		global $wpdb;
		$states = $this->listed_states();
		if ( array() === $states ) {
			return $this->wrap( 'urlset', '' );
		}
		$marks = implode( ',', array_fill( 0, count( $states ), '%s' ) );
		$sql   = 'SELECT p.post_name, p.post_modified_gmt FROM ' . $wpdb->posts . ' p INNER JOIN ' . StateRepository::table() . ' s ON s.profile_id = p.ID WHERE p.post_type = %s AND p.post_status = %s AND s.pub_state IN (' . $marks . ') ORDER BY p.ID ASC LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names; the marks are placeholders.
		$args  = array_merge( array( ProfileType::POST_TYPE, 'publish' ), $states, array( self::PER_PART, ( $part - 1 ) * self::PER_PART ) );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$xml   = '';
		foreach ( $rows as $row ) {
			$xml .= $this->url( ProfileSlugs::url_for_slug( (string) $row['post_name'] ), (string) $row['post_modified_gmt'] );
		}
		return $this->wrap( 'urlset', $xml );
	}

	/**
	 * How many profile parts there are.
	 */
	private function part_count(): int {
		global $wpdb;
		$states = $this->listed_states();
		if ( array() === $states ) {
			return 0;
		}
		$marks = implode( ',', array_fill( 0, count( $states ), '%s' ) );
		$sql   = 'SELECT COUNT(*) FROM ' . $wpdb->posts . ' p INNER JOIN ' . StateRepository::table() . ' s ON s.profile_id = p.ID WHERE p.post_type = %s AND p.post_status = %s AND s.pub_state IN (' . $marks . ')';// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names; the marks are placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( array( ProfileType::POST_TYPE, 'publish' ), $states ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders
		return (int) ceil( $total / self::PER_PART );
	}

	/**
	 * The states whose policy allows a sitemap entry.
	 *
	 * @return string[]
	 */
	private function listed_states(): array {
		return array_values( array_filter( PubState::all(), static fn ( string $state ): bool => StatePolicy::for_state( $state )->in_sitemap ) );
	}

	/**
	 * One url element.
	 *
	 * @param string $loc  Address.
	 * @param string $gmt  Last modified, UTC.
	 */
	private function url( string $loc, string $gmt ): string {
		$last = '' !== $gmt && '0000-00-00 00:00:00' !== $gmt ? '<lastmod>' . esc_xml( gmdate( 'c', (int) strtotime( $gmt . ' UTC' ) ) ) . '</lastmod>' : '';
		return '<url><loc>' . esc_url( $loc ) . '</loc>' . $last . '</url>';
	}

	/**
	 * Wrap elements in the XML envelope.
	 *
	 * @param string $root Root element.
	 * @param string $xml  Child elements.
	 */
	private function wrap( string $root, string $xml ): string {
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<' . $root . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $xml . '</' . $root . '>';
	}
}
