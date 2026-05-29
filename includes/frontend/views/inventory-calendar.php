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
            <dt><?php esc_html_e( 'Contact',  'xen-inventory' ); ?></dt><dd id="xen-pop-contact"></dd>
            <dt><?php esc_html_e( 'Tags',     'xen-inventory' ); ?></dt><dd id="xen-pop-tags"></dd>
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

    <!-- Return Confirmation Modal — opened by the Return button in the day-detail modal (staff/admin only) -->
    <?php if ( current_user_can( 'xen_return_items' ) ) : ?>
    <div id="xen-cal-return-modal"
         style="display:none;position:fixed;inset:0;z-index:100080;align-items:center;justify-content:center;"
         role="dialog" aria-modal="true" aria-labelledby="xen-cal-return-title">
        <div id="xen-cal-return-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.6);"></div>
        <div style="position:relative;background:#fff;border-radius:6px;padding:1.5rem 1.75rem;width:480px;max-width:95vw;max-height:85vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.3);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;border-bottom:2px solid #f0f0f0;padding-bottom:.75rem;">
                <h3 id="xen-cal-return-title" style="margin:0;font-size:1rem;font-weight:700;"><?php esc_html_e( 'Return Item', 'xen-inventory' ); ?></h3>
                <button type="button" id="xen-cal-return-close" class="xen-btn xen-btn--ghost" style="padding:.2rem .65rem;font-size:1.15rem;line-height:1.3;" aria-label="<?php esc_attr_e( 'Close', 'xen-inventory' ); ?>">&times;</button>
            </div>
            <p style="font-size:.9rem;margin:.25rem 0 1rem;"><?php esc_html_e( 'Returning:', 'xen-inventory' ); ?> <strong id="xen-cal-return-item-name"></strong></p>
            <div id="xen-cal-return-qty-wrap" style="display:none;margin-bottom:1rem;">
                <label for="xen-cal-return-qty" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">
                    <?php esc_html_e( 'Qty Returning', 'xen-inventory' ); ?> <span id="xen-cal-return-qty-max" style="font-weight:400;color:#666;"></span>
                </label>
                <input type="number" id="xen-cal-return-qty" min="1" style="width:6rem;padding:.3rem .5rem;border:1px solid #ccc;border-radius:4px;" />
            </div>
            <div style="margin-bottom:1rem;">
                <label for="xen-cal-return-condition" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">
                    <?php esc_html_e( 'Item Condition on Return', 'xen-inventory' ); ?> <span class="xen-required-star" aria-hidden="true">*</span>
                </label>
                <select id="xen-cal-return-condition" required style="width:100%;padding:.4rem .5rem;border:1px solid #ccc;border-radius:4px;">
                    <option value=""><?php esc_html_e( '— Select condition —', 'xen-inventory' ); ?></option>
                    <option value="good"><?php esc_html_e( '✅ In condition / Usable', 'xen-inventory' ); ?></option>
                    <option value="slight_damage"><?php esc_html_e( '⚠️ Slightly damaged / torn', 'xen-inventory' ); ?></option>
                    <option value="total_damage"><?php esc_html_e( '❌ Totally damaged / unusable', 'xen-inventory' ); ?></option>
                </select>
            </div>
            <div style="margin-bottom:1.25rem;">
                <label for="xen-cal-return-notes" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">
                    <?php esc_html_e( 'Return Remarks', 'xen-inventory' ); ?> <span class="xen-required-star" aria-hidden="true">*</span>
                </label>
                <textarea id="xen-cal-return-notes" rows="3" required
                    placeholder="<?php esc_attr_e( 'e.g. Returned by Juan Dela Cruz. Item was clean and in working order.', 'xen-inventory' ); ?>"
                    style="width:100%;padding:.4rem .5rem;border:1px solid #ccc;border-radius:4px;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                <button type="button" class="xen-btn xen-btn--primary" id="xen-cal-return-submit"><?php esc_html_e( 'Confirm Return', 'xen-inventory' ); ?></button>
                <button type="button" class="xen-btn xen-btn--ghost" id="xen-cal-return-cancel"><?php esc_html_e( 'Cancel', 'xen-inventory' ); ?></button>
            </div>
            <p id="xen-cal-return-status" style="margin-top:.75rem;font-size:.875rem;min-height:1.2em;color:#c00;" aria-live="polite"></p>
        </div>
    </div>
    <?php endif; ?>

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
                        <label class="xen-edit-modal__label"><?php esc_html_e( 'Borrower / Entity', 'xen-inventory' ); ?></label>
                        <p id="xen-edit-borrower" class="xen-edit-modal__static"></p>
                    </div>

                    <div class="xen-edit-modal__field">
                        <label class="xen-edit-modal__label"><?php esc_html_e( 'Contact', 'xen-inventory' ); ?></label>
                        <p id="xen-edit-contact" class="xen-edit-modal__static" style="color:#666;font-size:.875rem;"></p>
                    </div>

                    <div class="xen-edit-modal__field">
                        <label class="xen-edit-modal__label"><?php esc_html_e( 'Tags', 'xen-inventory' ); ?></label>
                        <p id="xen-edit-tags" class="xen-edit-modal__static" style="color:#666;font-size:.875rem;"></p>
                    </div>

                    <div class="xen-edit-modal__row">
                        <div class="xen-edit-modal__field">
                            <label class="xen-edit-modal__label" for="xen-edit-date-due">
                                <?php esc_html_e( 'Due Date &amp; Time', 'xen-inventory' ); ?>
                            </label>
                            <input type="datetime-local" id="xen-edit-date-due" name="date_due" class="xen-edit-modal__input" />
                        </div>

                        <div class="xen-edit-modal__field">
                            <label class="xen-edit-modal__label" for="xen-edit-date-returned">
                                <?php esc_html_e( 'Date &amp; Time Returned', 'xen-inventory' ); ?>
                                <span class="xen-edit-modal__hint"><?php esc_html_e( '(leave blank if still out)', 'xen-inventory' ); ?></span>
                            </label>
                            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                                <input type="datetime-local" id="xen-edit-date-returned" name="date_returned" class="xen-edit-modal__input" style="flex:1;min-width:0;" />
                                <button type="button" class="xen-btn xen-btn--ghost" id="xen-return-now-btn" style="white-space:nowrap;flex-shrink:0;font-size:.8125rem;">
                                    <?php esc_html_e( '&#x23F1; Now', 'xen-inventory' ); ?>
                                </button>
                            </div>
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
