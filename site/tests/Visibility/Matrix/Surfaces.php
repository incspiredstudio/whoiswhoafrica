<?php
/**
 * The list of public surfaces the matrix probes. Adding a surface is one call to add(): a name, whether the surface is
 * open to the public at all, and a probe that says whether an anonymous visitor can reach the fixture there (REQ-208).
 *
 * "Open" surfaces (permalink, shortlink, lists, search) must show a public fixture and hide every other one. "Closed"
 * surfaces (feeds, REST, core sitemaps, oEmbed, attachment and author pages) must show nothing, ever.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility\Matrix;

/**
 * Surface registry.
 */
final class Surfaces {

	/**
	 * Name => array( open, probe ).
	 *
	 * @var array<string, array{0: bool, 1: callable(Fixture, \WP_UnitTestCase): bool}>
	 */
	private static array $surfaces = array();

	/**
	 * Add a surface, one line each.
	 *
	 * @param string                $name  Surface name.
	 * @param bool                  $open  Whether public fixtures may appear here.
	 * @param callable(Fixture, \WP_UnitTestCase):bool $probe Returns true when an anonymous visitor can reach the fixture here.
	 */
	public static function add( string $name, bool $open, callable $probe ): void {
		self::$surfaces[ $name ] = array( $open, $probe );
	}

	/**
	 * Names of every surface.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		self::boot();
		return array_keys( self::$surfaces );
	}

	/**
	 * Whether a surface is open to public content.
	 *
	 * @param string $name Surface name.
	 */
	public static function is_open( string $name ): bool {
		self::boot();
		return self::$surfaces[ $name ][0];
	}

	/**
	 * Run one probe as an anonymous visitor.
	 *
	 * @param string  $name    Surface name.
	 * @param Fixture $fixture Fixture.
	 * @param \WP_UnitTestCase $t Running test case (go_to, factory).
	 */
	public static function reaches( string $name, Fixture $fixture, \WP_UnitTestCase $t ): bool {
		self::boot();
		wp_set_current_user( 0 );
		return (bool) call_user_func( self::$surfaces[ $name ][1], $fixture, $t );
	}

	/**
	 * Whether the main query of the last go_to holds the fixture.
	 *
	 * @param Fixture $f Fixture.
	 */
	private static function main_query_has( Fixture $f ): bool {
		global $wp_query;
		if ( is_404() ) {
			return false;
		}
		$ids = array_map( 'intval', wp_list_pluck( (array) $wp_query->posts, 'ID' ) );
		return in_array( $f->id, $ids, true );
	}

	/**
	 * Register the built-in surfaces once.
	 */
	private static function boot(): void {
		if ( array() !== self::$surfaces ) {
			return;
		}
		$has = array( self::class, 'main_query_has' );

		self::add(
			'permalink',
			true,
			static function ( Fixture $f, \WP_UnitTestCase $t ) use ( $has ): bool {
				$post = get_post( $f->id );
				$t->go_to( get_permalink( $post ) );
				return $has( $f );
			}
		);
		self::add(
			'shortlink',
			true,
			static function ( Fixture $f, \WP_UnitTestCase $t ) use ( $has ): bool {
				$t->go_to( '/?p=' . $f->id );
				return $has( $f );
			}
		);
		self::add(
			'preview',
			true,
			static function ( Fixture $f, \WP_UnitTestCase $t ) use ( $has ): bool {
				$t->go_to( '/?p=' . $f->id . '&preview=true' );
				return $has( $f );
			}
		);
		self::add(
			'home_list',
			true,
			static function ( Fixture $f, \WP_UnitTestCase $t ) use ( $has ): bool {
				$t->go_to( '/' );
				return $has( $f );
			}
		);
		self::add(
			'search',
			true,
			static function ( Fixture $f, \WP_UnitTestCase $t ) use ( $has ): bool {
				$t->go_to( '/?s=' . $f->token );
				return $has( $f );
			}
		);
		self::add(
			'feed',
			false,
			static function ( Fixture $f, \WP_UnitTestCase $t ) use ( $has ): bool {
				$t->go_to( '/?feed=rss2' );
				$refused = false !== has_action( 'do_feed_rss2', array( \WhoIsWho\Guard\Exposure\Exposure::class, 'refuse_feed' ) );
				return ! $refused && $has( $f );
			}
		);
		self::add(
			'core_sitemap',
			false,
			static function ( Fixture $f, \WP_UnitTestCase $t ): bool {
				unset( $f, $t );
				return (bool) apply_filters( 'wp_sitemaps_enabled', true );
			}
		);
		self::add(
			'rest',
			false,
			static function ( Fixture $f, \WP_UnitTestCase $t ): bool {
				unset( $f, $t );
				return ! ( apply_filters( 'rest_authentication_errors', null ) instanceof \WP_Error );
			}
		);
		self::add(
			'oembed',
			false,
			static function ( Fixture $f, \WP_UnitTestCase $t ) use ( $has ): bool {
				$t->go_to( '/?p=' . $f->id . '&embed=true' );
				do_action( 'template_redirect' );
				return $has( $f );
			}
		);
		self::add(
			'attachment_page',
			false,
			static function ( Fixture $f, \WP_UnitTestCase $t ): bool {
				$att = wp_insert_attachment(
					array(
						'post_title'     => 'matrix',
						'post_status'    => 'inherit',
						'post_mime_type' => 'image/jpeg',
					),
					'matrix.jpg',
					$f->id
				);
				$t->go_to( '/?attachment_id=' . $att );
				do_action( 'template_redirect' );
				return ! is_404();
			}
		);
		self::add(
			'author_archive',
			false,
			static function ( Fixture $f, \WP_UnitTestCase $t ): bool {
				$t->go_to( '/?author=' . $f->author );
				do_action( 'template_redirect' );
				return ! is_404();
			}
		);
	}
}
