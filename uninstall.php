<?php
/**
 * Uninstall routine for XEN Inventory.
 *
 * Called automatically by WordPress when the plugin is deleted via the admin.
 * This file must NOT be called directly.
 *
 * Behaviour is controlled by the "Delete Data on Uninstall" checkbox in
 * XEN Inventory → Settings → Advanced:
 *
 *  - Checkbox OFF (default): plugin options are removed but all inventory
 *    items, departments, and the borrow log table are preserved so data
 *    survives a plugin reinstall.
 *  - Checkbox ON: everything is permanently deleted — log table, all
 *    xen_item posts and their meta, all xen_department terms, the xen_staff
 *    role, and all plugin options.
 *
 * @package XenInventory
 */

// Exit if not called by WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$settings            = get_option( 'xen_inventory_settings', [] );
$delete_all_data     = ! empty( $settings['delete_data_on_uninstall'] );

// Always remove plugin options regardless of the checkbox — they are only
// meaningful while the plugin is active.
delete_option( 'xen_inventory_version' );
delete_option( 'xen_inventory_settings' );

if ( ! $delete_all_data ) {
    // Safe uninstall: leave items, logs, and taxonomy terms intact.
    return;
}

// -----------------------------------------------------------------------
// Full data wipe — only reached when the checkbox is enabled.
// -----------------------------------------------------------------------

// 1. Drop the custom borrow log table.
$table = $wpdb->prefix . 'xen_inventory_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // Table name is hard-coded, not user-supplied.

// 2. Delete all xen_item posts and their postmeta.
$item_ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'xen_item' AND post_status != 'auto-draft'"
);
foreach ( $item_ids as $id ) {
    wp_delete_post( (int) $id, true );
}

// 3. Delete all xen_department taxonomy terms and their meta.
$terms = get_terms( [
    'taxonomy'   => 'xen_department',
    'hide_empty' => false,
    'fields'     => 'ids',
] );
if ( is_array( $terms ) ) {
    foreach ( $terms as $term_id ) {
        wp_delete_term( (int) $term_id, 'xen_department' );
    }
}

// 4. Remove the xen_staff role.
remove_role( 'xen_staff' );

// 5. Remove inventory capabilities from the Administrator role.
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
    foreach ( [
        'xen_view_inventory',
        'xen_borrow_items',
        'xen_return_items',
        'xen_manage_inventory',
        'xen_manage_departments',
    ] as $cap ) {
        $admin_role->remove_cap( $cap );
    }
}
