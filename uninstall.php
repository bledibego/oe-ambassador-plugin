<?php
/**
 * Uninstall OE Ambassador.
 *
 * Drops plugin tables and deletes options.
 * Only runs when the plugin is deleted via WP Admin.
 *
 * @package OE_Ambassador
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oe_amb_payouts" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oe_amb_commissions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oe_ambassadors" );

// Delete options
delete_option( 'oe_amb_settings' );
delete_option( 'oe_amb_tiers' );
delete_option( 'oe_amb_db_version' );

// Remove cron events
wp_clear_scheduled_hook( 'oe_amb_monthly_reports' );
wp_clear_scheduled_hook( 'oe_amb_auto_approve' );

// Remove ambassador role
remove_role( 'ambassador' );

// Remove admin capability
$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'manage_oe_ambassadors' );
}
