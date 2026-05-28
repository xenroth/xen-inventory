<?php
/**
 * Meta Boxes for the xen_item Custom Post Type.
 *
 * Registers and handles:
 *   1. Item Details   — status, quantity, date added.
 *   2. Borrow History — read-only log view.
 *
 * @package XenInventory\Admin
 */

namespace XenInventory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MetaBoxes
 */
class MetaBoxes {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes'  ] );
        add_action( 'save_post',      [ $this, 'save_meta_boxes' ], 10, 2 );
    }

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    /**
     * Add meta boxes to the xen_item edit screen.
     *
     * @return void
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'xen_item_details',
            __( 'Item Details', 'xen-inventory' ),
            [ $this, 'render_item_details' ],
            'xen_item',
            'normal',
            'high'
        );

        add_meta_box(
            'xen_borrow_history',
            __( 'Borrow History', 'xen-inventory' ),
            [ $this, 'render_borrow_history' ],
            'xen_item',
            'normal',
            'default'
        );
    }

    // -----------------------------------------------------------------------
    // Render callbacks
    // -----------------------------------------------------------------------

    /**
     * Render the Item Details meta box.
     *
     * @param  \WP_Post $post Current post object.
     * @return void
     */
    public function render_item_details( \WP_Post $post ): void {
        wp_nonce_field( 'xen_save_item_details_' . $post->ID, 'xen_item_details_nonce' );

        $status         = get_post_meta( $post->ID, '_xen_item_status',   true ) ?: 'available';
        $total_quantity = (int) get_post_meta( $post->ID, '_xen_total_quantity', true );
        $date_added     = get_post_meta( $post->ID, '_xen_date_added',    true ) ?: '';

        $statuses = [
            'available'   => __( 'Available',   'xen-inventory' ),
            'borrowed'    => __( 'Borrowed',     'xen-inventory' ),
            'maintenance' => __( 'Maintenance',  'xen-inventory' ),
        ];

        include XEN_INVENTORY_PATH . 'includes/admin/views/meta-box-item-details.php';
    }

    /**
     * Render the Borrow History meta box.
     *
     * @param  \WP_Post $post Current post object.
     * @return void
     */
    public function render_borrow_history( \WP_Post $post ): void {
        $logs = \XenInventory\Models\InventoryLog::get_logs_for_item( $post->ID );
        include XEN_INVENTORY_PATH . 'includes/admin/views/meta-box-borrow-history.php';
    }

    // -----------------------------------------------------------------------
    // Save
    // -----------------------------------------------------------------------

    /**
     * Save item details meta box data.
     *
     * @param  int      $post_id Post ID being saved.
     * @param  \WP_Post $post    Post object.
     * @return void
     */
    public function save_meta_boxes( int $post_id, \WP_Post $post ): void {
        // Guard: nonce present?
        if ( empty( $_POST['xen_item_details_nonce'] ) ) {
            return;
        }

        // Guard: nonce valid?
        if ( ! wp_verify_nonce(
            sanitize_key( $_POST['xen_item_details_nonce'] ),
            'xen_save_item_details_' . $post_id
        ) ) {
            return;
        }

        // Guard: correct post type?
        if ( 'xen_item' !== $post->post_type ) {
            return;
        }

        // Guard: auto-save / bulk edit?
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Guard: capability check.
        if ( ! current_user_can( 'xen_manage_inventory' ) ) {
            return;
        }

        // --- Status ---
        $allowed_statuses = [ 'available', 'borrowed', 'maintenance' ];
        $status           = sanitize_key( $_POST['_xen_item_status'] ?? 'available' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'available';
        }
        update_post_meta( $post_id, '_xen_item_status', $status );

        // --- Total Quantity ---
        $quantity = absint( $_POST['_xen_total_quantity'] ?? 1 );
        update_post_meta( $post_id, '_xen_total_quantity', $quantity );

        // --- Date Added ---
        $date_added = sanitize_text_field( $_POST['_xen_date_added'] ?? '' );
        // Validate date format Y-m-d.
        if ( $date_added && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_added ) ) {
            $date_added = '';
        }
        if ( $date_added ) {
            update_post_meta( $post_id, '_xen_date_added', $date_added );
        } else {
            delete_post_meta( $post_id, '_xen_date_added' );
        }
    }
}
