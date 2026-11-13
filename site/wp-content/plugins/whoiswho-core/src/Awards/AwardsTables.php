<?php
/**
 * Table definitions of the Awards module (REQ-034, REQ-266, REQ-267, REQ-268, REQ-202). Stage is Nominated or Winner; status is
 * Live or Not live; there is no Ineligible, Declined, Shortlisted or Finalist value, and no judge, conflict or score column
 * (judging happens offline, D-064). No Awards table has a foreign key to, or a column about, any commercial table: the Awards
 * module has no reference to Commerce (REQ-199).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

/**
 * Awards tables.
 */
final class AwardsTables {

	public const EDITIONS     = 'wiw_editions';
	public const CATEGORIES   = 'wiw_award_categories';
	public const NOMINATIONS  = 'wiw_nominations';
	public const CHANGES      = 'wiw_nomination_changes';
	public const STAGES       = array( 'nominated', 'winner' );
	public const STATUSES     = array( 'not_live', 'live' );
	public const SOURCES      = array( 'public_form', 'staff' );
	public const CHANGE_TYPES = array( 'stage', 'status', 'removal' );

	/**
	 * The five award groups categories sit under (REQ-266), key => label.
	 */
	public const GROUPS = array(
		'leadership-public-impact'     => 'Leadership and Public Impact',
		'entrepreneurship-business'    => 'Entrepreneurship and Business',
		'technology-innovation'        => 'Technology and Innovation',
		'professional-excellence'      => 'Professional Excellence',
		'industry-institutional-recog' => 'Industry and Institutional Recognition',
	);

	public const EDITIONS_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		title varchar(120) NOT NULL,
		year smallint(5) unsigned NOT NULL,
		starts_on date NULL,
		ends_on date NULL,
		wording text NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY year (year)';

	public const CATEGORIES_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		edition_id bigint(20) unsigned NULL,
		group_key varchar(40) NOT NULL,
		title varchar(190) NOT NULL,
		sort_order int(11) NOT NULL DEFAULT 0,
		archived tinyint(1) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY edition_group (edition_id, group_key, sort_order),
		KEY archived (archived)';

	public const NOMINATIONS_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		edition_id bigint(20) unsigned NULL,
		category_id bigint(20) unsigned NOT NULL,
		profile_id bigint(20) unsigned NULL,
		nominator_name varchar(190) NULL,
		nominator_email varchar(190) NULL,
		nominator_verified_at datetime NULL,
		contact_email varchar(190) NULL,
		reference varchar(24) NULL,
		nominee_name varchar(190) NULL,
		nominee_org varchar(190) NULL,
		relationship_declared tinyint(1) unsigned NOT NULL DEFAULT 0,
		relationship_note text NULL,
		evidence text NULL,
		citation text NULL,
		stage enum(\'nominated\',\'winner\') NOT NULL DEFAULT \'nominated\',
		status enum(\'not_live\',\'live\') NOT NULL DEFAULT \'not_live\',
		source enum(\'public_form\',\'staff\') NOT NULL DEFAULT \'public_form\',
		nomination_sent_by bigint(20) unsigned NULL,
		nomination_sent_at datetime NULL,
		winner_sent_by bigint(20) unsigned NULL,
		winner_sent_at datetime NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY category (category_id),
		KEY profile (profile_id),
		KEY edition (edition_id),
		KEY stage_status (stage, status),
		UNIQUE KEY reference (reference)';

	public const CHANGES_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		nomination_id bigint(20) unsigned NOT NULL,
		change_type enum(\'stage\',\'status\',\'removal\') NOT NULL,
		old_value varchar(20) NULL,
		new_value varchar(20) NULL,
		user_id bigint(20) unsigned NOT NULL,
		changed_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY nomination (nomination_id)';

	/**
	 * Table name with the site prefix.
	 *
	 * @param string $name One of the table constants.
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	/**
	 * Every table with its definition.
	 *
	 * @return array<string, string>
	 */
	public static function definitions(): array {
		return array(
			self::EDITIONS    => self::EDITIONS_DEFINITION,
			self::CATEGORIES  => self::CATEGORIES_DEFINITION,
			self::NOMINATIONS => self::NOMINATIONS_DEFINITION,
			self::CHANGES     => self::CHANGES_DEFINITION,
		);
	}
}
