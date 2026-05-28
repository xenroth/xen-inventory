<?php
/**
 * Admin View: Settings page.
 *
 * Uses the WordPress Settings API (registered in Settings class).
 *
 * @package XenInventory\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap xen-admin-wrap">
    <h1><?php esc_html_e( 'XEN Inventory Settings', 'xen-inventory' ); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'xen_inventory_settings_group' );
        do_settings_sections( 'xen-inventory-settings' );
        submit_button();
        ?>
    </form>

    <hr />

    <h2><?php esc_html_e( 'Shortcode Reference', 'xen-inventory' ); ?></h2>
    <table class="widefat fixed">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Shortcode',   'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Description', 'xen-inventory' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[xen_inventory_display]</code></td>
                <td><?php esc_html_e( 'Frontend item grid with department filter and availability status.', 'xen-inventory' ); ?></td>
            </tr>
            <tr>
                <td><code>[xen_inventory_calendar]</code></td>
                <td><?php esc_html_e( 'Interactive FullCalendar showing borrow history.', 'xen-inventory' ); ?></td>
            </tr>
            <tr>
                <td><code>[xen_inventory_login]</code></td>
                <td><?php esc_html_e( 'Frontend login form for inventory users.', 'xen-inventory' ); ?></td>
            </tr>
        </tbody>
    </table>
</div>
