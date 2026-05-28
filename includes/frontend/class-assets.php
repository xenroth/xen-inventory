<?php
/**
 * Enqueue frontend CSS and JavaScript.
 *
 * @package XenInventory\Frontend
 */

namespace XenInventory\Frontend;

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
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    /**
     * Enqueue frontend assets.
     *
     * Scripts / styles are loaded only when a XEN shortcode is present
     * on the current page (checked via has_shortcode after the post content
     * is available via the 'wp' action).
     *
     * @return void
     */
    public function enqueue(): void {
        global $post;

        // Determine whether this is a XEN rewrite view (e.g. /inventory/calendar/).
        // Must be resolved BEFORE the $post guard because rewrite-view requests
        // have no associated WP post object.
        $xen_view = get_query_var( 'xen_view' );

        // Prefer get_queried_object() over $post global — it is reliable even
        // when the main query context is ambiguous (e.g. a Page whose slug
        // previously conflicted with the CPT archive URL).
        $page_post = get_queried_object();
        if ( ! ( $page_post instanceof \WP_Post ) ) {
            $page_post = is_a( $post, 'WP_Post' ) ? $post : null;
        }

        // Whether a XEN shortcode is present on the current page post.
        $has_xen_shortcode = ( $page_post instanceof \WP_Post ) && (
            has_shortcode( $page_post->post_content, 'xen_inventory_display' )
            || has_shortcode( $page_post->post_content, 'xen_inventory_calendar' )
            || has_shortcode( $page_post->post_content, 'xen_inventory_login' )
        );

        // Bail if neither a shortcode page nor a XEN rewrite view nor a single item page.
        if ( ! $has_xen_shortcode && ! $xen_view && ! is_singular( 'xen_item' ) ) {
            return;
        }

        // Core styles.
        wp_enqueue_style(
            'xen-inventory-frontend',
            XEN_INVENTORY_ASSETS_URL . 'css/frontend.css',
            [],
            XEN_INVENTORY_VERSION
        );

        // FullCalendar — loaded for the calendar shortcode and the /inventory/calendar/ view.
        $needs_calendar = ( ( $page_post instanceof \WP_Post ) && has_shortcode( $page_post->post_content, 'xen_inventory_calendar' ) )
            || 'calendar' === $xen_view;

        if ( $needs_calendar ) {
            wp_enqueue_style(
                'fullcalendar',
                'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css',
                [],
                '6.1.11'
            );

            wp_enqueue_script(
                'fullcalendar',
                'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js',
                [],
                '6.1.11',
                true
            );

            wp_enqueue_script(
                'xen-inventory-calendar',
                XEN_INVENTORY_ASSETS_URL . 'js/calendar.js',
                [ 'fullcalendar' ],
                XEN_INVENTORY_VERSION,
                true
            );

            // Convert WordPress locale (en_US) to BCP 47 format (en-US) expected by FullCalendar.
            $fc_locale = str_replace( '_', '-', get_locale() );

            wp_localize_script( 'xen-inventory-calendar', 'xenCalendar', [
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'xen_calendar_nonce' ),
                'updateNonce'  => wp_create_nonce( 'xen_update_borrow' ),
                'returnNonce'  => wp_create_nonce( 'xen_return_nonce' ),
                'canEdit'      => current_user_can( 'xen_return_items' ) ? 1 : 0,
                'locale'       => $fc_locale,
                'firstDay'     => (int) get_option( 'start_of_week' ),
            ] );
        }

        // Inventory display / borrow JS.
        $needs_frontend_js = ( ( $page_post instanceof \WP_Post ) && has_shortcode( $page_post->post_content, 'xen_inventory_display' ) )
            || 'borrow' === $xen_view
            || is_singular( 'xen_item' );

        if ( $needs_frontend_js ) {
            wp_enqueue_script(
                'xen-inventory-frontend',
                XEN_INVENTORY_ASSETS_URL . 'js/frontend.js',
                [ 'jquery' ],
                XEN_INVENTORY_VERSION,
                true
            );

            wp_localize_script( 'xen-inventory-frontend', 'xenInventory', [
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'borrowNonce' => wp_create_nonce( 'xen_borrow_nonce' ),
                'returnNonce' => wp_create_nonce( 'xen_return_nonce' ),
                'updateNonce' => wp_create_nonce( 'xen_update_borrow' ),
                'itemsNonce'  => wp_create_nonce( 'xen_items_nonce' ),
                'canReturn'   => current_user_can( 'xen_return_items' )   ? 1 : 0,
                'canEdit'     => current_user_can( 'xen_manage_inventory' ) ? 1 : 0,
                'i18n'        => [
                    'borrowTitle'         => __( 'Borrow: %s',                                           'xen-inventory' ),
                    'confirmBorrow'       => __( 'Confirm Borrow',                                       'xen-inventory' ),
                    'borrowSuccess'       => __( 'Item borrowed successfully!',                           'xen-inventory' ),
                    'returnSuccess'       => __( 'Item returned successfully!',                           'xen-inventory' ),
                    'errorGeneric'        => __( 'An error occurred. Please try again.',                  'xen-inventory' ),
                    'confirm'             => __( 'Are you sure?',                                        'xen-inventory' ),
                    'saving'              => __( 'Saving…',                                              'xen-inventory' ),
                    'available'           => __( 'Available',                                            'xen-inventory' ),
                    /* translators: %d: partial quantity being returned */
                    'confirmPartialReturn' => __( 'Return %d item(s)? The rest will remain as borrowed.', 'xen-inventory' ),
                    /* translators: %d: remaining borrowed quantity */
                    'qtyBorrowed'         => __( 'Qty borrowed: %d',                                     'xen-inventory' ),
                ],
            ] );
        }
    }
}
