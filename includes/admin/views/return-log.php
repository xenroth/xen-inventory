<?php
/**
 * Admin View: Return Log page.
 *
 * Displays a paginated, filterable table of all 'returned' log entries,
 * including item condition, return remarks, and the WP account that
 * processed the return.
 *
 * @package XenInventory\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load deleted entities for badge display.
$xen_deleted_borrowers = get_option( 'xen_deleted_borrowers', [] );
if ( ! is_array( $xen_deleted_borrowers ) ) {
    $xen_deleted_borrowers = [];
}

$per_page = 30;
$current  = max( 1, absint( $_GET['paged'] ?? 1 ) );

// --- Filter inputs ---
$filter_search    = sanitize_text_field( wp_unslash( $_GET['xen_search']    ?? '' ) );
$filter_condition = sanitize_key(         wp_unslash( $_GET['xen_condition'] ?? '' ) );
$filter_date_from = sanitize_text_field( wp_unslash( $_GET['xen_date_from'] ?? '' ) );
$filter_date_to   = sanitize_text_field( wp_unslash( $_GET['xen_date_to']   ?? '' ) );

// Validate date formats.
if ( $filter_date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date_from ) ) {
    $filter_date_from = '';
}
if ( $filter_date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date_to ) ) {
    $filter_date_to = '';
}

// Validate condition filter.
$allowed_conditions = [ 'good', 'slight_damage', 'total_damage' ];
if ( $filter_condition && ! in_array( $filter_condition, $allowed_conditions, true ) ) {
    $filter_condition = '';
}

// --- Queries via model ---
$logs  = \XenInventory\Models\InventoryLog::get_return_logs(
    $filter_search, $filter_condition, $filter_date_from, $filter_date_to, $per_page, $current
);
$total = \XenInventory\Models\InventoryLog::count_return_logs(
    $filter_search, $filter_condition, $filter_date_from, $filter_date_to
);
$pages = (int) ceil( $total / $per_page );

$date_fmt      = get_option( 'date_format' );
$datetime_fmt  = $date_fmt . ' ' . get_option( 'time_format' );

$condition_labels = [
    'good'          => __( 'Good',             'xen-inventory' ),
    'slight_damage' => __( 'Slightly Damaged', 'xen-inventory' ),
    'total_damage'  => __( 'Totally Damaged',  'xen-inventory' ),
];

$condition_badge_class = [
    'good'          => 'xen-badge--returned',   // green
    'slight_damage' => 'xen-badge--overdue',    // amber
    'total_damage'  => 'xen-badge--deleted',    // red
];
?>
<div class="wrap xen-admin-wrap">
    <h1><?php esc_html_e( 'Return Log', 'xen-inventory' ); ?></h1>

    <!-- Filter form -->
    <form method="get" class="xen-log-filters">
        <input type="hidden" name="page" value="xen-return-log" />

        <input
            type="search"
            name="xen_search"
            value="<?php echo esc_attr( $filter_search ); ?>"
            placeholder="<?php esc_attr_e( 'Item, borrower or contact…', 'xen-inventory' ); ?>"
            class="regular-text"
        />

        <select name="xen_condition">
            <option value=""><?php esc_html_e( 'All Conditions', 'xen-inventory' ); ?></option>
            <option value="good"          <?php selected( $filter_condition, 'good' ); ?>><?php esc_html_e( 'Good',             'xen-inventory' ); ?></option>
            <option value="slight_damage" <?php selected( $filter_condition, 'slight_damage' ); ?>><?php esc_html_e( 'Slightly Damaged', 'xen-inventory' ); ?></option>
            <option value="total_damage"  <?php selected( $filter_condition, 'total_damage' ); ?>><?php esc_html_e( 'Totally Damaged',  'xen-inventory' ); ?></option>
        </select>

        <label class="screen-reader-text" for="xen-date-from"><?php esc_html_e( 'Return date from', 'xen-inventory' ); ?></label>
        <input
            type="date"
            id="xen-date-from"
            name="xen_date_from"
            value="<?php echo esc_attr( $filter_date_from ); ?>"
            title="<?php esc_attr_e( 'Return date from', 'xen-inventory' ); ?>"
        />

        <label class="screen-reader-text" for="xen-date-to"><?php esc_html_e( 'Return date to', 'xen-inventory' ); ?></label>
        <input
            type="date"
            id="xen-date-to"
            name="xen_date_to"
            value="<?php echo esc_attr( $filter_date_to ); ?>"
            title="<?php esc_attr_e( 'Return date to', 'xen-inventory' ); ?>"
        />

        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'xen-inventory' ); ?></button>

        <?php if ( $filter_search || $filter_condition || $filter_date_from || $filter_date_to ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-return-log' ) ); ?>" class="button">
                <?php esc_html_e( 'Clear', 'xen-inventory' ); ?>
            </a>
        <?php endif; ?>
    </form>

    <!-- Export form — submits current filters, streams a CSV file. -->
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="xen-export-form">
        <input type="hidden" name="action"        value="xen_export_return_log" />
        <input type="hidden" name="xen_search"    value="<?php echo esc_attr( $filter_search ); ?>" />
        <input type="hidden" name="xen_condition" value="<?php echo esc_attr( $filter_condition ); ?>" />
        <input type="hidden" name="xen_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>" />
        <input type="hidden" name="xen_date_to"   value="<?php echo esc_attr( $filter_date_to ); ?>" />
        <?php wp_nonce_field( 'xen_export_return_log' ); ?>
        <button type="submit" class="button xen-btn-export">
            &#8595;&nbsp;<?php esc_html_e( 'Export CSV', 'xen-inventory' ); ?>
        </button>
    </form>

    <p class="xen-log-count">
        <?php
        /* translators: %d: number of matching return log entries */
        printf( esc_html__( '%d entries found.', 'xen-inventory' ), $total );
        ?>
    </p>

    <?php if ( empty( $logs ) ) : ?>
        <p><?php esc_html_e( 'No return records found.', 'xen-inventory' ); ?></p>
    <?php else : ?>

        <table class="wp-list-table widefat fixed striped xen-log-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Item',             'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Borrower',         'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Qty Returned',     'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Condition',        'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Return Remarks',   'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Return Date',      'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Returned By',      'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Borrow Date',      'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Tags',             'xen-inventory' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <?php
                    $cond_key   = $log->item_condition ?? '';
                    $cond_label = $condition_labels[ $cond_key ] ?? esc_html( ucfirst( str_replace( '_', ' ', $cond_key ) ) );
                    $cond_class = $condition_badge_class[ $cond_key ] ?? '';

                    $entity_key = strtolower( trim( $log->borrower_full_name ?: ( $log->borrower_name ?? '' ) ) );
                    $is_deleted = $entity_key && in_array( $entity_key, $xen_deleted_borrowers, true );

                    $returned_by = $log->returned_by_display_name ?? '';
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( (int) $log->item_id ) ); ?>">
                                <?php echo esc_html( $log->item_title ?? '' ); ?>
                            </a>
                        </td>
                        <td>
                            <strong><?php echo esc_html( $log->borrower_name ?? '' ); ?></strong>
                            <?php if ( ! empty( $log->borrower_full_name ) ) : ?>
                                <br><span><?php echo esc_html( $log->borrower_full_name ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $log->borrower_contact ) ) : ?>
                                <br><span class="description"><?php echo esc_html( $log->borrower_contact ); ?></span>
                            <?php endif; ?>
                            <?php if ( $is_deleted ) : ?>
                                <br><span class="xen-badge xen-badge--deleted" title="<?php esc_attr_e( 'This borrower account has been deleted', 'xen-inventory' ); ?>">
                                    <?php esc_html_e( 'Deleted', 'xen-inventory' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="xen-log-qty-cell"><?php echo (int) $log->quantity; ?></td>
                        <td>
                            <?php if ( $cond_key ) : ?>
                                <span class="xen-badge <?php echo esc_attr( $cond_class ); ?>">
                                    <?php echo esc_html( $cond_label ); ?>
                                </span>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="xen-log-notes-cell">
                            <?php echo esc_html( $log->return_notes ?? '' ); ?>
                        </td>
                        <td>
                            <?php if ( $log->date_returned ) : ?>
                                <?php echo esc_html( wp_date( $datetime_fmt, strtotime( $log->date_returned ) ) ); ?>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $returned_by ) : ?>
                                <?php echo esc_html( $returned_by ); ?>
                            <?php else : ?>
                                <span class="description"><?php esc_html_e( '(legacy)', 'xen-inventory' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html( wp_date( $date_fmt, strtotime( $log->date_borrowed ) ) ); ?>
                        </td>
                        <td>
                            <?php echo esc_html( $log->borrow_tags ?? '' ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $page_link_args = array_filter( [
                        'page'          => 'xen-return-log',
                        'xen_search'    => $filter_search,
                        'xen_condition' => $filter_condition,
                        'xen_date_from' => $filter_date_from,
                        'xen_date_to'   => $filter_date_to,
                    ] );
                    echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        'base'    => add_query_arg( array_merge( $page_link_args, [ 'paged' => '%#%' ] ) ),
                        'format'  => '',
                        'current' => $current,
                        'total'   => $pages,
                    ] );
                    ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
