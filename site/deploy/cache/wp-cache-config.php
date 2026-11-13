<?php
/**
 * Settings for the WP Super Cache page cache, applied by code (REQ-041, REQ-054, REQ-217). deploy/cache/install.php copies this
 * file to wp-content/wp-cache-config.php. Nothing here is changed from wp-admin: the plugin's settings screen is hidden.
 *
 * Mode: "simple" (PHP serves the stored page), so every request still passes the plugin's cookie checks and DONOTCACHEPAGE,
 * which PageCachePolicy sets for anything that is not an anonymous public page. No rewrite rules are used, so a stored page is
 * never served by the web server around those checks.
 *
 * @package WhoIsWho
 */

if ( ! defined( 'WPCACHEHOME' ) ) {
	define( 'WPCACHEHOME', WP_PLUGIN_DIR . '/wp-super-cache/' );
}

$cache_enabled       = true;
$super_cache_enabled = false; // Simple mode, not the mod_rewrite "expert" mode.
$wp_cache_mod_rewrite = 0;
$cache_compression   = 0;
$cache_path          = WP_CONTENT_DIR . '/cache/';
$file_prefix         = 'wp-cache-';

// Lifetime: at most 120 seconds for any page, 60 for lists and search (REQ-121). One value, so the lower one for everything.
$cache_max_time = 60;
// Remove expired files on every shutdown instead of waiting for a scheduled run, so an expired page is not served for long.
$wp_cache_shutdown_gc = 1;
$cache_schedule_type  = 'interval';
$cache_time_interval  = 60;

// Anonymous visitors only: no stored page for anyone with a WordPress login cookie, a commenter or a post-password cookie.
$wp_cache_not_logged_in = 2;
$wp_cache_anon_only     = 0;
$wp_cache_make_known_anon = 0;
// A request with a query string (search, preview, tracking) is never stored.
$wp_cache_no_cache_for_get = 1;
// Never store: feeds, author, category, tag and archive pages (closed by the guard anyway). 1 means "do not cache".
$wp_cache_pages['search']    = 0;
$wp_cache_pages['feed']      = 1;
$wp_cache_pages['category']  = 1;
$wp_cache_pages['tag']       = 1;
$wp_cache_pages['archives']  = 1;
$wp_cache_pages['author']    = 1;
$wp_cache_pages['home']      = 0;
$wp_cache_pages['frontpage'] = 0;
$wp_cache_pages['pages']     = 0;
$wp_cache_pages['single']    = 0;

// Pages that carry a time-based form token or a signed link, previews and the admin: refused by address as well as by PageCachePolicy.
$cache_rejected_uri        = array( 'wp-.*\\.php', 'index\\.php', 'wp-admin', 'wp-json', 'feed', 'preview', 'contact', 'get-listed', 'claim', 'nominate', 'correct', 'correction', 'removal', 'remove', 'report', 'newsletter', 'unsubscribe', 'confirm/', 'link/', 'healthz' );
$cache_acceptable_files    = array();
$cache_rejected_user_agent = array();

$wp_cache_clear_on_post_edit = 1;
$wp_cache_mobile_enabled     = 0;
$wp_cache_refresh_single_only = 0;
$wp_cache_hello_world        = 0;
$wp_cache_preload_on         = 0;
$wp_cache_mutex_disabled     = 1;
$wp_cache_debug_to_file      = 0;
$wp_super_cache_debug        = 0;
$wpsc_save_headers           = 0;
$cache_rebuild_files         = 1;
$ossdlcdn                    = 0;
$wp_cache_plugins_dir        = WPCACHEHOME . 'plugins';
