<?php
/**
 * Profile field allowlists per entity type (import contract sections 4.1 and 4.2, REQ-004, REQ-005, REQ-139).
 * A field that is not listed has no storage: no meta key is registered for it and the validator refuses it.
 * Each field: type (string, url, int, list), required, and optional max length.
 * "name" is stored in the post title, every other field in post meta "wiw_<key>".
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

$wiw_organisation = array(
	'name'             => array(
		'type'     => 'string',
		'required' => true,
	),
	'org_type'         => array(
		'type'     => 'string',
		'required' => true,
	),
	'industry'         => array(
		'type'     => 'string',
		'required' => false,
	),
	'sector'           => array(
		'type'     => 'string',
		'required' => false,
	),
	'licence_category' => array(
		'type'     => 'string',
		'required' => false,
	),
	'regulator'        => array(
		'type'     => 'string',
		'required' => false,
	),
	'mandate'          => array(
		'type'     => 'string',
		'required' => false,
	),
	'hq_city'          => array(
		'type'     => 'string',
		'required' => false,
	),
	'website'          => array(
		'type'     => 'url',
		'required' => false,
	),
	'year_founded'     => array(
		'type'     => 'int',
		'required' => false,
	),
	'summary'          => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 2000,
	),
);

$wiw_person = array(
	'name'                     => array(
		'type'     => 'string',
		'required' => true,
	),
	'current_title'            => array(
		'type'     => 'string',
		'required' => true,
	),
	'current_organisation'     => array(
		'type'     => 'string',
		'required' => true,
	),
	'current_organisation_ref' => array(
		'type'     => 'string',
		'required' => false,
	),
	'industry'                 => array(
		'type'     => 'string',
		'required' => false,
	),
	'public_record_roles'      => array(
		'type'     => 'list',
		'required' => false,
	),
	'published_awards'         => array(
		'type'     => 'list',
		'required' => false,
	),
	'official_website'         => array(
		'type'     => 'url',
		'required' => false,
	),
	'bio'                      => array(
		'type'     => 'string',
		'required' => false,
		'max'      => 4000,
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
