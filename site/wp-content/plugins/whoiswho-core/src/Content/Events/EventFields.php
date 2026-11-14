<?php
/**
 * Event details box and its save. The submission goes through EventValidator (REQ-262).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Events;

use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Event fields.
 */
final class EventFields {

	public const NONCE_ACTION = 'wiw_event_fields';
	public const NONCE_FIELD  = '_wiw_event_nonce';
	public const META_PREFIX  = 'wiw_event_';
	public const ERROR_KEY    = 'wiw_event_errors';

	/**
	 * Hook registration.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'add_meta_boxes_' . EventPostType::TYPE, array( self::class, 'add_box' ) );
		add_action( 'save_post_' . EventPostType::TYPE, array( self::class, 'save' ), 10, 1 );
	}

	/**
	 * Register the meta keys (not exposed in REST).
	 */
	public static function register_meta(): void {
		foreach ( ( new EventValidator() )->field_names() as $name ) {
			register_post_meta(
				EventPostType::TYPE,
				self::META_PREFIX . $name,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function (): bool {
						return current_user_can( EventPostType::CAP );
					},
				)
			);
		}
	}

	/**
	 * Add the box.
	 */
	public static function add_box(): void {
		add_meta_box( 'wiw_event_fields', __( 'Event details', 'whoiswho' ), array( self::class, 'render' ), EventPostType::TYPE, 'normal', 'high' );
	}

	/**
	 * Read one stored value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Field name.
	 */
	public static function value( int $post_id, string $name ): string {
		return (string) get_post_meta( $post_id, self::META_PREFIX . $name, true );
	}

	/**
	 * Render the box.
	 *
	 * @param \WP_Post $post Event.
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$rows = array(
			'start'            => array( __( 'Start (required)', 'whoiswho' ), 'datetime-local' ),
			'end'              => array( __( 'End (optional)', 'whoiswho' ), 'datetime-local' ),
			'venue'            => array( __( 'Venue', 'whoiswho' ), 'text' ),
			'city'             => array( __( 'City (required unless Online)', 'whoiswho' ), 'text' ),
			'country'          => array( __( 'Country (required unless Online)', 'whoiswho' ), 'text' ),
			'organiser'        => array( __( 'Organiser', 'whoiswho' ), 'text' ),
			'registration_url' => array( __( 'Registration link (external https)', 'whoiswho' ), 'url' ),
			'price_note'       => array( __( 'Price note (text only; the site takes no payment)', 'whoiswho' ), 'text' ),
		);
		echo '<p><label><input type="checkbox" name="wiw_online" value="1"' . checked( '1', self::value( $post->ID, 'online' ), false ) . ' /> ' . esc_html__( 'Online event', 'whoiswho' ) . '</label></p>';
		foreach ( $rows as $name => $row ) {
			$value = self::value( $post->ID, $name );
			if ( 'datetime-local' === $row[1] ) {
				$value = str_replace( ' ', 'T', $value );
			}
			echo '<p><label for="wiw_' . esc_attr( $name ) . '"><strong>' . esc_html( $row[0] ) . '</strong></label><br />';
			echo '<input class="widefat" type="' . esc_attr( $row[1] ) . '" id="wiw_' . esc_attr( $name ) . '" name="wiw_' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" /></p>';
		}
		$errors = get_transient( self::ERROR_KEY . '_' . $post->ID );
		if ( is_array( $errors ) ) {
			foreach ( $errors as $message ) {
				echo '<p class="notice notice-error">' . esc_html( (string) $message ) . '</p>';
			}
		}
	}

	/**
	 * Save: nonce, capability, validator. An event that fails validation is sent back to draft.
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
		$validator = new EventValidator();
		$input     = array();
		foreach ( $validator->field_names() as $name ) {
			$input[ $name ] = $request->string( 'wiw_' . $name );
		}
		$result = $validator->validate( $input );
		foreach ( $result['values'] as $name => $value ) {
			update_post_meta( $post_id, self::META_PREFIX . $name, wp_slash( $value ) );
		}
		$post = get_post( $post_id );
		if ( array() !== $result['errors'] ) {
			set_transient( self::ERROR_KEY . '_' . $post_id, array_values( $result['errors'] ), 60 );
			if ( $post instanceof \WP_Post && in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
				remove_action( 'save_post_' . EventPostType::TYPE, array( self::class, 'save' ), 10 );
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
				add_action( 'save_post_' . EventPostType::TYPE, array( self::class, 'save' ), 10, 1 );
			}
		} else {
			delete_transient( self::ERROR_KEY . '_' . $post_id );
		}
	}
}
