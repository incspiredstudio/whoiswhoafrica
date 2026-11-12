<?php
/**
 * Starter list of sensitive terms for the import free-text scan (REQ-141, REQ-068). A free-text field that contains any of these
 * words or phrases, whole-word and case-insensitive, blocks the record from review until a reviewer clears it. The list is a
 * starting point: Legal confirms it (M1-IMP-17). It covers the categories the contract never allows in a record: health,
 * religion, politics, ethnicity, finances, allegations and family.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

return array(
	'version' => 1,
	'terms'   => array(
		// Health.
		'diagnosed',
		'cancer',
		'hiv',
		'aids',
		'diabetes',
		'disability',
		'mental illness',
		'depression',
		'pregnant',
		'illness',
		'surgery',
		// Religion, politics and ethnicity.
		'religion',
		'christian',
		'muslim',
		'catholic',
		'pastor',
		'political party',
		'party member',
		'ethnicity',
		'tribe',
		'tribal',
		// Allegations and legal trouble.
		'allegation',
		'alleged',
		'accused',
		'convicted',
		'arrested',
		'fraud',
		'scandal',
		'sued',
		'lawsuit',
		'indicted',
		'bankrupt',
		// Finances and family.
		'net worth',
		'salary',
		'divorce',
		'divorced',
		'married',
		'wife',
		'husband',
		'children',
		'son of',
		'daughter of',
		// Biometrics, sexual life, trade-union membership and criminal records.
		'biometric',
		'fingerprint',
		'sexual orientation',
		'sexuality',
		'trade union',
		'union member',
		'criminal record',
		'prison',
		// Under 18.
		'minor',
		'underage',
		'teenager',
	),
);
