<?php
/**
 * WP Quiet Admin Uninstall
 *
 * Fired when the plugin is deleted via the WordPress Admin Plugins screen.
 * Cleans up options and audit records.
 *
 * @package WP_Quiet_Admin
 * @author RemarkablePC / David Hewitt
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Option keys used by WP Quiet Admin.
$options_to_delete = array(
	'wp_quiet_admin_settings',
	'wp_quiet_admin_stored_notices',
	'wp_quiet_admin_audit_log',
);

if ( is_multisite() ) {
	// If multisite, delete options across all sites in the network.
	global $wpdb;
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

	if ( is_array( $blog_ids ) ) {
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			foreach ( $options_to_delete as $option_name ) {
				delete_option( $option_name );
			}
			restore_current_blog();
		}
	}
} else {
	// Single site deletion.
	foreach ( $options_to_delete as $option_name ) {
		delete_option( $option_name );
	}
}
