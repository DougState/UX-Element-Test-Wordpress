<?php
/**
 * Fired when the plugin is uninstalled via the WordPress admin.
 *
 * Removes all options and custom database tables created by ElementTest Pro
 * so the site is left in the same state it was before the plugin was installed.
 *
 * @package ElementTestPro
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// $site_ids and $site_id below are local function/loop variables; they
// are not globals despite Plugin Check's report (uninstall.php is loaded
// outside of any class scope).
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * Delete plugin options and drop plugin-owned tables for a single site.
 *
 * @return void
 */
function elementtest_pro_uninstall_site() {
	global $wpdb;

	// Plugin options created via add_option()/update_option().
	$options = array(
		'elementtest_version',
		'elementtest_db_version',
		'elementtest_settings',
		'elementtest_proxy_notice_dismissed',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Drop custom tables created by the plugin's activation routine.
	$tables = array(
		$wpdb->prefix . 'elementtest_tests',
		$wpdb->prefix . 'elementtest_variants',
		$wpdb->prefix . 'elementtest_events',
		$wpdb->prefix . 'elementtest_conversions',
	);

	foreach ( $tables as $table ) {
		// Table names cannot be parameterized; the values above are built from
		// $wpdb->prefix and hard-coded suffixes, so this is safe.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Remove any transients the plugin sets for rate limiting, invalid-request
	// caps, etc. These are best-effort: the transient API does not expose a
	// prefix-delete helper, so we clean only the well-known keys.
	delete_transient( 'elementtest_proxy_notice_dismissed' );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array(
		'fields' => 'ids',
		'number' => 0,
	) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		elementtest_pro_uninstall_site();
		restore_current_blog();
	}
} else {
	elementtest_pro_uninstall_site();
}
