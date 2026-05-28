<?php
/**
 * Registers the admin top-level menu and sub-pages.
 *
 * @package XenInventory\Admin
 */

namespace XenInventory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AdminMenu
 */
class AdminMenu {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_pages' ] );
    }

    /**
     * Add admin menu pages.
     *
     * @return void
     */
    public function add_menu_pages(): void {
        // Top-level menu.
        add_menu_page(
            __( 'XEN Inventory', 'xen-inventory' ),
            __( 'XEN Inventory', 'xen-inventory' ),
            'xen_manage_inventory',
            'xen-inventory',
            [ $this, 'render_dashboard' ],
            'dashicons-archive',
            25
        );

        // Dashboard (first sub-page mirrors top-level).
        add_submenu_page(
            'xen-inventory',
            __( 'Dashboard',     'xen-inventory' ),
            __( 'Dashboard',     'xen-inventory' ),
            'xen_manage_inventory',
            'xen-inventory',
            [ $this, 'render_dashboard' ]
        );

        // All Items.
        add_submenu_page(
            'xen-inventory',
            __( 'All Items',     'xen-inventory' ),
            __( 'All Items',     'xen-inventory' ),
            'xen_manage_inventory',
            'edit.php?post_type=xen_item'
        );

        // Add New Item.
        add_submenu_page(
            'xen-inventory',
            __( 'Add New Item',  'xen-inventory' ),
            __( 'Add New Item',  'xen-inventory' ),
            'xen_manage_inventory',
            'post-new.php?post_type=xen_item'
        );

        // Departments.
        add_submenu_page(
            'xen-inventory',
            __( 'Departments',   'xen-inventory' ),
            __( 'Departments',   'xen-inventory' ),
            'xen_manage_departments',
            'edit-tags.php?taxonomy=xen_department&post_type=xen_item'
        );

        // Borrow Log.
        add_submenu_page(
            'xen-inventory',
            __( 'Borrow Log',    'xen-inventory' ),
            __( 'Borrow Log',    'xen-inventory' ),
            'xen_manage_inventory',
            'xen-borrow-log',
            [ $this, 'render_borrow_log' ]
        );

        // Borrowers.
        add_submenu_page(
            'xen-inventory',
            __( 'Borrowers',     'xen-inventory' ),
            __( 'Borrowers',     'xen-inventory' ),
            'xen_manage_inventory',
            'xen-borrowers',
            [ $this, 'render_borrowers' ]
        );

        // Settings.
        add_submenu_page(
            'xen-inventory',
            __( 'Settings',      'xen-inventory' ),
            __( 'Settings',      'xen-inventory' ),
            'manage_options',
            'xen-inventory-settings',
            [ $this, 'render_settings' ]
        );
    }

    /**
     * Render the admin dashboard page.
     *
     * @return void
     */
    public function render_dashboard(): void {
        if ( ! current_user_can( 'xen_manage_inventory' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'xen-inventory' ) );
        }

        $template = XEN_INVENTORY_PATH . 'includes/admin/views/dashboard.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    /**
     * Render the borrow log admin page.
     *
     * @return void
     */
    public function render_borrow_log(): void {
        if ( ! current_user_can( 'xen_manage_inventory' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'xen-inventory' ) );
        }

        $template = XEN_INVENTORY_PATH . 'includes/admin/views/borrow-log.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    /**
     * Render the borrowers admin page.
     *
     * @return void
     */
    public function render_borrowers(): void {
        if ( ! current_user_can( 'xen_manage_inventory' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'xen-inventory' ) );
        }

        $template = XEN_INVENTORY_PATH . 'includes/admin/views/borrowers.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    /**
     * Render the settings admin page.
     *
     * @return void
     */
    public function render_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'xen-inventory' ) );
        }

        $template = XEN_INVENTORY_PATH . 'includes/admin/views/settings.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }
}
