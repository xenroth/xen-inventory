<?php
/**
 * Plugin settings (WP Settings API).
 *
 * @package XenInventory\Admin
 */

namespace XenInventory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Settings
 */
class Settings {

    /** Settings option key. */
    const OPTION_KEY = 'xen_inventory_settings';

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Register settings sections and fields via the WP Settings API.
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            'xen_inventory_settings_group',
            self::OPTION_KEY,
            [ $this, 'sanitize_settings' ]
        );

        // --- General section ---
        add_settings_section(
            'xen_general',
            __( 'General Settings', 'xen-inventory' ),
            '__return_false',
            'xen-inventory-settings'
        );

        add_settings_field(
            'login_page_id',
            __( 'Frontend Login Page', 'xen-inventory' ),
            [ $this, 'field_page_select' ],
            'xen-inventory-settings',
            'xen_general',
            [
                'key'   => 'login_page_id',
                'label' => __( 'Page containing the [xen_inventory_login] shortcode.', 'xen-inventory' ),
            ]
        );

        add_settings_field(
            'items_per_page',
            __( 'Items Per Page (Frontend)', 'xen-inventory' ),
            [ $this, 'field_number' ],
            'xen-inventory-settings',
            'xen_general',
            [
                'key'     => 'items_per_page',
                'min'     => 1,
                'max'     => 100,
                'default' => 20,
            ]
        );

        add_settings_field(
            'allow_guest_calendar',
            __( 'Public Calendar', 'xen-inventory' ),
            [ $this, 'field_checkbox' ],
            'xen-inventory-settings',
            'xen_general',
            [
                'key'   => 'allow_guest_calendar',
                'label' => __( 'Allow non-logged-in users to view the calendar.', 'xen-inventory' ),
            ]
        );

    }

    // -----------------------------------------------------------------------
    // Field renderers
    // -----------------------------------------------------------------------

    /**
     * Render a page-selector dropdown field.
     *
     * @param  array<string, mixed> $args Field arguments.
     * @return void
     */
    public function field_page_select( array $args ): void {
        $options  = get_option( self::OPTION_KEY, [] );
        $selected = (int) ( $options[ $args['key'] ] ?? 0 );

        wp_dropdown_pages( [
            'name'             => self::OPTION_KEY . '[' . esc_attr( $args['key'] ) . ']',
            'selected'         => $selected,
            'show_option_none' => __( '— Select a page —', 'xen-inventory' ),
        ] );

        if ( ! empty( $args['label'] ) ) {
            echo '<p class="description">' . esc_html( $args['label'] ) . '</p>';
        }
    }

    /**
     * Render a number input field.
     *
     * @param  array<string, mixed> $args Field arguments.
     * @return void
     */
    public function field_number( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $value   = (int) ( $options[ $args['key'] ] ?? $args['default'] );

        printf(
            '<input type="number" name="%s[%s]" value="%d" min="%d" max="%d" class="small-text" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $args['key'] ),
            $value,
            (int) $args['min'],
            (int) $args['max']
        );
    }

    /**
     * Render a checkbox field.
     *
     * @param  array<string, mixed> $args Field arguments.
     * @return void
     */
    public function field_checkbox( array $args ): void {
        $options = get_option( self::OPTION_KEY, [] );
        $checked = ! empty( $options[ $args['key'] ] );

        printf(
            '<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $args['key'] ),
            checked( $checked, true, false ),
            esc_html( $args['label'] )
        );
    }

    // -----------------------------------------------------------------------
    // Sanitization
    // -----------------------------------------------------------------------

    /**
     * Sanitize and validate the submitted settings array.
     *
     * @param  array<string, mixed>|mixed $input Raw input from the form.
     * @return array<string, mixed>
     */
    public function sanitize_settings( mixed $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $clean = [];

        $clean['login_page_id']       = absint( $input['login_page_id'] ?? 0 );
        $clean['items_per_page']       = min( 100, max( 1, absint( $input['items_per_page'] ?? 20 ) ) );
        $clean['allow_guest_calendar'] = ! empty( $input['allow_guest_calendar'] ) ? 1 : 0;

        return $clean;
    }
}
