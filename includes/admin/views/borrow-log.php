<?php
/**
 * Admin View: Borrow Log page.
 *
 * Displays a paginated, filterable table of all log entries.
 *
 * @package XenInventory\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table    = $wpdb->prefix . XEN_INVENTORY_LOG_TABLE;
$per_page = 30;
$current  = max( 1, absint( $_GET['paged'] ?? 1 ) );
$offset   = ( $current - 1 ) * $per_page;

// --- Filter inputs ---
$filter_search   = sanitize_text_field( $_GET['xen_search']  ?? '' );
$filter_status   = sanitize_key( $_GET['xen_status']         ?? '' );
$filter_date_from = sanitize_text_field( $_GET['xen_date_from'] ?? '' );
$filter_date_to   = sanitize_text_field( $_GET['xen_date_to']   ?? '' );

// Validate date formats.
if ( $filter_date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date_from ) ) {
    $filter_date_from = '';
}
if ( $filter_date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date_to ) ) {
    $filter_date_to = '';
}

// --- Build WHERE clause ---
$where      = [];
$where_args = [];

if ( $filter_search ) {
    $like          = '%' . $wpdb->esc_like( $filter_search ) . '%';
    $where[]       = '( l.borrower_name LIKE %s OR p.post_title LIKE %s )';
    $where_args[]  = $like;
    $where_args[]  = $like;
}

$allowed_statuses = [ 'open', 'returned' ];
if ( $filter_status && in_array( $filter_status, $allowed_statuses, true ) ) {
    if ( 'open' === $filter_status ) {
        $where[] = "( l.action = 'borrowed' AND l.date_returned IS NULL )";
    } else {
        $where[] = 'l.date_returned IS NOT NULL';
    }
}

if ( $filter_date_from ) {
    $where[]      = 'l.date_borrowed >= %s';
    $where_args[] = $filter_date_from . ' 00:00:00';
}
if ( $filter_date_to ) {
    $where[]      = 'l.date_borrowed <= %s';
    $where_args[] = $filter_date_to . ' 23:59:59';
}

$where_sql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

// --- Queries ---
$base_query = "FROM {$table} l LEFT JOIN {$wpdb->posts} p ON p.ID = l.item_id {$where_sql}";

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
if ( $where_args ) {
    $logs  = $wpdb->get_results( $wpdb->prepare(
        "SELECT l.*, p.post_title AS item_title {$base_query} ORDER BY l.date_borrowed DESC LIMIT %d OFFSET %d",
        array_merge( $where_args, [ $per_page, $offset ] )
    ) );
    $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$base_query}", $where_args ) );
} else {
    $logs  = $wpdb->get_results( $wpdb->prepare(
        "SELECT l.*, p.post_title AS item_title {$base_query} ORDER BY l.date_borrowed DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ) );
    $total = (int) $wpdb->get_var( "SELECT COUNT(*) {$base_query}" );
}
// phpcs:enable

$pages = (int) ceil( $total / $per_page );
?>

<div class="wrap xen-admin-wrap">
    <h1><?php esc_html_e( 'Borrow Log', 'xen-inventory' ); ?></h1>

    <!-- Filter form -->
    <form method="get" class="xen-log-filters">
        <input type="hidden" name="page" value="xen-borrow-log" />

        <input
            type="search"
            name="xen_search"
            value="<?php echo esc_attr( $filter_search ); ?>"
            placeholder="<?php esc_attr_e( 'Item or borrower…', 'xen-inventory' ); ?>"
            class="regular-text"
        />

        <select name="xen_status">
            <option value=""><?php esc_html_e( 'All Statuses', 'xen-inventory' ); ?></option>
            <option value="open"     <?php selected( $filter_status, 'open' ); ?>><?php esc_html_e( 'Open (not returned)', 'xen-inventory' ); ?></option>
            <option value="returned" <?php selected( $filter_status, 'returned' ); ?>><?php esc_html_e( 'Returned', 'xen-inventory' ); ?></option>
        </select>

        <label class="screen-reader-text" for="xen-date-from"><?php esc_html_e( 'From date', 'xen-inventory' ); ?></label>
        <input type="date" id="xen-date-from" name="xen_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>" title="<?php esc_attr_e( 'From date', 'xen-inventory' ); ?>" />

        <label class="screen-reader-text" for="xen-date-to"><?php esc_html_e( 'To date', 'xen-inventory' ); ?></label>
        <input type="date" id="xen-date-to" name="xen_date_to" value="<?php echo esc_attr( $filter_date_to ); ?>" title="<?php esc_attr_e( 'To date', 'xen-inventory' ); ?>" />

        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'xen-inventory' ); ?></button>

        <?php if ( $filter_search || $filter_status || $filter_date_from || $filter_date_to ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-borrow-log' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'xen-inventory' ); ?></a>
        <?php endif; ?>
    </form>

    <p class="xen-log-count">
        <?php
        /* translators: %d: number of matching log entries */
        printf( esc_html__( '%d entries found.', 'xen-inventory' ), $total );
        ?>
    </p>

    <?php if ( empty( $logs ) ) : ?>
        <p><?php esc_html_e( 'No log entries found.', 'xen-inventory' ); ?></p>
    <?php else : ?>

        <table class="wp-list-table widefat fixed striped xen-log-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Item',      'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Borrower',  'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Action',    'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Qty',       'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Borrowed',  'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Due',       'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Returned',  'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Notes',     'xen-inventory' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( (int) $log->item_id ) ); ?>">
                                <?php echo esc_html( $log->item_title ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $log->borrower_name ); ?></td>
                        <td>
                            <span class="xen-badge xen-badge--<?php echo esc_attr( $log->action ); ?>">
                                <?php echo esc_html( ucfirst( $log->action ) ); ?>
                            </span>
                        </td>
                        <td><?php echo (int) $log->quantity; ?></td>
                        <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $log->date_borrowed ) ) ); ?></td>
                        <td>
                            <?php if ( $log->date_due ) : ?>
                                <?php
                                $due_time  = strtotime( $log->date_due );
                                $is_overdue = ! $log->date_returned && $due_time < time();
                                ?>
                                <span <?php if ( $is_overdue ) : ?>class="xen-badge xen-badge--overdue"<?php endif; ?>>
                                    <?php echo esc_html( wp_date( get_option( 'date_format' ), $due_time ) ); ?>
                                </span>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $log->date_returned ) : ?>
                                <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $log->date_returned ) ) ); ?>
                            <?php else : ?>
                                <span class="xen-badge xen-badge--open"><?php esc_html_e( 'Open', 'xen-inventory' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $log->notes ?? '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $page_link_args = array_filter( [
                        'page'          => 'xen-borrow-log',
                        'xen_search'    => $filter_search,
                        'xen_status'    => $filter_status,
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
