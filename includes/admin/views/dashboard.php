<?php
/**
 * Admin View: Dashboard.
 *
 * @package XenInventory\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Quick stats.
$total_items       = wp_count_posts( 'xen_item' )->publish ?? 0;
$total_departments = wp_count_terms( 'xen_department', [ 'hide_empty' => false ] );

global $wpdb;
$table        = $wpdb->prefix . XEN_INVENTORY_LOG_TABLE;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$open_borrows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE action = 'borrowed' AND date_returned IS NULL" );
$overdue      = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE action = 'borrowed' AND date_returned IS NULL AND date_due IS NOT NULL AND date_due < %s",
    current_time( 'mysql', true )
) );

// Recent activity (last 10 entries).
$recent = $wpdb->get_results(
    "SELECT l.*, p.post_title AS item_title
     FROM {$table} l
     LEFT JOIN {$wpdb->posts} p ON p.ID = l.item_id
     ORDER BY l.date_borrowed DESC
     LIMIT 10"
);
// phpcs:enable
?>

<div class="wrap xen-admin-wrap">
    <h1><?php esc_html_e( 'XEN Inventory — Dashboard', 'xen-inventory' ); ?></h1>

    <div class="xen-stats-grid">
        <div class="xen-stat-card">
            <span class="xen-stat-number"><?php echo (int) $total_items; ?></span>
            <span class="xen-stat-label"><?php esc_html_e( 'Total Items', 'xen-inventory' ); ?></span>
        </div>

        <div class="xen-stat-card">
            <span class="xen-stat-number"><?php echo (int) $total_departments; ?></span>
            <span class="xen-stat-label"><?php esc_html_e( 'Departments', 'xen-inventory' ); ?></span>
        </div>

        <div class="xen-stat-card xen-stat-card--alert">
            <span class="xen-stat-number"><?php echo $open_borrows; ?></span>
            <span class="xen-stat-label"><?php esc_html_e( 'Items Currently Borrowed', 'xen-inventory' ); ?></span>
        </div>

        <div class="xen-stat-card <?php echo $overdue > 0 ? 'xen-stat-card--alert' : ''; ?>">
            <span class="xen-stat-number"><?php echo $overdue; ?></span>
            <span class="xen-stat-label"><?php esc_html_e( 'Overdue Returns', 'xen-inventory' ); ?></span>
        </div>
    </div>

    <div class="xen-quick-links">
        <h2><?php esc_html_e( 'Quick Actions', 'xen-inventory' ); ?></h2>
        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=xen_item' ) ); ?>" class="button button-primary">
            <?php esc_html_e( '+ Add New Item', 'xen-inventory' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=xen_department&post_type=xen_item' ) ); ?>" class="button">
            <?php esc_html_e( 'Manage Departments', 'xen-inventory' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-borrow-log' ) ); ?>" class="button">
            <?php esc_html_e( 'View Borrow Log', 'xen-inventory' ); ?>
        </a>
        <?php if ( $overdue > 0 ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-borrow-log&xen_status=open' ) ); ?>" class="button button-link-delete">
                <?php
                /* translators: %d: number of overdue items */
                printf( esc_html__( 'View %d Overdue', 'xen-inventory' ), $overdue );
                ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if ( ! empty( $recent ) ) : ?>
        <div class="xen-recent-activity">
            <h2><?php esc_html_e( 'Recent Activity', 'xen-inventory' ); ?></h2>
            <table class="wp-list-table widefat fixed striped xen-log-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Item',     'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Borrower', 'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Action',   'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Date',     'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Status',   'xen-inventory' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $entry ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( (int) $entry->item_id ) ); ?>">
                                    <?php echo esc_html( $entry->item_title ); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html( $entry->borrower_name ); ?></td>
                            <td>
                                <span class="xen-badge xen-badge--<?php echo esc_attr( $entry->action ); ?>">
                                    <?php echo esc_html( ucfirst( $entry->action ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $entry->date_borrowed ) ) ); ?></td>
                            <td>
                                <?php if ( $entry->date_returned ) : ?>
                                    <span class="xen-badge xen-badge--returned"><?php esc_html_e( 'Returned', 'xen-inventory' ); ?></span>
                                <?php elseif ( $entry->date_due && strtotime( $entry->date_due ) < time() ) : ?>
                                    <span class="xen-badge xen-badge--overdue"><?php esc_html_e( 'Overdue', 'xen-inventory' ); ?></span>
                                <?php else : ?>
                                    <span class="xen-badge xen-badge--open"><?php esc_html_e( 'Open', 'xen-inventory' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Shortcode Reference -->
    <div class="xen-shortcode-reference">
        <h2><?php esc_html_e( 'Shortcode Reference', 'xen-inventory' ); ?></h2>
        <div class="xen-shortcode-cards">

            <div class="xen-shortcode-card">
                <div class="xen-shortcode-card__header">
                    <code class="xen-shortcode-card__code">[xen_inventory_display]</code>
                    <button
                        class="button button-small xen-copy-shortcode"
                        data-shortcode="[xen_inventory_display]"
                    ><?php esc_html_e( 'Copy', 'xen-inventory' ); ?></button>
                </div>
                <p class="xen-shortcode-card__desc">
                    <?php esc_html_e( 'Filterable item grid with borrow modal and active-borrows panel.', 'xen-inventory' ); ?>
                </p>
                <p class="xen-shortcode-card__attrs">
                    <strong><?php esc_html_e( 'Attributes:', 'xen-inventory' ); ?></strong>
                    <code>department=""</code>
                    <code>status=""</code>
                    <code>columns="3"</code>
                    <code>per_page="20"</code>
                </p>
            </div>

            <div class="xen-shortcode-card">
                <div class="xen-shortcode-card__header">
                    <code class="xen-shortcode-card__code">[xen_inventory_calendar]</code>
                    <button
                        class="button button-small xen-copy-shortcode"
                        data-shortcode="[xen_inventory_calendar]"
                    ><?php esc_html_e( 'Copy', 'xen-inventory' ); ?></button>
                </div>
                <p class="xen-shortcode-card__desc">
                    <?php esc_html_e( 'Interactive FullCalendar showing borrow history colour-coded by status.', 'xen-inventory' ); ?>
                </p>
                <p class="xen-shortcode-card__attrs">
                    <?php esc_html_e( 'No required attributes. Control guest access via Settings → Public Calendar.', 'xen-inventory' ); ?>
                </p>
            </div>

            <div class="xen-shortcode-card">
                <div class="xen-shortcode-card__header">
                    <code class="xen-shortcode-card__code">[xen_inventory_login]</code>
                    <button
                        class="button button-small xen-copy-shortcode"
                        data-shortcode="[xen_inventory_login]"
                    ><?php esc_html_e( 'Copy', 'xen-inventory' ); ?></button>
                </div>
                <p class="xen-shortcode-card__desc">
                    <?php esc_html_e( 'Branded frontend login form. Redirects to /inventory/ after successful login.', 'xen-inventory' ); ?>
                </p>
                <p class="xen-shortcode-card__attrs">
                    <?php esc_html_e( 'No attributes required.', 'xen-inventory' ); ?>
                </p>
            </div>

        </div>
    </div>

</div>
