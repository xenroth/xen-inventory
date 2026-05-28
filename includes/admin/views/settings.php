<?php
/**
 * Admin View: Settings page.
 *
 * Uses the WordPress Settings API (registered in Settings class).
 *
 * @package XenInventory\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- Purge status notice ---
$purge_status = sanitize_key( $_GET['xen_purge'] ?? '' );
$purge_count  = absint( $_GET['xen_purge_count'] ?? 0 );
?>
<div class="wrap xen-admin-wrap">
    <h1><?php esc_html_e( 'XEN Inventory Settings', 'xen-inventory' ); ?></h1>

    <?php if ( 'done' === $purge_status ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %d: number of records deleted */
                    esc_html__( 'All borrow/return records have been deleted. %d row(s) removed.', 'xen-inventory' ),
                    $purge_count
                );
                ?>
            </p>
        </div>
    <?php elseif ( 'invalid' === $purge_status ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e( 'Deletion cancelled: confirmation text did not match "CONFIRM DELETION".', 'xen-inventory' ); ?></p>
        </div>
    <?php elseif ( 'no_reason' === $purge_status ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e( 'Deletion cancelled: a reason is required.', 'xen-inventory' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'xen_inventory_settings_group' );
        do_settings_sections( 'xen-inventory-settings' );
        submit_button();
        ?>
    </form>

    <hr />

    <!-- ===== DANGER ZONE ===== -->
    <div class="xen-danger-zone">
        <h2 class="xen-danger-zone__heading">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <?php esc_html_e( 'Danger Zone', 'xen-inventory' ); ?>
        </h2>

        <div class="xen-danger-zone__card">
            <div class="xen-danger-zone__card-info">
                <strong><?php esc_html_e( 'Delete All Borrow &amp; Return Records', 'xen-inventory' ); ?></strong>
                <p class="description">
                    <?php esc_html_e( 'Permanently removes every row from the borrow log. This action cannot be undone. Inventory items and settings are not affected. An audit entry is stored recording who deleted the records and why.', 'xen-inventory' ); ?>
                </p>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xen-purge-form" class="xen-danger-zone__form">
                <?php wp_nonce_field( 'xen_purge_borrow_log' ); ?>
                <input type="hidden" name="action" value="xen_purge_borrow_log" />

                <div class="xen-danger-zone__field">
                    <label for="xen_purge_reason">
                        <strong><?php esc_html_e( 'Reason for deletion', 'xen-inventory' ); ?></strong>
                        <span class="xen-required" aria-label="<?php esc_attr_e( 'required', 'xen-inventory' ); ?>">*</span>
                    </label>
                    <textarea
                        id="xen_purge_reason"
                        name="xen_purge_reason"
                        rows="2"
                        class="large-text"
                        placeholder="<?php esc_attr_e( 'Why are you deleting all records?', 'xen-inventory' ); ?>"
                        required
                    ></textarea>
                </div>

                <div class="xen-danger-zone__field">
                    <label for="xen_purge_confirm">
                        <strong><?php esc_html_e( 'Type CONFIRM DELETION to enable the button', 'xen-inventory' ); ?></strong>
                    </label>
                    <input
                        type="text"
                        id="xen_purge_confirm"
                        name="xen_purge_confirm"
                        class="regular-text"
                        autocomplete="off"
                        placeholder="CONFIRM DELETION"
                    />
                </div>

                <button
                    type="submit"
                    id="xen-purge-btn"
                    class="button xen-danger-zone__submit"
                    disabled
                >
                    <?php esc_html_e( 'Delete All Records', 'xen-inventory' ); ?>
                </button>
            </form>
        </div>

        <!-- Audit log (shown only if entries exist) -->
        <?php
        $audit = get_option( 'xen_purge_audit_log', [] );
        if ( ! empty( $audit ) ) :
            $audit = array_reverse( $audit );
            $date_fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        ?>
        <div class="xen-danger-zone__audit">
            <h3><?php esc_html_e( 'Deletion Audit Log', 'xen-inventory' ); ?></h3>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date',     'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'User',     'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Records',  'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Reason',   'xen-inventory' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $audit as $entry ) : ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( $date_fmt, strtotime( $entry['date'] ) ) ); ?></td>
                        <td><?php echo esc_html( $entry['user_display'] ?? '—' ); ?></td>
                        <td><?php echo (int) ( $entry['records_deleted'] ?? 0 ); ?></td>
                        <td><?php echo esc_html( $entry['reason'] ?? '—' ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <hr />

    <h2><?php esc_html_e( 'Shortcode Reference', 'xen-inventory' ); ?></h2>
    <table class="widefat fixed">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Shortcode',   'xen-inventory' ); ?></th>
                <th><?php esc_html_e( 'Description', 'xen-inventory' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[xen_inventory_display]</code></td>
                <td><?php esc_html_e( 'Frontend item grid with department filter and availability status.', 'xen-inventory' ); ?></td>
            </tr>
            <tr>
                <td><code>[xen_inventory_calendar]</code></td>
                <td><?php esc_html_e( 'Interactive FullCalendar showing borrow history.', 'xen-inventory' ); ?></td>
            </tr>
            <tr>
                <td><code>[xen_inventory_login]</code></td>
                <td><?php esc_html_e( 'Frontend login form for inventory users.', 'xen-inventory' ); ?></td>
            </tr>
        </tbody>
    </table>
</div>
