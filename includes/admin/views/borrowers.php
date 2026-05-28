<?php
/**
 * Admin View: Borrowers page.
 *
 * Two modes:
 *   - List view (default): aggregated table of all unique borrowers with stats.
 *   - Detail view (?xen_borrower_id=N): full borrow history for one WP user.
 *
 * @package XenInventory\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$view_user_id = absint( $_GET['xen_borrower_id'] ?? 0 );

// ===========================================================================
// DETAIL VIEW
// ===========================================================================
if ( $view_user_id ) {

    $user = get_userdata( $view_user_id );
    if ( ! $user ) {
        wp_die( esc_html__( 'Borrower not found.', 'xen-inventory' ) );
    }

    $logs = \XenInventory\Models\InventoryLog::get_logs_for_borrower( $view_user_id );

    // Pull borrower_full_name / borrower_contact from the most recent log that has them.
    $latest_full_name = '';
    $latest_contact   = '';
    foreach ( $logs as $log ) {
        if ( '' === $latest_full_name && ! empty( $log->borrower_full_name ) ) {
            $latest_full_name = $log->borrower_full_name;
        }
        if ( '' === $latest_contact && ! empty( $log->borrower_contact ) ) {
            $latest_contact = $log->borrower_contact;
        }
        if ( $latest_full_name && $latest_contact ) {
            break;
        }
    }

    // Stats.
    $total    = count( $logs );
    $active   = 0;
    $returned = 0;
    $overdue  = 0;
    foreach ( $logs as $log ) {
        if ( $log->date_returned ) {
            $returned++;
        } elseif ( 'borrowed' === $log->action ) {
            $active++;
            if ( $log->date_due && strtotime( $log->date_due ) < time() ) {
                $overdue++;
            }
        }
    }

    $date_fmt    = get_option( 'date_format' );
    $back_url    = admin_url( 'admin.php?page=xen-borrowers' );
    ?>

    <div class="wrap xen-admin-wrap">

        <h1 class="wp-heading-inline">
            <?php echo esc_html( $user->display_name ); ?>
        </h1>
        <a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
            &larr; <?php esc_html_e( 'All Borrowers', 'xen-inventory' ); ?>
        </a>
        <hr class="wp-header-end">

        <!-- Borrower Profile Card -->
        <div class="xen-borrower-profile">
            <div class="xen-borrower-profile__row">
                <span class="xen-borrower-profile__label"><?php esc_html_e( 'WP Account', 'xen-inventory' ); ?></span>
                <span class="xen-borrower-profile__value">
                    <?php echo esc_html( $user->user_login ); ?>
                    <span class="xen-text-muted">&lt;<?php echo esc_html( $user->user_email ); ?>&gt;</span>
                </span>
            </div>

            <?php if ( $latest_full_name ) : ?>
            <div class="xen-borrower-profile__row">
                <span class="xen-borrower-profile__label"><?php esc_html_e( 'Full Name / Entity', 'xen-inventory' ); ?></span>
                <span class="xen-borrower-profile__value"><?php echo esc_html( $latest_full_name ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $latest_contact ) : ?>
            <div class="xen-borrower-profile__row">
                <span class="xen-borrower-profile__label"><?php esc_html_e( 'Contact', 'xen-inventory' ); ?></span>
                <span class="xen-borrower-profile__value"><?php echo esc_html( $latest_contact ); ?></span>
            </div>
            <?php endif; ?>

            <div class="xen-borrower-profile__row">
                <span class="xen-borrower-profile__label"><?php esc_html_e( 'Summary', 'xen-inventory' ); ?></span>
                <span class="xen-borrower-profile__value xen-borrower-stats">
                    <strong><?php echo (int) $total; ?></strong> <?php esc_html_e( 'total', 'xen-inventory' ); ?>
                    &nbsp;
                    <span class="xen-badge xen-badge--borrowed"><?php echo (int) $active; ?> <?php esc_html_e( 'active', 'xen-inventory' ); ?></span>
                    <?php if ( $overdue > 0 ) : ?>
                    <span class="xen-badge xen-badge--overdue"><?php echo (int) $overdue; ?> <?php esc_html_e( 'overdue', 'xen-inventory' ); ?></span>
                    <?php endif; ?>
                    <span class="xen-badge xen-badge--returned"><?php echo (int) $returned; ?> <?php esc_html_e( 'returned', 'xen-inventory' ); ?></span>
                </span>
            </div>
        </div><!-- .xen-borrower-profile -->

        <h2><?php esc_html_e( 'Borrow History', 'xen-inventory' ); ?></h2>

        <?php if ( empty( $logs ) ) : ?>
            <p><?php esc_html_e( 'No borrow history for this borrower.', 'xen-inventory' ); ?></p>
        <?php else : ?>

        <table class="wp-list-table widefat fixed striped xen-log-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Item',       'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Full Name',  'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Contact',    'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Action',     'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Qty',        'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Borrowed',   'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Due',        'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Returned',   'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Status',     'xen-inventory' ); ?></th>
                    <th><?php esc_html_e( 'Notes',      'xen-inventory' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $logs as $log ) :
                $due_time   = $log->date_due ? strtotime( $log->date_due ) : null;
                $is_overdue = ! $log->date_returned && $due_time && $due_time < time();

                if ( $log->date_returned ) {
                    $status_label = esc_html__( 'Returned', 'xen-inventory' );
                    $status_class = 'returned';
                } elseif ( $is_overdue ) {
                    $status_label = esc_html__( 'Overdue', 'xen-inventory' );
                    $status_class = 'overdue';
                } else {
                    $status_label = esc_html__( 'Open', 'xen-inventory' );
                    $status_class = 'open';
                }
            ?>
                <tr>
                    <td>
                        <?php if ( $log->item_id ) : ?>
                            <a href="<?php echo esc_url( get_edit_post_link( (int) $log->item_id ) ); ?>">
                                <?php echo esc_html( $log->item_title ?? __( '(deleted)', 'xen-inventory' ) ); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html( $log->item_title ?? '—' ); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $log->borrower_full_name ?? '' ); ?></td>
                    <td><?php echo esc_html( $log->borrower_contact ?? '' ); ?></td>
                    <td>
                        <span class="xen-badge xen-badge--<?php echo esc_attr( $log->action ); ?>">
                            <?php echo esc_html( ucfirst( $log->action ) ); ?>
                        </span>
                    </td>
                    <td><?php echo (int) $log->quantity; ?></td>
                    <td><?php echo esc_html( wp_date( $date_fmt, strtotime( $log->date_borrowed ) ) ); ?></td>
                    <td>
                        <?php if ( $due_time ) : ?>
                            <span class="<?php echo $is_overdue ? 'xen-badge xen-badge--overdue' : ''; ?>">
                                <?php echo esc_html( wp_date( $date_fmt, $due_time ) ); ?>
                            </span>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $log->date_returned
                            ? esc_html( wp_date( $date_fmt, strtotime( $log->date_returned ) ) )
                            : '—'; ?>
                    </td>
                    <td>
                        <span class="xen-badge xen-badge--<?php echo esc_attr( $status_class ); ?>">
                            <?php echo esc_html( $status_label ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( $log->notes ?? '' ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>

    </div><!-- .wrap -->

<?php
// ===========================================================================
// LIST VIEW
// ===========================================================================
} else {

    $borrowers = \XenInventory\Models\InventoryLog::get_borrowers_summary();
    $date_fmt  = get_option( 'date_format' );
    ?>

    <div class="wrap xen-admin-wrap">
        <h1><?php esc_html_e( 'Borrowers', 'xen-inventory' ); ?></h1>

        <?php if ( empty( $borrowers ) ) : ?>
            <p><?php esc_html_e( 'No borrowers recorded yet.', 'xen-inventory' ); ?></p>
        <?php else : ?>

        <p class="xen-log-count">
            <?php
            /* translators: %d: number of borrowers */
            printf( esc_html__( '%d borrower(s) found.', 'xen-inventory' ), count( $borrowers ) );
            ?>
        </p>

        <table class="wp-list-table widefat fixed striped xen-borrowers-table">
            <thead>
                <tr>
                    <th style="width:14%"><?php esc_html_e( 'WP Account',         'xen-inventory' ); ?></th>
                    <th style="width:18%"><?php esc_html_e( 'Full Name / Entity', 'xen-inventory' ); ?></th>
                    <th style="width:15%"><?php esc_html_e( 'Contact',            'xen-inventory' ); ?></th>
                    <th style="width:7%"><?php esc_html_e( 'Total',              'xen-inventory' ); ?></th>
                    <th style="width:7%"><?php esc_html_e( 'Active',             'xen-inventory' ); ?></th>
                    <th style="width:7%"><?php esc_html_e( 'Overdue',            'xen-inventory' ); ?></th>
                    <th style="width:7%"><?php esc_html_e( 'Returned',           'xen-inventory' ); ?></th>
                    <th style="width:12%"><?php esc_html_e( 'Last Borrowed',     'xen-inventory' ); ?></th>
                    <th style="width:10%"><?php esc_html_e( 'Actions',           'xen-inventory' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $borrowers as $row ) :
                $user       = $row->user_id ? get_userdata( (int) $row->user_id ) : null;
                $detail_url = $row->user_id
                    ? add_query_arg( 'xen_borrower_id', (int) $row->user_id, admin_url( 'admin.php?page=xen-borrowers' ) )
                    : '';
            ?>
                <tr>
                    <td>
                        <?php if ( $user ) : ?>
                            <strong><?php echo esc_html( $user->display_name ); ?></strong>
                            <br><span class="xen-text-muted"><?php echo esc_html( $user->user_login ); ?></span>
                        <?php else : ?>
                            <?php echo esc_html( $row->borrower_name ); ?>
                            <br><span class="xen-text-muted"><?php esc_html_e( '(guest)', 'xen-inventory' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $row->borrower_full_name ?? '' ); ?></td>
                    <td><?php echo esc_html( $row->borrower_contact ?? '' ); ?></td>
                    <td><strong><?php echo (int) $row->total_borrows; ?></strong></td>
                    <td>
                        <?php if ( (int) $row->active_borrows > 0 ) : ?>
                            <span class="xen-badge xen-badge--borrowed"><?php echo (int) $row->active_borrows; ?></span>
                        <?php else : ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( (int) $row->overdue_borrows > 0 ) : ?>
                            <span class="xen-badge xen-badge--overdue"><?php echo (int) $row->overdue_borrows; ?></span>
                        <?php else : ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td><?php echo (int) $row->returned_borrows; ?></td>
                    <td>
                        <?php echo $row->last_borrowed
                            ? esc_html( wp_date( $date_fmt, strtotime( $row->last_borrowed ) ) )
                            : '—'; ?>
                    </td>
                    <td>
                        <?php if ( $detail_url ) : ?>
                            <a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small">
                                <?php esc_html_e( 'View History', 'xen-inventory' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>

    </div><!-- .wrap -->

<?php } ?>
