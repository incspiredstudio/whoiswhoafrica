<?php
/**
 * Column and key definitions of the three profile tables, shared by the migrations and by tests that need a scratch copy.
 * Pass them to Platform\Migrations\Schema::create_table(). Updates to these definitions are forward-only: add a new migration.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\State;

/**
 * Table definitions.
 */
final class Tables {

	/**
	 * One row per profile; the primary key is the profile's post id. Indexed for the source, batch, type, state and origin filters.
	 */
	public const STATE = 'profile_id bigint(20) unsigned NOT NULL,
		pub_state varchar(32) NOT NULL,
		entity_type varchar(20) NOT NULL,
		tier varchar(4) NULL,
		origin varchar(20) NOT NULL DEFAULT \'import\',
		source varchar(190) NULL,
		import_batch varchar(40) NULL,
		content_hash char(64) NULL,
		importer_id bigint(20) unsigned NULL,
		reviewer_id bigint(20) unsigned NULL,
		reviewed_at datetime NULL,
		claimed tinyint(1) unsigned NOT NULL DEFAULT 0,
		claimed_at datetime NULL,
		assigned_user_id bigint(20) unsigned NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (profile_id),
		KEY pub_state (pub_state),
		KEY entity_type (entity_type),
		KEY origin (origin),
		KEY source (source),
		KEY import_batch (import_batch),
		KEY state_type (pub_state, entity_type),
		KEY assigned_user (assigned_user_id)';

	/**
	 * Version snapshots. Append-only: a pending revision is decided by a later row that points back at it (revision_of).
	 * author_id is 0 for an import.
	 */
	public const VERSIONS = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		profile_id bigint(20) unsigned NOT NULL,
		author_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_via varchar(20) NOT NULL,
		reason text NULL,
		created_at datetime NOT NULL,
		approval_status varchar(12) NULL,
		revision_of bigint(20) unsigned NULL,
		old_value longtext NULL,
		new_value longtext NULL,
		PRIMARY KEY  (id),
		KEY profile (profile_id, id),
		KEY approval (approval_status, profile_id),
		KEY revision_of (revision_of)';

	/**
	 * One row per profile field and source.
	 */
	public const PROVENANCE = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		profile_id bigint(20) unsigned NOT NULL,
		field varchar(60) NOT NULL,
		source_url varchar(2048) NOT NULL,
		retrieved_at datetime NOT NULL,
		confidence varchar(8) NOT NULL,
		method varchar(8) NOT NULL,
		source_hash char(64) NULL,
		PRIMARY KEY  (id),
		KEY profile_field (profile_id, field)';

	/**
	 * Names of the two triggers that make the versions table append-only.
	 */
	public const VERSIONS_TRIGGERS = array( 'wiw_profile_versions_no_update', 'wiw_profile_versions_no_delete' );
}
