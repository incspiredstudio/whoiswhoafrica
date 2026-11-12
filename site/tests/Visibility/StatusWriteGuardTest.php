<?php
/**
 * The guard refuses and logs status writes that bypass the state machine (REQ-210, REQ-208). A probe post type is guarded
 * in the test; the filter is generic, so it covers wp-admin, quick edit, bulk edit, REST, XML-RPC and imports alike.
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Guard\Guard;
use WhoIsWho\Guard\Status\StateChangeToken;
use WhoIsWho\Tests\WpTestCase;

final class StatusWriteGuardTest extends WpTestCase {

	/**
	 * Refusals seen through the action.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $refusals = array();

	public function set_up(): void {
		parent::set_up();
		Guard::guard_post_type( 'wiw_probe' );
		// The exposure guard turns REST off for guarded types (REQ-217). This probe turns it back on so the REST refusal can still be tested.
		add_filter(
			'register_post_type_args',
			static fn ( $args, $type ) => 'wiw_probe' === $type ? array_merge( $args, array( 'show_in_rest' => true ) ) : $args,
			100,
			2
		);
		register_post_type(
			'wiw_probe',
			array(
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
			)
		);
		$this->refusals = array();
		add_action(
			'wiw_guard_status_refused',
			function ( ...$args ): void {
				$this->refusals[] = $args;
			},
			10,
			5
		);
	}

	private function probe( string $status = 'wiw_hidden' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'wiw_probe',
				'post_status' => 'wiw_hidden',
			)
		);
		if ( 'wiw_hidden' !== $status ) {
			StateChangeToken::run(
				static function () use ( $id, $status ): void {
					wp_update_post(
						array(
							'ID'          => $id,
							'post_status' => $status,
						)
					);
				}
			);
		}
		$this->refusals = array();
		return $id;
	}

	public function test_REQ_210_direct_status_write_is_refused_and_logged(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = $this->probe();

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'wiw_hidden', get_post_status( $id ), 'the status is put back' );
		$this->assertCount( 1, $this->refusals );
		$this->assertSame( array( 'wiw_probe', $id, 'wiw_hidden', 'publish', get_current_user_id() ), $this->refusals[0] );
	}

	public function test_REQ_210_a_new_guarded_post_never_starts_public(): void {
		$id = wp_insert_post(
			array(
				'post_type'   => 'wiw_probe',
				'post_title'  => 'New',
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'wiw_hidden', get_post_status( $id ) );
	}

	public function test_REQ_210_the_refusal_covers_quick_edit_bulk_edit_and_rest(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		// Quick edit and the editor save through wp_update_post / edit_post.
		$quick = $this->probe();
		$_POST = array(
			'post_ID'     => $quick,
			'post_type'   => 'wiw_probe',
			'post_status' => 'publish',
			'post_title'  => 'Quick',
		);
		require_once ABSPATH . 'wp-admin/includes/post.php';
		edit_post( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the test calls the core function directly.
		$this->assertSame( 'wiw_hidden', get_post_status( $quick ), 'quick edit' );
		$_POST = array();

		// Bulk edit.
		$bulk = $this->probe();
		bulk_edit_posts(
			array(
				'post_type'   => 'wiw_probe',
				'post'        => array( $bulk ),
				'_status'     => 'publish',
				'post_author' => -1,
			)
		);
		$this->assertSame( 'wiw_hidden', get_post_status( $bulk ), 'bulk edit' );

		// REST.
		$rest                      = $this->probe();
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- rebuild the routes so the probe type has one.
		rest_get_server();
		$request = new \WP_REST_Request( 'POST', '/wp/v2/wiw_probe/' . $rest );
		$request->set_body_params( array( 'status' => 'publish' ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status(), 'the REST route exists and answered: ' . wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'wiw_hidden', get_post_status( $rest ), 'REST' );
		$this->assertNotEmpty( $this->refusals );
	}

	public function test_REQ_210_token_from_state_machine_allows_change(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = $this->probe();
		$this->assertFalse( StateChangeToken::is_active() );

		StateChangeToken::run(
			static function () use ( $id ): void {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'publish',
					)
				);
			}
		);

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( array(), $this->refusals );
		$this->assertFalse( StateChangeToken::is_active(), 'the permission ends with the callback' );
	}

	public function test_REQ_210_the_token_is_withdrawn_even_when_the_change_throws(): void {
		try {
			StateChangeToken::run(
				static function (): void {
					throw new \RuntimeException( 'boom' );
				}
			);
			$this->fail( 'the exception should pass through' );
		} catch ( \RuntimeException $e ) {
			$this->assertFalse( StateChangeToken::is_active() );
		}
	}

	public function test_REQ_210_unguarded_types_and_trashing_are_not_touched(): void {
		$post = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		wp_update_post(
			array(
				'ID'          => $post,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'publish', get_post_status( $post ), 'an ordinary post is not guarded' );

		$id = $this->probe();
		wp_trash_post( $id );
		$this->assertSame( 'trash', get_post_status( $id ) );
		$this->assertSame( array(), $this->refusals );
	}

	public function test_REQ_210_refusals_are_written_to_the_audit_log_when_the_core_plugin_is_active(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wiw_audit_log';
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) { // phpcs:ignore WordPress.DB
			$this->markTestSkipped( 'The audit table is created by the migration runner, which has not run in this database.' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id     = $this->probe();
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE action = 'guard.status_write_refused'" ); // phpcs:ignore WordPress.DB
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE action = 'guard.status_write_refused'" ); // phpcs:ignore WordPress.DB
		$this->assertSame( $before + 1, $after );
	}
}
