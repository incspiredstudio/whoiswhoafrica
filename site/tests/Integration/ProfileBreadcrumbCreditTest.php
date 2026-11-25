<?php
/**
 * Profile breadcrumb, classified header and card lines, the compact image credit and the key facts setting (REQ-009, REQ-003,
 * REQ-203, REQ-162, REQ-144, REQ-043). Needs the WordPress test suite and the profile tables.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration;

use WhoIsWho\Core\Directory\ClassificationSeeder;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\ProfileClassifier;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Migrations\Schema;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateMachine;
use WhoIsWho\Tests\TestCase;

final class ProfileBreadcrumbCreditTest extends TestCase {

	private const PROBE = 'wiw_display_audit_probe';

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__ ) . '/../wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		wp_set_current_user( 0 );
		switch_theme( 'whoiswho-child' );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( StateRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( ProvenanceRepository::table(), array( 'profile_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$this->posts = array();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::PROFILE_IMAGE_CREDIT );
		delete_option( SettingsService::OPTION_PREFIX . SettingsService::PROFILE_KEY_FACTS );
		parent::tearDown();
	}

	/**
	 * A tree classification as PublicProfile::classification() returns it.
	 *
	 * @return array<string, mixed>
	 */
	private function classified(): array {
		$country = home_url( '/directory/?country=nigeria' );
		return array(
			'country'  => array(
				'name' => 'Nigeria',
				'slug' => 'nigeria',
				'code' => 'NG',
				'url'  => $country,
			),
			'sector'   => array(
				'name' => 'Financial services',
				'slug' => 'financial-services',
				'url'  => $country . '&industry=financial-services',
			),
			'industry' => array(
				'name' => 'Banking',
				'slug' => 'banking',
				'url'  => $country . '&industry=banking',
			),
			'extra'    => array(),
		);
	}

	/**
	 * Create a public profile with sourced facts, optionally with an image carrying the given credit meta.
	 *
	 * @param string                     $name   Name.
	 * @param array<string, mixed>       $fields Sourced fields.
	 * @param array<string, string>|null $image  Image meta (ImageMeta key => value), or null for no image.
	 */
	private function profile( string $name, array $fields, ?array $image = null ): PublicProfile {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'individual' ) );
		$store = new ProfileFields();
		$prov  = new ProvenanceRepository();
		foreach ( $fields as $key => $value ) {
			$store->set( $id, $key, $value );
			$prov->record(
				$id,
				array(
					'field'        => $key,
					'source_url'   => 'https://www.example.org/entry',
					'retrieved_at' => '2026-11-10T09:00:00Z',
					'confidence'   => 'high',
					'method'       => 'import',
				)
			);
		}
		if ( null !== $image ) {
			update_post_meta( $id, PublicProfile::IMAGE_META, $this->attachment( $image ) );
		}
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		return $profile;
	}

	/**
	 * A small real image attachment with the given credit meta.
	 *
	 * @param array<string, string> $meta Meta.
	 */
	private function attachment( array $meta ): int {
		$dir  = wp_upload_dir();
		$file = $dir['path'] . '/wiw-credit-test-' . bin2hex( random_bytes( 3 ) ) . '.jpg';
		$im   = imagecreatetruecolor( 30, 40 );
		imagejpeg( $im, $file, 80 );
		$id            = (int) wp_insert_attachment(
			array(
				'post_title'     => 'Portrait',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			),
			$file
		);
		$this->posts[] = $id;
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	/**
	 * Render a template part.
	 *
	 * @param string        $part    Part.
	 * @param PublicProfile $profile Profile.
	 */
	private function render( string $part, PublicProfile $profile ): string {
		ob_start();
		get_template_part( $part, null, array( 'profile' => $profile ) );
		return (string) ob_get_clean();
	}

	/**
	 * Set a display setting directly (the service's capability and audit are tested separately).
	 *
	 * @param string $key   Key.
	 * @param string $value Value.
	 */
	private function setting( string $key, string $value ): void {
		update_option( SettingsService::OPTION_PREFIX . $key, $value, false );
	}

	// ---------- breadcrumb ----------

	public function test_REQ_009_breadcrumb_is_country_sector_industry_name_each_linking_to_the_filtered_directory(): void {
		$crumbs = wiw_profile_crumbs( 'Ada Bank', $this->classified() );
		$this->assertSame( array( 'Nigeria', 'Financial services', 'Banking', 'Ada Bank' ), array_column( $crumbs, 'label' ) );
		$this->assertSame( home_url( '/directory/?country=nigeria' ), $crumbs[0]['url'] );
		$this->assertStringEndsWith( '?country=nigeria&industry=financial-services', $crumbs[1]['url'] );
		$this->assertStringEndsWith( '?country=nigeria&industry=banking', $crumbs[2]['url'] );
		$this->assertSame( '', $crumbs[3]['url'], 'the last crumb is the page itself' );
		$this->assertSame( array( false, true, true, false ), array_column( $crumbs, 'mid' ) );
	}

	public function test_REQ_009_unclassified_gives_country_then_name_and_no_country_gives_directory_then_name(): void {
		$unclassified = array_merge(
			$this->classified(),
			array(
				'sector'   => null,
				'industry' => null,
			)
		);
		$this->assertSame( array( 'Nigeria', 'Ada Bank' ), array_column( wiw_profile_crumbs( 'Ada Bank', $unclassified ), 'label' ) );
		$none = wiw_profile_crumbs(
			'Ada Bank',
			array(
				'country'  => null,
				'sector'   => null,
				'industry' => null,
				'extra'    => array(),
			)
		);
		$this->assertSame( array( 'Directory', 'Ada Bank' ), array_column( $none, 'label' ) );
		$this->assertSame( home_url( '/directory/' ), $none[0]['url'] );
	}

	public function test_REQ_043_breadcrumb_markup_is_an_ordered_list_with_aria_current_and_a_decorative_fold_for_phones(): void {
		$html = wiw_profile_crumbs_html( wiw_profile_crumbs( 'Ada <Bank>', $this->classified() ) );
		$this->assertStringContainsString( '<nav class="crumbs crumbs--profile crumbs--foldable" aria-label="Breadcrumb"><ol class="crumbs-list">', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		$this->assertStringContainsString( '<span aria-current="page">Ada &lt;Bank&gt;</span>', $html, 'the name is escaped' );
		$this->assertSame( 2, substr_count( $html, 'class="crumbs-mid"' ), 'sector and industry stay in the markup for screen readers' );
		$this->assertStringContainsString( '<li class="crumbs-fold" aria-hidden="true">', $html, 'the "…" is hidden from assistive technology' );
		$this->assertSame( 3, substr_count( $html, '<a href=' ) );
		$short = wiw_profile_crumbs_html( wiw_profile_crumbs( 'Ada Bank', array( 'country' => null ) ) );
		$this->assertStringNotContainsString( 'crumbs-fold', $short, 'nothing to fold without middle crumbs' );
		$this->assertStringNotContainsString( 'crumbs--foldable', $short );
	}

	public function test_REQ_009_country_flags_come_from_the_theme_for_valid_codes_only(): void {
		$this->assertStringEndsWith( '/assets/flags/ng.svg', wiw_country_flag_url( 'NG' ) );
		$this->assertStringEndsWith( '/assets/flags/za.svg', wiw_country_flag_url( ' za ' ) );
		$this->assertSame( '', wiw_country_flag_url( 'PAN' ), 'Pan-African has no flag' );
		$this->assertSame( '', wiw_country_flag_url( 'FR' ), 'only African flags ship with the theme' );
		$this->assertSame( '', wiw_country_flag_url( '../x' ) );
		$this->assertSame( '', wiw_country_flag_url( '' ) );
	}

	public function test_REQ_043_the_breadcrumb_separator_is_a_decorative_chevron(): void {
		$html = wiw_profile_crumbs_html( wiw_profile_crumbs( 'Ada Bank', $this->classified() ) );
		$this->assertStringNotContainsString( '›', $html );
		$this->assertSame( 4, substr_count( $html, '<svg class="crumbs-sep" viewBox="0 0 16 16" aria-hidden="true" focusable="false">' ), 'three between the crumbs and one before the phone "…"' );
		$kept = wp_kses( $html, wiw_crumbs_kses() );
		$this->assertStringContainsString( 'class="crumbs-sep"', $kept, 'the chevron survives the allowlist' );
		$this->assertStringContainsString( '<path d="m6 3 5 5-5 5"', $kept );
	}

	public function test_REQ_009_the_header_renders_the_breadcrumb_from_the_read_model_and_no_old_text_meta(): void {
		$profile = $this->profile( 'Crumbtest Person', array( 'current_title' => 'Governor' ) );
		update_post_meta( $profile->id, 'wiw_industry', 'Oldtext Industry' );
		update_post_meta( $profile->id, 'wiw_hq_city', 'Oldtext City' );
		$out = $this->render( 'template-parts/profile-head', $profile );
		$this->assertStringContainsString( 'crumbs--profile', $out );
		$this->assertStringContainsString( '<span aria-current="page">Crumbtest Person</span>', $out );
		$this->assertStringNotContainsString( 'Oldtext', $out );
		$this->assertStringContainsString( 'Governor', $out );
	}

	public function test_REQ_009_classified_profile_header_and_card_use_the_tree(): void {
		( new ClassificationSeeder() )->seed();
		$profile = $this->profile( 'Crumbtest Classified', array( 'current_title' => 'Founder' ) );
		( new ProfileClassifier() )->assign(
			$profile->id,
			'individual',
			array(
				'primary_industry' => 'banking',
				'country'          => 'NG',
			)
		);
		$fresh = PublicProfile::find_by_id( $profile->id );
		$this->assertNotNull( $fresh );
		$head = $this->render( 'template-parts/profile-head', $fresh );
		$this->assertMatchesRegularExpression( '/>Nigeria<.*>Financial Services<.*>Banking<.*aria-current="page">Crumbtest Classified</s', $head );
		$this->assertStringContainsString( 'crumbs--foldable', $head );
		$this->assertStringContainsString( 'directory/?country=nigeria&amp;industry=banking"', $head );
		$this->assertMatchesRegularExpression( '#<a href="[^"]+"><img class="crumbs-flag" src="[^"]+/assets/flags/ng\.svg" alt="" width="20" height="15"[^>]*>Nigeria</a>#', $head, 'the country crumb shows its flag, decorative, before the name' );
		$this->assertStringContainsString( 'Nigeria', $head, 'the place line names the country' );
		$card = $this->render( 'template-parts/profile-card', $fresh );
		$this->assertStringContainsString( '<p class="pcard-kick">Banking</p>', $card );
	}

	public function test_REQ_009_unclassified_profile_shows_country_then_name_and_an_organisation_type_role(): void {
		( new ClassificationSeeder() )->seed();
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => 'Crumbtest Unclassified Org',
			)
		);
		$this->posts[] = $id;
		$machine       = new StateMachine();
		$machine->enter( $id, PubState::PUBLISHED_PENDING, array( 'entity_type' => 'organisation' ) );
		( new ProfileClassifier() )->assign(
			$id,
			'organisation',
			array(
				'primary_industry' => '',
				'country'          => 'GH',
				'org_type'         => 'ngo',
			)
		);
		$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', 1 );
		$profile = PublicProfile::find_by_id( $id );
		$this->assertNotNull( $profile );
		$head = $this->render( 'template-parts/profile-head', $profile );
		$this->assertMatchesRegularExpression( '/>Ghana<\/a><\/li><li><svg class="crumbs-sep"[^>]*>.*?<\/svg><span aria-current="page">Crumbtest Unclassified Org</s', $head );
		$this->assertStringNotContainsString( 'crumbs-fold', $head );
		$this->assertStringNotContainsString( 'Unclassified<', $head, 'unclassified is never shown as an industry' );
		$type = $profile->org_type();
		$this->assertNotNull( $type );
		$this->assertStringContainsString( '<p class="profile-role">' . esc_html( $type['name'] ), $head );
		$card = $this->render( 'template-parts/profile-card', $profile );
		$this->assertStringNotContainsString( 'pcard-kick', $card );
		$this->assertStringContainsString( esc_html( $type['name'] ), $card );
	}

	public function test_REQ_009_the_directory_industry_filter_groups_industries_under_selectable_sectors_without_unclassified(): void {
		( new ClassificationSeeder() )->seed();
		$groups = wiw_directory_filter_options( 'industry' );
		$this->assertArrayHasKey( 'group:financial-services', $groups );
		$this->assertArrayNotHasKey( 'unclassified', $groups );
		$fin = $groups['group:financial-services'];
		$this->assertIsArray( $fin );
		$this->assertSame( 'Financial Services', $fin['label'] );
		$this->assertSame( 'financial-services', array_key_first( $fin['options'] ), 'the sector itself is the first choice' );
		$this->assertArrayHasKey( 'banking', $fin['options'] );
		foreach ( $groups as $group ) {
			$this->assertIsArray( $group );
			$this->assertArrayNotHasKey( 'unclassified', $group['options'] );
		}
		ob_start();
		get_template_part(
			'template-parts/filter-bar',
			null,
			array(
				'action'   => home_url( '/directory/' ),
				'controls' => array(
					array(
						'name'    => 'industry',
						'label'   => 'Industry',
						'blank'   => 'All industries',
						'options' => $groups,
						'value'   => 'banking',
					),
				),
			)
		);
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '<optgroup label="Financial Services">', $html );
		$this->assertMatchesRegularExpression( '/<option value="banking"\s+selected=.selected.>Banking<\/option>/', $html );
		$this->assertStringContainsString( '<option value="financial-services"', $html );
		$this->assertStringNotContainsString( 'value="unclassified"', $html );
	}

	public function test_REQ_009_the_industries_index_groups_industries_under_their_sector(): void {
		$rows    = array(
			array(
				'term_id' => 2,
				'slug'    => 'energy',
				'name'    => 'Energy',
				'count'   => 3,
				'parent'  => 0,
			),
			array(
				'term_id' => 3,
				'slug'    => 'mining',
				'name'    => 'Mining',
				'count'   => 1,
				'parent'  => 2,
			),
			array(
				'term_id' => 1,
				'slug'    => 'banks',
				'name'    => 'Banks',
				'count'   => 0,
				'parent'  => 5,
			),
			array(
				'term_id' => 4,
				'slug'    => 'gas',
				'name'    => 'Gas',
				'count'   => 2,
				'parent'  => 2,
			),
			array(
				'term_id' => 5,
				'slug'    => 'finance',
				'name'    => 'Finance',
				'count'   => 0,
				'parent'  => 0,
			),
		);
		$sectors = wiw_industry_sectors( $rows );
		$this->assertSame( array( 'Energy', 'Finance' ), array_column( $sectors, 'name' ) );
		$this->assertSame( array( 'Gas', 'Mining' ), array_column( $sectors[0]['industries'], 'name' ) );
		$this->assertSame( 3, $sectors[0]['count'] );
		$this->assertSame( array( 'Banks' ), array_column( $sectors[1]['industries'], 'name' ) );
	}

	// ---------- key facts rows ----------

	public function test_REQ_009_key_fact_rows_show_the_classified_values_with_the_fact_source_never_the_old_text(): void {
		$source = array(
			'source_url'   => 'https://www.example.org/entry',
			'retrieved_at' => '2026-11-10T09:00:00Z',
		);
		$facts  = array(
			'industry'  => array( 'value' => 'banks and stuff' ) + $source,
			'sector'    => array( 'value' => 'money' ) + $source,
			'org_type'  => array( 'value' => 'plc' ) + $source,
			'hq_city'   => array( 'value' => 'lagos island' ) + $source,
			'regulator' => array( 'value' => 'Central Bank' ) + $source,
		);
		$rows   = wiw_key_fact_rows(
			$facts,
			$this->classified(),
			'Public company',
			array(
				'city'    => 'Lagos',
				'country' => 'Nigeria',
			)
		);
		$this->assertSame( array( 'Sector', 'Industry', 'Organisation type', 'City', 'Regulator' ), array_column( $rows, 'label' ) );
		$this->assertSame( array( 'Financial services', 'Banking', 'Public company', 'Lagos', 'Central Bank' ), array_column( $rows, 'value' ) );
		$this->assertSame( 'example.org', $rows[0]['source_host'] );
		$bare = wiw_key_fact_rows(
			$facts,
			array(
				'country'  => null,
				'sector'   => null,
				'industry' => null,
			),
			'',
			array(
				'city'    => '',
				'country' => '',
			)
		);
		$this->assertSame( array( 'Regulator' ), array_column( $bare, 'label' ), 'an unclassified profile shows no industry, sector, type or city row' );
	}

	public function test_REQ_144_a_staff_edit_source_is_plain_text_never_a_link(): void {
		$facts = array(
			'current_title'        => array(
				'value'        => 'Governor',
				'source_url'   => 'Staff edit',
				'retrieved_at' => '2026-11-14T09:00:00Z',
			),
			'current_organisation' => array(
				'value'        => 'Central Bank',
				'source_url'   => 'javascript:alert(1)',
				'retrieved_at' => '2026-11-14T09:00:00Z',
			),
		);
		$rows  = wiw_key_fact_rows(
			$facts,
			array(),
			'',
			array(
				'city'    => '',
				'country' => '',
			)
		);
		$this->assertCount( 2, $rows );
		$this->assertSame( '', $rows[0]['source_url'] );
		$this->assertSame( '', $rows[0]['source_host'], 'no host is made up from the text' );
		$this->assertSame( 'Staff edit', $rows[0]['source_label'] );
		$this->assertSame( '', $rows[1]['source_url'], 'a non-http address is never linked' );

		$this->setting( 'profile_key_facts', 'show' );
		$profile = $this->profile( 'Factsmode Staff', array( 'current_title' => 'Governor' ) );
		( new ProvenanceRepository() )->record(
			$profile->id,
			array(
				'field'        => 'current_title',
				'source_url'   => 'Staff edit',
				'retrieved_at' => '2026-11-14T09:00:00Z',
				'confidence'   => 'medium',
				'method'       => 'manual',
			)
		);
		$fresh = PublicProfile::find_by_id( $profile->id );
		$this->assertNotNull( $fresh );
		$out = $this->render( 'template-parts/profile-facts', $fresh );
		$this->assertStringContainsString( 'Staff edit', $out );
		$this->assertStringNotContainsString( '<a href', $out, 'the staff edit source is not a link' );
	}

	// ---------- key facts setting ----------

	public function test_REQ_144_key_facts_are_hidden_by_default_with_no_heading_or_anchor(): void {
		$profile = $this->profile( 'Factsmode Hidden', array( 'current_organisation' => 'Central Bank' ) );
		$this->assertSame( 'hidden', wiw_profile_key_facts_mode() );
		$this->assertSame( '', trim( $this->render( 'template-parts/profile-facts', $profile ) ) );
		ob_start();
		set_query_var( 'wiw_profile_id', $profile->id );
		include get_template_directory() . '/templates/profile.php';
		$page = (string) ob_get_clean();
		set_query_var( 'wiw_profile_id', 0 );
		$this->assertStringNotContainsString( 'key-facts', $page );
		$this->assertStringNotContainsString( 'Key facts and sources', $page );
	}

	public function test_REQ_144_show_renders_the_section_and_collapsed_renders_a_closed_details_at_the_foot(): void {
		$profile = $this->profile( 'Factsmode Shown', array( 'current_organisation' => 'Central Bank' ) );
		$this->setting( 'profile_key_facts', 'show' );
		$shown = $this->render( 'template-parts/profile-facts', $profile );
		$this->assertStringContainsString( '<section id="key-facts" aria-labelledby="key-facts-h">', $shown );
		$this->assertStringContainsString( 'Central Bank', $shown );
		$this->setting( 'profile_key_facts', 'collapsed' );
		$folded = $this->render( 'template-parts/profile-facts', $profile );
		$this->assertStringContainsString( '<details id="key-facts" class="facts-fold">', $folded );
		$this->assertStringNotContainsString( '<details id="key-facts" class="facts-fold" open', $folded, 'closed by default' );
		$this->assertStringContainsString( 'Key facts and sources', $folded );
		$this->assertStringContainsString( 'Central Bank', $folded );
		$this->assertStringNotContainsString( '<h2', $folded );
		ob_start();
		set_query_var( 'wiw_profile_id', $profile->id );
		include get_template_directory() . '/templates/profile.php';
		$page = (string) ob_get_clean();
		set_query_var( 'wiw_profile_id', 0 );
		$this->assertSame( 1, substr_count( $page, 'id="key-facts"' ) );
		$this->assertGreaterThan( strpos( $page, 'related-stories' ) ?: 0, strpos( $page, 'facts-fold' ), 'the folded facts sit at the foot' );
		$this->setting( 'profile_key_facts', 'nonsense' );
		$this->assertSame( 'hidden', wiw_profile_key_facts_mode(), 'an unknown stored value falls back to the default' );
	}

	// ---------- image credit ----------

	public function test_REQ_203_a_trailing_licence_label_is_stripped_from_the_credit_text(): void {
		$cases = array(
			'Jane Doe, CC BY 4.0'          => 'Jane Doe',
			'Jane Doe / CC-BY-SA-4.0'      => 'Jane Doe',
			'Jane Doe (CC BY-SA 3.0)'      => 'Jane Doe',
			'Jean-Luc Mbeki - CC0'         => 'Jean-Luc Mbeki',
			'Studio Lagos · Public domain' => 'Studio Lagos',
			'Jane Doe'                     => 'Jane Doe',
			'Ccby Photos Ltd'              => 'Ccby Photos Ltd',
			'CC BY 4.0'                    => '',
		);
		foreach ( $cases as $in => $out ) {
			$this->assertSame( $out, wiw_credit_text( $in ), $in );
		}
		$html = wiw_profile_credit_html(
			array(
				'kind'        => 'photo',
				'credit'      => 'Jane Doe, CC BY 4.0',
				'licence'     => 'CC-BY-4.0',
				'licence_url' => 'https://creativecommons.org/licenses/by/4.0/',
				'source_url'  => 'https://commons.wikimedia.org/wiki/File:X.jpg',
			)
		);
		$this->assertSame( 1, substr_count( $html, 'CC BY 4.0' ), 'the licence shows once (issue 829)' );
		$this->assertStringContainsString( 'Photo:', $html );
		$this->assertStringContainsString( 'rel="license nofollow noopener"', $html );
	}

	public function test_REQ_203_credit_mode_follows_the_setting_and_hides_only_images_that_need_no_attribution(): void {
		$by   = array(
			'credit'  => 'Jane Doe',
			'licence' => 'CC-BY-4.0',
		);
		$cc0  = array(
			'credit'  => 'Jane Doe',
			'licence' => 'CC0-1.0',
		);
		$pdm  = array(
			'credit'  => 'Archive',
			'licence' => 'PDM-1.0',
		);
		$own  = array(
			'credit'  => 'Supplied by the owner',
			'licence' => '',
		);
		$bysa = array(
			'credit'  => 'Jane Doe',
			'licence' => 'CC-BY-SA-4.0',
		);
		$this->assertSame( 'compact', wiw_profile_credit_mode( $by ), 'compact is the default' );
		$this->setting( 'profile_image_credit', 'full' );
		$this->assertSame( 'full', wiw_profile_credit_mode( $cc0 ) );
		$this->setting( 'profile_image_credit', 'hidden_when_not_required' );
		$this->assertSame( 'hidden', wiw_profile_credit_mode( $cc0 ) );
		$this->assertSame( 'hidden', wiw_profile_credit_mode( $pdm ) );
		$this->assertSame( 'hidden', wiw_profile_credit_mode( $own ) );
		$this->assertSame( 'compact', wiw_profile_credit_mode( $by ), 'CC BY keeps the compact credit' );
		$this->assertSame( 'compact', wiw_profile_credit_mode( $bysa ), 'CC BY-SA keeps the compact credit' );
		$this->assertSame( 'hidden', wiw_profile_credit_mode( null ) );
	}

	public function test_REQ_203_compact_credit_is_an_i_button_over_the_photo_and_an_image_credits_line_at_the_foot(): void {
		$profile = $this->profile(
			'Credittest Person',
			array(),
			array(
				ImageMeta::KIND        => 'photo',
				ImageMeta::ATTRIBUTION => 'Jane Doe, CC BY 4.0',
				ImageMeta::LICENCE     => 'CC-BY-4.0',
				ImageMeta::LICENCE_URL => 'https://creativecommons.org/licenses/by/4.0/',
				ImageMeta::SOURCE_URL  => 'https://commons.wikimedia.org/wiki/File:X.jpg',
			)
		);
		$head    = $this->render( 'template-parts/profile-head', $profile );
		$this->assertStringContainsString( 'class="credit-toggle" aria-expanded="false" aria-controls="profile-credit-pop"', $head );
		$this->assertStringContainsString( '<span class="sr">Photo credit</span>', $head, 'the button has an accessible name' );
		$this->assertStringContainsString( '<p class="credit-pop" id="profile-credit-pop" hidden>', $head );
		$this->assertStringNotContainsString( 'class="profile-credit small"', $head );
		$foot = wiw_profile_foot_credit( $profile );
		$this->assertStringContainsString( 'Image credits', $foot );
		$this->assertSame( 1, substr_count( $foot, 'CC BY 4.0' ) );
		$this->assertStringContainsString( 'href="https://commons.wikimedia.org/wiki/File:X.jpg"', $foot );

		$this->setting( 'profile_image_credit', 'full' );
		$full = $this->render( 'template-parts/profile-head', $profile );
		$this->assertStringContainsString( 'class="profile-credit small"', $full );
		$this->assertStringNotContainsString( 'credit-toggle', $full );
		$this->assertSame( '', wiw_profile_foot_credit( $profile ), 'the full line needs no foot line' );
	}

	public function test_REQ_203_hidden_when_not_required_hides_a_cc0_credit_everywhere(): void {
		$profile = $this->profile(
			'Credittest Cc0 Person',
			array(),
			array(
				ImageMeta::CREATOR => 'Jane Doe',
				ImageMeta::LICENCE => 'CC0-1.0',
			)
		);
		$this->setting( 'profile_image_credit', 'hidden_when_not_required' );
		$head = $this->render( 'template-parts/profile-head', $profile );
		$this->assertStringNotContainsString( 'credit-toggle', $head );
		$this->assertStringNotContainsString( 'profile-credit', $head );
		$this->assertStringNotContainsString( 'Jane Doe', $head );
		$this->assertSame( '', wiw_profile_foot_credit( $profile ) );
	}

	// ---------- settings ----------

	public function test_REQ_144_REQ_203_the_two_settings_are_registered_with_their_defaults_and_choices(): void {
		$registry = SettingsService::default_registry();
		$this->assertSame( 'compact', $registry['profile_image_credit']['default'] );
		$this->assertSame( array( 'full', 'compact', 'hidden_when_not_required' ), $registry['profile_image_credit']['choices'] );
		$this->assertSame( 'hidden', $registry['profile_key_facts']['default'] );
		$this->assertSame( array( 'show', 'collapsed', 'hidden' ), $registry['profile_key_facts']['choices'] );
		foreach ( array( 'profile_image_credit', 'profile_key_facts' ) as $key ) {
			$this->assertSame( 'manage_options', $registry[ $key ]['capability'] );
			$this->assertTrue( $registry[ $key ]['audited'] );
		}
	}

	public function test_REQ_144_changes_are_admin_only_audited_and_limited_to_the_choices(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::PROBE ); // phpcs:ignore WordPress.DB
		Schema::create_table( self::PROBE, AuditLogger::TABLE_DEFINITION );
		$held    = array();
		$audit   = new AuditLogger( new WpdbConnection( self::PROBE ), static fn (): string => '2026-11-14 10:00:00', static fn (): int => 9 );
		$service = new SettingsService(
			SettingsService::default_registry(),
			$audit,
			static function ( string $cap ) use ( &$held ): bool {
				return in_array( $cap, $held, true );
			}
		);
		try {
			$service->set( 'profile_key_facts', 'show' );
			$this->fail( 'A user without the capability changed the setting.' );
		} catch ( \DomainException $e ) {
			$this->assertSame( 'hidden', $service->get( 'profile_key_facts' ) );
		}
		$held = array( 'manage_options' );
		try {
			$service->set( 'profile_key_facts', 'sometimes' );
			$this->fail( 'A value outside the choices was accepted.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'hidden', $service->get( 'profile_key_facts' ) );
		}
		wiw_save_profile_display(
			new Request(
				array(
					'profile_key_facts'    => 'collapsed',
					'profile_image_credit' => 'not-a-mode',
				)
			),
			$service
		);
		$this->assertSame( 'collapsed', $service->get( 'profile_key_facts' ) );
		$this->assertSame( 'compact', $service->get( 'profile_image_credit' ), 'an unknown value is ignored' );
		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::PROBE . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertCount( 1, $rows );
		$this->assertSame( 'settings.change', $rows[0]['action'] );
		$this->assertSame( 'profile_key_facts', $rows[0]['subject_id'] );
	}

	public function test_REQ_144_the_theme_settings_screen_offers_both_settings_only_to_an_administrator(): void {
		$current = array(
			'profile_image_credit' => 'compact',
			'profile_key_facts'    => 'hidden',
		);
		$form    = wiw_theme_profile_settings_html( $current, true, 'n0nce' );
		$this->assertStringContainsString( 'name="action" value="wiw_save_profile_display"', $form );
		$this->assertStringContainsString( 'name="_wpnonce" value="n0nce"', $form );
		$this->assertSame( 3, substr_count( $form, 'name="profile_image_credit"' ) );
		$this->assertSame( 3, substr_count( $form, 'name="profile_key_facts"' ) );
		$this->assertStringContainsString( 'id="wiw-profile_image_credit-compact" name="profile_image_credit" value="compact" checked="checked"', $form );
		$this->assertStringContainsString( 'id="wiw-profile_key_facts-hidden" name="profile_key_facts" value="hidden" checked="checked"', $form );
		$this->assertStringContainsString( '<legend>', $form );
		$kept = wp_kses( $form, wiw_theme_settings_kses() );
		$this->assertStringContainsString( 'type="radio"', $kept, 'the allowlist keeps the radios' );
		$this->assertStringContainsString( 'checked="checked"', $kept );
		$readonly = wiw_theme_profile_settings_html( $current, false, 'n0nce' );
		$this->assertStringNotContainsString( '<form', $readonly );
		$this->assertStringContainsString( 'Only an Administrator can change this.', $readonly );
	}
}
