<?php
/**
 * The public Awards routes (REQ-033, REQ-198, REQ-064, REQ-226): /awards/category/{n}, /awards/nominees, /awards/nominee/{ref}
 * and /awards/winners. A nominee page, and a category page that does not exist, answer 404 to visitors; a nominee whose nomination
 * is Not live, or whose profile is not reviewed, is the same 404, so a visitor cannot tell it from a page that never existed. A
 * staff member who may manage awards sees a Not live nominee as a preview that is never indexed or cached. The handler stores
 * the view on the query for the theme; the theme reads PublicNominee and PublicWinner, never the tables. /awards and
 * /awards/rules are ordinary pages and are not handled here.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

/**
 * Awards routes.
 */
final class AwardsRoutes {

	public const VIEW_VAR = 'wiw_awards_view';
	public const ARG_VAR  = 'wiw_awards_arg';

	public const OPTION_FLUSHED = 'wiw_awards_rewrite_version';
	public const RULES_VERSION  = '1';

	/**
	 * Hook everything.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rules' ), 6 );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle' ), 1 );
	}

	/**
	 * Add the rewrite rules.
	 */
	public function add_rules(): void {
		$view = 'index.php?' . self::VIEW_VAR . '=';
		add_rewrite_rule( '^awards/nominees/?$', $view . 'nominees', 'top' );
		add_rewrite_rule( '^awards/winners/?$', $view . 'winners', 'top' );
		add_rewrite_rule( '^awards/category/([0-9]+)/?$', $view . 'category&' . self::ARG_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( '^awards/nominee/([A-Za-z0-9]+)/?$', $view . 'nominee&' . self::ARG_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Flush the rules once per rules version.
	 */
	public function maybe_flush(): void {
		if ( self::RULES_VERSION !== get_option( self::OPTION_FLUSHED ) ) {
			flush_rewrite_rules( false );
			update_option( self::OPTION_FLUSHED, self::RULES_VERSION, false );
		}
	}

	/**
	 * Register the query variables.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		$vars[] = self::VIEW_VAR;
		$vars[] = self::ARG_VAR;
		return $vars;
	}

	/**
	 * Decide the answer for a view, without sending anything.
	 *
	 * @param string $view View: nominees, winners, category or nominee.
	 * @param string $arg  Category number or nominee reference.
	 * @return array{action: string, status: int} action is serve, preview or notfound.
	 */
	public function resolve( string $view, string $arg = '' ): array {
		switch ( $view ) {
			case 'nominees':
			case 'winners':
				return $this->answer( 'serve' );
			case 'category':
				return $this->answer( $this->category_exists( (int) $arg ) ? 'serve' : 'notfound' );
			case 'nominee':
				if ( null !== PublicNominee::find( $arg ) ) {
					return $this->answer( 'serve' );
				}
				return $this->answer( null !== PublicNominee::find( $arg, true ) ? 'preview' : 'notfound' );
		}
		return $this->answer( 'notfound' );
	}

	/**
	 * The template_redirect handler.
	 */
	public function handle(): void {
		global $wp_query;
		$view = get_query_var( self::VIEW_VAR );
		if ( ! is_string( $view ) || '' === $view ) {
			return;
		}
		$arg    = get_query_var( self::ARG_VAR );
		$result = $this->resolve( $view, is_string( $arg ) ? $arg : '' );
		if ( 'notfound' === $result['action'] ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}
		$wp_query->is_404 = false;
		status_header( 200 );
		if ( 'preview' === $result['action'] ) {
			nocache_headers();
			if ( ! headers_sent() ) {
				header( 'X-Robots-Tag: noindex', true );
			}
		}
	}

	/**
	 * Whether a category exists and is not archived.
	 *
	 * @param int $id Category.
	 */
	private function category_exists( int $id ): bool {
		global $wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$archived = $wpdb->get_var( $wpdb->prepare( 'SELECT archived FROM ' . AwardsTables::table( AwardsTables::CATEGORIES ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		return null !== $archived && 0 === (int) $archived;
	}

	/**
	 * Answer.
	 *
	 * @param string $action Action.
	 * @return array{action: string, status: int}
	 */
	private function answer( string $action ): array {
		return array(
			'action' => $action,
			'status' => 'notfound' === $action ? 404 : 200,
		);
	}
}
