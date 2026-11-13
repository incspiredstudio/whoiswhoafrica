<?php
/**
 * The front page view model (REQ-264, REQ-198, REQ-001). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Home;

use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Content\Curation\ProfileCuration;
use WhoIsWho\Core\Content\Curation\StoryCuration;
use WhoIsWho\Core\Content\Home\CategoriesProvider;
use WhoIsWho\Core\Content\Home\HomeData;
use WhoIsWho\Core\Content\Home\NomineesProvider;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Tests\WpTestCase;

final class HomeDataTest extends WpTestCase {

	/**
	 * Home data with the given pieces.
	 *
	 * @param callable|null           $events     Events.
	 * @param NomineesProvider|null   $nominees   Nominees.
	 * @param CategoriesProvider|null $categories Categories.
	 */
	private function data( ?callable $events = null, ?NomineesProvider $nominees = null, ?CategoriesProvider $categories = null ): HomeData {
		$settings = new CurationSettings( AuditLogger::for_site() );
		return new HomeData( new ProfileCuration( $settings ), new StoryCuration( $settings ), $events ?? static fn (): array => array(), $nominees, $categories );
	}

	public function test_REQ_264_home_events_empty_when_none_upcoming(): void {
		$this->assertSame( array(), $this->data()->view()['events'] );
		$real = new HomeData( new ProfileCuration( new CurationSettings( AuditLogger::for_site() ) ), new StoryCuration( new CurationSettings( AuditLogger::for_site() ) ) );
		$this->assertSame( array(), $real->view()['events'], 'No published events means an empty list.' );
	}

	public function test_REQ_264_home_shows_at_most_the_next_three_events_and_an_all_events_link(): void {
		$asked  = 0;
		$events = static function ( int $count ) use ( &$asked ): array {
			$asked = $count;
			return array(
				array( 'title' => 'One' ),
				array( 'title' => 'Two' ),
				array( 'title' => 'Three' ),
				array( 'title' => 'Four' ),
			);
		};
		$view   = $this->data( $events )->view();
		$this->assertSame( 3, $asked );
		$this->assertCount( 3, $view['events'] );
		$this->assertStringEndsWith( '/events/', $view['events_url'] );
	}

	public function test_REQ_198_nominees_block_absent_before_publication(): void {
		$this->assertNull( $this->data()->view()['nominees'], 'No provider: absent.' );

		$empty = new class() implements NomineesProvider {
			/**
			 * Nothing published.
			 *
			 * @return array<int, array<string, mixed>>
			 */
			public function nominees(): array {
				return array();
			}
		};
		$this->assertNull( $this->data( null, $empty )->view()['nominees'], 'A provider with nothing published: absent, not empty.' );

		$live = new class() implements NomineesProvider {
			/**
			 * One Live nominee.
			 *
			 * @return array<int, array<string, mixed>>
			 */
			public function nominees(): array {
				return array( array( 'name' => 'Ada' ) );
			}
		};
		$this->assertSame( array( array( 'name' => 'Ada' ) ), $this->data( null, $live )->view()['nominees'] );
	}

	public function test_REQ_264_marquee_returns_category_names_as_text_only(): void {
		$this->assertNull( $this->data()->view()['marquee'], 'Absent until the categories provider exists.' );
		$names = new class() implements CategoriesProvider {
			/**
			 * Names with markup in one.
			 *
			 * @return array<int, string>
			 */
			public function names(): array {
				return array( 'Technology', '<b>Leadership</b>', '  ' );
			}
		};
		$this->assertSame( array( 'Technology', 'Leadership' ), $this->data( null, null, $names )->view()['marquee'] );
	}

	public function test_REQ_001_home_view_model_has_no_awards_band_data(): void {
		foreach ( array_keys( $this->data()->view() ) as $key ) {
			$this->assertDoesNotMatchRegularExpression( '/award|band|stage|button/i', $key );
		}
	}
}
