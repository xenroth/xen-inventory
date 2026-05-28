<?php
/**
 * Registers all frontend shortcodes.
 *
 * Shortcodes:
 *   [xen_inventory_display]   — Item grid with filters.
 *   [xen_inventory_calendar]  — FullCalendar borrow history.
 *   [xen_inventory_login]     — Frontend login form.
 *
 * @package XenInventory\Frontend
 */

namespace XenInventory\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Shortcodes
 */
class Shortcodes {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_shortcode( 'xen_inventory_display',  [ $this, 'render_inventory_display'  ] );
        add_shortcode( 'xen_inventory_calendar', [ $this, 'render_inventory_calendar' ] );
        add_shortcode( 'xen_inventory_login',    [ $this, 'render_login_form'         ] );

        // Redirect logged-in users away from pages containing [xen_inventory_login].
        add_action( 'template_redirect', [ $this, 'redirect_logged_in_from_login_page' ] );
    }

    // -----------------------------------------------------------------------
    // [xen_inventory_display]
    // -----------------------------------------------------------------------

    /**
     * Render the inventory item grid.
     *
     * Supported attributes:
     *   department  — Slug or ID of a department to pre-filter.
     *   status      — Pre-filter by status: available | borrowed | maintenance.
     *   columns     — Number of grid columns (default 3).
     *   per_page    — Items per page (default uses global setting).
     *
     * @param  array<string, string>|string $atts Shortcode attributes.
     * @return string  HTML output.
     */
    public function render_inventory_display( $atts ): string {
        if ( ! current_user_can( 'xen_view_inventory' ) ) {
            return '<p class="xen-notice">' . esc_html__( 'You must be logged in to view the inventory.', 'xen-inventory' ) . '</p>';
        }

        $settings = get_option( 'xen_inventory_settings', [] );

        // Derive defaults from settings so the admin can change them globally.
        $default_columns = min( 6, max( 1, absint( $settings['inventory_columns'] ?? 3 ) ) );

        $atts = shortcode_atts(
            [
                'department' => '',
                'status'     => '',
                'columns'    => $default_columns,
                'per_page'   => 0, // 0 = auto-compute from columns (3 rows)
            ],
            $atts,
            'xen_inventory_display'
        );

        // Sanitize attributes.
        $atts['department'] = sanitize_text_field( $atts['department'] );
        $atts['status']     = sanitize_key( $atts['status'] );
        $atts['columns']    = min( 6, max( 1, absint( $atts['columns'] ) ) );

        // Default per_page = columns × 3 (exactly 3 full rows).
        if ( 0 === (int) $atts['per_page'] ) {
            $atts['per_page'] = $atts['columns'] * 3;
        }
        $atts['per_page'] = min( 100, max( 1, absint( $atts['per_page'] ) ) );

        // Allow the filter form (GET params) to override shortcode attribute defaults.
        $allowed_statuses = [ 'available', 'borrowed', 'maintenance' ];
        $get_dept   = sanitize_text_field( $_GET['xen_dept']   ?? '' );
        $get_status = sanitize_key( $_GET['xen_status'] ?? '' );

        if ( $get_dept ) {
            $atts['department'] = $get_dept;
        }
        if ( $get_status && in_array( $get_status, $allowed_statuses, true ) ) {
            $atts['status'] = $get_status;
        }

        // Build WP_Query args.
        $query_args = [
            'post_type'      => 'xen_item',
            'post_status'    => 'publish',
            'posts_per_page' => $atts['per_page'],
            'paged'          => max( 1, absint( get_query_var( 'paged' ) ) ),
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if ( $atts['department'] ) {
            $query_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                [
                    'taxonomy' => 'xen_department',
                    'field'    => is_numeric( $atts['department'] ) ? 'term_id' : 'slug',
                    'terms'    => $atts['department'],
                ],
            ];
        }

        if ( $atts['status'] ) {
            if ( 'borrowed' === $atts['status'] ) {
                // 'borrowed' means any item that has at least one active (unreturned)
                // borrow record — not just items whose status meta = 'borrowed' (which
                // only happens when ALL stock is out).  Query the log table directly
                // so partial-stock borrows are included.
                global $wpdb;
                $table    = $wpdb->prefix . XEN_INVENTORY_LOG_TABLE;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $item_ids = $wpdb->get_col(
                    "SELECT DISTINCT item_id FROM {$table}
                     WHERE action = 'borrowed' AND date_returned IS NULL"
                );
                if ( empty( $item_ids ) ) {
                    // No active borrows — return empty query.
                    $query_args['post__in'] = [ 0 ];
                } else {
                    $query_args['post__in'] = array_map( 'intval', $item_ids );
                    $query_args['orderby']  = 'post__in'; // preserve meaningful sort fallback
                    $query_args['orderby']  = 'title';
                }
            } else {
                $query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    [
                        'key'   => '_xen_item_status',
                        'value' => $atts['status'],
                    ],
                ];
            }
        }

        $items_query = new \WP_Query( $query_args );

        // When filtering by 'borrowed', build a map of item_id → active borrow rows
        // so the view can display who currently has each item.
        $active_borrowers = [];
        if ( 'borrowed' === $atts['status'] || 'borrowed' === ( $_GET['xen_status'] ?? '' ) ) {
            global $wpdb;
            $table            = $wpdb->prefix . XEN_INVENTORY_LOG_TABLE;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $active_rows      = $wpdb->get_results(
                "SELECT item_id, borrower_full_name, borrower_name, borrower_contact, quantity, date_borrowed, date_due
                 FROM {$table}
                 WHERE action = 'borrowed' AND date_returned IS NULL
                 ORDER BY date_borrowed ASC"
            );
            foreach ( $active_rows as $row ) {
                $active_borrowers[ (int) $row->item_id ][] = $row;
            }
        }

        // Get departments for filter dropdown.
        $departments = get_terms( [
            'taxonomy'   => 'xen_department',
            'hide_empty' => false,
        ] );

        ob_start();
        include XEN_INVENTORY_PATH . 'includes/frontend/views/inventory-display.php';
        return ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // [xen_inventory_calendar]
    // -----------------------------------------------------------------------

    /**
     * Render the FullCalendar borrow history view.
     *
     * @param  array<string, string>|string $atts Shortcode attributes.
     * @return string  HTML output.
     */
    public function render_inventory_calendar( $atts ): string {
        $settings = get_option( 'xen_inventory_settings', [] );

        // Check access.
        $allow_guests = ! empty( $settings['allow_guest_calendar'] );
        if ( ! $allow_guests && ! current_user_can( 'xen_view_inventory' ) ) {
            return '<p class="xen-notice">' . esc_html__( 'You must be logged in to view the calendar.', 'xen-inventory' ) . '</p>';
        }

        $calendar_size = $settings['calendar_size'] ?? 'normal';
        if ( ! in_array( $calendar_size, [ 'compact', 'normal', 'large' ], true ) ) {
            $calendar_size = 'normal';
        }

        ob_start();
        include XEN_INVENTORY_PATH . 'includes/frontend/views/inventory-calendar.php';
        return ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // [xen_inventory_login]
    // -----------------------------------------------------------------------

    /**
     * Redirect logged-in users away from any page that contains the
     * [xen_inventory_login] shortcode, before the template is rendered.
     *
     * Fires on template_redirect so headers have not been sent yet.
     *
     * @return void
     */
    public function redirect_logged_in_from_login_page(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $post = get_queried_object();
        if ( ! ( $post instanceof \WP_Post ) ) {
            return;
        }

        if ( has_shortcode( $post->post_content, 'xen_inventory_login' ) ) {
            wp_safe_redirect( home_url( '/inventory/' ), 302 );
            exit;
        }
    }

    /**
     * Render a frontend login form.
     *
     * Redirects to inventory page after successful login.
     *
     * @param  array<string, string>|string $atts Shortcode attributes.
     * @return string  HTML output.
     */
    public function render_login_form( $atts ): string {
        if ( is_user_logged_in() ) {
            $settings = get_option( 'xen_inventory_settings', [] );
            $redirect = home_url( '/inventory/' );
            return '<p class="xen-notice">' .
                sprintf(
                    /* translators: %s: inventory URL */
                    wp_kses(
                        __( 'You are already logged in. <a href="%s">Go to Inventory</a>.', 'xen-inventory' ),
                        [ 'a' => [ 'href' => [] ] ]
                    ),
                    esc_url( $redirect )
                ) .
                '</p>';
        }

        // Determine redirect URL after login.
        $redirect_url = home_url( '/inventory/' );

        ob_start();
        include XEN_INVENTORY_PATH . 'includes/frontend/views/login-form.php';
        return ob_get_clean();
    }
}
