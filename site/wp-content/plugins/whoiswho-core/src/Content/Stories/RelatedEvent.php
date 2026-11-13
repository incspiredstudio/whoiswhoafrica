<?php
/**
 * The related event of a story (REQ-265): one published event picked in the story editor and stored as related_event_id. Only
 * published events can be chosen, and a value that is not one is dropped on save. Unlinking clears the field. The public side
 * (PublicStory) shows the event only while it is published, so an unpublished event hides the card without losing the link.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Stories;

use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Related event field.
 */
final class RelatedEvent {

	public const META         = 'related_event_id';
	public const NONCE_ACTION = 'wiw_related_event';
	public const NONCE_FIELD  = '_wiw_related_event_nonce';
	public const FIELD        = 'wiw_related_event';

	/**
	 * Hook everything in one place.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'add_meta_boxes_post', array( self::class, 'add_box' ) );
		add_action( 'save_post_post', array( self::class, 'save' ), 11, 1 );
	}

	/**
	 * Register the meta key (an integer). The picker and save only offer and accept published events, but an existing link is kept when the event is later unpublished.
	 */
	public static function register_meta(): void {
		register_post_meta(
			'post',
			self::META,
			array(
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => static fn (): bool => current_user_can( 'edit_posts' ),
			)
		);
	}

	/**
	 * Published events as id => title, newest first.
	 *
	 * @param int $limit Maximum events.
	 * @return array<int, string>
	 */
	public static function choices( int $limit = 200 ): array {
		$ids = get_posts(
			array(
				'post_type'      => EventPostType::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, $limit ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[ (int) $id ] = get_the_title( (int) $id );
		}
		return $out;
	}

	/**
	 * Keep an id only when it is a published event; anything else becomes 0 (no link).
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitise( $value ): int {
		$id = is_numeric( $value ) ? (int) $value : 0;
		if ( $id <= 0 ) {
			return 0;
		}
		$event = get_post( $id );
		return ( $event instanceof \WP_Post && EventPostType::TYPE === $event->post_type && 'publish' === $event->post_status ) ? $id : 0;
	}

	/**
	 * Add the box to the post editor.
	 */
	public static function add_box(): void {
		add_meta_box( 'wiw_related_event', __( 'Related event', 'whoiswho' ), array( self::class, 'render' ), 'post', 'side', 'default' );
	}

	/**
	 * Render the picker.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$current = (int) get_post_meta( $post->ID, self::META, true );
		echo '<p><label for="' . esc_attr( self::FIELD ) . '"><strong>' . esc_html__( 'Published event', 'whoiswho' ) . '</strong></label><br />';
		echo '<select name="' . esc_attr( self::FIELD ) . '" id="' . esc_attr( self::FIELD ) . '"><option value="">' . esc_html__( 'None', 'whoiswho' ) . '</option>';
		foreach ( self::choices() as $id => $title ) {
			echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( $current, $id, false ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select></p>';
	}

	/**
	 * Save: nonce, capability, then the value. None clears the link.
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
		$id = self::sanitise( $request->int( self::FIELD ) );
		if ( $id > 0 ) {
			update_post_meta( $post_id, self::META, $id );
		} else {
			delete_post_meta( $post_id, self::META );
		}
	}
}
