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
                    <label for="xen-borrow-due"><?php esc_html_e( 'Expected Return Date', 'xen-inventory' ); ?></label>
                    <input type="date" id="xen-borrow-due" name="date_due" />
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
        <div class="xen-borrows-list">
            <?php foreach ( $my_borrows as $borrow ) :
                $due_time   = $borrow->date_due ? strtotime( $borrow->date_due ) : null;
                $is_overdue = $due_time && $due_time < time();
            ?>
            <div class="xen-return-row<?php echo $is_overdue ? ' xen-return-row--overdue' : ''; ?>">
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
                        printf( esc_html__( 'Borrowed: %s', 'xen-inventory' ), esc_html( wp_date( get_option( 'date_format' ), strtotime( $borrow->date_borrowed ) ) ) );
                        ?>
                    </span>
                    <?php if ( $due_time ) : ?>
                        <span class="<?php echo $is_overdue ? 'xen-overdue-text' : ''; ?>">
                            <?php
                            /* translators: %s: due date */
                            printf( esc_html__( 'Due: %s', 'xen-inventory' ), esc_html( wp_date( get_option( 'date_format' ), $due_time ) ) );
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
                    <input
                        type="text"
                        class="xen-return-notes"
                        placeholder="<?php esc_attr_e( 'Return notes (optional)', 'xen-inventory' ); ?>"
                    />
                    <button
                        class="xen-btn xen-btn--secondary xen-return-btn"
                        data-log-id="<?php echo (int) $borrow->id; ?>"
                        data-qty="<?php echo (int) $borrow->quantity; ?>"
                    ><?php esc_html_e( 'Return', 'xen-inventory' ); ?></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- My Borrow History (current user only) -->
    <?php
    if ( is_user_logged_in() ) :
        $my_history = \XenInventory\Models\InventoryLog::get_all_borrows_for_user( get_current_user_id() );
        $df         = get_option( 'date_format' );
    ?>
    <div class="xen-my-history">
        <h3 class="xen-my-borrows__title"><?php esc_html_e( 'My Borrow History', 'xen-inventory' ); ?></h3>
        <?php if ( empty( $my_history ) ) : ?>
            <p class="xen-notice"><?php esc_html_e( 'You have no borrow history yet.', 'xen-inventory' ); ?></p>
        <?php else : ?>
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
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( get_permalink( $row->item_id ) ); ?>">
                                <?php echo esc_html( $row->item_title ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( wp_date( $df, strtotime( $row->date_borrowed ) ) ); ?></td>
                        <td><?php echo $due_ts ? esc_html( wp_date( $df, $due_ts ) ) : '—'; ?></td>
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
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- .xen-inventory-wrap -->
