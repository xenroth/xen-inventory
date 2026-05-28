<?php
/**
 * Enqueue admin-side CSS and JavaScript.
 *
 * @package XenInventory\Admin
 */

namespace XenInventory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Assets
 */
class Assets {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    /**
     * Enqueue scripts and styles on XEN Inventory admin screens only.
     *
     * @param  string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueue( string $hook_suffix ): void {
        // Only load on xen_item edit screens and our own admin pages.
        $xen_pages = [
            'toplevel_page_xen-inventory',
            'xen-inventory_page_xen-borrow-log',
            'xen-inventory_page_xen-inventory-settings',
        ];

        // Detect xen_item edit screens — covers both new posts (post_type in URL)
        // and existing post edits (post ID in URL, no post_type param).
        $is_item_screen = false;
        if ( in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
            if ( isset( $_GET['post_type'] ) && 'xen_item' === sanitize_key( $_GET['post_type'] ) ) {
                $is_item_screen = true;
            } elseif ( isset( $_GET['post'] ) && 'xen_item' === get_post_type( absint( $_GET['post'] ) ) ) {
                $is_item_screen = true;
            }
        }

        $is_xen_page = in_array( $hook_suffix, $xen_pages, true );

        if ( ! $is_item_screen && ! $is_xen_page ) {
            return;
        }

        wp_enqueue_style(
            'xen-inventory-admin',
            XEN_INVENTORY_ASSETS_URL . 'css/admin.css',
            [],
            XEN_INVENTORY_VERSION
        );

        wp_enqueue_script(
            'xen-inventory-admin',
            XEN_INVENTORY_ASSETS_URL . 'js/admin.js',
            [ 'jquery' ],
            XEN_INVENTORY_VERSION,
            true
        );

        wp_localize_script( 'xen-inventory-admin', 'xenInventoryAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'xen_admin_nonce' ),
            'i18n'    => [
                'confirmDelete' => __( 'Are you sure you want to delete this log entry?', 'xen-inventory' ),
                'saving'        => __( 'Saving…', 'xen-inventory' ),
                'saved'         => __( 'Saved.', 'xen-inventory' ),
            ],
        ] );
    }
}
