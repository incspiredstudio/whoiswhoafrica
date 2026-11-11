<?php
/**
 * Home and stories curation settings (REQ-257, REQ-051). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Curation;

use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\TestCase;

final class CurationSettingsTest extends TestCase {

	private const PROBE = 'wiw_curation_audit_probe';

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		foreach ( CurationSettings::BLOCKS as $block ) {
			delete_option( CurationSettings::OPTION_PREFIX . $block );
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $role . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function settings( int $actor ): CurationSettings {
		wp_set_current_user( 0 );
		wp_set_current_user( $actor );
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-11 10:00:00', static fn (): int => $actor );
		return new CurationSettings( $audit, static fn ( string $block, int $id ): bool => $id < 1000 );
	}

	/**
	 * Audit rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public function test_REQ_257_defaults_match_recommended_setup(): void {
		$s   = $this->settings( $this->user( 'wiw_editor' ) );
		$otr = $s->get( CurationSettings::ON_THE_RECORD );
		$this->assertSame( 'pinned_then_fill', $otr['mode'] );
		$this->assertSame( 'random', $otr['fill'] );
		$this->assertSame( array(), $otr['pinned'] );
		$home = $s->get( CurationSettings::STORIES_HOME );
		$this->assertSame( 'latest', $home['mode'] );
		$this->assertTrue( $home['featured_first'] );
		$page = $s->get( CurationSettings::STORIES_PAGE );
		$this->assertSame( 'latest', $page['mode'] );
		$this->assertTrue( $page['featured_first'] );
		$this->assertNotContains( 'nominees', CurationSettings::BLOCKS, 'Nominees have no mode.' );
	}

	public function test_REQ_257_curation_requires_capability_and_is_audited(): void {
		try {
			$this->settings( $this->user( 'wiw_reviewer' ) )->set( CurationSettings::ON_THE_RECORD, array( 'mode' => 'latest' ) );
			$this->fail( 'A reviewer changed curation.' );
		} catch ( \DomainException $e ) {
			$this->assertSame( array(), $this->rows() );
		}
		$editor = $this->user( 'wiw_editor' );
		$s      = $this->settings( $editor );
		$s->set(
			CurationSettings::ON_THE_RECORD,
			array(
				'mode'   => 'manual',
				'count'  => 6,
				'pinned' => array( 5, 3, 5 ),
			),
			'Spring picks'
		);
		$saved = $s->get( CurationSettings::ON_THE_RECORD );
		$this->assertSame( 'manual', $saved['mode'] );
		$this->assertSame( 6, $saved['count'] );
		$this->assertSame( array( 5, 3 ), $saved['pinned'], 'Order kept, duplicates dropped.' );
		$rows = $this->rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'curation.change', $rows[0]['action'] );
		$this->assertSame( CurationSettings::ON_THE_RECORD, $rows[0]['subject_id'] );
		$this->assertSame( (string) $editor, (string) $rows[0]['actor_id'] );
		$this->assertSame( 'Spring picks', $rows[0]['reason'] );
		$this->assertStringContainsString( 'pinned_then_fill', (string) $rows[0]['old_value'] );
		$this->assertStringContainsString( 'manual', (string) $rows[0]['new_value'] );

		$s->set( CurationSettings::ON_THE_RECORD, $saved );
		$this->assertCount( 1, $this->rows(), 'No change, no row.' );
	}

	public function test_REQ_257_a_role_granted_the_capability_can_curate(): void {
		$reviewer = $this->user( 'wiw_reviewer' );
		wp_set_current_user( 0 );
		( new \WP_User( $reviewer ) )->add_cap( CurationSettings::CAP );
		$this->settings( $reviewer )->set( CurationSettings::STORIES_PAGE, array( 'mode' => 'random' ) );
		$this->assertSame( 'random', $this->settings( $reviewer )->get( CurationSettings::STORIES_PAGE )['mode'] );
	}

	public function test_REQ_257_invalid_mode_is_rejected(): void {
		$s          = $this->settings( $this->user( 'wiw_editor' ) );
		$bad_inputs = array(
			array( 'mode' => 'shuffle' ),
			array(
			'mode'  => 'latest',
			'count' => 0,
			), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
			array(
		'mode'  => 'latest',
		'count' => 999,
		), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
			array(
		'mode' => 'pinned_then_fill',
		'fill' => 'manual',
		), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
			array(
		'mode'   => 'manual',
		'pinned' => array( 'abc' ),
		), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
			array(
		'mode'   => 'manual',
		'pinned' => array( -4 ),
		), // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
			array(
		'mode'   => 'manual',
		'pinned' => array( 5000 ),
		), // An id that does not exist. // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		);
		foreach ( $bad_inputs as $input ) {
			try {
				$s->set( CurationSettings::ON_THE_RECORD, $input );
				$this->fail( 'Accepted ' . wp_json_encode( $input ) );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'pinned_then_fill', $s->get( CurationSettings::ON_THE_RECORD )['mode'], 'Nothing was saved.' );
			}
		}
		$this->expectException( \InvalidArgumentException::class );
		$s->set( 'nominees', array( 'mode' => 'latest' ) );
	}
}
