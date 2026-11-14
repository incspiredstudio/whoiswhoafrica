<?php
/**
 * Profile field allowlists per entity type (import contract sections 4.1 and 4.2, REQ-004, REQ-005, REQ-139).
 * A field that is not listed has no storage: no meta key is registered for it and the validator refuses it.
 * Each field: type (string, url, int, list), required, optional max length in characters, and label (the plain name staff
 * screens show for it, issue 806).
 * "name" is stored in the post title, every other field in post meta "wiw_<key>".
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

$wiw_organisation = array(
	'name'             => array(
		'type'     => 'string',
		'required' => true,
		'label'    => 'Name',
	),
	'org_type'         => array(
		'type'     => 'string',
		'required' => true,
		'label'    => 'Organisation type',
	),
	'industry'         => array(
		'type'     => 'string',
		'required' => false,
		'label'    => 'Industry',
	),
	'sector'           => array(
		'type'     => 'string',
		'required' => false,
		'label'    => 'Sector',
	),
	'licence_category' => array(
		'type'     => 'string',
		'required' => false,
		'label'    => 'Licence category',
	),
	'regulator'        => array(
		'type'     => 'string',
		'required' => false,
		'label'    => 'Regulator',
	),
	'mandate'          => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 2000,
		'label'    => 'Mandate',
	),
	'hq_city'          => array(
		'type'     => 'string',
		'required' => false,
		'label'    => 'Head office city',
	),
	'website'          => array(
		'type'     => 'url',
		'required' => false,
		'label'    => 'Website',
	),
	'year_founded'     => array(
		'type'     => 'int',
		'required' => false,
		'label'    => 'Year founded',
	),
	'summary'          => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 2000,
		'label'    => 'Summary',
	),
	// Narrative sections (contract 1.1): plain text, paragraphs separated by blank lines.
	'bio'              => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 6000,
		'label'    => 'Bio',
	),
	'achievement'      => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 6000,
		'label'    => 'Achievement',
	),
	'influence'        => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 6000,
		'label'    => 'Influence',
	),
	'legacy'           => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 6000,
		'label'    => 'Legacy',
	),
);

$wiw_person = array(
	'name'                     => array(
		'type'     => 'string',
		'required' => true,
		'label'    => 'Name',
	),
	'current_title'            => array(
		'type'     => 'string',
		'required' => false,
		'label'    => 'Current title',
	),
	'current_organisation'     => array(
		'type'     => 'string',
		'required' => false,
		'label'    => 'Current organisation',
	),
	'current_organisation_ref' => array(
		'type'     => 'string',
		'required' => false,
		'label'    => 'Organisation reference (set by the import)',
	),
	'industry'                 => array(
		'type'     => 'string',
		'required' => false,
		'label'    => 'Industry',
	),
	'public_record_roles'      => array(
		'type'     => 'list',
		'required' => false,
		'label'    => 'Public roles on record',
	),
	'published_awards'         => array(
		'type'     => 'list',
		'required' => false,
		'label'    => 'Published awards',
	),
	'official_website'         => array(
		'type'     => 'url',
		'required' => false,
		'label'    => 'Official website',
	),
	// Contract 1.1: lifespan (for people who have died, such as "1924–2013") and field (profession, such as "Music").
	'lifespan'                 => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 40,
		'label'    => 'Lifespan (only for people who have died, for example 1924–2013)',
	),
	'field'                    => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 120,
		'label'    => 'Field or profession',
	),
	// Narrative sections (contract 1.1): plain text, paragraphs separated by blank lines.
	'bio'                      => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 6000,
		'label'    => 'Bio',
	),
	'achievement'              => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 6000,
		'label'    => 'Achievement',
	),
	'influence'                => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 6000,
		'label'    => 'Influence',
	),
	'legacy'                   => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 6000,
		'label'    => 'Legacy',
	),
);

return array(
	'types'     => array(
		'organisation' => $wiw_organisation,
		'institution'  => $wiw_organisation,
		'individual'   => $wiw_person,
		'entrepreneur' => $wiw_person,
	),
	// Attributes that must never have storage (REQ-140, REQ-067, REQ-068). Used by the validator to name a refusal and by tests.
	'forbidden' => array(
		'address',
		'home_address',
		'phone',
		'mobile',
		'email',
		'personal_email',
		'family',
		'spouse',
		'children',
		'date_of_birth',
		'dob',
		'birth_date',
		'age',
		'health',
		'religion',
		'politics',
		'party_affiliation',
		'ethnicity',
		'finances',
		'net_worth',
		'allegations',
		'social_handles',
		'twitter',
		'instagram',
		'facebook',
		'leadership',
		'founder',
		'board',
	),
);
