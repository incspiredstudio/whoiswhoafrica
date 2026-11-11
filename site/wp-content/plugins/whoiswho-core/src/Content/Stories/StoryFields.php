<?php
/**
 * Story fields on core posts: registered SEO meta, the story box (type, linked profiles, SEO) and its save (REQ-027, REQ-028).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Story fields.
 */
final class StoryFields {

	public const NONCE_ACTION = 'wiw_story_fields';
	public const NONCE_FIELD  = '_wiw_story_nonce';

	/**
	 * Hook everything in one place.
	 */
	public static function hooks(): void {
		add_action( 'init', array( StoryTypes::class, 'register' ), 5 );
		add_action( 'init', array( self::class, 'ensure_terms_once' ), 6 );
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'init', array( self::class, 'attach_industry' ), 30 );
		add_action( 'add_meta_boxes_post', array( self::class, 'add_box' ) );
		add_action( 'save_post_post', array( self::class, 'save' ), 10, 1 );
	}

	/**
	 * Create the six type terms the first time, and again after a version bump.
	 */
	public static function ensure_terms_once(): void {
		if ( '1' !== get_option( 'wiw_story_types_seeded' ) ) {
			StoryTypes::ensure_terms();
			update_option( 'wiw_story_types_seeded', '1', false );
		}
	}

	/**
	 * Give posts the industry taxonomy, when the Directory module has registered it.
	 */
	public static function attach_industry(): void {
		if ( taxonomy_exists( 'wiw_industry' ) ) {
			register_taxonomy_for_object_type( 'wiw_industry', 'post' );
		}
	}

	/**
	 * Registered meta: SEO title, SEO description and linked profiles.
	 */
	public static function register_meta(): void {
		$auth = static function (): bool {
			return current_user_can( 'edit_posts' );
		};
		register_post_meta(
			'post',
			'wiw_seo_title',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			'post',
			'wiw_seo_description',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			'post',
			LinkedProfiles::META,
			array(
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => $auth,
			)
		);
	}

	/**
	 * Add the story box to the post editor.
	 */
	public static function add_box(): void {
		add_meta_box( 'wiw_story_fields', __( 'Story details', 'whoiswho' ), array( self::class, 'render' ), 'post', 'side', 'high' );
	}

	/**
	 * Render the box.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$type    = StoryTypes::of( $post->ID );
		$linked  = get_post_meta( $post->ID, LinkedProfiles::META, true );
		$linked  = is_array( $linked ) ? array_map( 'intval', $linked ) : array();
		$choices = LinkedProfiles::choices();
		echo '<p><label for="wiw_story_type"><strong>' . esc_html__( 'Story type (required to publish)', 'whoiswho' ) . '</strong></label><br />';
		echo '<select name="wiw_story_type" id="wiw_story_type"><option value="">' . esc_html__( 'Choose a type', 'whoiswho' ) . '</option>';
		foreach ( StoryTypes::TYPES as $slug ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $type, $slug, false ) . '>' . esc_html( ucfirst( $slug ) ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="wiw_linked_profiles"><strong>' . esc_html__( 'Linked profiles (public only)', 'whoiswho' ) . '</strong></label><br />';
		if ( array() === $choices ) {
			echo '<em>' . esc_html__( 'No public profiles to link yet.', 'whoiswho' ) . '</em>';
		} else {
			echo '<select name="wiw_linked_profiles[]" id="wiw_linked_profiles" multiple size="6">';
			foreach ( $choices as $id => $name ) {
				echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( in_array( $id, $linked, true ), true, false ) . '>' . esc_html( $name ) . '</option>';
			}
			echo '</select>';
		}
		echo '</p>';
		$title = (string) get_post_meta( $post->ID, 'wiw_seo_title', true );
		$desc  = (string) get_post_meta( $post->ID, 'wiw_seo_description', true );
		echo '<p><label for="wiw_seo_title"><strong>' . esc_html__( 'SEO title', 'whoiswho' ) . '</strong></label><br />';
		echo '<input type="text" class="widefat" name="wiw_seo_title" id="wiw_seo_title" value="' . esc_attr( $title ) . '" /></p>';
		echo '<p><label for="wiw_seo_description"><strong>' . esc_html__( 'SEO description', 'whoiswho' ) . '</strong></label><br />';
		echo '<textarea class="widefat" rows="3" name="wiw_seo_description" id="wiw_seo_description">' . esc_textarea( $desc ) . '</textarea></p>';
	}

	/**
	 * Save the box: nonce, capability, then the values.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save( int $post_id ): void {
		$request = Request::from_globals();
		if ( ! wp_verify_nonce( $request->string( self::NONCE_FIELD ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$type = $request->string( 'wiw_story_type' );
		if ( StoryTypes::is_valid( $type ) ) {
			wp_set_object_terms( $post_id, $type, StoryTypes::TAXONOMY );
		} elseif ( '' === $type ) {
			wp_set_object_terms( $post_id, array(), StoryTypes::TAXONOMY );
		}
		update_post_meta( $post_id, LinkedProfiles::META, LinkedProfiles::sanitise( $request->ints( 'wiw_linked_profiles' ) ) );
		update_post_meta( $post_id, 'wiw_seo_title', $request->string( 'wiw_seo_title' ) );
		update_post_meta( $post_id, 'wiw_seo_description', sanitize_textarea_field( $request->string( 'wiw_seo_description' ) ) );
		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post ) {
			StoryRules::enforce_type( $post_id, $post );
		}
	}
}
