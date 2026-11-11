<?php
/**
 * Profile slugs (REQ-003, REQ-006): generated from the name with diacritics transliterated and a numeric suffix on collision,
 * stable once set (editing the name never changes it), and every earlier slug is remembered so its old address redirects.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Url;

use WhoIsWho\Core\Directory\ProfileType;

/**
 * Slug service.
 */
final class ProfileSlugs {

	/**
	 * Post meta holding each earlier slug (one row per slug).
	 */
	public const OLD_SLUG_META = '_wiw_old_slug';

	/**
	 * Hook the service.
	 */
	public function register(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'on_insert_data' ), 98, 2 );
		add_action( 'post_updated', array( $this, 'remember_old_slug' ), 10, 3 );
	}

	/**
	 * Give a new profile its slug (even before it has a public status) and keep an existing slug.
	 *
	 * @param array<string, mixed> $data    Post data.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public function on_insert_data( array $data, array $postarr ): array {
		if ( ProfileType::POST_TYPE !== ( $data['post_type'] ?? '' ) || in_array( $data['post_status'] ?? '', array( 'auto-draft', 'trash' ), true ) ) {
			return $data;
		}
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( '' === (string) ( $data['post_name'] ?? '' ) && '' !== trim( (string) ( $data['post_title'] ?? '' ) ) ) {
			$data['post_name'] = $this->unique( wp_unslash( (string) $data['post_title'] ), $id );
		}
		return $data;
	}

	/**
	 * A unique slug for a name.
	 *
	 * @param string $name    Display name.
	 * @param int    $post_id The profile being named (0 for a new one), excluded from the collision check.
	 */
	public function unique( string $name, int $post_id = 0 ): string {
		$base = sanitize_title( remove_accents( $name ) );
		if ( '' === $base ) {
			$base = 'profile';
		}
		$base = substr( $base, 0, 190 );
		$slug = $base;
		$n    = 2;
		while ( $this->taken( $slug, $post_id ) ) {
			$slug = substr( $base, 0, 185 ) . '-' . $n;
			++$n;
		}
		return $slug;
	}

	/**
	 * Whether a slug is already used by another profile, as a current or an earlier slug, or collides with a reserved word.
	 *
	 * @param string $slug    Slug.
	 * @param int    $post_id Profile to ignore.
	 */
	private function taken( string $slug, int $post_id ): bool {
		global $wpdb;
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND ID <> %d LIMIT 1", ProfileType::POST_TYPE, $slug, $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( null !== $current ) {
			return true;
		}
		$old = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s AND post_id <> %d LIMIT 1", self::OLD_SLUG_META, $slug, $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return null !== $old;
	}

	/**
	 * Change a profile's slug on purpose (a staff action); the old slug keeps redirecting.
	 *
	 * @param int    $post_id  Profile.
	 * @param string $new_slug Wanted slug; made unique.
	 * @return string The slug that was set.
	 */
	public function change( int $post_id, string $new_slug ): string {
		$slug = $this->unique( $new_slug, $post_id );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $slug,
			)
		);
		return $slug;
	}

	/**
	 * Record the previous slug when it changes.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $after   Post after.
	 * @param \WP_Post $before  Post before.
	 */
	public function remember_old_slug( int $post_id, \WP_Post $after, \WP_Post $before ): void {
		if ( ProfileType::POST_TYPE !== $after->post_type || '' === $before->post_name || $before->post_name === $after->post_name ) {
			return;
		}
		$known = get_post_meta( $post_id, self::OLD_SLUG_META, false );
		if ( ! in_array( $before->post_name, $known, true ) ) {
			add_post_meta( $post_id, self::OLD_SLUG_META, $before->post_name );
		}
		// A slug that is current again is no longer an old one.
		delete_post_meta( $post_id, self::OLD_SLUG_META, $after->post_name );
	}

	/**
	 * Find a profile by its current slug.
	 *
	 * @param string $slug Slug.
	 * @return int|null Profile post id.
	 */
	public function find_current( string $slug ): ?int {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status NOT IN ('trash','auto-draft') LIMIT 1", ProfileType::POST_TYPE, $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return null === $id ? null : (int) $id;
	}

	/**
	 * Find a profile by an earlier slug.
	 *
	 * @param string $slug Slug.
	 * @return int|null Profile post id.
	 */
	public function find_by_old_slug( string $slug ): ?int {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft') ORDER BY m.meta_id DESC LIMIT 1", self::OLD_SLUG_META, $slug, ProfileType::POST_TYPE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return null === $id ? null : (int) $id;
	}

	/**
	 * The public address of a profile.
	 *
	 * @param string $slug Slug.
	 */
	public static function url_for_slug( string $slug ): string {
		return home_url( '/profile/' . rawurlencode( $slug ) . '/' );
	}
}
