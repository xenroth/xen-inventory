<?php
/**
 * Frontend View: Inventory Calendar ([xen_inventory_calendar] shortcode).
 *
 * Renders a FullCalendar container. The JS in assets/js/calendar.js
 * initialises FullCalendar and fetches events via the xen_get_calendar_events AJAX action.
 *
 * @package XenInventory\Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="xen-calendar-wrap" id="xen-calendar-wrap">
    <div class="xen-calendar-toolbar">
        <h2 class="xen-calendar-title"><?php esc_html_e( 'Borrow Calendar', 'xen-inventory' ); ?></h2>
        <p class="xen-calendar-legend">
            <span class="xen-legend xen-legend--borrowed"><?php esc_html_e( 'Borrowed', 'xen-inventory' ); ?></span>
            <span class="xen-legend xen-legend--returned"><?php esc_html_e( 'Returned', 'xen-inventory' ); ?></span>
        </p>
    </div>

    <!-- FullCalendar mounts here. -->
    <div id="xen-fullcalendar"></div>

    <!-- Event detail popover (filled via JS). -->
    <div class="xen-event-popover" id="xen-event-popover" hidden role="tooltip">
        <button class="xen-event-popover__close" id="xen-popover-close" aria-label="<?php esc_attr_e( 'Close', 'xen-inventory' ); ?>">&times;</button>
        <dl class="xen-event-popover__dl">
            <dt><?php esc_html_e( 'Item',     'xen-inventory' ); ?></dt><dd id="xen-pop-item"></dd>
            <dt><?php esc_html_e( 'Borrower', 'xen-inventory' ); ?></dt><dd id="xen-pop-action"></dd>
            <dt><?php esc_html_e( 'Quantity', 'xen-inventory' ); ?></dt><dd id="xen-pop-qty"></dd>
            <dt><?php esc_html_e( 'Notes',    'xen-inventory' ); ?></dt><dd id="xen-pop-notes"></dd>
        </dl>
    </div>
</div>
