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

        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $has_xen_shortcode = has_shortcode( $post->post_content, 'xen_inventory_display' )
            || has_shortcode( $post->post_content, 'xen_inventory_calendar' )
            || has_shortcode( $post->post_content, 'xen_inventory_login' );

        // Also load on XEN custom rewrite views.
        $xen_view = get_query_var( 'xen_view' );

        if ( ! $has_xen_shortcode && ! $xen_view ) {
            return;
        }

        // Core styles.
        wp_enqueue_style(
            'xen-inventory-frontend',
            XEN_INVENTORY_ASSETS_URL . 'css/frontend.css',
            [],
            XEN_INVENTORY_VERSION
        );

        // FullCalendar (from CDN — swap for local copy if needed).
        if ( has_shortcode( $post->post_content, 'xen_inventory_calendar' ) || 'calendar' === $xen_view ) {
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

            wp_localize_script( 'xen-inventory-calendar', 'xenCalendar', [
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'xen_calendar_nonce' ),
                'locale'    => get_locale(),
                'firstDay'  => (int) get_option( 'start_of_week' ),
            ] );
        }

        // Inventory display / borrow JS.
        if ( has_shortcode( $post->post_content, 'xen_inventory_display' ) || 'borrow' === $xen_view ) {
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
                'itemsNonce'  => wp_create_nonce( 'xen_items_nonce' ),
                'i18n'        => [
                    'borrowSuccess' => __( 'Item borrowed successfully!',  'xen-inventory' ),
                    'returnSuccess' => __( 'Item returned successfully!',  'xen-inventory' ),
                    'errorGeneric'  => __( 'An error occurred. Please try again.', 'xen-inventory' ),
                    'confirm'       => __( 'Are you sure?',                'xen-inventory' ),
                ],
            ] );
        }
    }
}
