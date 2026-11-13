<?php
/**
 * The pending-review label is a setting (REQ-216, REQ-260): the page, the card and the share preview all read it from the
 * settings service, only an Administrator changes it, and a change is audited. Needs the WordPress test suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Core\Review\StatePolicy;
use WhoIsWho\Tests\TestCase;

final class PendingLabelSettingTest extends TestCase {

	private const PROBE = 'wiw_pending_label_audit_probe';

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Capabilities the fake user holds.
	 *
	 * @var string[]
	 */
	private array $held = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		global $wpdb;
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::PENDING_REVIEW_LABEL );
		$this->held = array();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::PENDING_REVIEW_LABEL );
		wp_cache_delete( SettingsService::OPTION_PREFIX . SettingsService::PENDING_REVIEW_LABEL, 'options' );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function service(): SettingsService {
		$audit = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-12 10:00:00', static fn (): int => 9 );
		return new SettingsService( SettingsService::default_registry(), $audit, fn ( string $cap ): bool => in_array( $cap, $this->held, true ) );
	}

	private function pending_profile(): PublicProfile {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Label Setting Org Qwv',
			)
		);
		$this->posts[] = $id;
		( new StateMachine() )->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		return $profile;
	}

	public function test_REQ_216_default_label_is_the_documented_wording(): void {
		$this->assertSame( StatePolicy::PENDING_LABEL, $this->service()->get( SettingsService::PENDING_REVIEW_LABEL ) );
		$this->assertSame( StatePolicy::PENDING_LABEL, $this->pending_profile()->label );
	}

	public function test_REQ_216_label_setting_changes_label_on_page_card_and_share_preview(): void {
		$this->held = array( 'manage_options' );
		$this->service()->set( SettingsService::PENDING_REVIEW_LABEL, 'Draft from public sources. Not yet reviewed.', 'Legal wording' );
		$profile = $this->pending_profile();
		$this->assertSame( 'Draft from public sources. Not yet reviewed.', $profile->label, 'page and card read the profile label' );
		$this->assertSame( $profile->label, $profile->share['label'], 'the share preview carries the same label' );
		$this->assertSame( 'Draft from public sources. Not yet reviewed.', PublicProfile::pending_label() );
	}

	public function test_REQ_216_an_empty_label_falls_back_to_the_default(): void {
		update_option( SettingsService::OPTION_PREFIX . SettingsService::PENDING_REVIEW_LABEL, '', false );
		$this->assertSame( StatePolicy::PENDING_LABEL, PublicProfile::pending_label() );
	}

	public function test_REQ_216_label_setting_requires_administrator(): void {
		$service = $this->service();
		try {
			$service->set( SettingsService::PENDING_REVIEW_LABEL, 'Sneaky' );
			$this->fail( 'A user without the capability changed the label.' );
		} catch ( \DomainException $e ) {
			$this->assertSame( StatePolicy::PENDING_LABEL, $service->get( SettingsService::PENDING_REVIEW_LABEL ) );
		}
		$def = SettingsService::default_registry()[ SettingsService::PENDING_REVIEW_LABEL ];
		$this->assertSame( 'manage_options', $def['capability'], 'Administrator-only' );
		$this->assertTrue( $def['audited'] );
	}

	public function test_REQ_260_label_change_is_audited_and_stored_with_autoload_off(): void {
		global $wpdb;
		$this->held = array( 'manage_options' );
		$this->service()->set( SettingsService::PENDING_REVIEW_LABEL, 'New wording', 'Legal asked' );
		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE, ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertCount( 1, $rows );
		$this->assertSame( SettingsService::PENDING_REVIEW_LABEL, $rows[0]['subject_id'] );
		$this->assertSame( 'Legal asked', $rows[0]['reason'] );
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", SettingsService::OPTION_PREFIX . SettingsService::PENDING_REVIEW_LABEL ) ); // phpcs:ignore WordPress.DB
		$this->assertContains( $autoload, array( 'no', 'off' ) );
	}
}
