<?php
/**
 * Admin View: Borrow History Meta Box.
 *
 * Variables available from MetaBoxes::render_borrow_history():
 *   $post   WP_Post
 *   $logs   array   Rows from wp_xen_inventory_logs.
 *
 * @package XenInventory\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<?php if ( empty( $logs ) ) : ?>
    <p><?php esc_html_e( 'No borrow history recorded yet for this item.', 'xen-inventory' ); ?></p>
<?php else : ?>
    <!-- Filter toolbar -->
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.6rem;align-items:center;">
        <input
            type="search"
            id="xen-meta-box-history-search"
            placeholder="<?php esc_attr_e( 'Search borrower or notes…', 'xen-inventory' ); ?>"
            style="flex:1 1 160px;min-width:130px;padding:.3rem .5rem;border:1px solid #ccd0d4;border-radius:3px;"
            aria-label="<?php esc_attr_e( 'Filter history', 'xen-inventory' ); ?>"
        >
        <select id="xen-meta-box-history-status" style="padding:.3rem .5rem;border:1px solid #ccd0d4;border-radius:3px;" aria-label="<?php esc_attr_e( 'Filter by status', 'xen-inventory' ); ?>">
            <option value=""><?php esc_html_e( 'All', 'xen-inventory' ); ?></option>
            <option value="open"><?php esc_html_e( 'Open', 'xen-inventory' ); ?></option>
            <option value="returned"><?php esc_html_e( 'Returned', 'xen-inventory' ); ?></option>
        </select>
    </div>

    <div id="xen-meta-box-history-wrap">
    <table class="widefat striped xen-history-table">
        <thead>
            <tr>
                <th><?php esc_html_e( '#',            'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Borrower',     'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Action',       'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Qty',          'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Borrowed',     'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Due',          'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Returned',     'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Notes',        'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Actions',      'xen-inventory' ); ?></th>
            </tr>
        </thead>
        <tbody id="xen-meta-box-history-tbody">
            <?php foreach ( $logs as $log ) : ?>
                <tr class="xen-history-row"
                    style="cursor:pointer"
                    title="<?php esc_attr_e( 'Double-click to view full details', 'xen-inventory' ); ?>"
                    data-log-id="<?php echo (int) $log->id; ?>"
                    data-item-title="<?php echo esc_attr( $post->post_title ?? '' ); ?>"
                    data-borrower-name="<?php echo esc_attr( $log->borrower_name ?? '' ); ?>"
                    data-borrower-full-name="<?php echo esc_attr( $log->borrower_full_name ?? '' ); ?>"
                    data-borrower-contact="<?php echo esc_attr( $log->borrower_contact ?? '' ); ?>"
                    data-borrow-tags="<?php echo esc_attr( $log->borrow_tags ?? '' ); ?>"
                    data-action="<?php echo esc_attr( $log->action ?? '' ); ?>"
                    data-qty="<?php echo (int) $log->quantity; ?>"
                    data-date-borrowed="<?php echo esc_attr( $log->date_borrowed ?? '' ); ?>"
                    data-date-due="<?php echo esc_attr( $log->date_due ?? '' ); ?>"
                    data-date-returned="<?php echo esc_attr( $log->date_returned ?? '' ); ?>"
                    data-notes="<?php echo esc_attr( $log->notes ?? '' ); ?>"
                    data-return-notes="<?php echo esc_attr( $log->return_notes ?? '' ); ?>"
                    data-item-condition="<?php echo esc_attr( $log->item_condition ?? '' ); ?>"
                    data-filter="<?php echo esc_attr( strtolower( ( $log->borrower_full_name ?? $log->borrower_name ?? '' ) . ' ' . ( $log->notes ?? '' ) ) ); ?>"
                    data-status-mb="<?php echo $log->date_returned ? 'returned' : 'open'; ?>"
                >
                    <td><?php echo (int) $log->id; ?></td>
                    <td>
                        <?php echo esc_html( $log->borrower_name ); ?>
                        <?php if ( ! empty( $log->borrower_full_name ) ) : ?>
                            <br><small><?php echo esc_html( $log->borrower_full_name ); ?></small>
                        <?php endif; ?>
                        <?php if ( ! empty( $log->borrower_contact ) ) : ?>
                            <br><small class="xen-text-muted"><?php echo esc_html( $log->borrower_contact ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="xen-badge xen-badge--<?php echo esc_attr( $log->action ); ?>">
                            <?php echo esc_html( ucfirst( $log->action ) ); ?>
                        </span>
                    </td>
                    <td class="xen-log-qty-cell"><?php echo (int) $log->quantity; ?></td>
                    <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $log->date_borrowed ) ) ); ?></td>
                    <td class="xen-log-due-cell"><?php echo $log->date_due ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->date_due ) ) ) : '—'; ?></td>
                    <td class="xen-log-returned-cell">
                        <?php if ( $log->date_returned ) : ?>
                            <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->date_returned ) ) ); ?>
                        <?php else : ?>
                            <span class="xen-badge xen-badge--open"><?php esc_html_e( 'Open', 'xen-inventory' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="xen-log-notes-cell"><?php echo esc_html( $log->notes ?? '' ); ?></td>
                    <td>
                        <button
                            type="button"
                            class="button button-small xen-delete-log"
                            data-log-id="<?php echo (int) $log->id; ?>"
                            aria-label="<?php esc_attr_e( 'Delete log entry', 'xen-inventory' ); ?>"
                        ><?php esc_html_e( 'Delete', 'xen-inventory' ); ?></button>
                        <button
                            type="button"
                            class="button button-small xen-edit-log"
                            data-log-id="<?php echo (int) $log->id; ?>"
                            data-date-due="<?php echo esc_attr( $log->date_due ?? '' ); ?>"
                            data-date-returned="<?php echo esc_attr( $log->date_returned ?? '' ); ?>"
                            data-notes="<?php echo esc_attr( $log->notes ?? '' ); ?>"
                            aria-label="<?php esc_attr_e( 'Edit log entry', 'xen-inventory' ); ?>"
                            style="margin-top:2px;"
                        ><?php esc_html_e( 'Edit', 'xen-inventory' ); ?></button>
                        <?php if ( ! $log->date_returned ) : ?>
                            <button
                                type="button"
                                class="button button-small button-primary xen-return-log"
                                data-log-id="<?php echo (int) $log->id; ?>"
                                data-qty="<?php echo (int) $log->quantity; ?>"
                                aria-label="<?php esc_attr_e( 'Mark as returned', 'xen-inventory' ); ?>"
                                style="margin-top:2px;"
                            ><?php esc_html_e( 'Return', 'xen-inventory' ); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div><!-- #xen-meta-box-history-wrap -->
    <div id="xen-meta-box-history-pagination" style="margin-top:.5rem;display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;"></div>
<?php endif; ?>
