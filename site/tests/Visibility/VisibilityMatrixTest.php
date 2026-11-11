<?php
/**
 * The visibility matrix: one fixture per publication state, requested on every surface (REQ-208, REQ-217, REQ-209).
 * Runs on a bare install now; M1 adds the profile states with FixtureFactory::add_state(), and the matrix runs again
 * after each third-party plugin is added (the `visibility` CI job runs this suite on every plugin, guard or theme change).
 * Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Visibility;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Review\StatePolicy;
use WhoIsWho\Tests\Visibility\Matrix\FixtureFactory;
use WhoIsWho\Tests\Visibility\Matrix\Surfaces;
use WhoIsWho\Tests\WpTestCase;

final class VisibilityMatrixTest extends WpTestCase {

	public function test_REQ_208_visibility_matrix_bare_install(): void {
		$failures = array();
		foreach ( FixtureFactory::states() as $state ) {
			$fixture = FixtureFactory::create( self::factory(), $state );
			foreach ( Surfaces::names() as $surface ) {
				$expected = $fixture->is_public && Surfaces::is_open( $surface );
				$actual   = Surfaces::reaches( $surface, $fixture, $this );
				if ( $expected !== $actual ) {
					$failures[] = sprintf( '%s on %s: expected %s, got %s', $state, $surface, $expected ? 'reachable' : 'closed', $actual ? 'reachable' : 'closed' );
				}
			}
		}
		$this->assertSame( array(), $failures, "Visibility matrix failures:\n" . implode( "\n", $failures ) );
	}

	public function test_REQ_208_the_matrix_can_tell_open_from_closed(): void {
		$published = FixtureFactory::create( self::factory(), 'published' );
		$this->assertTrue( Surfaces::reaches( 'permalink', $published, $this ), 'a published fixture is reachable, so a closed answer for the others is meaningful' );
		$this->assertTrue( Surfaces::reaches( 'search', $published, $this ) );
		$this->assertFalse( Surfaces::reaches( 'rest', $published, $this ) );
	}

	public function test_REQ_217_hidden_fixture_has_no_sitemap_entry_and_is_noindex(): void {
		$hidden = FixtureFactory::create( self::factory(), 'hidden' );
		$this->assertFalse( Surfaces::reaches( 'core_sitemap', $hidden, $this ), 'core sitemaps are off' );

		$this->go_to( '/?p=' . $hidden->id );
		$this->assertTrue( is_404(), 'a hidden record answers 404' );

		$policy = StatePolicy::for_state( PubState::UNPUBLISHED );
		$this->assertTrue( $policy->noindex );
		$this->assertFalse( $policy->in_sitemap );
		$this->assertTrue( StatePolicy::for_state( null )->noindex, 'an unknown state is closed and noindex' );
	}

	public function test_REQ_209_adding_a_surface_is_one_line(): void {
		Surfaces::add(
			'probe_never',
			false,
			static function (): bool {
				return false;
			}
		);
		$this->assertContains( 'probe_never', Surfaces::names() );
		$this->assertFalse( Surfaces::reaches( 'probe_never', FixtureFactory::create( self::factory(), 'draft' ), $this ) );
	}
}
