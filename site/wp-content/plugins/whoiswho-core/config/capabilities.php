<?php
/**
 * Capability catalogue: every capability, where it comes from, which default role bundles hold it and whether it is fixed.
 * The authoritative list is docs/requirements.md, "Capabilities and default role mapping". Edit this file, bump `version`, and
 * the next sync adds new capabilities to the roles without removing grants an Administrator added later.
 *
 * Roles: reviewer, editor, administrator. `fixed` capabilities cannot be granted to a non-Administrator.
 * Withdrawn and not listed: wiw_set_trust_labels (D-059), wiw_manage_leads (D-061).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

$wiw_all    = array( 'reviewer', 'editor', 'administrator' );
$wiw_edit   = array( 'editor', 'administrator' );
$wiw_admin  = array( 'administrator' );
$wiw_review = array( 'reviewer', 'administrator' );

return array(
	'version'      => 5,
	'roles'        => array(
		'wiw_reviewer' => array(
			'label'  => 'Reviewer',
			'bundle' => 'reviewer',
		),
		'wiw_editor'   => array(
			'label'  => 'Editor',
			'bundle' => 'editor',
		),
	),
	'capabilities' => array(
		'read'                          => array(
			'source' => 'core',
			'roles'  => $wiw_all,
		),

		// Stories.
		'edit_posts'                    => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'moderate_comments'             => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'edit_others_posts'             => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'publish_posts'                 => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'edit_published_posts'          => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'delete_posts'                  => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'upload_files'                  => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),

		// Pages: About, FAQ, rules and legal pages (REQ-260). Editors and Administrators; a Reviewer has none.
		'edit_pages'                    => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'edit_others_pages'             => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'publish_pages'                 => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'edit_published_pages'          => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),

		// Profiles (names generated for the profile post type, added in M1).
		'read_private_wiw_profiles'     => array(
			'source' => 'core',
			'roles'  => $wiw_all,
		),
		'edit_wiw_profiles'             => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'edit_others_wiw_profiles'      => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'publish_wiw_profiles'          => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'edit_published_wiw_profiles'   => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'delete_wiw_profiles'           => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),

		// Taxonomy terms (the controlled lists).
		'assign_terms'                  => array(
			'source' => 'core',
			'roles'  => $wiw_all,
		),
		'manage_terms'                  => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'edit_terms'                    => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),
		'delete_terms'                  => array(
			'source' => 'core',
			'roles'  => $wiw_edit,
		),

		// Administration. Fixed: never grantable to a non-Administrator.
		'list_users'                    => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'create_users'                  => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'edit_users'                    => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'promote_users'                 => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'delete_users'                  => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'manage_options'                => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'install_plugins'               => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'activate_plugins'              => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'update_plugins'                => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'install_themes'                => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'switch_themes'                 => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'edit_theme_options'            => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'export'                        => array(
			'source' => 'core',
			'roles'  => $wiw_admin,
		),

		// Plugin capabilities.
		'wiw_view_dashboard_review'     => array(
			'source' => 'plugin',
			'roles'  => $wiw_all,
		),
		'wiw_review_records'            => array(
			'source' => 'plugin',
			'roles'  => $wiw_all,
		),
		'wiw_resolve_duplicates'        => array(
			'source' => 'plugin',
			'roles'  => $wiw_all,
		),
		'wiw_view_sources'              => array(
			'source' => 'plugin',
			'roles'  => $wiw_all,
		),
		'wiw_view_suppression'          => array(
			'source' => 'plugin',
			'roles'  => $wiw_all,
		),
		'wiw_bulk_unpublish'            => array(
			'source' => 'plugin',
			'roles'  => $wiw_review,
		),
		'wiw_unpublish_profiles'        => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_review_claims'             => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_curate_home'               => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_manage_events'             => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_set_sponsored'             => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_manage_awards'             => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_manage_subscribers'        => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_support_inbox'             => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_handle_disputes'           => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_handle_requests'           => array(
			'source' => 'plugin',
			'roles'  => $wiw_edit,
		),
		'wiw_receive_enquiries'         => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
		),
		'wiw_view_dashboard_commercial' => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
		),
		'wiw_manage_register'           => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'wiw_run_imports'               => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
		),
		'wiw_export_data'               => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
		),
		'wiw_view_audit'                => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
		),
		'wiw_edit_suppression'          => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
		),
		'wiw_toggle_nominations'        => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'wiw_edit_assigned_profiles'    => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
		),
		'wiw_run_setup'                 => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
		'wiw_manage_capabilities'       => array(
			'source' => 'plugin',
			'roles'  => $wiw_admin,
			'fixed'  => true,
		),
	),
);
