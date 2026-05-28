<?php
/**
 * Uninstall routine for XEN Inventory.
 *
 * Called automatically by WordPress when the plugin is deleted via the admin.
 * This file must NOT be called directly.
 *
 * @package XenInventory
 */

// Exit if not called by WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop the custom log table.
$table = $wpdb->prefix . 'xen_inventory_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // Table name is hard-coded, not user-supplied.

// Remove plugin options.
delete_option( 'xen_inventory_version' );
delete_option( 'xen_inventory_settings' );

// Optionally: delete all xen_item posts and their meta (uncomment if desired).
/*
$items = get_posts( [
    'post_type'      => 'xen_item',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );
foreach ( $items as $id ) {
    wp_delete_post( $id, true );
}
*/
