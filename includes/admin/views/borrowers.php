<?php
/**
 * Admin View: Borrowers page.
 *
 * Two modes:
 *   - List view (default): aggregated table of all unique borrower entities
 *     (grouped by borrower_full_name, case-insensitive).
 *   - Detail view (?xen_entity=<name>): full borrow history for one entity.
 *
 * Identity is determined by the borrower_full_name entered in the borrow form,
 * NOT by the WP account that performed the action.  The same name (regardless of
 * capitalisation) is always merged into a single entity.
 *
 * @package XenInventory\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Grab and sanitize the entity name URL param.
$view_entity = isset( $_GET['xen_entity'] ) ? sanitize_text_field( wp_unslash( $_GET['xen_entity'] ) ) : '';

// ===========================================================================
// DETAIL VIEW — history for a single borrower entity
// ===========================================================================
if ( '' !== $view_entity ) {

    $logs = \XenInventory\Models\InventoryLog::get_logs_for_entity( $view_entity );

    // Derive a nicely-cased display name and contact from the most recent log.
    $display_name   = '';
    $latest_contact = '';
    foreach ( $logs as $log ) {
        if ( '' === $display_name && ! empty( $log->borrower_full_name ) ) {
            $display_name = $log->borrower_full_name;
        }
        if ( '' === $latest_contact && ! empty( $log->borrower_contact ) ) {
            $latest_contact = $log->borrower_contact;
        }
        if ( $display_name && $latest_contact ) {
            break;
        }
    }
    if ( '' === $display_name ) {
        $display_name = $view_entity;
    }

    // Collect all unique WP accounts associated with this entity.
    $associated_users = [];
    foreach ( $logs as $log ) {
        $uid = (int) $log->user_id;
        if ( $uid && ! isset( $associated_users[ $uid ] ) ) {
            $u = get_userdata( $uid );
            if ( $u ) {
                $associated_users[ $uid ] = $u;
            }
        }
    }

    // Summary stats.
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

    $date_fmt = get_option( 'date_format' );
    $back_url = admin_url( 'admin.php?page=xen-borrowers' );
    ?>

    <div class="wrap xen-admin-wrap xen-borrowers-wrap">

        <!-- Page header -->
        <div class="xen-borrower-detail-header">
            <div class="xen-borrower-detail-header__back">
                <a href="<?php echo esc_url( $back_url ); ?>" class="xen-back-btn">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <?php esc_html_e( 'All Borrowers', 'xen-inventory' ); ?>
                </a>
            </div>
            <div class="xen-borrower-detail-header__title-row">
                <div class="xen-entity-avatar" aria-hidden="true">
                    <?php echo esc_html( mb_strtoupper( mb_substr( $display_name, 0, 1 ) ) ); ?>
                </div>
                <div>
                    <h1 class="xen-borrower-detail-header__name"><?php echo esc_html( $display_name ); ?></h1>
                    <?php if ( $latest_contact ) : ?>
                        <p class="xen-borrower-detail-header__contact">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21L8.5 10.5s1 2 3 3 3 3 3 3l.113-1.724a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <?php echo esc_html( $latest_contact ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats row -->
        <div class="xen-entity-stats">
            <div class="xen-entity-stat">
                <span class="xen-entity-stat__number"><?php echo (int) $total; ?></span>
                <span class="xen-entity-stat__label"><?php esc_html_e( 'Total', 'xen-inventory' ); ?></span>
            </div>
            <div class="xen-entity-stat xen-entity-stat--active">
                <span class="xen-entity-stat__number"><?php echo (int) $active; ?></span>
                <span class="xen-entity-stat__label"><?php esc_html_e( 'Active', 'xen-inventory' ); ?></span>
            </div>
            <?php if ( $overdue > 0 ) : ?>
            <div class="xen-entity-stat xen-entity-stat--overdue">
                <span class="xen-entity-stat__number"><?php echo (int) $overdue; ?></span>
                <span class="xen-entity-stat__label"><?php esc_html_e( 'Overdue', 'xen-inventory' ); ?></span>
            </div>
            <?php endif; ?>
            <div class="xen-entity-stat xen-entity-stat--returned">
                <span class="xen-entity-stat__number"><?php echo (int) $returned; ?></span>
                <span class="xen-entity-stat__label"><?php esc_html_e( 'Returned', 'xen-inventory' ); ?></span>
            </div>
        </div>

        <!-- Associated WP accounts (informational only) -->
        <?php if ( ! empty( $associated_users ) ) : ?>
        <div class="xen-entity-accounts">
            <h3 class="xen-entity-accounts__heading">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <?php esc_html_e( 'Associated WP Accounts', 'xen-inventory' ); ?>
            </h3>
            <div class="xen-entity-accounts__list">
                <?php foreach ( $associated_users as $u ) : ?>
                    <span class="xen-entity-account-chip">
                        <strong><?php echo esc_html( $u->display_name ); ?></strong>
                        <span class="xen-text-muted">&lt;<?php echo esc_html( $u->user_email ); ?>&gt;</span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Borrow History -->
        <div class="xen-section-heading">
            <h2><?php esc_html_e( 'Borrow History', 'xen-inventory' ); ?></h2>
            <span class="xen-section-heading__count"><?php echo (int) $total; ?></span>
        </div>

        <?php if ( empty( $logs ) ) : ?>
            <div class="xen-empty-state">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <p><?php esc_html_e( 'No borrow history for this entity.', 'xen-inventory' ); ?></p>
            </div>
        <?php else : ?>
        <div class="xen-table-wrap">
            <table class="wp-list-table widefat fixed striped xen-log-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Item',       'xen-inventory' ); ?></th>
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
                        <td class="xen-notes-cell"><?php echo esc_html( $log->notes ?? '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div><!-- .xen-table-wrap -->
        <?php endif; ?>

    </div><!-- .wrap -->

<?php
// ===========================================================================
// LIST VIEW — all borrower entities
// ===========================================================================
} else {

    $borrowers      = \XenInventory\Models\InventoryLog::get_borrowers_summary();
    $date_fmt       = get_option( 'date_format' );
    $total_entities = count( $borrowers );
    ?>

    <div class="wrap xen-admin-wrap xen-borrowers-wrap">

        <!-- Page header -->
        <div class="xen-borrowers-page-header">
            <div>
                <h1 class="xen-borrowers-page-header__title">
                    <?php esc_html_e( 'Borrowers', 'xen-inventory' ); ?>
                    <?php if ( $total_entities > 0 ) : ?>
                        <span class="xen-entity-count-chip"><?php echo (int) $total_entities; ?></span>
                    <?php endif; ?>
                </h1>
                <p class="xen-borrowers-page-header__sub">
                    <?php
                    printf(
                        /* translators: %d: number of unique borrower entities */
                        esc_html__( '%d borrower entities — identified by name entered at borrow time, not WP account.', 'xen-inventory' ),
                        (int) $total_entities
                    );
                    ?>
                </p>
            </div>
            <?php if ( ! empty( $borrowers ) ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="xen-borrowers-export-form">
                <?php wp_nonce_field( 'xen_export_borrowers_csv' ); ?>
                <input type="hidden" name="action" value="xen_export_borrowers_csv" />
                <button type="submit" class="button">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <?php esc_html_e( 'Export CSV', 'xen-inventory' ); ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ( empty( $borrowers ) ) : ?>
            <div class="xen-empty-state">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <p><?php esc_html_e( 'No borrowers recorded yet.', 'xen-inventory' ); ?></p>
            </div>
        <?php else : ?>

        <!-- Live search + filter bar -->
        <div class="xen-borrowers-toolbar">
            <input
                type="search"
                id="xen-borrower-search"
                class="xen-borrowers-search"
                placeholder="<?php esc_attr_e( 'Filter by name or contact…', 'xen-inventory' ); ?>"
                aria-label="<?php esc_attr_e( 'Filter borrowers', 'xen-inventory' ); ?>"
            >
            <div class="xen-borrowers-filters">
                <select id="xen-borrower-status-filter" class="xen-borrowers-filter-select" aria-label="<?php esc_attr_e( 'Filter by status', 'xen-inventory' ); ?>">
                    <option value=""><?php esc_html_e( 'All statuses', 'xen-inventory' ); ?></option>
                    <option value="active"><?php esc_html_e( 'Has active borrows', 'xen-inventory' ); ?></option>
                    <option value="overdue"><?php esc_html_e( 'Has overdue', 'xen-inventory' ); ?></option>
                    <option value="returned"><?php esc_html_e( 'Returned only', 'xen-inventory' ); ?></option>
                </select>
            </div>
        </div>

        <div class="xen-table-wrap">
        <table class="wp-list-table widefat fixed striped xen-borrowers-table" id="xen-borrowers-table">
            <thead>
                <tr>
                    <th class="xen-col-entity"><?php esc_html_e( 'Entity / Borrower',       'xen-inventory' ); ?></th>
                    <th class="xen-col-contact"><?php esc_html_e( 'Contact',                 'xen-inventory' ); ?></th>
                    <th class="xen-col-stat"><?php esc_html_e( 'Transactions',               'xen-inventory' ); ?></th>
                    <th class="xen-col-stat"><?php esc_html_e( 'Active',                     'xen-inventory' ); ?></th>
                    <th class="xen-col-stat"><?php esc_html_e( 'Overdue',                    'xen-inventory' ); ?></th>
                    <th class="xen-col-stat"><?php esc_html_e( 'Returned',                   'xen-inventory' ); ?></th>
                    <th class="xen-col-date"><?php esc_html_e( 'Last Borrowed',              'xen-inventory' ); ?></th>
                    <th class="xen-col-actions"><?php esc_html_e( 'Actions',                 'xen-inventory' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $borrowers as $row ) :
                $detail_url = add_query_arg(
                    'xen_entity',
                    rawurlencode( $row->display_name ),
                    admin_url( 'admin.php?page=xen-borrowers' )
                );
                $initials = mb_strtoupper( mb_substr( $row->display_name, 0, 1 ) );

                // Build status flags for JS filtering.
                $status_flags = '';
                if ( (int) $row->active_borrows > 0 )  $status_flags .= ' active';
                if ( (int) $row->overdue_borrows > 0 )  $status_flags .= ' overdue';
                if ( (int) $row->active_borrows === 0 && (int) $row->returned_borrows > 0 ) $status_flags .= ' returned';
            ?>
                <tr
                    data-search="<?php echo esc_attr( strtolower( $row->display_name . ' ' . ( $row->borrower_contact ?? '' ) ) ); ?>"
                    data-status="<?php echo esc_attr( trim( $status_flags ) ); ?>"
                >
                    <td>
                        <div class="xen-entity-name-cell">
                            <div class="xen-entity-avatar xen-entity-avatar--sm" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
                            <strong class="xen-entity-primary"><?php echo esc_html( $row->display_name ); ?></strong>
                        </div>
                    </td>
                    <td class="xen-contact-cell"><?php echo esc_html( $row->borrower_contact ?? '' ); ?></td>
                    <td><strong><?php echo (int) $row->total_borrows; ?></strong></td>
                    <td>
                        <?php if ( (int) $row->active_borrows > 0 ) : ?>
                            <span class="xen-badge xen-badge--borrowed"><?php echo (int) $row->active_borrows; ?></span>
                        <?php else : ?>
                            <span class="xen-zero">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( (int) $row->overdue_borrows > 0 ) : ?>
                            <span class="xen-badge xen-badge--overdue"><?php echo (int) $row->overdue_borrows; ?></span>
                        <?php else : ?>
                            <span class="xen-zero">0</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo (int) $row->returned_borrows; ?></td>
                    <td class="xen-date-cell">
                        <?php echo $row->last_borrowed
                            ? esc_html( wp_date( $date_fmt, strtotime( $row->last_borrowed ) ) )
                            : '—'; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small xen-btn-history">
                            <?php esc_html_e( 'View History', 'xen-inventory' ); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div><!-- .xen-table-wrap -->

        <?php endif; ?>

    </div><!-- .wrap -->

<script>
( function () {
    var searchInput  = document.getElementById( 'xen-borrower-search' );
    var statusSelect = document.getElementById( 'xen-borrower-status-filter' );

    function applyFilters() {
        var q      = searchInput  ? searchInput.value.toLowerCase().trim()  : '';
        var status = statusSelect ? statusSelect.value.toLowerCase().trim() : '';
        var rows   = document.querySelectorAll( '#xen-borrowers-table tbody tr' );

        rows.forEach( function ( row ) {
            var haystack    = ( row.dataset.search || '' );
            var rowStatus   = ( row.dataset.status || '' );

            var matchSearch = ! q      || haystack.indexOf( q ) > -1;
            var matchStatus = ! status || rowStatus.indexOf( status ) > -1;

            row.style.display = ( matchSearch && matchStatus ) ? '' : 'none';
        } );
    }

    if ( searchInput )  searchInput.addEventListener( 'input',  applyFilters );
    if ( statusSelect ) statusSelect.addEventListener( 'change', applyFilters );
} )();
</script>

<?php } ?>
