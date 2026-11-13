<?php
/**
 * The theme-facing Awards helpers: the stage, the winners data for the address and the categories (REQ-259, REQ-246, REQ-250).
 * The winners page, the Nominate page and the nomination form read only these, never the tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\WinnersQuery;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\WpTestCase;

final class AwardsThemeHelpersTest extends WpTestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
	}

	protected function setUp(): void {
		parent::setUp();
		require_once dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Awards/functions.php';
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED );
	}

	public function test_REQ_259_stage_helper_follows_the_switch_for_the_nominate_page_and_nav(): void {
		$closed = wiw_awards_stage();
		$this->assertFalse( $closed['open'] );
		$this->assertSame( 'Nominations closed', $closed['nav_label'] );
		$this->assertNull( $closed['nominate_url'] );
		$this->assertSame( 'closed', $closed['stage'] );

		update_option( SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED, '1' );
		$open = wiw_awards_stage();
		$this->assertTrue( $open['open'] );
		$this->assertSame( 'Nominate', $open['nav_label'] );
		$this->assertSame( home_url( '/nominate/' ), $open['nominate_url'] );
		$this->assertSame( 'Nominate someone', $open['label'] );
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED );
	}

	public function test_REQ_246_winners_helper_reads_filters_from_the_address_and_builds_urls(): void {
		$request = new Request(
			array(
				'year'  => '2026',
				'type'  => 'organisation',
				'q'     => 'open data',
				'sort'  => 'name',
				'other' => 'ignored',
			)
		);
		$values  = WinnersQuery::from_request( $request );
		$this->assertSame( 'organisation', WinnersQuery::params( $values )['type'] );
		$this->assertSame( 2026, WinnersQuery::params( $values )['year'] );
		$this->assertArrayNotHasKey( 'other', $values, 'only the known filters are read' );

		$this->assertSame( home_url( '/awards/winners/' ), wiw_awards_winners_url() );
		$url = wiw_awards_winners_url( $values );
		$this->assertStringStartsWith( home_url( '/awards/winners/' ) . '?', $url );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $back );
		$this->assertSame( WinnersQuery::params( $values ), WinnersQuery::params( $back ), 'the address round-trips' );

		$data = wiw_awards_winners();
		foreach ( array( 'items', 'by_year', 'total', 'pages', 'years', 'groups', 'categories', 'countries', 'params' ) as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		$this->assertIsArray( wiw_awards_categories() );
	}
}
