<?php
/**
 * The classification tree config (REQ-009 as amended 2026-11-14): exactly the sectors, industries and organisation types the
 * acquisition side classifies into (same slugs and labels), no alias used twice in a list, and the shared
 * docs/contracts/classification-tree.json generated from it. Pure logic.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Directory;

use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Tests\TestCase;

final class ClassificationTreeTest extends TestCase {

	/**
	 * The tree as agreed with the acquisition side: sector slug => [label, [industry slug => label]].
	 */
	private const TREE = array(
		'energy-resources'          => array(
			'Energy & Resources',
			array(
				'oil-gas'         => 'Oil & Gas',
				'mining'          => 'Mining',
				'power-utilities' => 'Power & Utilities',
				'renewables'      => 'Renewables',
			),
		),
		'financial-services'        => array(
			'Financial Services',
			array(
				'banking'                     => 'Banking',
				'insurance'                   => 'Insurance',
				'investment-asset-management' => 'Investment & Asset Management',
				'fintech'                     => 'Fintech',
				'central-banking-regulation'  => 'Central Banking & Regulation',
			),
		),
		'technology-telecoms'       => array(
			'Technology & Telecoms',
			array(
				'telecoms'             => 'Telecoms',
				'software-internet'    => 'Software & Internet',
				'hardware-electronics' => 'Hardware & Electronics',
			),
		),
		'industry-manufacturing'    => array(
			'Industry & Manufacturing',
			array(
				'manufacturing'       => 'Manufacturing',
				'construction-cement' => 'Construction & Cement',
				'chemicals'           => 'Chemicals',
			),
		),
		'consumer-retail'           => array(
			'Consumer & Retail',
			array(
				'food-beverage'  => 'Food & Beverage',
				'retail'         => 'Retail',
				'fashion-beauty' => 'Fashion & Beauty',
			),
		),
		'agriculture-food'          => array(
			'Agriculture & Food',
			array(
				'agriculture'  => 'Agriculture',
				'agribusiness' => 'Agribusiness',
			),
		),
		'transport-logistics'       => array(
			'Transport & Logistics',
			array(
				'aviation'       => 'Aviation',
				'shipping-ports' => 'Shipping & Ports',
				'logistics'      => 'Logistics',
			),
		),
		'real-estate'               => array( 'Real Estate', array( 'real-estate-property' => 'Real Estate & Property' ) ),
		'health'                    => array(
			'Health',
			array(
				'healthcare'      => 'Healthcare',
				'pharmaceuticals' => 'Pharmaceuticals',
				'public-health'   => 'Public Health',
			),
		),
		'education-research'        => array(
			'Education & Research',
			array(
				'universities' => 'Universities',
				'schools'      => 'Schools',
				'research'     => 'Research',
			),
		),
		'government-public-service' => array(
			'Government & Public Service',
			array(
				'government'                     => 'Government',
				'diplomacy-international-bodies' => 'Diplomacy & International Bodies',
				'law-justice'                    => 'Law & Justice',
				'military-security'              => 'Military & Security',
			),
		),
		'media-entertainment'       => array(
			'Media & Entertainment',
			array(
				'media-publishing' => 'Media & Publishing',
				'film-tv'          => 'Film & TV',
				'music'            => 'Music',
			),
		),
		'arts-culture-sport'        => array(
			'Arts, Culture & Sport',
			array(
				'literature'  => 'Literature',
				'visual-arts' => 'Visual Arts',
				'sport'       => 'Sport',
			),
		),
		'civil-society'             => array(
			'Civil Society',
			array(
				'nonprofits-ngos'       => 'Non-profits & NGOs',
				'religion'              => 'Religion',
				'activism-human-rights' => 'Activism & Human Rights',
			),
		),
	);

	/**
	 * Organisation type slugs as agreed with the acquisition side.
	 */
	private const ORG_TYPES = array( 'company', 'listed-company', 'state-owned-enterprise', 'government-body', 'regulator', 'central-bank', 'international-organisation', 'university', 'school', 'research-institute', 'hospital', 'ngo-nonprofit', 'foundation', 'cooperative', 'religious-organisation', 'sports-club', 'media-outlet', 'other' );

	public function test_REQ_009_the_tree_has_exactly_the_agreed_sectors_and_industries(): void {
		$config = Classification::config();
		$this->assertSame( 1, $config['version'] );
		$this->assertSame( 'unclassified', $config['unclassified'] );
		$this->assertSame( array_keys( self::TREE ), array_keys( $config['sectors'] ) );
		foreach ( self::TREE as $slug => list( $label, $industries ) ) {
			$this->assertSame( $label, $config['sectors'][ $slug ]['label'], $slug );
			$got = array();
			foreach ( $config['sectors'][ $slug ]['industries'] as $industry => $row ) {
				$got[ $industry ] = $row['label'];
			}
			$this->assertSame( $industries, $got, $slug );
		}
		$this->assertFalse( Classification::is_industry( 'unclassified' ), 'Unclassified is not an industry leaf.' );
		$this->assertFalse( Classification::is_industry( 'energy-resources' ), 'A sector is not an industry leaf.' );
		$this->assertSame( 'industry-manufacturing', Classification::industry_sectors()['construction-cement'] );
	}

	public function test_REQ_009_the_org_types_are_the_agreed_slugs_with_sentence_case_labels(): void {
		$types = Classification::config()['org_types'];
		$this->assertSame( self::ORG_TYPES, array_keys( $types ) );
		$this->assertSame( 'State-owned enterprise', $types['state-owned-enterprise']['label'] );
		$this->assertSame( 'NGO / non-profit', $types['ngo-nonprofit']['label'] );
		$this->assertSame( array(), $types['other']['aliases'] );
	}

	public function test_REQ_009_no_alias_or_label_means_two_entries_in_one_list(): void {
		$config = Classification::config();
		$lists  = array(
			'industry' => array(),
			'org_type' => array(),
		);
		foreach ( $config['sectors'] as $sector ) {
			$lists['industry'] += $sector['industries'];
		}
		$lists['org_type'] = $config['org_types'];
		foreach ( $lists as $name => $rows ) {
			$seen = array();
			foreach ( $rows as $slug => $row ) {
				foreach ( array_merge( array( $slug, $row['label'] ), $row['aliases'] ) as $value ) {
					$key = Classification::normalise( (string) $value );
					$this->assertFalse( isset( $seen[ $key ] ) && $seen[ $key ] !== $slug, "{$name}: \"{$value}\" is used by {$slug} and " . ( $seen[ $key ] ?? '' ) );
					$seen[ $key ] = $slug;
				}
			}
		}
	}

	public function test_REQ_009_matching_reads_and_as_ampersand_and_ignores_case(): void {
		$this->assertSame( Classification::normalise( 'Oil & Gas' ), Classification::normalise( '  oil   AND gas ' ) );
		$this->assertSame( Classification::normalise( 'Oil &amp; Gas' ), Classification::normalise( 'Oil & Gas' ) );
	}

	public function test_REQ_009_the_shared_tree_json_matches_the_config(): void {
		$file = dirname( __DIR__, 4 ) . '/docs/contracts/classification-tree.json';
		$this->assertFileExists( $file );
		$this->assertSame(
			Classification::tree_json(),
			str_replace( "\r\n", "\n", (string) file_get_contents( $file ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a repository file.
			'docs/contracts/classification-tree.json is out of date: run php site/bin/classification-tree.php'
		);
		$tree = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- as above.
		$this->assertSame( 'oil-gas', $tree['sectors'][0]['industries'][0]['slug'] );
		$this->assertSame( 'unclassified', $tree['unclassified'] );
	}
}
