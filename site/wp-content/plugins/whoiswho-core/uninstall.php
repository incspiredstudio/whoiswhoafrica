<?php
/**
 * Uninstall. Deletes nothing unless the setting wiw_delete_data_on_uninstall is on; data is never dropped by default.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! get_option( 'wiw_delete_data_on_uninstall' ) ) {
	return;
}

// Explicit opt-in only. Later issues list what to remove here.
delete_option( 'wiw_core_version' );
