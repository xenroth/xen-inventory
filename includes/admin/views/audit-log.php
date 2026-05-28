<?php
/**
 * Admin View: Audit Log page.
 *
 * Displays a paginated, filterable table of all audit entries
 * showing who performed what action and when.
 *
 * @package XenInventory\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$per_page = 50;
$current  = max( 1, absint( $_GET['paged'] ?? 1 ) );
$offset   = ( $current - 1 ) * $per_page;

// --- Filter inputs ---
$filter_action    = sanitize_key( $_GET['xen_action']    ?? '' );
$filter_user      = absint( $_GET['xen_user']            ?? 0 );
$filter_date_from = sanitize_text_field( $_GET['xen_date_from'] ?? '' );
$filter_date_to   = sanitize_text_field( $_GET['xen_date_to']   ?? '' );

if ( $filter_date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date_from ) ) {
    $filter_date_from = '';
}
if ( $filter_date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date_to ) ) {
    $filter_date_to = '';
}

$filters = array_filter( [
    'action'    => $filter_action,
    'user_id'   => $filter_user ?: null,
    'date_from' => $filter_date_from,
    'date_to'   => $filter_date_to,
] );

$logs  = \XenInventory\Core\AuditLog::get_logs( $per_page, $offset, $filters );
$total = \XenInventory\Core\AuditLog::count_logs( $filters );
$pages = (int) ceil( $total / $per_page );

$date_fmt  = get_option( 'date_format' );
$time_fmt  = get_option( 'time_format' );

// Action badge colours.
$action_classes = [
    'borrow'  => 'borrowed',
    'return'  => 'returned',
    'update'  => 'note',
    'delete'  => 'overdue',
    'purge'   => 'overdue',
];
?>

<div class="wrap xen-admin-wrap">
    <h1><?php esc_html_e( 'Audit Log', 'xen-inventory' ); ?></h1>
    <p class="xen-page-desc"><?php esc_html_e( 'A complete trace of all inventory actions performed by users.', 'xen-inventory' ); ?></p>

    <!-- Filter form -->
    <form method="get" class="xen-log-filters">
        <input type="hidden" name="page" value="xen-audit-log" />

        <select name="xen_action">
            <option value=""><?php esc_html_e( 'All Actions', 'xen-inventory' ); ?></option>
            <option value="borrow"  <?php selected( $filter_action, 'borrow' ); ?>><?php esc_html_e( 'Borrow',  'xen-inventory' ); ?></option>
            <option value="return"  <?php selected( $filter_action, 'return' ); ?>><?php esc_html_e( 'Return',  'xen-inventory' ); ?></option>
            <option value="update"  <?php selected( $filter_action, 'update' ); ?>><?php esc_html_e( 'Update',  'xen-inventory' ); ?></option>
            <option value="delete"  <?php selected( $filter_action, 'delete' ); ?>><?php esc_html_e( 'Delete',  'xen-inventory' ); ?></option>
            <option value="purge"   <?php selected( $filter_action, 'purge'  ); ?>><?php esc_html_e( 'Purge',   'xen-inventory' ); ?></option>
        </select>

        <label class="screen-reader-text" for="xen-audit-date-from"><?php esc_html_e( 'From date', 'xen-inventory' ); ?></label>
        <input type="date" id="xen-audit-date-from" name="xen_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>" title="<?php esc_attr_e( 'From date', 'xen-inventory' ); ?>" />

        <label class="screen-reader-text" for="xen-audit-date-to"><?php esc_html_e( 'To date', 'xen-inventory' ); ?></label>
        <input type="date" id="xen-audit-date-to" name="xen_date_to" value="<?php echo esc_attr( $filter_date_to ); ?>" title="<?php esc_attr_e( 'To date', 'xen-inventory' ); ?>" />

        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'xen-inventory' ); ?></button>

        <?php if ( $filter_action || $filter_date_from || $filter_date_to ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-audit-log' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'xen-inventory' ); ?></a>
        <?php endif; ?>
    </form>

    <p class="xen-log-count">
        <?php
        /* translators: %d: number of matching audit entries */
        printf( esc_html__( '%d entries found.', 'xen-inventory' ), $total );
        ?>
    </p>

    <?php if ( empty( $logs ) ) : ?>
        <p><?php esc_html_e( 'No audit entries found.', 'xen-inventory' ); ?></p>
    <?php else : ?>

        <table class="wp-list-table widefat fixed striped xen-log-table xen-audit-table">
            <thead>
                <tr>
                    <th style="width:16%"><?php esc_html_e( 'Date / Time',   'xen-inventory' ); ?></th>
                    <th style="width:14%"><?php esc_html_e( 'User',          'xen-inventory' ); ?></th>
                    <th style="width:10%"><?php esc_html_e( 'Action',        'xen-inventory' ); ?></th>
                    <th style="width:10%"><?php esc_html_e( 'Object Type',   'xen-inventory' ); ?></th>
                    <th style="width:25%"><?php esc_html_e( 'Label',         'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Details',     'xen-inventory' ); ?></th>
                    <th style="width:12%"><?php esc_html_e( 'IP Address',    'xen-inventory' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $entry ) :
                    $badge_class = $action_classes[ $entry->action ] ?? 'note';
                    $created_ts  = strtotime( $entry->created_at );
                    $details_arr = $entry->details ? json_decode( $entry->details, true ) : [];
                    $details_str = '';
                    if ( is_array( $details_arr ) ) {
                        $parts = [];
                        foreach ( $details_arr as $k => $v ) {
                            if ( '' !== (string) $v && null !== $v ) {
                                $parts[] = esc_html( $k ) . ': ' . esc_html( (string) $v );
                            }
                        }
                        $details_str = implode( ' · ', $parts );
                    }
                ?>
                    <tr>
                        <td>
                            <span title="<?php echo esc_attr( $entry->created_at ); ?>">
                                <?php echo esc_html( wp_date( $date_fmt . ' ' . $time_fmt, $created_ts ) ); ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo esc_html( $entry->user_name ); ?></strong>
                            <?php if ( $entry->user_id ) : ?>
                                <br><small class="xen-text-muted">#<?php echo (int) $entry->user_id; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="xen-badge xen-badge--<?php echo esc_attr( $badge_class ); ?>">
                                <?php echo esc_html( ucfirst( $entry->action ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( ucfirst( $entry->object_type ) ); ?></td>
                        <td><?php echo esc_html( $entry->label ); ?></td>
                        <td class="xen-audit-details"><?php echo $details_str ? esc_html( $details_str ) : '—'; ?></td>
                        <td><code><?php echo esc_html( $entry->ip ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $page_link_args = array_filter( [
                        'page'          => 'xen-audit-log',
                        'xen_action'    => $filter_action,
                        'xen_date_from' => $filter_date_from,
                        'xen_date_to'   => $filter_date_to,
                    ] );
                    echo paginate_links( [
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
