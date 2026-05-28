<?php
/**
 * Registers the xen_department Custom Taxonomy.
 *
 * @package XenInventory\Core
 */

namespace XenInventory\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Taxonomy
 */
class Taxonomy {

    /**
     * Hook into WordPress to register the taxonomy.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'init', [ $this, 'register_xen_department' ] );
    }

    /**
     * Register the xen_department taxonomy.
     *
     * Hierarchical (like categories) so departments can optionally have
     * sub-departments (e.g. IT → Hardware, IT → Software).
     *
     * @return void
     */
    public function register_xen_department(): void {
        $labels = [
            'name'              => _x( 'Departments',         'Taxonomy general name', 'xen-inventory' ),
            'singular_name'     => _x( 'Department',          'Taxonomy singular name', 'xen-inventory' ),
            'search_items'      => __( 'Search Departments',   'xen-inventory' ),
            'all_items'         => __( 'All Departments',      'xen-inventory' ),
            'parent_item'       => __( 'Parent Department',    'xen-inventory' ),
            'parent_item_colon' => __( 'Parent Department:',   'xen-inventory' ),
            'edit_item'         => __( 'Edit Department',      'xen-inventory' ),
            'update_item'       => __( 'Update Department',    'xen-inventory' ),
            'add_new_item'      => __( 'Add New Department',   'xen-inventory' ),
            'new_item_name'     => __( 'New Department Name',  'xen-inventory' ),
            'menu_name'         => __( 'Departments',          'xen-inventory' ),
            'not_found'         => __( 'No departments found.','xen-inventory' ),
        ];

        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'department', 'with_front' => false ],
            'show_in_rest'      => true,
            'capabilities'      => [
                'manage_terms' => 'xen_manage_departments',
                'edit_terms'   => 'xen_manage_departments',
                'delete_terms' => 'xen_manage_departments',
                'assign_terms' => 'edit_posts',
            ],
        ];

        register_taxonomy( 'xen_department', [ 'xen_item' ], $args );
    }
}
