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
				)
			);
			if ( $id > 0 ) {
				++$created;
			}
		}//end foreach
		return $created;
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
	 * The draft text for each page. Plain and short; Legal replaces it. Nothing here states prices, packages or fees.
	 *
	 * @return array<string, array{title: string, body: string}>
	 */
	private static function drafts(): array {
		return array(
			'privacy'     => array(
				'title' => __( 'Privacy policy', 'whoiswho' ),
				'body'  => '<p>' . __( 'This is draft wording. It explains what personal information this site collects, why, how long it is kept and who can see it. It will be replaced by the reviewed policy.', 'whoiswho' ) . '</p>'
					. '<h2>' . __( 'What we collect', 'whoiswho' ) . '</h2><p>' . __( 'Details you give us in a form, and the public information shown in directory listings.', 'whoiswho' ) . '</p>'
					. '<h2>' . __( 'Your choices', 'whoiswho' ) . '</h2><p>' . __( 'You can ask to see, correct or remove your information at any time from the data rights page.', 'whoiswho' ) . '</p>',
			),
			'terms'       => array(
				'title' => __( 'Terms of use', 'whoiswho' ),
				'body'  => '<p>' . __( 'This is draft wording. It sets out the rules for using this site. It will be replaced by the reviewed terms.', 'whoiswho' ) . '</p>'
					. '<h2>' . __( 'Using the site', 'whoiswho' ) . '</h2><p>' . __( 'The directory and stories are provided for information. Listings are published after review.', 'whoiswho' ) . '</p>'
					. '<h2>' . __( 'Corrections', 'whoiswho' ) . '</h2><p>' . __( 'If something about you or your organisation is wrong, tell us and we will look at it.', 'whoiswho' ) . '</p>',
			),
			'cookies'     => array(
				'title' => __( 'Cookie policy', 'whoiswho' ),
				'body'  => '<p>' . __( 'This is draft wording. It lists the cookies this site sets and what each one is for. Cookies that are not essential are off until you accept them.', 'whoiswho' ) . '</p>',
			),
			'data-rights' => array(
				'title' => __( 'Your data rights', 'whoiswho' ),
				'body'  => '<p>' . __( 'This is draft wording. You may ask to see, correct, export, or remove the personal information we hold about you. Use the request form and we will reply within the legal time limit.', 'whoiswho' ) . '</p>',
			),
			'complaints'  => array(
				'title' => __( 'Complaints and takedown', 'whoiswho' ),
				'body'  => '<p>' . __( 'This is draft wording. If a listing is wrong, harmful or should not be public, tell us. We confirm who you are, look at it, and answer within the stated time.', 'whoiswho' ) . '</p>',
			),
		);
	}
}
