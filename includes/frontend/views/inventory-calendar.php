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

<div class="xen-calendar-wrap xen-calendar-wrap--<?php echo esc_attr( $calendar_size ?? 'normal' ); ?>" id="xen-calendar-wrap">
    <div class="xen-calendar-toolbar">
        <h2 class="xen-calendar-title"><?php esc_html_e( 'Borrow Calendar', 'xen-inventory' ); ?></h2>
        <p class="xen-calendar-legend">
            <span class="xen-legend xen-legend--borrowed"><?php esc_html_e( 'Borrowed', 'xen-inventory' ); ?></span>
            <span class="xen-legend xen-legend--returned"><?php esc_html_e( 'Returned', 'xen-inventory' ); ?></span>
        </p>
    </div>

    <!-- FullCalendar mounts here (wrapped for external scrollbar). -->
    <div class="xen-calendar-scroller">
        <div id="xen-fullcalendar" data-size="<?php echo esc_attr( $calendar_size ?? 'normal' ); ?>"></div>
    </div>

    <!-- Event detail popover (filled via JS on event click). -->
    <div class="xen-event-popover" id="xen-event-popover" hidden role="tooltip">
        <button class="xen-event-popover__close" id="xen-popover-close" aria-label="<?php esc_attr_e( 'Close', 'xen-inventory' ); ?>">&times;</button>
        <dl class="xen-event-popover__dl">
            <dt><?php esc_html_e( 'Item',     'xen-inventory' ); ?></dt><dd id="xen-pop-item"></dd>
            <dt><?php esc_html_e( 'Borrower', 'xen-inventory' ); ?></dt><dd id="xen-pop-action"></dd>
            <dt><?php esc_html_e( 'Quantity', 'xen-inventory' ); ?></dt><dd id="xen-pop-qty"></dd>
            <dt><?php esc_html_e( 'Notes',    'xen-inventory' ); ?></dt><dd id="xen-pop-notes"></dd>
        </dl>
    </div>

    <!-- Day detail modal (filled via JS on date cell click). -->
    <div class="xen-day-modal" id="xen-day-modal" hidden role="dialog" aria-modal="true" aria-labelledby="xen-day-modal-title">
        <div class="xen-day-modal__backdrop"></div>
        <div class="xen-day-modal__panel">
            <div class="xen-day-modal__header">
                <h3 class="xen-day-modal__title" id="xen-day-modal-title"></h3>
                <button class="xen-day-modal__close" id="xen-day-modal-close" aria-label="<?php esc_attr_e( 'Close', 'xen-inventory' ); ?>">&times;</button>
            </div>
            <div class="xen-day-modal__body" id="xen-day-modal-body">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>

    <!-- Edit Borrow Record modal — opened by double-clicking a calendar event (staff/admin only) -->
    <div class="xen-edit-modal" id="xen-edit-borrow-modal" hidden role="dialog" aria-modal="true" aria-labelledby="xen-edit-modal-title">
        <div class="xen-edit-modal__backdrop"></div>
        <div class="xen-edit-modal__panel">
            <div class="xen-edit-modal__header">
                <h3 class="xen-edit-modal__title" id="xen-edit-modal-title">
                    <?php esc_html_e( 'Edit Borrow Record', 'xen-inventory' ); ?>
                </h3>
                <button class="xen-edit-modal__close" id="xen-edit-modal-close" aria-label="<?php esc_attr_e( 'Close', 'xen-inventory' ); ?>">&times;</button>
            </div>
            <div class="xen-edit-modal__body">
                <form id="xen-edit-borrow-form" novalidate>
                    <input type="hidden" id="xen-edit-log-id" name="log_id" value="" />

                    <div class="xen-edit-modal__field">
                        <label class="xen-edit-modal__label"><?php esc_html_e( 'Item', 'xen-inventory' ); ?></label>
                        <p id="xen-edit-item-name" class="xen-edit-modal__static"></p>
                    </div>

                    <div class="xen-edit-modal__field">
                        <label class="xen-edit-modal__label"><?php esc_html_e( 'Borrower', 'xen-inventory' ); ?></label>
                        <p id="xen-edit-borrower" class="xen-edit-modal__static"></p>
                    </div>

                    <div class="xen-edit-modal__row">
                        <div class="xen-edit-modal__field">
                            <label class="xen-edit-modal__label" for="xen-edit-date-due">
                                <?php esc_html_e( 'Due Date', 'xen-inventory' ); ?>
                            </label>
                            <input type="date" id="xen-edit-date-due" name="date_due" class="xen-edit-modal__input" />
                        </div>

                        <div class="xen-edit-modal__field">
                            <label class="xen-edit-modal__label" for="xen-edit-date-returned">
                                <?php esc_html_e( 'Date Returned', 'xen-inventory' ); ?>
                                <span class="xen-edit-modal__hint"><?php esc_html_e( '(leave blank if still out)', 'xen-inventory' ); ?></span>
                            </label>
                            <input type="date" id="xen-edit-date-returned" name="date_returned" class="xen-edit-modal__input" />
                        </div>
                    </div>

                    <div class="xen-edit-modal__field">
                        <label class="xen-edit-modal__label" for="xen-edit-notes">
                            <?php esc_html_e( 'Notes', 'xen-inventory' ); ?>
                        </label>
                        <textarea id="xen-edit-notes" name="notes" class="xen-edit-modal__textarea" rows="3"></textarea>
                    </div>

                    <p id="xen-edit-modal-status" class="xen-edit-modal__status" aria-live="polite"></p>

                    <div class="xen-edit-modal__actions">
                        <button type="submit" class="xen-btn xen-btn--primary" id="xen-edit-save-btn">
                            <?php esc_html_e( 'Save Changes', 'xen-inventory' ); ?>
                        </button>
                        <button type="button" class="xen-btn xen-btn--ghost" id="xen-edit-cancel-btn">
                            <?php esc_html_e( 'Cancel', 'xen-inventory' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
