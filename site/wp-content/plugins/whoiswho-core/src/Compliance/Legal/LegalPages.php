<?php
/**
 * The legal pages and the policy version (REQ-100, REQ-120, REQ-058). Privacy, Terms, Cookie policy, Your data rights and
 * Complaints exist, published, with draft sample text, so terms are public before any customer data is collected. Final wording
 * comes from Legal and is edited in the page editor; seeding never overwrites an edited page. When the wording of one of these
 * pages changes, the policy version setting goes up by one, so consent records show which wording was agreed to. There is no
 * public commercial terms page and no draft states prices, packages or fees.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Legal;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Settings\SettingsService;

/**
 * Legal pages.
 */
final class LegalPages {

	public const SLUGS = array( 'privacy', 'terms', 'cookies', 'data-rights', 'complaints' );

	/**
	 * Whether the listener is attached (one listener, however many times the module registers).
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Listen for wording changes.
	 */
	public function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_action( 'post_updated', array( $this, 'on_page_updated' ), 10, 3 );
	}

	/**
	 * Create any missing legal page. A page that exists, in any status, is left alone.
	 *
	 * @return int How many pages were created.
	 */
	public function ensure(): int {
		$created = 0;
		foreach ( self::drafts() as $slug => $draft ) {
			$existing = get_posts(
				array(
					'post_type'      => 'page',
					'name'           => $slug,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( array() !== $existing ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $draft['title'],
					'post_content' => $draft['body'],
					'meta_input'   => array( LegalDrafts::DRAFT_META => '1' ),
				)
			);
			if ( $id > 0 ) {
				++$created;
			}
		}//end foreach
		return $created;
	}

	/**
	 * Replace a page that still holds the original short draft (unchanged, byte for byte) with the fuller draft. A page that
	 * anyone edited, or that is missing, is left alone.
	 *
	 * @return int How many pages were replaced.
	 */
	public function upgrade_untouched(): int {
		$first_drafts = array(
			'privacy'     => '09ad6bc0f51a0d8010b3ae5402a874f6',
			'terms'       => 'c72a1ac546b6102c2485e04a2e25ab36',
			'cookies'     => '078088c624790066642f6739adaef6c3',
			'data-rights' => '3fd6eb48a94e605ef63d964be4502610',
			'complaints'  => 'd08e04be220bc15570ba7d63a44c00ab',
		);
		$replaced     = 0;
		foreach ( $first_drafts as $slug => $hash ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page instanceof \WP_Post || md5( $page->post_content ) !== $hash ) {
				continue;
			}
			wp_update_post(
				array(
					'ID'           => $page->ID,
					'post_content' => LegalDrafts::for_slug( $slug )['body'],
				)
			);
			update_post_meta( $page->ID, LegalDrafts::DRAFT_META, '1' );
			++$replaced;
		}
		return $replaced;
	}

	/**
	 * Bump the policy version when the wording of a legal page changes (title-only edits do not count).
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $after   Post after the update.
	 * @param \WP_Post $before  Post before the update.
	 */
	public function on_page_updated( int $post_id, \WP_Post $after, \WP_Post $before ): void {
		unset( $post_id );
		if ( 'page' !== $after->post_type || ! in_array( $after->post_name, self::SLUGS, true ) || $after->post_content === $before->post_content ) {
			return;
		}
		// A system step, not a staff action on settings: the page edit was already allowed by the editor's own capability.
		$settings = new SettingsService( SettingsService::default_registry(), AuditLogger::for_site(), static fn (): bool => true );
		$current  = (string) $settings->get( SettingsService::POLICY_VERSION );
		$next     = ctype_digit( $current ) ? (string) ( (int) $current + 1 ) : '2';
		$settings->set( SettingsService::POLICY_VERSION, $next, 'Legal page wording changed: ' . $after->post_name );
	}

	/**
	 * The draft text for each page (see LegalDrafts). Nothing here states prices, packages or fees.
	 *
	 * @return array<string, array{title: string, body: string}>
	 */
	private static function drafts(): array {
		return LegalDrafts::all();
	}
}
