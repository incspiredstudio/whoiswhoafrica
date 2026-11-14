<?php
/**
 * The classification tree (REQ-009 as amended 2026-11-14): sectors, each with its industries, the Unclassified term and the
 * organisation types. It is the one source of the tree: the seeder creates the terms from it (hierarchical `wiw_industry`, flat
 * `wiw_org_type`), the importer and the upgrade migration match raw values through the aliases, and
 * `docs/contracts/classification-tree.json` is generated from it for the acquisition side (a test keeps the two in step).
 *
 * Slugs never change once shipped: they are the values contract v1.2 carries. Labels may be renamed by staff on the term screens.
 * Aliases are raw values seen in the data (any language, compared case-insensitively, with "&" read as "and"); a value that
 * matches nothing maps to Unclassified, and an unknown organisation type maps to "other".
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

return array(
	'version'      => 1,
	'sectors'      => array(
		'energy-resources'          => array(
			'label'      => 'Energy & Resources',
			'industries' => array(
				'oil-gas'         => array(
					'label'   => 'Oil & Gas',
					'aliases' => array( 'Oil and gas', 'Petroleum', 'Oil', 'Gas' ),
				),
				'mining'          => array(
					'label'   => 'Mining',
					'aliases' => array( 'Mining and metals', 'Metals and mining', 'Mines' ),
				),
				'power-utilities' => array(
					'label'   => 'Power & Utilities',
					'aliases' => array( 'Electricity and water', 'Electricity', 'Utilities', 'Power', 'Energy' ),
				),
				'renewables'      => array(
					'label'   => 'Renewables',
					'aliases' => array( 'Renewable energy', 'Solar energy', 'Clean energy' ),
				),
			),
		),
		'financial-services'        => array(
			'label'      => 'Financial Services',
			'industries' => array(
				'banking'                     => array(
					'label'   => 'Banking',
					'aliases' => array( 'Banking and financial services', 'Banks', 'Financial services', 'Financial sector', 'Finance' ),
				),
				'insurance'                   => array(
					'label'   => 'Insurance',
					'aliases' => array( 'Insurers', 'Reinsurance' ),
				),
				'investment-asset-management' => array(
					'label'   => 'Investment & Asset Management',
					'aliases' => array( 'Investment', 'Asset management', 'Private equity', 'Investment banking' ),
				),
				'fintech'                     => array(
					'label'   => 'Fintech',
					'aliases' => array( 'Financial Services & Fintech', 'Financial technology', 'Payments' ),
				),
				'central-banking-regulation'  => array(
					'label'   => 'Central Banking & Regulation',
					'aliases' => array( 'Central banking', 'Central bank', 'Financial regulation' ),
				),
			),
		),
		'technology-telecoms'       => array(
			'label'      => 'Technology & Telecoms',
			'industries' => array(
				'telecoms'             => array(
					'label'   => 'Telecoms',
					'aliases' => array( 'Telecommunications', 'Telecommunication', 'Telecommunications industry', 'Mobile telecommunications' ),
				),
				'software-internet'    => array(
					'label'   => 'Software & Internet',
					'aliases' => array( 'Technology and software', 'Technology', 'Software', 'Internet', 'Information technology', 'IT' ),
				),
				'hardware-electronics' => array(
					'label'   => 'Hardware & Electronics',
					'aliases' => array( 'Hardware', 'Electronics', 'Consumer electronics' ),
				),
			),
		),
		'industry-manufacturing'    => array(
			'label'      => 'Industry & Manufacturing',
			'industries' => array(
				'manufacturing'       => array(
					'label'   => 'Manufacturing',
					'aliases' => array( 'Automotive', 'Industry', 'Industrial manufacturing' ),
				),
				'construction-cement' => array(
					'label'   => 'Construction & Cement',
					'aliases' => array( 'Construction and materials', 'Construction', 'Cement', 'Building materials', 'Engineering and construction' ),
				),
				'chemicals'           => array(
					'label'   => 'Chemicals',
					'aliases' => array( 'Chemical industry', 'Petrochemicals' ),
				),
			),
		),
		'consumer-retail'           => array(
			'label'      => 'Consumer & Retail',
			'industries' => array(
				'food-beverage'  => array(
					'label'   => 'Food & Beverage',
					'aliases' => array( 'Food and beverages', 'Food', 'Beverages', 'Food processing', 'Brewing' ),
				),
				'retail'         => array(
					'label'   => 'Retail',
					'aliases' => array( 'Retail and trade', 'Trade', 'Wholesale', 'Commerce', 'E-commerce' ),
				),
				'fashion-beauty' => array(
					'label'   => 'Fashion & Beauty',
					'aliases' => array( 'Fashion', 'Beauty', 'Cosmetics', 'Textiles and clothing' ),
				),
			),
		),
		'agriculture-food'          => array(
			'label'      => 'Agriculture & Food',
			'industries' => array(
				'agriculture'  => array(
					'label'   => 'Agriculture',
					'aliases' => array( 'Agriculture & Agritech', 'Farming', 'Agritech', 'Fisheries', 'Forestry' ),
				),
				'agribusiness' => array(
					'label'   => 'Agribusiness',
					'aliases' => array( 'Agro-processing', 'Agricultural commodities' ),
				),
			),
		),
		'transport-logistics'       => array(
			'label'      => 'Transport & Logistics',
			'industries' => array(
				'aviation'       => array(
					'label'   => 'Aviation',
					'aliases' => array( 'Aviation and aerospace', 'Airlines', 'Aerospace', 'Airline' ),
				),
				'shipping-ports' => array(
					'label'   => 'Shipping & Ports',
					'aliases' => array( 'Shipping', 'Ports', 'Maritime' ),
				),
				'logistics'      => array(
					'label'   => 'Logistics',
					'aliases' => array( 'Transport and logistics', 'Transport', 'Transportation', 'Railways', 'Road transport' ),
				),
			),
		),
		'real-estate'               => array(
			'label'      => 'Real Estate',
			'industries' => array(
				'real-estate-property' => array(
					'label'   => 'Real Estate & Property',
					'aliases' => array( 'Real estate', 'Property', 'Property development' ),
				),
			),
		),
		'health'                    => array(
			'label'      => 'Health',
			'industries' => array(
				'healthcare'      => array(
					'label'   => 'Healthcare',
					'aliases' => array( 'Health care', 'Health', 'Health & Life Sciences', 'Hospitals', 'Medicine' ),
				),
				'pharmaceuticals' => array(
					'label'   => 'Pharmaceuticals',
					'aliases' => array( 'Pharmaceutical industry', 'Pharma' ),
				),
				'public-health'   => array(
					'label'   => 'Public Health',
					'aliases' => array( 'Global health', 'Epidemiology' ),
				),
			),
		),
		'education-research'        => array(
			'label'      => 'Education & Research',
			'industries' => array(
				'universities' => array(
					'label'   => 'Universities',
					'aliases' => array( 'Education', 'Higher education', 'University', 'University / school' ),
				),
				'schools'      => array(
					'label'   => 'Schools',
					'aliases' => array( 'School', 'Secondary education', 'Primary education' ),
				),
				'research'     => array(
					'label'   => 'Research',
					'aliases' => array( 'Research and science', 'Science', 'Scientific research' ),
				),
			),
		),
		'government-public-service' => array(
			'label'      => 'Government & Public Service',
			'industries' => array(
				'government'                     => array(
					'label'   => 'Government',
					'aliases' => array( 'Public administration', 'Politics and government', 'Royalty and traditional rulers', 'Public Sector', 'Politics', 'Public service' ),
				),
				'diplomacy-international-bodies' => array(
					'label'   => 'Diplomacy & International Bodies',
					'aliases' => array( 'Diplomacy', 'International relations', 'International organisations' ),
				),
				'law-justice'                    => array(
					'label'   => 'Law & Justice',
					'aliases' => array( 'Law and justice', 'Law', 'Legal', 'Legal & Professional Services', 'Judiciary' ),
				),
				'military-security'              => array(
					'label'   => 'Military & Security',
					'aliases' => array( 'Military', 'Armed forces', 'Security' ),
				),
			),
		),
		'media-entertainment'       => array(
			'label'      => 'Media & Entertainment',
			'industries' => array(
				'media-publishing' => array(
					'label'   => 'Media & Publishing',
					'aliases' => array( 'Media and publishing', 'Media & Creative', 'Media', 'Publishing', 'Journalism', 'Broadcasting' ),
				),
				'film-tv'          => array(
					'label'   => 'Film & TV',
					'aliases' => array( 'Film, theatre and broadcasting', 'Film', 'Television', 'Cinema', 'Theatre' ),
				),
				'music'            => array(
					'label'   => 'Music',
					'aliases' => array( 'Musician', 'Music industry' ),
				),
			),
		),
		'arts-culture-sport'        => array(
			'label'      => 'Arts, Culture & Sport',
			'industries' => array(
				'literature'  => array(
					'label'   => 'Literature',
					'aliases' => array( 'Literature and journalism', 'Writing', 'Poetry' ),
				),
				'visual-arts' => array(
					'label'   => 'Visual Arts',
					'aliases' => array( 'Visual arts and architecture', 'Art', 'Architecture', 'Photography', 'Design' ),
				),
				'sport'       => array(
					'label'   => 'Sport',
					'aliases' => array( 'Sports', 'Football', 'Athletics' ),
				),
			),
		),
		'civil-society'             => array(
			'label'      => 'Civil Society',
			'industries' => array(
				'nonprofits-ngos'       => array(
					'label'   => 'Non-profits & NGOs',
					'aliases' => array( 'Humanitarian and development', 'Environment and conservation', 'Non-profit', 'NGO', 'Charity', 'Philanthropy' ),
				),
				'religion'              => array(
					'label'   => 'Religion',
					'aliases' => array( 'Religious', 'Faith' ),
				),
				'activism-human-rights' => array(
					'label'   => 'Activism & Human Rights',
					'aliases' => array( 'Activism and liberation', 'Activism', 'Human rights' ),
				),
			),
		),
	),
	'unclassified' => 'unclassified',
	'org_types'    => array(
		'company'                    => array(
			'label'   => 'Company',
			'aliases' => array( 'Business', 'Private company', 'Limited company', 'Enterprise' ),
		),
		'listed-company'             => array(
			'label'   => 'Listed company',
			'aliases' => array( 'Public company', 'Publicly listed company', 'Listed' ),
		),
		'state-owned-enterprise'     => array(
			'label'   => 'State-owned enterprise',
			'aliases' => array( 'State enterprise', 'State-owned company', 'Parastatal' ),
		),
		'government-body'            => array(
			'label'   => 'Government body',
			'aliases' => array( 'Public body', 'Public sector body', 'Government agency', 'Government department', 'Government', 'Ministry', 'Public institution' ),
		),
		'regulator'                  => array(
			'label'   => 'Regulator',
			'aliases' => array( 'Regulatory body', 'Regulatory agency' ),
		),
		'central-bank'               => array(
			'label'   => 'Central bank',
			'aliases' => array( 'Reserve bank' ),
		),
		'international-organisation' => array(
			'label'   => 'International organisation',
			'aliases' => array( 'International organization', 'Intergovernmental organisation', 'International body' ),
		),
		'university'                 => array(
			'label'   => 'University',
			'aliases' => array( 'University / school', 'Public university', 'Private university', 'Polytechnic', 'Higher education institution', 'College' ),
		),
		'school'                     => array(
			'label'   => 'School',
			'aliases' => array( 'Secondary school', 'Primary school' ),
		),
		'research-institute'         => array(
			'label'   => 'Research institute',
			'aliases' => array( 'Research institution', 'Think tank', 'Research centre' ),
		),
		'hospital'                   => array(
			'label'   => 'Hospital',
			'aliases' => array( 'Clinic', 'Teaching hospital' ),
		),
		'ngo-nonprofit'              => array(
			'label'   => 'NGO / non-profit',
			'aliases' => array( 'NGO', 'Non-profit', 'Nonprofit', 'NGO / social enterprise', 'Social enterprise', 'Charity', 'Non-governmental organisation' ),
		),
		'foundation'                 => array(
			'label'   => 'Foundation',
			'aliases' => array( 'Charitable foundation' ),
		),
		'cooperative'                => array(
			'label'   => 'Cooperative',
			'aliases' => array( 'Co-operative' ),
		),
		'religious-organisation'     => array(
			'label'   => 'Religious organisation',
			'aliases' => array( 'Church', 'Mosque', 'Religious organization' ),
		),
		'sports-club'                => array(
			'label'   => 'Sports club',
			'aliases' => array( 'Football club', 'Sports team' ),
		),
		'media-outlet'               => array(
			'label'   => 'Media outlet',
			'aliases' => array( 'Newspaper', 'Broadcaster', 'Media company' ),
		),
		'other'                      => array(
			'label'   => 'Other',
			'aliases' => array( 'Professional association', 'Professional body', 'Trade association', 'Association' ),
		),
	),
);
