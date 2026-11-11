<?php
/**
 * The plugin's own route for /profile/{slug} (REQ-003, REQ-006): a rewrite rule, and a template_redirect handler that decides
 * the response from the profile's state (read from the database on every request) through Review\StatePolicy. An earlier slug
 * answers 301 to the current address, an unknown slug answers 404, and a profile that is gone answers 410 with noindex.
 * When the profile may be shown the handler stores its id on the main query for the theme and lets the request continue.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Url;

use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Review\StatePolicy;

/**
 * Profile route.
 */
final class ProfileRoute {

	public const QUERY_VAR = 'wiw_profile_slug';
	public const ID_VAR    = 'wiw_profile_id';

	/**
	 * Option that records the rewrite rules version already flushed.
	 */
	public const OPTION_FLUSHED = 'wiw_rewrite_version';
	public const RULES_VERSION  = '1';

	/**
	 * Slugs.
	 *
	 * @var ProfileSlugs
	 */
	private ProfileSlugs $slugs;

	/**
	 * Constructor.
	 *
	 * @param ProfileSlugs|null $slugs Slug service.
	 */
	public function __construct( ?ProfileSlugs $slugs = null ) {
		$this->slugs = $slugs ?? new ProfileSlugs();
	}

	/**
	 * Hook everything.
	 */
	public function register(): void {
		$this->slugs->register();
		add_action( 'init', array( $this, 'add_rules' ), 6 );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle' ), 1 );
	}

	/**
	 * Add the rewrite rule.
	 */
	public function add_rules(): void {
		add_rewrite_rule( '^profile/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Flush the rules once per rules version, so deploys pick the route up without a manual step.
	 */
	public function maybe_flush(): void {
		if ( self::RULES_VERSION !== get_option( self::OPTION_FLUSHED ) ) {
			flush_rewrite_rules( false );
			update_option( self::OPTION_FLUSHED, self::RULES_VERSION, false );
		}
	}

	/**
	 * Register the query variable.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Decide the response for a slug, without sending anything.
	 *
	 * @param string $slug Slug from the address.
	 * @return array{action: string, status: int, id: int|null, location: string|null, policy: StatePolicy|null}
	 *         action is one of redirect, serve, gone, notfound.
	 */
	public function resolve( string $slug ): array {
		$slug = sanitize_title( $slug );
		$id   = $this->slugs->find_current( $slug );
		if ( null === $id ) {
			$old = $this->slugs->find_by_old_slug( $slug );
			if ( null !== $old ) {
				$new  = get_post_field( 'post_name', $old );
				$st   = ( new StateRepository() )->pub_state( $old );
				$show = StatePolicy::for_state( $st );
				// An old address only forwards to a page that exists; otherwise it answers as the current address would.
				if ( '' !== $new && $show->is_visible() ) {
					return $this->result( 'redirect', 301, $old, ProfileSlugs::url_for_slug( $new ), null );
				}
				$id = $old;
			}
		}
		if ( null === $id ) {
			return $this->result( 'notfound', 404, null, null, null );
		}
		$policy = StatePolicy::for_state( ( new StateRepository() )->pub_state( $id ) );
		if ( 410 === $policy->status ) {
			return $this->result( 'gone', 410, $id, null, $policy );
		}
		if ( 200 !== $policy->status ) {
			return $this->result( 'notfound', 404, null, null, $policy );
		}
		return $this->result( 'serve', 200, $id, null, $policy );
	}

	/**
	 * Build a result.
	 *
	 * @param string           $action   Action.
	 * @param int              $status   Status.
	 * @param int|null         $id       Profile id.
	 * @param string|null      $location Redirect target.
	 * @param StatePolicy|null $policy  Policy.
	 * @return array{action: string, status: int, id: int|null, location: string|null, policy: StatePolicy|null}
	 */
	private function result( string $action, int $status, ?int $id, ?string $location, ?StatePolicy $policy ): array {
		return array(
			'action'   => $action,
			'status'   => $status,
			'id'       => $id,
			'location' => $location,
			'policy'   => $policy,
		);
	}

	/**
	 * The template_redirect handler.
	 */
	public function handle(): void {
		global $wp_query;
		$slug = get_query_var( self::QUERY_VAR );
		if ( ! is_string( $slug ) || '' === $slug ) {
			return;
		}
		$result = $this->resolve( $slug );
		switch ( $result['action'] ) {
			case 'redirect':
				wp_safe_redirect( (string) $result['location'], 301 );
				exit;
			case 'serve':
				$wp_query->set( self::ID_VAR, $result['id'] );
				$wp_query->is_404 = false;
				status_header( 200 );
				nocache_headers();
				if ( null !== $result['policy'] && $result['policy']->noindex ) {
					header( 'X-Robots-Tag: noindex' );
				}
				return;
			case 'gone':
				$wp_query->set_404();
				status_header( 410 );
				nocache_headers();
				header( 'X-Robots-Tag: noindex' );
				return;
			default:
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();
		}//end switch
	}
}
