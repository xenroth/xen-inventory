<?php
/**
 * AJAX action handlers (both logged-in and nopriv where appropriate).
 *
 * @package XenInventory\Core
 */

namespace XenInventory\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AjaxHandlers
 */
class AjaxHandlers {

    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public function register(): void {
        // Borrow an item (logged-in users only).
        add_action( 'wp_ajax_xen_borrow_item',  [ $this, 'borrow_item' ] );

        // Return an item (logged-in users only).
        add_action( 'wp_ajax_xen_return_item',  [ $this, 'return_item' ] );

        // Delete a log entry (admin only).
        add_action( 'wp_ajax_xen_delete_log',   [ $this, 'delete_log'  ] );

        // Fetch calendar events (public – also available to non-logged-in users if desired).
        add_action( 'wp_ajax_xen_get_calendar_events',        [ $this, 'get_calendar_events' ] );
        add_action( 'wp_ajax_nopriv_xen_get_calendar_events', [ $this, 'get_calendar_events' ] );

        // Fetch item availability (logged-in users only — inventory display requires xen_view_inventory).
        add_action( 'wp_ajax_xen_get_items', [ $this, 'get_items' ] );
    }

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    /**
     * Handle borrow-item AJAX request.
     *
     * Expected POST fields:
     *   nonce      — xen_borrow_nonce
     *   item_id    — int
     *   quantity   — int (optional, defaults to 1)
     *   date_due   — Y-m-d (optional)
     *   notes      — string (optional)
     *
     * @return void  Outputs JSON and exits.
     */
    public function borrow_item(): void {
        check_ajax_referer( 'xen_borrow_nonce', 'nonce' );

        if ( ! current_user_can( 'xen_borrow_items' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'xen-inventory' ) ], 403 );
        }

        $item_id  = absint( $_POST['item_id'] ?? 0 );
        $quantity = absint( $_POST['quantity'] ?? 1 );
        $date_due = sanitize_text_field( $_POST['date_due'] ?? '' );
        $notes    = sanitize_textarea_field( $_POST['notes'] ?? '' );

        if ( ! $item_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid item.', 'xen-inventory' ) ], 400 );
        }

        // Quantity must be at least 1.
        if ( $quantity < 1 ) {
            wp_send_json_error( [ 'message' => __( 'Quantity must be at least 1.', 'xen-inventory' ) ], 400 );
        }

        // Verify the item is a published xen_item post.
        $post = get_post( $item_id );
        if ( ! $post || 'xen_item' !== $post->post_type || 'publish' !== $post->post_status ) {
            wp_send_json_error( [ 'message' => __( 'Invalid item.', 'xen-inventory' ) ], 400 );
        }

        // Validate date_due format if provided.
        if ( $date_due && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_due ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid date format.', 'xen-inventory' ) ], 400 );
        }

        // Validate requested quantity against available stock.
        $available = \XenInventory\Models\InventoryLog::get_available_quantity( $item_id );
        if ( $available <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'This item is currently unavailable.', 'xen-inventory' ) ], 400 );
        }
        if ( $quantity > $available ) {
            wp_send_json_error(
                [
                    /* translators: %d: number of units available */
                    'message' => sprintf( __( 'Only %d unit(s) available.', 'xen-inventory' ), $available ),
                ],
                400
            );
        }

        $result = \XenInventory\Models\InventoryLog::create_log( [
            'item_id'       => $item_id,
            'user_id'       => get_current_user_id(),
            'borrower_name' => wp_get_current_user()->display_name,
            'action'        => 'borrowed',
            'quantity'      => $quantity,
            'date_borrowed' => current_time( 'mysql', true ), // UTC.
            'date_due'      => $date_due ? $date_due . ' 00:00:00' : null,
            'notes'         => $notes,
        ] );

        if ( $result ) {
            // Update item status: mark as 'borrowed' only when no stock remains.
            $available_after = \XenInventory\Models\InventoryLog::get_available_quantity( $item_id );
            if ( $available_after <= 0 ) {
                update_post_meta( $item_id, '_xen_item_status', 'borrowed' );
            }
            wp_send_json_success( [ 'message' => __( 'Item borrowed successfully.', 'xen-inventory' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Could not log the borrow action.', 'xen-inventory' ) ], 500 );
        }
    }

    /**
     * Handle return-item AJAX request.
     *
     * Expected POST fields:
     *   nonce   — xen_return_nonce
     *   log_id  — int  (the log row to close)
     *   notes   — string (optional)
     *
     * @return void
     */
    public function return_item(): void {
        check_ajax_referer( 'xen_return_nonce', 'nonce' );

        if ( ! current_user_can( 'xen_return_items' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'xen-inventory' ) ], 403 );
        }

        $log_id = absint( $_POST['log_id'] ?? 0 );
        $notes  = sanitize_textarea_field( $_POST['notes'] ?? '' );

        if ( ! $log_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid log entry.', 'xen-inventory' ) ], 400 );
        }

        $updated = \XenInventory\Models\InventoryLog::close_log( $log_id, $notes );

        if ( $updated ) {
            wp_send_json_success( [ 'message' => __( 'Item returned successfully.', 'xen-inventory' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Could not update the log entry.', 'xen-inventory' ) ], 500 );
        }
    }

    /**
     * Hard-delete a borrow log entry (admin-only).
     *
     * Expected POST fields:
     *   nonce   — xen_admin_nonce
     *   log_id  — int
     *
     * @return void
     */
    public function delete_log(): void {
        check_ajax_referer( 'xen_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'xen_manage_inventory' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'xen-inventory' ) ], 403 );
        }

        $log_id = absint( $_POST['log_id'] ?? 0 );
        if ( ! $log_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid log entry.', 'xen-inventory' ) ], 400 );
        }

        $deleted = \XenInventory\Models\InventoryLog::delete_log( $log_id );

        if ( $deleted ) {
            wp_send_json_success( [ 'message' => __( 'Log entry deleted.', 'xen-inventory' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Could not delete the log entry.', 'xen-inventory' ) ], 500 );
        }
    }

    /**
     * Return calendar events as JSON for FullCalendar.
     *
     * Accepts GET params: start (Y-m-d), end (Y-m-d).
     *
     * @return void
     */
    public function get_calendar_events(): void {
        check_ajax_referer( 'xen_calendar_nonce', 'nonce' );

        $start = sanitize_text_field( $_GET['start'] ?? '' );
        $end   = sanitize_text_field( $_GET['end']   ?? '' );

        $events = \XenInventory\Models\InventoryLog::get_calendar_events( $start, $end );

        wp_send_json_success( $events );
    }

    /**
     * Return items list as JSON for the frontend display.
     *
     * @return void
     */
    public function get_items(): void {
        check_ajax_referer( 'xen_items_nonce', 'nonce' );

        $department = absint( $_GET['department'] ?? 0 );
        $status     = sanitize_key( $_GET['status'] ?? '' );

        $args = [
            'post_type'      => 'xen_item',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if ( $department ) {
            $args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                [
                    'taxonomy' => 'xen_department',
                    'field'    => 'term_id',
                    'terms'    => $department,
                ],
            ];
        }

        if ( $status ) {
            $args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'   => '_xen_item_status',
                    'value' => $status,
                ],
            ];
        }

        $query = new \WP_Query( $args );
        $items = [];

        foreach ( $query->posts as $post ) {
            $items[] = [
                'id'          => $post->ID,
                'title'       => get_the_title( $post ),
                'status'      => get_post_meta( $post->ID, '_xen_item_status',   true ),
                'quantity'    => (int) get_post_meta( $post->ID, '_xen_total_quantity', true ),
                'date_added'  => get_post_meta( $post->ID, '_xen_date_added',    true ),
                'thumbnail'   => get_the_post_thumbnail_url( $post, 'thumbnail' ),
                'permalink'   => get_permalink( $post ),
                'departments' => wp_get_post_terms( $post->ID, 'xen_department', [ 'fields' => 'names' ] ),
            ];
        }

        wp_send_json_success( $items );
    }
}
