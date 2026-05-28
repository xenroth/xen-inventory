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

// Version check data.
$installed_version = XEN_INVENTORY_VERSION;
$cached_release    = get_site_transient( 'xen_inventory_update_data' );
$remote_version    = '';
$release_body      = '';
if ( $cached_release instanceof stdClass ) {
    $remote_version = ltrim( $cached_release->tag_name ?? '', 'v' );
    $release_body   = $cached_release->body ?? '';
}
$update_available = $remote_version && version_compare( $remote_version, $installed_version, '>' );

$check_update_url = wp_nonce_url(
    add_query_arg( 'xen_check_update', '1', admin_url( 'plugins.php' ) ),
    'xen_check_update'
);
?>

<div class="wrap xen-admin-wrap">
    <h1><?php esc_html_e( 'XEN Inventory — Dashboard', 'xen-inventory' ); ?></h1>

    <!-- ===== TOP INFO ROW: Version + What's New + Quick Start ===== -->
    <div class="xen-dashboard-info-row">

        <!-- Version status card -->
        <div class="xen-info-card xen-info-card--version">
            <div class="xen-info-card__icon" aria-hidden="true">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <div class="xen-info-card__body">
                <h3 class="xen-info-card__title"><?php esc_html_e( 'Plugin Version', 'xen-inventory' ); ?></h3>
                <p class="xen-info-card__value">
                    v<?php echo esc_html( $installed_version ); ?>
                    <?php if ( $update_available ) : ?>
                        <span class="xen-badge xen-badge--overdue" style="margin-left:.5rem;">
                            <?php
                            printf(
                                /* translators: %s: new version number */
                                esc_html__( 'v%s available', 'xen-inventory' ),
                                esc_html( $remote_version )
                            );
                            ?>
                        </span>
                    <?php elseif ( $remote_version ) : ?>
                        <span class="xen-badge xen-badge--returned" style="margin-left:.5rem;"><?php esc_html_e( 'Up to date', 'xen-inventory' ); ?></span>
                    <?php endif; ?>
                </p>
                <a href="<?php echo esc_url( $check_update_url ); ?>" class="button button-small" style="margin-top:.5rem;">
                    <?php esc_html_e( 'Check for Updates', 'xen-inventory' ); ?>
                </a>
            </div>
        </div>

        <!-- What's New card -->
        <div class="xen-info-card xen-info-card--whats-new">
            <div class="xen-info-card__icon" aria-hidden="true">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
            </div>
            <div class="xen-info-card__body">
                <h3 class="xen-info-card__title">
                    <?php
                    printf(
                        /* translators: %s: version number */
                        esc_html__( "What's New in v%s", 'xen-inventory' ),
                        esc_html( $installed_version )
                    );
                    ?>
                </h3>
                <ul class="xen-whats-new-list">
                    <li><?php esc_html_e( 'Fixed double-click update phantom notice', 'xen-inventory' ); ?></li>
                    <li><?php esc_html_e( 'Danger Zone: purge all borrow records with audit log', 'xen-inventory' ); ?></li>
                    <li><?php esc_html_e( 'Dashboard version check + announcement cards', 'xen-inventory' ); ?></li>
                    <li><?php esc_html_e( 'Borrowers: status filters + CSV export', 'xen-inventory' ); ?></li>
                    <li><?php esc_html_e( 'Calendar scrollbar no longer overlaps grid columns', 'xen-inventory' ); ?></li>
                    <li><?php esc_html_e( 'Inventory grid: 3-row default, configurable column count', 'xen-inventory' ); ?></li>
                </ul>
            </div>
        </div>

        <!-- Quick Start guide card -->
        <div class="xen-info-card xen-info-card--guide">
            <div class="xen-info-card__icon" aria-hidden="true">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <div class="xen-info-card__body">
                <details class="xen-guide-details">
                    <summary class="xen-info-card__title"><?php esc_html_e( 'Quick Start Guide', 'xen-inventory' ); ?></summary>
                    <ol class="xen-guide-steps">
                        <li><strong><?php esc_html_e( 'Add Departments', 'xen-inventory' ); ?></strong> — <?php esc_html_e( 'Go to Inventory → Departments to create categories like "AV Equipment".', 'xen-inventory' ); ?></li>
                        <li><strong><?php esc_html_e( 'Add Items', 'xen-inventory' ); ?></strong> — <?php esc_html_e( 'Use Inventory → Add New Item. Set total quantity, status, and assign a department.', 'xen-inventory' ); ?></li>
                        <li><strong><?php esc_html_e( 'Place Shortcodes', 'xen-inventory' ); ?></strong> — <?php esc_html_e( 'Add [xen_inventory_display] to your inventory page and [xen_inventory_login] to your login page.', 'xen-inventory' ); ?></li>
                        <li><strong><?php esc_html_e( 'Set Login Page', 'xen-inventory' ); ?></strong> — <?php esc_html_e( 'Go to Settings and select the page that holds the login shortcode.', 'xen-inventory' ); ?></li>
                        <li><strong><?php esc_html_e( 'Borrow &amp; Return', 'xen-inventory' ); ?></strong> — <?php esc_html_e( 'Frontend users can borrow items; admins can return them via the Borrow Log.', 'xen-inventory' ); ?></li>
                        <li><strong><?php esc_html_e( 'View Calendar', 'xen-inventory' ); ?></strong> — <?php esc_html_e( 'Add [xen_inventory_calendar] to any page to see borrow history on a monthly calendar.', 'xen-inventory' ); ?></li>
                    </ol>
                </details>
            </div>
        </div>

    </div><!-- .xen-dashboard-info-row -->

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
                            <td><?php echo esc_html( $entry->borrower_full_name ?: $entry->borrower_name ); ?></td>
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
                    <code>per_page="9"</code>
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

    <!-- ===== COMPANY CREDITS ===== -->
    <div class="xen-credits-bar">
        <img
            src="<?php echo esc_url( plugins_url( 'assets/images/xenroth-logo.png', XEN_INVENTORY_FILE ) ); ?>"
            alt="Xenroth Digital Innovations"
            class="xen-credits-bar__logo"
            onerror="this.style.display='none'"
        />
        <div class="xen-credits-bar__text">
            <span class="xen-credits-bar__name">
                <?php esc_html_e( 'XEN Inventory', 'xen-inventory' ); ?>
            </span>
            <span class="xen-credits-bar__sep">·</span>
            <?php
            printf(
                /* translators: %s: developer name */
                esc_html__( 'Developed by %s', 'xen-inventory' ),
                '<strong>Richard C. Cupal, LPT</strong>'
            );
            ?>
            <span class="xen-credits-bar__sep">·</span>
            <a href="https://xenroth.com" target="_blank" rel="noopener noreferrer" class="xen-credits-bar__link">
                <?php esc_html_e( 'Xenroth Digital Innovations', 'xen-inventory' ); ?>
            </a>
            <span class="xen-credits-bar__sep">·</span>
            <a href="https://xenroth.com" target="_blank" rel="noopener noreferrer" class="xen-credits-bar__link">
                xenroth.com
            </a>
        </div>
    </div>

</div>
