<?php
/**
 * Frontend View: Inventory Display ([xen_inventory_display] shortcode).
 *
 * Variables available from Shortcodes::render_inventory_display():
 *   $items_query   WP_Query
 *   $atts          array   Shortcode attributes (columns, per_page, status, department).
 *   $departments   array   WP_Term[]
 *
 * @package XenInventory\Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_dept   = sanitize_text_field( $_GET['xen_dept']   ?? $atts['department'] );
$current_status = sanitize_key( $_GET['xen_status'] ?? $atts['status'] );
?>

<div class="xen-inventory-wrap" id="xen-inventory">

    <!-- Filters -->
    <form class="xen-filters" method="get" action="">
        <div class="xen-filters__inner">

            <!-- Department filter -->
            <label for="xen-dept-filter" class="screen-reader-text">
                <?php esc_html_e( 'Filter by Department', 'xen-inventory' ); ?>
            </label>
            <select id="xen-dept-filter" name="xen_dept" class="xen-select">
                <option value=""><?php esc_html_e( 'All Departments', 'xen-inventory' ); ?></option>
                <?php foreach ( $departments as $dept ) : ?>
                    <option value="<?php echo esc_attr( $dept->slug ); ?>" <?php selected( $current_dept, $dept->slug ); ?>>
                        <?php echo esc_html( $dept->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Status filter -->
            <label for="xen-status-filter" class="screen-reader-text">
                <?php esc_html_e( 'Filter by Status', 'xen-inventory' ); ?>
            </label>
            <select id="xen-status-filter" name="xen_status" class="xen-select">
                <option value=""><?php esc_html_e( 'All Statuses', 'xen-inventory' ); ?></option>
                <option value="available"   <?php selected( $current_status, 'available' ); ?>><?php esc_html_e( 'Available',   'xen-inventory' ); ?></option>
                <option value="borrowed"    <?php selected( $current_status, 'borrowed' ); ?>><?php esc_html_e( 'Borrowed',     'xen-inventory' ); ?></option>
                <option value="maintenance" <?php selected( $current_status, 'maintenance' ); ?>><?php esc_html_e( 'Maintenance', 'xen-inventory' ); ?></option>
            </select>

            <button type="submit" class="xen-btn xen-btn--secondary">
                <?php esc_html_e( 'Filter', 'xen-inventory' ); ?>
            </button>
        </div>
    </form>

    <!-- Item Grid -->
    <?php if ( $items_query->have_posts() ) : ?>

        <div class="xen-items-grid xen-items-grid--cols-<?php echo (int) $atts['columns']; ?>">
            <?php while ( $items_query->have_posts() ) : $items_query->the_post(); ?>

                <?php
                $item_status   = get_post_meta( get_the_ID(), '_xen_item_status',   true ) ?: 'available';
                $total_qty     = (int) get_post_meta( get_the_ID(), '_xen_total_quantity', true );
                $item_depts    = get_the_terms( get_the_ID(), 'xen_department' );
                $available_qty = \XenInventory\Models\InventoryLog::get_available_quantity( get_the_ID() );
                $item_excerpt  = get_the_excerpt();
                ?>

                <article class="xen-item-card xen-item-card--<?php echo esc_attr( $item_status ); ?>">

                    <!-- Image with overlaid status badge -->
                    <div class="xen-item-card__image-wrap">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                                <?php the_post_thumbnail( 'medium', [ 'class' => 'xen-item-card__img' ] ); ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true" class="xen-item-card__placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" aria-hidden="true" focusable="false">
                                    <rect x="14" y="10" width="52" height="8" rx="2" fill="currentColor" opacity=".35"/>
                                    <rect x="18" y="22" width="44" height="38" rx="3" fill="currentColor" opacity=".18"/>
                                    <rect x="30" y="22" width="20" height="16" rx="1" fill="currentColor" opacity=".4"/>
                                    <circle cx="24" cy="66" r="5" fill="currentColor" opacity=".35"/>
                                    <circle cx="56" cy="66" r="5" fill="currentColor" opacity=".35"/>
                                    <rect x="19" y="62" width="42" height="6" rx="1" fill="currentColor" opacity=".18"/>
                                </svg>
                            </a>
                        <?php endif; ?>

                        <span class="xen-status-badge xen-status-badge--<?php echo esc_attr( $item_status ); ?>">
                            <?php echo esc_html( ucfirst( $item_status ) ); ?>
                        </span>
                    </div><!-- .xen-item-card__image-wrap -->

                    <div class="xen-item-card__body">

                        <?php if ( $item_depts && ! is_wp_error( $item_depts ) ) : ?>
                            <p class="xen-item-card__dept">
                                <?php echo esc_html( implode( ', ', wp_list_pluck( $item_depts, 'name' ) ) ); ?>
                            </p>
                        <?php endif; ?>

                        <h3 class="xen-item-card__title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>

                        <p class="xen-item-card__desc">
                            <?php echo esc_html( $item_excerpt ?: __( 'A managed inventory item available for staff borrowing.', 'xen-inventory' ) ); ?>
                        </p>

                        <div class="xen-item-card__stock">
                            <?php if ( $available_qty > 0 ) : ?>
                                <span class="xen-item-card__stock-in">
                                    <?php
                                    /* translators: %d: available quantity */
                                    printf( esc_html__( '%d available', 'xen-inventory' ), $available_qty );
                                    ?>
                                </span>
                            <?php else : ?>
                                <span class="xen-item-card__stock-out">
                                    <?php esc_html_e( 'Out of stock', 'xen-inventory' ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( $total_qty ) : ?>
                                <span class="xen-item-card__stock-total">
                                    <?php
                                    /* translators: %d: total quantity */
                                    printf( esc_html__( '/ %d total', 'xen-inventory' ), $total_qty );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php
                        // When the 'borrowed' filter is active, show who currently has this item.
                        $item_borrows = $active_borrowers[ get_the_ID() ] ?? [];
                        if ( $current_status === 'borrowed' && ! empty( $item_borrows ) ) :
                        ?>
                            <div class="xen-item-card__borrowers">
                                <p class="xen-item-card__borrowers-label"><?php esc_html_e( 'Currently borrowed by:', 'xen-inventory' ); ?></p>
                                <ul class="xen-item-card__borrower-list">
                                    <?php foreach ( $item_borrows as $borrow ) : ?>
                                        <li class="xen-item-card__borrower-row">
                                            <span class="xen-item-card__borrower-name">
                                                <?php echo esc_html( $borrow->borrower_full_name ?: $borrow->borrower_name ); ?>
                                            </span>
                                            <?php if ( $borrow->borrower_contact ) : ?>
                                                <span class="xen-item-card__borrower-contact">
                                                    <?php echo esc_html( $borrow->borrower_contact ); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="xen-item-card__borrower-meta">
                                                ×<?php echo (int) $borrow->quantity; ?>
                                                <?php if ( $borrow->date_due ) : ?>
                                                    · <?php esc_html_e( 'due', 'xen-inventory' ); ?>
                                                    <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $borrow->date_due ) ) ); ?>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ( $available_qty > 0 && current_user_can( 'xen_borrow_items' ) ) : ?>
                            <button
                                class="xen-btn xen-btn--primary xen-borrow-btn xen-item-card__cta"
                                data-item-id="<?php echo (int) get_the_ID(); ?>"
                                data-item-title="<?php echo esc_attr( get_the_title() ); ?>"
                            >
                                <?php esc_html_e( 'Borrow', 'xen-inventory' ); ?>
                            </button>
                        <?php elseif ( 'maintenance' === $item_status ) : ?>
                            <button class="xen-btn xen-item-card__cta xen-item-card__cta--maintenance" disabled>
                                <?php esc_html_e( 'Under Maintenance', 'xen-inventory' ); ?>
                            </button>
                        <?php else : ?>
                            <button class="xen-btn xen-item-card__cta xen-item-card__cta--unavailable" disabled>
                                <?php esc_html_e( 'Unavailable', 'xen-inventory' ); ?>
                            </button>
                        <?php endif; ?>

                    </div><!-- .xen-item-card__body -->
                </article>

            <?php endwhile; wp_reset_postdata(); ?>
        </div><!-- .xen-items-grid -->

        <!-- Pagination -->
        <div class="xen-pagination">
            <?php
            $pagination_args = array_filter( [
                'xen_dept'   => $current_dept,
                'xen_status' => $current_status,
            ] );
            echo paginate_links( [
                'total'     => $items_query->max_num_pages,
                'current'   => max( 1, absint( get_query_var( 'paged' ) ) ),
                'add_args'  => $pagination_args,
            ] );
            ?>
        </div>

    <?php else : ?>
        <p class="xen-notice"><?php esc_html_e( 'No items found matching your criteria.', 'xen-inventory' ); ?></p>
    <?php endif; ?>

    <!-- Borrow Modal -->
    <div class="xen-modal" id="xen-borrow-modal" role="dialog" aria-modal="true" aria-labelledby="xen-modal-title" hidden>
        <div class="xen-modal__overlay" data-xen-close-modal></div>
        <div class="xen-modal__content">
            <h2 class="xen-modal__title" id="xen-modal-title"><?php esc_html_e( 'Borrow Item', 'xen-inventory' ); ?></h2>
            <?php
            $current_user = wp_get_current_user();
            $default_fullname = trim( ( $current_user->first_name ?? '' ) . ' ' . ( $current_user->last_name ?? '' ) ) ?: ( $current_user->display_name ?? '' );
            ?>
            <form id="xen-borrow-form" class="xen-form" data-user-fullname="<?php echo esc_attr( $default_fullname ); ?>">
                <input type="hidden" name="item_id" id="xen-borrow-item-id" value="" />

                <div class="xen-form__group">
                    <label for="xen-borrow-fullname">
                        <?php esc_html_e( 'Full Name / Entity', 'xen-inventory' ); ?>
                        <span class="xen-required-star" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="xen-borrow-fullname"
                        name="borrower_full_name"
                        required
                        placeholder="<?php esc_attr_e( 'e.g. Juan dela Cruz or IT Department', 'xen-inventory' ); ?>"
                    />
                </div>

                <div class="xen-form__group">
                    <label for="xen-borrow-contact">
                        <?php esc_html_e( 'Contact', 'xen-inventory' ); ?>
                        <span class="xen-form__optional"><?php esc_html_e( '(mobile or Facebook — optional)', 'xen-inventory' ); ?></span>
                    </label>
                    <input
                        type="text"
                        id="xen-borrow-contact"
                        name="borrower_contact"
                        placeholder="<?php esc_attr_e( '+63 912 345 6789 or fb.com/yourname', 'xen-inventory' ); ?>"
                    />
                </div>

                <div class="xen-form__group">
                    <label for="xen-borrow-tags">
                        <?php esc_html_e( 'Purpose / Tags', 'xen-inventory' ); ?>
                        <span class="xen-form__optional"><?php esc_html_e( '(optional)', 'xen-inventory' ); ?></span>
                    </label>
                    <input
                        type="text"
                        id="xen-borrow-tags"
                        name="borrow_tags"
                        placeholder="<?php esc_attr_e( 'e.g. IT Conference, Zone B, Annual Event', 'xen-inventory' ); ?>"
                    />
                    <p class="xen-form__hint"><?php esc_html_e( 'Separate multiple tags with commas.', 'xen-inventory' ); ?></p>
                </div>

                <div class="xen-form__group">
                    <label for="xen-borrow-quantity"><?php esc_html_e( 'Quantity', 'xen-inventory' ); ?></label>
                    <input type="number" id="xen-borrow-quantity" name="quantity" value="1" min="1" required />
                </div>

                <div class="xen-form__group">
                    <label for="xen-borrow-due"><?php esc_html_e( 'Expected Return Date &amp; Time', 'xen-inventory' ); ?></label>
                    <input type="datetime-local" id="xen-borrow-due" name="date_due" />
                </div>

                <div class="xen-form__group">
                    <label for="xen-borrow-notes"><?php esc_html_e( 'Notes', 'xen-inventory' ); ?></label>
                    <textarea id="xen-borrow-notes" name="notes" rows="3"></textarea>
                </div>

                <div class="xen-form__actions">
                    <button type="submit" class="xen-btn xen-btn--primary"><?php esc_html_e( 'Confirm Borrow', 'xen-inventory' ); ?></button>
                    <button type="button" class="xen-btn xen-btn--ghost" data-xen-close-modal><?php esc_html_e( 'Cancel', 'xen-inventory' ); ?></button>
                </div>

                <div class="xen-form__message" aria-live="polite"></div>
            </form>
        </div>
    </div>

    <!-- My Active Borrows -->
    <?php
    $my_borrows = \XenInventory\Models\InventoryLog::get_open_borrows_for_user( get_current_user_id() );
    if ( ! empty( $my_borrows ) ) :
    ?>
    <div class="xen-my-borrows">
        <h3 class="xen-my-borrows__title"><?php esc_html_e( 'My Active Borrows', 'xen-inventory' ); ?></h3>

        <!-- Filter toolbar -->
        <div class="xen-borrows-toolbar" style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;flex-wrap:wrap;">
            <input
                type="search"
                id="xen-borrows-filter"
                class="xen-input"
                placeholder="<?php esc_attr_e( 'Filter by item name…', 'xen-inventory' ); ?>"
                aria-label="<?php esc_attr_e( 'Filter active borrows by item name', 'xen-inventory' ); ?>"
                autocomplete="off"
                style="flex:1 1 200px;max-width:280px;"
            />
            <span class="xen-borrows-count" id="xen-borrows-count" style="font-size:.85rem;color:#666;"></span>
        </div>

        <div class="xen-borrows-list">
            <?php foreach ( $my_borrows as $borrow ) :
                $due_time   = $borrow->date_due ? strtotime( $borrow->date_due ) : null;
                $is_overdue = $due_time && $due_time < time();
                $bdf        = get_option( 'date_format' );
                $btf        = get_option( 'time_format' );
            ?>
            <div class="xen-return-row<?php echo $is_overdue ? ' xen-return-row--overdue' : ''; ?>"
                data-log-id="<?php echo (int) $borrow->id; ?>"
                data-item-title="<?php echo esc_attr( $borrow->item_title ); ?>"
                data-borrower-name="<?php echo esc_attr( $borrow->borrower_name ?? '' ); ?>"
                data-borrower-full-name="<?php echo esc_attr( $borrow->borrower_full_name ?? '' ); ?>"
                data-borrower-contact="<?php echo esc_attr( $borrow->borrower_contact ?? '' ); ?>"
                data-borrow-tags="<?php echo esc_attr( $borrow->borrow_tags ?? '' ); ?>"
                data-qty="<?php echo (int) $borrow->quantity; ?>"
                data-date-borrowed="<?php echo esc_attr( $borrow->date_borrowed ?? '' ); ?>"
                data-date-due="<?php echo esc_attr( $borrow->date_due ?? '' ); ?>"
                data-notes="<?php echo esc_attr( $borrow->notes ?? '' ); ?>"
                data-return-notes=""
                data-item-condition=""
                title="<?php esc_attr_e( 'Double-click to view full details', 'xen-inventory' ); ?>"
            >
                <div class="xen-return-row__item">
                    <strong><?php echo esc_html( $borrow->item_title ); ?></strong>
                    <span class="xen-return-row__qty">
                        <?php
                        /* translators: %d: quantity borrowed */
                        printf( esc_html__( 'Qty borrowed: %d', 'xen-inventory' ), (int) $borrow->quantity );
                        ?>
                    </span>
                </div>
                <div class="xen-return-row__dates">
                    <span>
                        <?php
                        /* translators: %s: date */
                        printf( esc_html__( 'Borrowed: %s', 'xen-inventory' ), esc_html( wp_date( $bdf . ' ' . $btf, strtotime( $borrow->date_borrowed ) ) ) );
                        ?>
                    </span>
                    <?php if ( $due_time ) : ?>
                        <span class="<?php echo $is_overdue ? 'xen-overdue-text' : ''; ?>">
                            <?php
                            /* translators: %s: due date */
                            printf( esc_html__( 'Due: %s', 'xen-inventory' ), esc_html( wp_date( $bdf . ' ' . $btf, $due_time ) ) );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="xen-return-row__actions">
                    <?php if ( (int) $borrow->quantity > 1 ) : ?>
                        <label class="xen-return-qty-label">
                            <?php esc_html_e( 'Qty returning:', 'xen-inventory' ); ?>
                            <input
                                type="number"
                                class="xen-return-qty"
                                min="1"
                                max="<?php echo (int) $borrow->quantity; ?>"
                                value="<?php echo (int) $borrow->quantity; ?>"
                                style="width:4.5rem;"
                            />
                        </label>
                    <?php else : ?>
                        <input type="hidden" class="xen-return-qty" value="1" />
                    <?php endif; ?>
                    <button
                        class="xen-btn xen-btn--secondary xen-return-btn"
                        data-log-id="<?php echo (int) $borrow->id; ?>"
                        data-qty="<?php echo (int) $borrow->quantity; ?>"
                    ><?php esc_html_e( 'Return', 'xen-inventory' ); ?></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div><!-- .xen-borrows-list -->

        <!-- Pagination (populated by JS) -->
        <div class="xen-borrows-pagination" id="xen-borrows-pagination" aria-label="<?php esc_attr_e( 'Active borrows pages', 'xen-inventory' ); ?>" style="margin-top:.6rem;display:flex;gap:.3rem;flex-wrap:wrap;"></div>
    </div><!-- .xen-my-borrows -->
    <?php endif; ?>

    <!-- My Borrow History (current user only) -->
    <?php
    if ( is_user_logged_in() ) :
        $my_history = \XenInventory\Models\InventoryLog::get_all_borrows_for_user( get_current_user_id() );
        $df         = get_option( 'date_format' );
        $tf         = get_option( 'time_format' );
    ?>
    <div class="xen-my-history">
        <h3 class="xen-my-borrows__title"><?php esc_html_e( 'My Borrow History', 'xen-inventory' ); ?></h3>
        <?php if ( empty( $my_history ) ) : ?>
            <p class="xen-notice"><?php esc_html_e( 'You have no borrow history yet.', 'xen-inventory' ); ?></p>
        <?php else : ?>
        <p class="xen-notice xen-notice--hint"><?php esc_html_e( 'Double-click a row to view its details.', 'xen-inventory' ); ?></p>
        <!-- Filter toolbar -->
        <div class="xen-borrows-toolbar" style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;flex-wrap:wrap;">
            <input
                type="search"
                id="xen-history-filter"
                class="xen-input"
                placeholder="<?php esc_attr_e( 'Filter by item or tags…', 'xen-inventory' ); ?>"
                aria-label="<?php esc_attr_e( 'Filter borrow history', 'xen-inventory' ); ?>"
                autocomplete="off"
                style="flex:1 1 200px;max-width:280px;"
            />
            <span class="xen-borrows-count" id="xen-history-count" style="font-size:.85rem;color:#666;"></span>
        </div>
        <div class="xen-history-table-wrap">
            <table class="xen-history-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Item',          'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Borrowed',      'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Due',           'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Returned',      'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Qty',           'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Tags',          'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Status',        'xen-inventory' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $my_history as $row ) :
                        $is_returned = ! empty( $row->date_returned );
                        $due_ts      = $row->date_due ? strtotime( $row->date_due ) : null;
                        $is_overdue  = ! $is_returned && $due_ts && $due_ts < time();
                        $status_cls  = $is_returned ? 'returned' : ( $is_overdue ? 'overdue' : 'active' );
                        $status_lbl  = $is_returned
                            ? esc_html__( 'Returned', 'xen-inventory' )
                            : ( $is_overdue ? esc_html__( 'Overdue', 'xen-inventory' ) : esc_html__( 'Active', 'xen-inventory' ) );
                        $tags        = array_filter( array_map( 'trim', explode( ',', (string) $row->borrow_tags ) ) );
                    ?>
                    <tr class="xen-my-history-row"
                        style="cursor: pointer;"
                        title="<?php esc_attr_e( 'Double-click to view or edit this record', 'xen-inventory' ); ?>"
                        data-log-id="<?php echo (int) $row->id; ?>"
                        data-item-title="<?php echo esc_attr( $row->item_title ); ?>"
                        data-borrower-name="<?php echo esc_attr( $row->borrower_name ?? '' ); ?>"
                        data-borrower-full-name="<?php echo esc_attr( $row->borrower_full_name ?? '' ); ?>"
                        data-borrower-contact="<?php echo esc_attr( $row->borrower_contact ?? '' ); ?>"
                        data-borrow-tags="<?php echo esc_attr( $row->borrow_tags ?? '' ); ?>"
                        data-qty="<?php echo (int) $row->quantity; ?>"
                        data-date-borrowed="<?php echo esc_attr( $row->date_borrowed ?? '' ); ?>"
                        data-date-due="<?php echo esc_attr( $row->date_due ?? '' ); ?>"
                        data-date-returned="<?php echo esc_attr( $row->date_returned ?? '' ); ?>"
                        data-notes="<?php echo esc_attr( $row->notes ?? '' ); ?>"
                        data-return-notes="<?php echo esc_attr( $row->return_notes ?? '' ); ?>"
                        data-item-condition="<?php echo esc_attr( $row->item_condition ?? '' ); ?>"
                        data-status="<?php echo esc_attr( $status_cls ); ?>"
                    >
                        <td>
                            <a href="<?php echo esc_url( get_permalink( $row->item_id ) ); ?>">
                                <?php echo esc_html( $row->item_title ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( wp_date( $df, strtotime( $row->date_borrowed ) ) ); ?></td>
                        <td><?php echo $due_ts ? esc_html( wp_date( $df . ' ' . $tf, $due_ts ) ) : '—'; ?></td>
                        <td><?php echo $is_returned ? esc_html( wp_date( $df, strtotime( $row->date_returned ) ) ) : '—'; ?></td>
                        <td><?php echo (int) $row->quantity; ?></td>
                        <td>
                            <?php if ( $tags ) : ?>
                                <span class="xen-tags">
                                    <?php foreach ( $tags as $tag ) : ?>
                                        <span class="xen-tag"><?php echo esc_html( $tag ); ?></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php else : ?>
                                <span class="xen-text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="xen-history-status xen-history-status--<?php echo esc_attr( $status_cls ); ?>">
                                <?php echo $status_lbl; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div><!-- end .xen-history-table-wrap -->
        <!-- Pagination (populated by JS) -->
        <div class="xen-borrows-pagination" id="xen-history-pagination" aria-label="<?php esc_attr_e( 'Borrow history pages', 'xen-inventory' ); ?>" style="margin-top:.6rem;display:flex;gap:.3rem;flex-wrap:wrap;"></div>
        <?php endif; ?>
    </div>

    <?php if ( current_user_can( 'xen_return_items' ) ) : ?>
    <!-- Borrow Record Detail / Edit Modal for My Borrow History ------------>
    <div class="xen-log-edit-modal" id="xen-log-edit-modal" role="dialog" aria-modal="true" aria-labelledby="xen-log-edit-title" hidden>
        <div class="xen-log-edit-modal__overlay"></div>
        <div class="xen-log-edit-modal__panel">
            <button class="xen-log-edit-modal__close" id="xen-log-edit-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'xen-inventory' ); ?>">&#x2715;</button>
            <h2 class="xen-log-edit-modal__title" id="xen-log-edit-title"><?php esc_html_e( 'Borrow Record', 'xen-inventory' ); ?></h2>

            <!-- Read-only transaction details -->
            <div class="xen-log-edit-modal__info" style="margin-bottom:1rem;">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                    <tr style="border-bottom:1px solid #f0f0f0;"><th style="text-align:left;padding:.35rem .5rem .35rem 0;width:38%;font-weight:600;color:#555;"><?php esc_html_e( 'Item',          'xen-inventory' ); ?></th><td style="padding:.35rem 0;" id="xen-log-edit-item-title">—</td></tr>
                    <tr style="border-bottom:1px solid #f0f0f0;"><th style="text-align:left;padding:.35rem .5rem .35rem 0;font-weight:600;color:#555;"><?php esc_html_e( 'Entity / Name', 'xen-inventory' ); ?></th><td style="padding:.35rem 0;" id="xen-log-edit-entity">—</td></tr>
                    <tr style="border-bottom:1px solid #f0f0f0;"><th style="text-align:left;padding:.35rem .5rem .35rem 0;font-weight:600;color:#555;"><?php esc_html_e( 'Contact',       'xen-inventory' ); ?></th><td style="padding:.35rem 0;" id="xen-log-edit-contact">—</td></tr>
                    <tr style="border-bottom:1px solid #f0f0f0;"><th style="text-align:left;padding:.35rem .5rem .35rem 0;font-weight:600;color:#555;"><?php esc_html_e( 'Tags',          'xen-inventory' ); ?></th><td style="padding:.35rem 0;" id="xen-log-edit-tags">—</td></tr>
                    <tr style="border-bottom:1px solid #f0f0f0;"><th style="text-align:left;padding:.35rem .5rem .35rem 0;font-weight:600;color:#555;"><?php esc_html_e( 'Quantity',      'xen-inventory' ); ?></th><td style="padding:.35rem 0;" id="xen-log-edit-qty">—</td></tr>
                    <tr style="border-bottom:1px solid #f0f0f0;"><th style="text-align:left;padding:.35rem .5rem .35rem 0;font-weight:600;color:#555;"><?php esc_html_e( 'Borrowed',      'xen-inventory' ); ?></th><td style="padding:.35rem 0;" id="xen-log-edit-borrowed">—</td></tr>
                    <tr style="border-bottom:1px solid #f0f0f0;"><th style="text-align:left;padding:.35rem .5rem .35rem 0;font-weight:600;color:#555;"><?php esc_html_e( 'Condition',     'xen-inventory' ); ?></th><td style="padding:.35rem 0;" id="xen-log-edit-condition-display">—</td></tr>
                    <tr><th style="text-align:left;padding:.35rem .5rem .35rem 0;font-weight:600;color:#555;"><?php esc_html_e( 'Return Notes',  'xen-inventory' ); ?></th><td style="padding:.35rem 0;" id="xen-log-edit-return-notes-display">—</td></tr>
                </table>
            </div>

            <form id="xen-log-edit-form" class="xen-form xen-log-edit-form">
                <input type="hidden" id="xen-log-edit-id" name="log_id" />
                <div class="xen-form__group">
                    <label for="xen-log-edit-due"><?php esc_html_e( 'Due Date &amp; Time', 'xen-inventory' ); ?></label>
                    <input type="datetime-local" id="xen-log-edit-due" name="date_due" />
                </div>
                <div class="xen-form__group">
                    <label for="xen-log-edit-returned"><?php esc_html_e( 'Date &amp; Time Returned', 'xen-inventory' ); ?></label>
                    <div style="display:flex;gap:.5rem;align-items:center;">
                        <input type="datetime-local" id="xen-log-edit-returned" name="date_returned" />
                        <button type="button" class="xen-btn xen-btn--ghost xen-log-edit-return-now" title="<?php esc_attr_e( 'Set to current date and time', 'xen-inventory' ); ?>">&#x23F1; Now</button>
                    </div>
                </div>
                <div class="xen-form__group">
                    <label for="xen-log-edit-notes"><?php esc_html_e( 'Notes', 'xen-inventory' ); ?></label>
                    <textarea id="xen-log-edit-notes" name="notes" rows="3"></textarea>
                </div>
                <div class="xen-form__group">
                    <label for="xen-log-edit-condition"><?php esc_html_e( 'Item Condition on Return', 'xen-inventory' ); ?></label>
                    <select id="xen-log-edit-condition" name="item_condition">
                        <option value=""><?php esc_html_e( '— Not set —', 'xen-inventory' ); ?></option>
                        <option value="good"><?php esc_html_e( 'In condition / Usable', 'xen-inventory' ); ?></option>
                        <option value="slight_damage"><?php esc_html_e( 'Slightly damaged / torn', 'xen-inventory' ); ?></option>
                        <option value="total_damage"><?php esc_html_e( 'Totally damaged / unusable', 'xen-inventory' ); ?></option>
                    </select>
                </div>
                <div class="xen-form__group">
                    <label for="xen-log-edit-return-notes"><?php esc_html_e( 'Return Notes', 'xen-inventory' ); ?></label>
                    <textarea id="xen-log-edit-return-notes" name="return_notes" rows="2"></textarea>
                </div>
                <div class="xen-form__actions">
                    <button type="submit" class="xen-btn xen-btn--primary" id="xen-log-edit-save"><?php esc_html_e( 'Save Changes', 'xen-inventory' ); ?></button>
                    <button type="button" class="xen-btn xen-btn--ghost" id="xen-log-edit-cancel"><?php esc_html_e( 'Cancel', 'xen-inventory' ); ?></button>
                </div>
                <p class="xen-log-edit-modal__status" id="xen-log-edit-status" aria-live="polite"></p>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- Read-only borrow detail modal — available to all logged-in users (active borrows dblclick + non-admin history dblclick) -->
    <?php if ( is_user_logged_in() ) : ?>
    <div id="xen-active-detail-modal" style="display:none;position:fixed;inset:0;z-index:100060;align-items:center;justify-content:center;" role="dialog" aria-modal="true" aria-labelledby="xen-active-detail-title">
        <div id="xen-active-detail-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.55);"></div>
        <div style="position:relative;background:#fff;border-radius:6px;padding:1.5rem 1.75rem;width:520px;max-width:95vw;max-height:85vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.25);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;border-bottom:2px solid #f0f0f0;padding-bottom:.75rem;">
                <h3 id="xen-active-detail-title" style="margin:0;font-size:1rem;font-weight:700;"></h3>
                <button type="button" id="xen-active-detail-close" class="xen-btn xen-btn--ghost" style="padding:.2rem .65rem;font-size:1.15rem;line-height:1.3;" aria-label="<?php esc_attr_e( 'Close', 'xen-inventory' ); ?>">&times;</button>
            </div>
            <div id="xen-active-detail-body"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Return Confirmation Modal (shared by My Active Borrows + item history return actions) -->
    <?php if ( is_user_logged_in() ) : ?>
    <div id="xen-return-confirm-modal" style="display:none;position:fixed;inset:0;z-index:100070;align-items:center;justify-content:center;" role="dialog" aria-modal="true" aria-labelledby="xen-return-confirm-title">
        <div id="xen-return-confirm-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.6);"></div>
        <div style="position:relative;background:#fff;border-radius:6px;padding:1.5rem 1.75rem;width:480px;max-width:95vw;max-height:85vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.3);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;border-bottom:2px solid #f0f0f0;padding-bottom:.75rem;">
                <h3 id="xen-return-confirm-title" style="margin:0;font-size:1rem;font-weight:700;"><?php esc_html_e( 'Return Item', 'xen-inventory' ); ?></h3>
                <button type="button" id="xen-return-confirm-close" class="xen-btn xen-btn--ghost" style="padding:.2rem .65rem;font-size:1.15rem;line-height:1.3;" aria-label="<?php esc_attr_e( 'Close', 'xen-inventory' ); ?>">&times;</button>
            </div>
            <p style="font-size:.9rem;margin:.25rem 0 1rem;"><?php esc_html_e( 'Returning:', 'xen-inventory' ); ?> <strong id="xen-return-confirm-item-name"></strong></p>
            <div id="xen-return-confirm-qty-wrap" style="display:none;margin-bottom:1rem;">
                <label for="xen-return-confirm-qty" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">
                    <?php esc_html_e( 'Qty Returning', 'xen-inventory' ); ?> <span id="xen-return-confirm-qty-max-label" style="font-weight:400;color:#666;"></span>
                </label>
                <input type="number" id="xen-return-confirm-qty" min="1" style="width:6rem;padding:.3rem .5rem;border:1px solid #ccc;border-radius:4px;" />
            </div>
            <div style="margin-bottom:1rem;">
                <label for="xen-return-confirm-condition" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">
                    <?php esc_html_e( 'Item Condition on Return', 'xen-inventory' ); ?> <span class="xen-required-star" aria-hidden="true">*</span>
                </label>
                <select id="xen-return-confirm-condition" required style="width:100%;padding:.4rem .5rem;border:1px solid #ccc;border-radius:4px;">
                    <option value=""><?php esc_html_e( '\u2014 Select condition \u2014', 'xen-inventory' ); ?></option>
                    <option value="good"><?php esc_html_e( '✅ In condition / Usable', 'xen-inventory' ); ?></option>
                    <option value="slight_damage"><?php esc_html_e( '⚠️ Slightly damaged / torn', 'xen-inventory' ); ?></option>
                    <option value="total_damage"><?php esc_html_e( '❌ Totally damaged / unusable', 'xen-inventory' ); ?></option>
                </select>
            </div>
            <div style="margin-bottom:1.25rem;">
                <label for="xen-return-confirm-notes" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">
                    <?php esc_html_e( 'Return Remarks', 'xen-inventory' ); ?> <span class="xen-required-star" aria-hidden="true">*</span>
                </label>
                <textarea id="xen-return-confirm-notes" rows="3" required placeholder="<?php esc_attr_e( 'e.g. Returned by Juan Dela Cruz. Item was clean and in working order.', 'xen-inventory' ); ?>" style="width:100%;padding:.4rem .5rem;border:1px solid #ccc;border-radius:4px;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
            <div class="xen-form__actions">
                <button type="button" class="xen-btn xen-btn--primary" id="xen-return-confirm-submit"><?php esc_html_e( 'Confirm Return', 'xen-inventory' ); ?></button>
                <button type="button" class="xen-btn xen-btn--ghost" id="xen-return-confirm-cancel"><?php esc_html_e( 'Cancel', 'xen-inventory' ); ?></button>
            </div>
            <p id="xen-return-confirm-status" style="margin-top:.75rem;font-size:.875rem;min-height:1.2em;" aria-live="polite"></p>
        </div>
    </div>
    <?php endif; ?>

</div><!-- .xen-inventory-wrap -->
