<?php
/**
 * Registers the xen_item Custom Post Type.
 *
 * @package XenInventory\Core
 */

namespace XenInventory\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class PostTypes
 */
class PostTypes {

    /**
     * Hook into WordPress to register the CPT.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'init', [ $this, 'register_xen_item' ] );
    }

    /**
     * Register the xen_item Custom Post Type.
     *
     * @return void
     */
    public function register_xen_item(): void {
        $labels = [
            'name'                  => _x( 'Inventory Items', 'Post type general name', 'xen-inventory' ),
            'singular_name'         => _x( 'Inventory Item',  'Post type singular name', 'xen-inventory' ),
            'menu_name'             => _x( 'XEN Inventory',   'Admin Menu text',          'xen-inventory' ),
            'name_admin_bar'        => _x( 'Inventory Item',  'Add New on Toolbar',        'xen-inventory' ),
            'add_new'               => __( 'Add New',                   'xen-inventory' ),
            'add_new_item'          => __( 'Add New Item',              'xen-inventory' ),
            'new_item'              => __( 'New Item',                   'xen-inventory' ),
            'edit_item'             => __( 'Edit Item',                  'xen-inventory' ),
            'view_item'             => __( 'View Item',                  'xen-inventory' ),
            'all_items'             => __( 'All Items',                  'xen-inventory' ),
            'search_items'          => __( 'Search Items',               'xen-inventory' ),
            'not_found'             => __( 'No items found.',            'xen-inventory' ),
            'not_found_in_trash'    => __( 'No items found in Trash.',   'xen-inventory' ),
            'featured_image'        => __( 'Item Image',                 'xen-inventory' ),
            'set_featured_image'    => __( 'Set item image',             'xen-inventory' ),
            'remove_featured_image' => __( 'Remove item image',          'xen-inventory' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,         // Managed entirely by AdminMenu — prevents duplicate top-level entry.
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'inventory-item', 'with_front' => false ],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'has_archive'        => 'inventory',   // /inventory/ archive page.
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-archive',
            'supports'           => [ 'title', 'editor', 'thumbnail', 'revisions' ],
            'show_in_rest'       => true,          // Block editor & REST API support.
            'taxonomies'         => [ 'xen_department' ],
        ];

        register_post_type( 'xen_item', $args );
    }
}
