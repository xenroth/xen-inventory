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
        <tbody>
            <?php foreach ( $logs as $log ) : ?>
                <tr>
                    <td><?php echo (int) $log->id; ?></td>
                    <td>
                        <?php echo esc_html( $log->borrower_name ); ?>
                        <?php if ( $log->user_id ) : ?>
                            <br><small><?php echo esc_html( get_userdata( (int) $log->user_id )->user_login ?? '' ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="xen-badge xen-badge--<?php echo esc_attr( $log->action ); ?>">
                            <?php echo esc_html( ucfirst( $log->action ) ); ?>
                        </span>
                    </td>
                    <td><?php echo (int) $log->quantity; ?></td>
                    <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $log->date_borrowed ) ) ); ?></td>
                    <td><?php echo $log->date_due ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $log->date_due ) ) ) : '—'; ?></td>
                    <td>
                        <?php if ( $log->date_returned ) : ?>
                            <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $log->date_returned ) ) ); ?>
                        <?php else : ?>
                            <span class="xen-badge xen-badge--open"><?php esc_html_e( 'Open', 'xen-inventory' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $log->notes ?? '' ); ?></td>
                    <td>
                        <button
                            type="button"
                            class="button button-small xen-delete-log"
                            data-log-id="<?php echo (int) $log->id; ?>"
                            aria-label="<?php esc_attr_e( 'Delete log entry', 'xen-inventory' ); ?>"
                        ><?php esc_html_e( 'Delete', 'xen-inventory' ); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
