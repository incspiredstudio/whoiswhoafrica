<?php
/**
 * The firewall between Commerce and everything the public trusts (REQ-199, REQ-184, REQ-183). The architecture rules
 * (tests/Arch) already forbid the references; these tests add the behaviour: creating, editing, scheduling, ending or cancelling
 * a deal changes no profile state, no profile meta, no nomination and no search row. Tolerates the Awards module growing.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\ArrangementRepository;
use WhoIsWho\Core\Commerce\CustomerRepository;
use WhoIsWho\Core\Commerce\DealService;
use WhoIsWho\Core\Commerce\PlacementRepository;
use WhoIsWho\Core\Commerce\PlacementService;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Capabilities\CapabilityCatalogue;
use WhoIsWho\Core\Platform\Capabilities\RoleSync;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Tests\Arch\Rules\AwardsNeverReferencesCommerce;
use WhoIsWho\Tests\Arch\Rules\CommerceNeverWritesClaimedOrFeatured;
use WhoIsWho\Tests\Arch\Support\SourceSet;
use WhoIsWho\Tests\TestCase;

final class CommerceFirewallTest extends TestCase {

	private const PROBE = 'wiw_firewall_audit_probe';

	/**
	 * Tables the money side must never change.
	 */
	private const GUARDED = array( 'wiw_profile_state', 'wiw_nominations', 'wiw_profile_search', 'wiw_editions', 'wiw_award_categories' );

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		( new RoleSync( new CapabilityCatalogue() ) )->activate();
		foreach ( array( CustomerRepository::table(), ArrangementRepository::table(), PlacementRepository::table() ) as $table ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $table ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		}
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function src(): SourceSet {
		return SourceSet::from_directory( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src' );
	}

	/**
	 * Checksums of the guarded tables that exist, plus the profile posts and their meta.
	 *
	 * @return array<string, string>
	 */
	private function snapshot(): array {
		global $wpdb;
		$out = array();
		foreach ( self::GUARDED as $table ) {
			$name = $wpdb->prefix . $table;
			if ( $name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$row           = $wpdb->get_row( 'CHECKSUM TABLE ' . $name, ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed name.
				$out[ $table ] = (string) ( is_array( $row ) ? $row['Checksum'] : '' );
			}
		}
		$out['profile_posts'] = (string) wp_json_encode( $wpdb->get_results( "SELECT ID, post_status, post_modified_gmt FROM {$wpdb->posts} WHERE post_type = 'wiw_profile' ORDER BY ID", ARRAY_A ) ); // phpcs:ignore WordPress.DB
		$out['profile_meta']  = (string) wp_json_encode( $wpdb->get_results( "SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = 'wiw_profile' ORDER BY m.meta_id", ARRAY_A ) ); // phpcs:ignore WordPress.DB
		$out['story_order']   = (string) wp_json_encode( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC, ID DESC" ) ); // phpcs:ignore WordPress.DB
		return $out;
	}

	/**
	 * Run the whole life of a deal as the Administrator and an Editor.
	 */
	private function live_a_deal(): void {
		$make  = function ( string $role ): int {
			$user = wp_insert_user(
				array(
					'user_login' => $role . wp_generate_password( 6, false ),
					'user_pass'  => wp_generate_password(),
					'role'       => $role,
				)
			);
			wp_set_current_user( (int) $user );
			return (int) $user;
		};
		$clock = static fn (): \DateTimeImmutable => new \DateTimeImmutable( '2026-11-12 10:00:00', new \DateTimeZone( 'UTC' ) );
		$admin = $make( 'administrator' );
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', static fn (): int => $admin );
		$deals = new DealService( new ArrangementRepository(), $audit, $clock );
		$cust  = ( new CustomerRepository() )->insert( array( 'name' => 'Obi Holdings' ), null, $clock() );
		$deal  = $deals->create(
			array(
				'customer_id'  => $cust,
				'amount_minor' => 100,
			)
		);
		$deals->record_payment(
			$deal,
			array(
				'method'      => 'transfer',
				'reference'   => 'TRX-5',
				'received_on' => '2026-11-10',
			)
		);
		$deals->update( $deal, array( 'amount_minor' => 250 ) );
		$story = (int) wp_insert_post(
			array(
				'post_title'  => 'Sponsored story',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		$make( 'wiw_editor' );
		$places = new PlacementService( new ArrangementRepository(), new PlacementRepository(), new CustomerRepository(), $audit, $clock );
		$places->choose_story( $deal, $story );
		$result = $places->schedule( $deal, 'both', '2026-12-01', '2026-12-31' );
		$places->end_now( $result['placement_id'] );
		wp_set_current_user( $admin );
		$deals->cancel( $deal );
	}

	public function test_REQ_199_awards_module_has_no_reference_to_commerce(): void {
		$this->assertSame( array(), ( new AwardsNeverReferencesCommerce() )->violations( $this->src() ) );
	}

	public function test_REQ_184_commerce_never_writes_claimed_or_featured(): void {
		$this->assertSame( array(), ( new CommerceNeverWritesClaimedOrFeatured() )->violations( $this->src() ) );
	}

	public function test_REQ_184_commercial_change_never_alters_a_profile(): void {
		$profile = (int) wp_insert_post(
			array(
				'post_title'  => 'A profile',
				'post_status' => 'draft',
				'post_type'   => 'wiw_profile',
			)
		);
		update_post_meta( $profile, 'wiw_example', 'x' );
		$before = $this->snapshot();
		$this->live_a_deal();
		$after = $this->snapshot();
		unset( $before['story_order'], $after['story_order'] );
		$this->assertSame( $before, $after, 'No profile, state row, nomination or search row changed.' );
	}

	public function test_REQ_183_deal_changes_leave_search_order_unchanged(): void {
		$old = (int) wp_insert_post(
			array(
				'post_title'  => 'Older story',
				'post_status' => 'publish',
				'post_type'   => 'post',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);
		$new = (int) wp_insert_post(
			array(
				'post_title'  => 'Newer story',
				'post_status' => 'publish',
				'post_type'   => 'post',
				'post_date'   => '2026-02-01 10:00:00',
			)
		);
		$this->assertGreaterThan( 0, $old + $new );
		$before = $this->snapshot();
		$this->live_a_deal();
		$after = $this->snapshot();
		$ids   = json_decode( $after['story_order'], true );
		$this->assertIsArray( $ids );
		$this->assertSame( array( $new, $old ), array_values( array_intersect( array_map( 'intval', $ids ), array( $new, $old ) ) ), 'The two unsponsored stories keep their order.' );
		$this->assertSame( $before['wiw_profile_search'] ?? '', $after['wiw_profile_search'] ?? '' );
	}

	public function test_REQ_199_no_commercial_field_in_awards_screens_or_exports(): void {
		$root  = dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src/Awards';
		$words = array( 'wiw_arrangements', 'wiw_placements', 'wiw_customers', 'amount_minor', 'ArrangementRepository', 'DealService', 'PlacementRepository', 'CustomerRepository' );
		$found = array();
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( ! $file instanceof \SplFileInfo || 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			foreach ( $words as $word ) {
				if ( str_contains( $code, $word ) ) {
					$found[] = $file->getFilename() . ':' . $word;
				}
			}
		}
		$this->assertSame( array(), $found );
	}
}
