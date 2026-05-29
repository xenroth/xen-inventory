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

        // Update a borrow log entry (staff/admin — edit due date, notes, or mark returned).
        add_action( 'wp_ajax_xen_update_borrow', [ $this, 'update_borrow' ] );

        // Delete a log entry (admin only).
        add_action( 'wp_ajax_xen_delete_log',   [ $this, 'delete_log'  ] );

        // Fetch calendar events (public – also available to non-logged-in users if desired).
        add_action( 'wp_ajax_xen_get_calendar_events',        [ $this, 'get_calendar_events' ] );
        add_action( 'wp_ajax_nopriv_xen_get_calendar_events', [ $this, 'get_calendar_events' ] );

        // Fetch item availability (logged-in users only — inventory display requires xen_view_inventory).
        add_action( 'wp_ajax_xen_get_items', [ $this, 'get_items' ] );

        // Export borrow log as CSV (admin only, standard form POST via admin-post.php).
        add_action( 'admin_post_xen_export_log', [ $this, 'export_log_csv' ] );

        // Export return log as CSV.
        add_action( 'admin_post_xen_export_return_log', [ $this, 'export_return_log_csv' ] );

        // Purge all borrow/return history (manage_options only).
        add_action( 'admin_post_xen_purge_borrow_log', [ $this, 'purge_borrow_log' ] );

        // Export borrowers list as CSV.
        add_action( 'admin_post_xen_export_borrowers_csv', [ $this, 'export_borrowers_csv' ] );

        // Entity name autocomplete on borrow forms (logged-in users only).
        add_action( 'wp_ajax_xen_get_entity_suggestions', [ $this, 'get_entity_suggestions' ] );

        // Delete a borrower entity (admin only — blocks if active borrows exist).
        add_action( 'wp_ajax_xen_delete_borrower', [ $this, 'delete_borrower' ] );
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

        $item_id            = absint( $_POST['item_id'] ?? 0 );
        $quantity           = absint( $_POST['quantity'] ?? 1 );
        $date_due           = sanitize_text_field(    wp_unslash( $_POST['date_due']            ?? '' ) );
        $notes              = sanitize_textarea_field( wp_unslash( $_POST['notes']              ?? '' ) );
        $borrower_full_name = sanitize_text_field(    wp_unslash( $_POST['borrower_full_name']  ?? '' ) );
        $borrower_contact   = sanitize_text_field(    wp_unslash( $_POST['borrower_contact']    ?? '' ) );
        $borrow_tags        = sanitize_text_field(    wp_unslash( $_POST['borrow_tags']         ?? '' ) );

        if ( ! $item_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid item.', 'xen-inventory' ) ], 400 );
        }

        if ( '' === $borrower_full_name ) {
            wp_send_json_error( [ 'message' => __( 'Please enter the full name or entity of the borrower.', 'xen-inventory' ) ], 400 );
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

        // Validate date_due format if provided (accepts YYYY-MM-DD or YYYY-MM-DDTHH:MM).
        if ( $date_due && ! preg_match( '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $date_due ) ) {
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
                    'code'      => 'qty_exceeded',
                    /* translators: 1: available units, 2: requested units */
                    'message'   => sprintf( __( 'Only %1$d unit(s) available, but you\'re requesting %2$d. The quantity has been adjusted to the maximum available.', 'xen-inventory' ), $available, $quantity ),
                    'available' => $available,
                ],
                400
            );
        }

        $result = \XenInventory\Models\InventoryLog::create_log( [
            'item_id'            => $item_id,
            'user_id'            => get_current_user_id(),
            'borrower_name'      => wp_get_current_user()->display_name,
            'borrower_full_name' => $borrower_full_name,
            'borrower_contact'   => $borrower_contact,
            'borrow_tags'        => $borrow_tags,
            'action'             => 'borrowed',
            'quantity'           => $quantity,
            'date_borrowed'      => current_time( 'mysql', true ), // UTC.
            'date_due'           => $date_due ? ( str_replace( 'T', ' ', $date_due ) . ( strpos( $date_due, 'T' ) !== false ? ':00' : ' 00:00:00' ) ) : null,
            'notes'              => $notes,
        ] );

        if ( $result ) {
            // Update item status: mark as 'borrowed' only when no stock remains.
            $available_after = \XenInventory\Models\InventoryLog::get_available_quantity( $item_id );
            if ( $available_after <= 0 ) {
                update_post_meta( $item_id, '_xen_item_status', 'borrowed' );
            }
            \XenInventory\Core\AuditLog::record( 'borrow', 'item', $item_id, $post->post_title, [
                'log_id'             => $result,
                'borrower'           => $borrower_full_name,
                'quantity'           => $quantity,
                'date_due'           => $date_due,
            ] );
            wp_send_json_success( [ 'message' => __( 'Item borrowed successfully.', 'xen-inventory' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Could not log the borrow action.', 'xen-inventory' ) ], 500 );
        }
    }

    /**
     * Handle return-item AJAX request.
     *
     * Expected POST fields:
     *   nonce          — xen_return_nonce
     *   log_id         — int  (the log row to close)
     *   qty_returned   — int  (units being returned; 0 / absent = return all)
     *   return_notes   — string  (mandatory return remark)
     *   item_condition — string  (mandatory: good | slight_damage | total_damage)
     *
     * @return void
     */
    public function return_item(): void {
        check_ajax_referer( 'xen_return_nonce', 'nonce' );

        if ( ! current_user_can( 'xen_return_items' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'xen-inventory' ) ], 403 );
        }

        $log_id         = absint( $_POST['log_id']        ?? 0 );
        $qty_returned   = absint( $_POST['qty_returned']   ?? 0 );
        $return_notes   = sanitize_textarea_field( wp_unslash( $_POST['return_notes']   ?? '' ) );
        $item_condition = sanitize_key( wp_unslash( $_POST['item_condition'] ?? '' ) );

        // Optional return date/time — must be a valid datetime string.
        $date_returned = '';
        if ( ! empty( $_POST['date_returned'] ) ) {
            $dr = sanitize_text_field( wp_unslash( $_POST['date_returned'] ) );
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2})?$/', $dr ) ) {
                $date_returned = str_replace( 'T', ' ', $dr );
                if ( 16 === strlen( $date_returned ) ) {
                    $date_returned .= ':00';
                }
            }
        }

        if ( ! $log_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid log entry.', 'xen-inventory' ) ], 400 );
        }

        // Return remarks are mandatory.
        if ( '' === $return_notes ) {
            wp_send_json_error( [ 'message' => __( 'Return remarks are required.', 'xen-inventory' ) ], 400 );
        }

        // Item condition is mandatory and must be one of the allowed values.
        $allowed_conditions = [ 'good', 'slight_damage', 'total_damage' ];
        if ( ! in_array( $item_condition, $allowed_conditions, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Please select the item condition on return.', 'xen-inventory' ) ], 400 );
        }

        $returned_by_user_id = get_current_user_id();

        // qty_returned = 0 means "return all" — delegate to close_log which
        // handles full returns and the item-status update.
        if ( 0 === $qty_returned ) {
            $ok = \XenInventory\Models\InventoryLog::close_log( $log_id, $return_notes, $item_condition, $date_returned, $returned_by_user_id );
        } else {
            $ok = \XenInventory\Models\InventoryLog::partial_return( $log_id, $qty_returned, $return_notes, $item_condition, $date_returned, $returned_by_user_id );
        }

        if ( $ok ) {
            \XenInventory\Core\AuditLog::record( 'return', 'log', $log_id, 'Log #' . $log_id, [
                'qty_returned'   => $qty_returned ?: 'all',
                'return_notes'   => $return_notes,
                'item_condition' => $item_condition,
            ] );
            wp_send_json_success( [ 'message' => __( 'Item returned successfully.', 'xen-inventory' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Could not update the log entry.', 'xen-inventory' ) ], 500 );
        }
    }

    /**
     * Update a borrow log entry — edit notes, due date, or mark as returned.
     *
     * Accepts (POST): log_id, nonce, and optionally notes, date_due, date_returned.
     * Requires: xen_return_items capability.
     *
     * @return void  Sends JSON response.
     */
    public function update_borrow(): void {
        check_ajax_referer( 'xen_update_borrow', 'nonce' );

        if ( ! current_user_can( 'xen_return_items' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'xen-inventory' ) ], 403 );
        }

        $log_id = isset( $_POST['log_id'] ) ? absint( wp_unslash( $_POST['log_id'] ) ) : 0;
        if ( ! $log_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid log ID.', 'xen-inventory' ) ], 400 );
        }

        $data = [];

        if ( isset( $_POST['notes'] ) ) {
            $data['notes'] = sanitize_textarea_field( wp_unslash( $_POST['notes'] ) );
        }

        if ( ! empty( $_POST['date_due'] ) ) {
            $date_due = sanitize_text_field( wp_unslash( $_POST['date_due'] ) );
            // Validate format YYYY-MM-DD or YYYY-MM-DDTHH:MM.
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $date_due ) ) {
                $data['date_due'] = str_replace( 'T', ' ', $date_due ) . ( strpos( $date_due, 'T' ) !== false ? ':00' : '' );
            }
        }

        if ( ! empty( $_POST['date_returned'] ) ) {
            $date_returned = sanitize_text_field( wp_unslash( $_POST['date_returned'] ) );
            // Accept both Y-m-d (date only) and Y-m-dTHH:MM (datetime-local) formats.
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $date_returned ) ) {
                $datetime_val = str_replace( 'T', ' ', $date_returned );
                if ( strpos( $date_returned, 'T' ) !== false ) {
                    $datetime_val .= ':00'; // append seconds
                }
                $data['date_returned']        = $datetime_val;
                $data['action']               = 'returned';
                $data['returned_by_user_id']  = get_current_user_id();
            }
        } elseif ( isset( $_POST['date_returned'] ) && '' === $_POST['date_returned'] ) {
            // Explicitly clearing the return date — re-open the record.
            $data['date_returned']       = null;
            $data['action']              = 'borrowed';
            $data['returned_by_user_id'] = null;
        }

        if ( isset( $_POST['return_notes'] ) ) {
            $data['return_notes'] = sanitize_textarea_field( wp_unslash( $_POST['return_notes'] ) );
        }

        if ( isset( $_POST['item_condition'] ) ) {
            $cond = sanitize_key( wp_unslash( $_POST['item_condition'] ) );
            if ( '' === $cond || in_array( $cond, [ 'good', 'slight_damage', 'total_damage' ], true ) ) {
                $data['item_condition'] = '' === $cond ? null : $cond;
            }
        }

        if ( empty( $data ) ) {
            wp_send_json_error( [ 'message' => __( 'No changes submitted.', 'xen-inventory' ) ], 400 );
        }

        $ok = \XenInventory\Models\InventoryLog::update_log( $log_id, $data );

        if ( $ok ) {
            \XenInventory\Core\AuditLog::record( 'update', 'log', $log_id, 'Log #' . $log_id, $data );
            wp_send_json_success( [ 'message' => __( 'Record updated.', 'xen-inventory' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Could not update the record.', 'xen-inventory' ) ], 500 );
        }
    }

    /**
     * Export the borrow log as a UTF-8 CSV file.
     *
     * Triggered by a standard form POST to admin-post.php with action=xen_export_log.
     * Respects the same search/status/date filters as the borrow log screen.
     *
     * @return void  Outputs CSV file and exits.
     */
    public function export_log_csv(): void {
        check_admin_referer( 'xen_export_log' );

        if ( ! current_user_can( 'xen_manage_inventory' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'xen-inventory' ), 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . XEN_INVENTORY_LOG_TABLE;

        // Rebuild filters (same validation as borrow-log.php view).
        $filter_search    = sanitize_text_field( wp_unslash( $_POST['xen_search']    ?? '' ) );
        $filter_status    = sanitize_key(         wp_unslash( $_POST['xen_status']    ?? '' ) );
        $filter_date_from = sanitize_text_field( wp_unslash( $_POST['xen_date_from'] ?? '' ) );
        $filter_date_to   = sanitize_text_field( wp_unslash( $_POST['xen_date_to']   ?? '' ) );

        if ( $filter_date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date_from ) ) {
            $filter_date_from = '';
        }
        if ( $filter_date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date_to ) ) {
            $filter_date_to = '';
        }

        $where      = [];
        $where_args = [];

        if ( $filter_search ) {
            $like         = '%' . $wpdb->esc_like( $filter_search ) . '%';
            $where[]      = '( l.borrower_name LIKE %s OR l.borrower_full_name LIKE %s OR l.borrower_contact LIKE %s OR p.post_title LIKE %s )';
            $where_args[] = $like;
            $where_args[] = $like;
            $where_args[] = $like;
            $where_args[] = $like;
        }

        $allowed_statuses = [ 'open', 'returned' ];
        if ( $filter_status && in_array( $filter_status, $allowed_statuses, true ) ) {
            if ( 'open' === $filter_status ) {
                $where[] = "( l.action = 'borrowed' AND l.date_returned IS NULL )";
            } else {
                $where[] = 'l.date_returned IS NOT NULL';
            }
        }

        if ( $filter_date_from ) {
            $where[]      = 'l.date_borrowed >= %s';
            $where_args[] = $filter_date_from . ' 00:00:00';
        }
        if ( $filter_date_to ) {
            $where[]      = 'l.date_borrowed <= %s';
            $where_args[] = $filter_date_to . ' 23:59:59';
        }

        $where_sql  = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';
        $base_query = "FROM {$table} l LEFT JOIN {$wpdb->posts} p ON p.ID = l.item_id {$where_sql}";

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $logs = $where_args
            ? $wpdb->get_results( $wpdb->prepare(
                "SELECT l.*, p.post_title AS item_title {$base_query} ORDER BY l.date_borrowed DESC",
                $where_args
            ) )
            : $wpdb->get_results(
                "SELECT l.*, p.post_title AS item_title {$base_query} ORDER BY l.date_borrowed DESC"
            );
        // phpcs:enable

        $date_fmt = get_option( 'date_format' );
        $filename = 'xen-borrow-log-' . gmdate( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );

        // UTF-8 BOM so Excel opens the file with correct encoding.
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, [
            __( 'ID',         'xen-inventory' ),
            __( 'Item',       'xen-inventory' ),
            __( 'Borrower',   'xen-inventory' ),
            __( 'Full Name',  'xen-inventory' ),
            __( 'Contact',    'xen-inventory' ),
            __( 'Action',     'xen-inventory' ),
            __( 'Quantity',   'xen-inventory' ),
            __( 'Borrowed',   'xen-inventory' ),
            __( 'Due Date',   'xen-inventory' ),
            __( 'Returned',   'xen-inventory' ),
            __( 'Status',     'xen-inventory' ),
            __( 'Notes',      'xen-inventory' ),
        ] );

        foreach ( $logs as $log ) {
            $due      = $log->date_due      ? wp_date( $date_fmt, strtotime( $log->date_due      ) ) : '';
            $returned = $log->date_returned ? wp_date( $date_fmt, strtotime( $log->date_returned ) ) : '';

            if ( $log->date_returned ) {
                $status = __( 'Returned', 'xen-inventory' );
            } elseif ( $log->date_due && strtotime( $log->date_due ) < time() ) {
                $status = __( 'Overdue', 'xen-inventory' );
            } else {
                $status = __( 'Open', 'xen-inventory' );
            }

            fputcsv( $out, [
                (int) $log->id,
                $log->item_title             ?? '',
                $log->borrower_name          ?? '',
                $log->borrower_full_name     ?? '',
                $log->borrower_contact       ?? '',
                ucfirst( $log->action ),
                (int) $log->quantity,
                wp_date( $date_fmt, strtotime( $log->date_borrowed ) ),
                $due,
                $returned,
                $status,
                $log->notes                  ?? '',
            ] );
        }

        fclose( $out );
        exit;
    }

    /**
     * Export the return log as a UTF-8 CSV file.
     *
     * Triggered by a form POST to admin-post.php with action=xen_export_return_log.
     * Respects the same search/condition/date filters as the return log screen.
     *
     * @return void  Outputs CSV file and exits.
     */
    public function export_return_log_csv(): void {
        check_admin_referer( 'xen_export_return_log' );

        if ( ! current_user_can( 'xen_manage_inventory' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'xen-inventory' ), 403 );
        }

        // Read and validate filters (same field names as return-log.php view).
        $search    = sanitize_text_field( wp_unslash( $_POST['xen_search']    ?? '' ) );
        $condition = sanitize_key(         wp_unslash( $_POST['xen_condition'] ?? '' ) );
        $date_from = sanitize_text_field( wp_unslash( $_POST['xen_date_from'] ?? '' ) );
        $date_to   = sanitize_text_field( wp_unslash( $_POST['xen_date_to']   ?? '' ) );

        if ( $date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
            $date_from = '';
        }
        if ( $date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
            $date_to = '';
        }

        // Fetch all matching rows (no pagination for export).
        $logs = \XenInventory\Models\InventoryLog::get_return_logs(
            $search, $condition, $date_from, $date_to, PHP_INT_MAX, 1
        );

        $condition_labels = [
            'good'         => __( 'Good',              'xen-inventory' ),
            'slight_damage'=> __( 'Slightly Damaged',  'xen-inventory' ),
            'total_damage' => __( 'Totally Damaged',   'xen-inventory' ),
        ];

        $date_fmt = get_option( 'date_format' );
        $filename = 'xen-return-log-' . gmdate( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );

        // UTF-8 BOM so Excel opens with correct encoding.
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, [
            __( 'ID',                    'xen-inventory' ),
            __( 'Item',                  'xen-inventory' ),
            __( 'Borrower (Entity)',     'xen-inventory' ),
            __( 'Borrower (Full Name)',  'xen-inventory' ),
            __( 'Contact',               'xen-inventory' ),
            __( 'Qty Returned',          'xen-inventory' ),
            __( 'Condition',             'xen-inventory' ),
            __( 'Return Remarks',        'xen-inventory' ),
            __( 'Return Date',           'xen-inventory' ),
            __( 'Returned By (WP Account)', 'xen-inventory' ),
            __( 'Original Borrow Date',  'xen-inventory' ),
            __( 'Tags',                  'xen-inventory' ),
            __( 'Borrow Notes',          'xen-inventory' ),
        ] );

        foreach ( $logs as $log ) {
            $cond_label    = $condition_labels[ $log->item_condition ] ?? ( $log->item_condition ?? '' );
            $date_returned = $log->date_returned ? wp_date( $date_fmt, strtotime( $log->date_returned ) ) : '';
            $date_borrowed = $log->date_borrowed ? wp_date( $date_fmt, strtotime( $log->date_borrowed ) ) : '';

            fputcsv( $out, [
                (int) $log->id,
                $log->item_title                ?? '',
                $log->borrower_name             ?? '',
                $log->borrower_full_name        ?? '',
                $log->borrower_contact          ?? '',
                (int) $log->quantity,
                $cond_label,
                $log->return_notes              ?? '',
                $date_returned,
                $log->returned_by_display_name  ?? '',
                $date_borrowed,
                $log->borrow_tags               ?? '',
                $log->notes                     ?? '',
            ] );
        }

        fclose( $out );
        exit;
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
            \XenInventory\Core\AuditLog::record( 'delete', 'log', $log_id, 'Log #' . $log_id );
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

        $start = sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) );
        $end   = sanitize_text_field( wp_unslash( $_GET['end']   ?? '' ) );

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

    /**
     * Purge all borrow/return history records (Danger Zone).
     *
     * Form POST to admin-post.php with:
     *   action              — xen_purge_borrow_log
     *   _wpnonce            — xen_purge_borrow_log nonce
     *   xen_purge_reason    — required reason text
     *   xen_purge_confirm   — must equal exactly "CONFIRM DELETION"
     *
     * @return void
     */
    public function purge_borrow_log(): void {
        check_admin_referer( 'xen_purge_borrow_log' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'xen-inventory' ), 403 );
        }

        $confirm = sanitize_text_field( wp_unslash( $_POST['xen_purge_confirm'] ?? '' ) );
        $reason  = sanitize_textarea_field( wp_unslash( $_POST['xen_purge_reason'] ?? '' ) );

        if ( 'CONFIRM DELETION' !== $confirm ) {
            wp_safe_redirect(
                add_query_arg(
                    [ 'page' => 'xen-inventory-settings', 'xen_purge' => 'invalid' ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        if ( '' === $reason ) {
            wp_safe_redirect(
                add_query_arg(
                    [ 'page' => 'xen-inventory-settings', 'xen_purge' => 'no_reason' ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        $count = \XenInventory\Models\InventoryLog::delete_all_logs( $reason, get_current_user_id() );

        wp_safe_redirect(
            add_query_arg(
                [ 'page' => 'xen-inventory-settings', 'xen_purge' => 'done', 'xen_purge_count' => $count ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Export borrowers summary as a UTF-8 CSV file.
     *
     * Triggered by a standard form POST to admin-post.php with action=xen_export_borrowers_csv.
     *
     * @return void  Outputs CSV and exits.
     */
    /**
     * Return entity name suggestions matching the supplied search term.
     *
     * Used by the borrow-form "Full Name / Entity" autocomplete field.
     * Returns up to 8 distinct names ordered by borrow frequency (most used first).
     *
     * @return void  Outputs JSON and exits.
     */
    public function get_entity_suggestions(): void {
        check_ajax_referer( 'xen_entity_suggestions_nonce', 'nonce' );

        if ( ! current_user_can( 'xen_borrow_items' ) ) {
            wp_send_json_error( [], 403 );
        }

        $term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
        if ( strlen( $term ) < 2 ) {
            wp_send_json_success( [] );
        }

        global $wpdb;
        $table = $wpdb->prefix . XEN_INVENTORY_LOG_TABLE;
        $like  = '%' . $wpdb->esc_like( $term ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT COALESCE(NULLIF(TRIM(borrower_full_name), ''), TRIM(borrower_name)) AS entity_name
                 FROM {$table}
                 WHERE COALESCE(NULLIF(TRIM(borrower_full_name), ''), TRIM(borrower_name)) LIKE %s
                 GROUP BY entity_name
                 ORDER BY COUNT(*) DESC, entity_name ASC
                 LIMIT 8",
                $like
            )
        );

        wp_send_json_success( array_values( array_filter( $results ) ) );
    }

    public function export_borrowers_csv(): void {
        check_admin_referer( 'xen_export_borrowers_csv' );

        if ( ! current_user_can( 'xen_manage_inventory' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'xen-inventory' ), 403 );
        }

        $borrowers = \XenInventory\Models\InventoryLog::get_borrowers_summary();
        $date_fmt  = get_option( 'date_format' );
        $filename  = 'xen-borrowers-' . gmdate( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.

        fputcsv( $out, [
            __( 'Entity / Borrower Name', 'xen-inventory' ),
            __( 'Contact',                'xen-inventory' ),
            __( 'Total Transactions',     'xen-inventory' ),
            __( 'Active Borrows',         'xen-inventory' ),
            __( 'Overdue',                'xen-inventory' ),
            __( 'Returned',               'xen-inventory' ),
            __( 'Last Borrowed',          'xen-inventory' ),
        ] );

        foreach ( $borrowers as $row ) {
            fputcsv( $out, [
                $row->display_name       ?? '',
                $row->borrower_contact   ?? '',
                (int) $row->total_borrows,
                (int) $row->active_borrows,
                (int) $row->overdue_borrows,
                (int) $row->returned_borrows,
                $row->last_borrowed
                    ? wp_date( $date_fmt, strtotime( $row->last_borrowed ) )
                    : '',
            ] );
        }

        fclose( $out );
        exit;
    }

    /**
     * Mark a borrower entity as deleted.
     *
     * The entity's borrow records are preserved; only the entity_key is added
     * to the `xen_deleted_borrowers` option so it can be flagged in views.
     * Deletion is blocked if the entity currently has any open borrow records.
     */
    public function delete_borrower(): void {
        check_ajax_referer( 'xen_delete_borrower', 'nonce' );

        if ( ! current_user_can( 'xen_manage_inventory' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'xen-inventory' ) ] );
        }

        $entity_name = sanitize_text_field( wp_unslash( $_POST['entity_name'] ?? '' ) );
        if ( '' === $entity_name ) {
            wp_send_json_error( [ 'message' => __( 'Invalid borrower.', 'xen-inventory' ) ] );
        }

        $active = \XenInventory\Models\InventoryLog::count_active_borrows_for_entity( $entity_name );
        if ( $active > 0 ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %d: number of open borrow records */
                    _n(
                        'Cannot delete: this borrower still has %d active borrow. Return all items first.',
                        'Cannot delete: this borrower still has %d active borrows. Return all items first.',
                        $active,
                        'xen-inventory'
                    ),
                    $active
                ),
            ] );
        }

        $entity_key = strtolower( trim( $entity_name ) );
        $deleted    = get_option( 'xen_deleted_borrowers', [] );
        if ( ! is_array( $deleted ) ) {
            $deleted = [];
        }

        if ( ! in_array( $entity_key, $deleted, true ) ) {
            $deleted[] = $entity_key;
            update_option( 'xen_deleted_borrowers', $deleted );
        }

        wp_send_json_success( [ 'message' => __( 'Borrower deleted.', 'xen-inventory' ) ] );
    }
}
