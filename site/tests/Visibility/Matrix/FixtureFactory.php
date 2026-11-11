<?php
/**
 * Creates one fixture per publication state. M1 adds the profile states by calling add_state() from its own test
 * bootstrap, so the same matrix then runs on real profiles (REQ-208).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility\Matrix;

/**
 * Fixture factory.
 */
final class FixtureFactory {

	/**
	 * State name => array( post_status, is_public, post_type ).
	 *
	 * @var array<string, array{0: string, 1: bool, 2: string}>
	 */
	private static array $states = array(
		'published' => array( 'publish', true, 'post' ),
		'hidden'    => array( 'wiw_hidden', false, 'post' ),
		'draft'     => array( 'draft', false, 'post' ),
		'pending'   => array( 'pending', false, 'post' ),
		'private'   => array( 'private', false, 'post' ),
		'trash'     => array( 'trash', false, 'post' ),
		'page'      => array( 'wiw_hidden', false, 'page' ),
	);

	/**
	 * Add or replace a state (one line per state).
	 *
	 * @param string $name      State name.
	 * @param string $status    Post status to store.
	 * @param bool   $is_public Whether a visitor may reach it.
	 * @param string $post_type Post type.
	 */
	public static function add_state( string $name, string $status, bool $is_public, string $post_type = 'post' ): void {
		self::$states[ $name ] = array( $status, $is_public, $post_type );
	}

	/**
	 * Names of the states.
	 *
	 * @return string[]
	 */
	public static function states(): array {
		return array_keys( self::$states );
	}

	/**
	 * Create the fixture for a state through the WordPress test factory.
	 *
	 * @param \WP_UnitTest_Factory $factory Test factory.
	 * @param string               $state   State name.
	 */
	public static function create( \WP_UnitTest_Factory $factory, string $state ): Fixture {
		list( $status, $is_public, $type ) = self::$states[ $state ];
		$author                            = $factory->user->create( array( 'role' => 'editor' ) );
		$token                             = 'vizmatrix' . strtolower( preg_replace( '/[^a-z]/i', '', $state ) ) . wp_rand( 1000, 9999 );
		$id                                = $factory->post->create(
			array(
				'post_type'    => $type,
				'post_status'  => $status,
				'post_author'  => $author,
				'post_title'   => $token,
				'post_content' => $token,
			)
		);
		return new Fixture( $state, (int) $id, (int) $author, $token, $is_public );
	}
}
