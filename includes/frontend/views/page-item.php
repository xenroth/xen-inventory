<?php
/**
 * Single Item Detail page template: /inventory-item/{slug}/
 *
 * Loaded by TemplateLoader when is_singular('xen_item').
 * Renders a WooCommerce-style product detail page with a borrow form.
 *
 * @package XenInventory\Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure we have a valid published xen_item in the main query.
if ( ! is_singular( 'xen_item' ) || ! have_posts() ) {
    wp_safe_redirect( home_url( '/inventory/' ) );
    exit;
}

the_post(); // Set up $post so template tags work.

$item_id   = get_the_ID();
$item      = get_post( $item_id );

$item_status   = get_post_meta( $item_id, '_xen_item_status',   true ) ?: 'available';
$total_qty     = (int) get_post_meta( $item_id, '_xen_total_quantity', true );
$item_depts    = get_the_terms( $item_id, 'xen_department' );
$dept_names    = ( $item_depts && ! is_wp_error( $item_depts ) )
    ? implode( ', ', wp_list_pluck( $item_depts, 'name' ) )
    : '';
$available_qty = \XenInventory\Models\InventoryLog::get_available_quantity( $item_id );

$can_borrow = current_user_can( 'xen_borrow_items' )
    && $available_qty > 0
    && 'available' === $item_status;

$current_user     = wp_get_current_user();
$default_fullname = trim( ( $current_user->first_name ?? '' ) . ' ' . ( $current_user->last_name ?? '' ) )
    ?: ( $current_user->display_name ?? '' );

get_header();
?>

<main id="xen-main" class="xen-page-wrap">
<div class="xen-item-detail">

    <!-- Breadcrumb --------------------------------------------------------->
    <nav class="xen-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'xen-inventory' ); ?>">
        <a href="<?php echo esc_url( home_url( '/inventory/' ) ); ?>"><?php esc_html_e( 'Inventory', 'xen-inventory' ); ?></a>
        <span class="xen-breadcrumb__sep" aria-hidden="true">&#8250;</span>
        <?php if ( $dept_names ) : ?>
            <span><?php echo esc_html( $dept_names ); ?></span>
            <span class="xen-breadcrumb__sep" aria-hidden="true">&#8250;</span>
        <?php endif; ?>
        <span aria-current="page"><?php the_title(); ?></span>
    </nav>

    <!-- Two-column product layout ----------------------------------------->
    <div class="xen-item-detail__layout">

        <!-- Left: Image gallery -->
        <div class="xen-item-detail__gallery">
            <div class="xen-item-detail__image-wrap">
                <span class="xen-status-badge xen-status-badge--<?php echo esc_attr( $item_status ); ?> xen-item-detail__badge">
                    <?php echo esc_html( ucfirst( $item_status ) ); ?>
                </span>
                <?php if ( has_post_thumbnail( $item_id ) ) : ?>
                    <?php echo get_the_post_thumbnail( $item_id, 'large', [ 'class' => 'xen-item-detail__img' ] ); ?>
                <?php else : ?>
                    <div class="xen-item-detail__placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" aria-hidden="true" focusable="false">
                            <rect x="20" y="14" width="80" height="12" rx="3" fill="currentColor" opacity=".28"/>
                            <rect x="26" y="30" width="68" height="56" rx="4" fill="currentColor" opacity=".14"/>
                            <rect x="44" y="30" width="32" height="24" rx="2" fill="currentColor" opacity=".32"/>
                            <circle cx="36" cy="98" r="7" fill="currentColor" opacity=".28"/>
                            <circle cx="84" cy="98" r="7" fill="currentColor" opacity=".28"/>
                            <rect x="28" y="92" width="64" height="10" rx="2" fill="currentColor" opacity=".14"/>
                        </svg>
                    </div>
                <?php endif; ?>
            </div>
        </div><!-- .xen-item-detail__gallery -->

        <!-- Right: Product info + CTA -->
        <div class="xen-item-detail__info">

            <?php if ( $dept_names ) : ?>
                <p class="xen-item-detail__dept"><?php echo esc_html( $dept_names ); ?></p>
            <?php endif; ?>

            <h1 class="xen-item-detail__title"><?php the_title(); ?></h1>

            <!-- Availability counter -->
            <div class="xen-item-detail__stock">
                <?php if ( $available_qty > 0 ) : ?>
                    <span class="xen-item-card__stock-in">
                        <?php
                        /* translators: %d: available units */
                        printf( esc_html__( '%d available', 'xen-inventory' ), $available_qty );
                        ?>
                    </span>
                <?php else : ?>
                    <span class="xen-item-card__stock-out"><?php esc_html_e( 'Out of stock', 'xen-inventory' ); ?></span>
                <?php endif; ?>
                <?php if ( $total_qty ) : ?>
                    <span class="xen-item-card__stock-total">
                        <?php
                        /* translators: %d: total units */
                        printf( esc_html__( '/ %d total', 'xen-inventory' ), $total_qty );
                        ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <?php if ( $item->post_content ) : ?>
                <div class="xen-item-detail__description">
                    <?php echo wp_kses_post( apply_filters( 'the_content', $item->post_content ) ); ?>
                </div>
            <?php elseif ( $item->post_excerpt ) : ?>
                <div class="xen-item-detail__description">
                    <p><?php echo esc_html( $item->post_excerpt ); ?></p>
                </div>
            <?php endif; ?>

            <!-- CTA -->
            <div class="xen-item-detail__cta-wrap">
                <?php if ( ! is_user_logged_in() ) : ?>
                    <a href="<?php echo esc_url( home_url( '/inventory/login/' ) ); ?>" class="xen-btn xen-btn--primary xen-item-detail__cta">
                        <?php esc_html_e( 'Login to Borrow', 'xen-inventory' ); ?>
                    </a>
                <?php elseif ( $can_borrow ) : ?>
                    <button
                        class="xen-btn xen-btn--primary xen-borrow-btn xen-item-detail__cta"
                        data-item-id="<?php echo (int) $item_id; ?>"
                        data-item-title="<?php echo esc_attr( get_the_title() ); ?>"
                    >
                        <?php esc_html_e( 'Borrow This Item', 'xen-inventory' ); ?>
                    </button>
                <?php elseif ( 'maintenance' === $item_status ) : ?>
                    <button class="xen-btn xen-item-detail__cta xen-item-card__cta--maintenance" disabled>
                        <?php esc_html_e( 'Under Maintenance', 'xen-inventory' ); ?>
                    </button>
                <?php else : ?>
                    <button class="xen-btn xen-item-detail__cta xen-item-card__cta--unavailable" disabled>
                        <?php esc_html_e( 'Currently Unavailable', 'xen-inventory' ); ?>
                    </button>
                <?php endif; ?>
            </div><!-- .xen-item-detail__cta-wrap -->

            <a href="<?php echo esc_url( home_url( '/inventory/' ) ); ?>" class="xen-btn xen-btn--ghost xen-item-detail__back">
                &larr; <?php esc_html_e( 'Back to Inventory', 'xen-inventory' ); ?>
            </a>

        </div><!-- .xen-item-detail__info -->
    </div><!-- .xen-item-detail__layout -->

    <!-- Borrow History ----------------------------------------------------->
    <?php
    $item_logs = \XenInventory\Models\InventoryLog::get_public_logs_for_item( $item_id, 30 );
    $df        = get_option( 'date_format' );
    $tf        = get_option( 'time_format' );
    $can_act   = current_user_can( 'xen_return_items' );
    ?>
    <div class="xen-item-detail__history">
        <h2 class="xen-item-detail__history-title"><?php esc_html_e( 'Borrow History', 'xen-inventory' ); ?></h2>

        <?php if ( empty( $item_logs ) ) : ?>
            <p class="xen-notice"><?php esc_html_e( 'This item has not been borrowed yet.', 'xen-inventory' ); ?></p>
        <?php else : ?>
        <div class="xen-history-table-wrap">
            <table class="xen-history-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Borrower',  'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Borrowed',  'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Due',       'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Returned',  'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Qty',       'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Tags',      'xen-inventory' ); ?></th>
                        <th><?php esc_html_e( 'Status',    'xen-inventory' ); ?></th>
                        <?php if ( $can_act ) : ?>
                        <th><?php esc_html_e( 'Actions',   'xen-inventory' ); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $item_logs as $log ) :
                        $is_returned = ! empty( $log->date_returned );
                        $due_ts      = $log->date_due ? strtotime( $log->date_due ) : null;
                        $is_overdue  = ! $is_returned && $due_ts && $due_ts < time();
                        $status_cls  = $is_returned ? 'returned' : ( $is_overdue ? 'overdue' : 'active' );
                        $status_lbl  = $is_returned
                            ? esc_html__( 'Returned', 'xen-inventory' )
                            : ( $is_overdue ? esc_html__( 'Overdue', 'xen-inventory' ) : esc_html__( 'Active', 'xen-inventory' ) );
                        $borrower    = $log->borrower_full_name ?: $log->borrower_name;
                        $tags        = array_filter( array_map( 'trim', explode( ',', (string) $log->borrow_tags ) ) );
                    ?>
                    <tr class="xen-item-log-row"
                        data-log-id="<?php echo (int) $log->id; ?>"
                        data-qty="<?php echo (int) $log->quantity; ?>"
                        data-borrower="<?php echo esc_attr( $borrower ); ?>"
                        data-date-borrowed="<?php echo esc_attr( $log->date_borrowed ?? '' ); ?>"
                        data-date-due="<?php echo esc_attr( $log->date_due ?? '' ); ?>"
                        data-date-returned="<?php echo esc_attr( $log->date_returned ?? '' ); ?>"
                        data-notes="<?php echo esc_attr( $log->notes ?? '' ); ?>"
                        data-status="<?php echo esc_attr( $status_cls ); ?>"
                    >
                        <td><?php echo esc_html( $borrower ); ?></td>
                        <td><?php echo esc_html( wp_date( $df, strtotime( $log->date_borrowed ) ) ); ?></td>
                        <td><?php echo $due_ts ? esc_html( wp_date( $df . ' ' . $tf, $due_ts ) ) : '—'; ?></td>
                        <td><?php echo $is_returned ? esc_html( wp_date( $df, strtotime( $log->date_returned ) ) ) : '—'; ?></td>
                        <td><?php echo (int) $log->quantity; ?></td>
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
                        <?php if ( $can_act ) : ?>
                        <td class="xen-item-log-actions">
                            <button type="button" class="xen-item-log-edit-btn" data-log-id="<?php echo (int) $log->id; ?>"><?php esc_html_e( 'Edit', 'xen-inventory' ); ?></button>
                            <?php if ( ! $is_returned ) : ?>
                            <button type="button" class="xen-item-log-return-btn" data-log-id="<?php echo (int) $log->id; ?>" data-qty="<?php echo (int) $log->quantity; ?>"><?php esc_html_e( 'Return', 'xen-inventory' ); ?></button>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div><!-- .xen-item-detail__history -->

</div><!-- .xen-item-detail -->
</main>

<?php if ( current_user_can( 'xen_return_items' ) ) : ?>
<!-- Borrow Log Edit Modal (item history quick actions) ------------------->
<div class="xen-log-edit-modal" id="xen-log-edit-modal" role="dialog" aria-modal="true" aria-labelledby="xen-log-edit-title" hidden>
    <div class="xen-log-edit-modal__overlay"></div>
    <div class="xen-log-edit-modal__panel">
        <button class="xen-log-edit-modal__close" id="xen-log-edit-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'xen-inventory' ); ?>">&#x2715;</button>
        <h2 class="xen-log-edit-modal__title" id="xen-log-edit-title"><?php esc_html_e( 'Edit Borrow Record', 'xen-inventory' ); ?></h2>
        <div class="xen-log-edit-modal__meta">
            <span class="xen-log-edit-modal__borrower" id="xen-log-edit-borrower"></span>
        </div>
        <form id="xen-log-edit-form" class="xen-form xen-log-edit-form">
            <input type="hidden" id="xen-log-edit-id" name="log_id" />
            <div class="xen-form__group">
                <label for="xen-log-edit-due"><?php esc_html_e( 'Due Date &amp; Time', 'xen-inventory' ); ?></label>
                <input type="datetime-local" id="xen-log-edit-due" name="date_due" />
            </div>
            <div class="xen-form__group">
                <label for="xen-log-edit-returned"><?php esc_html_e( 'Date Returned', 'xen-inventory' ); ?></label>
                <input type="date" id="xen-log-edit-returned" name="date_returned" />
            </div>
            <div class="xen-form__group">
                <label for="xen-log-edit-notes"><?php esc_html_e( 'Notes', 'xen-inventory' ); ?></label>
                <textarea id="xen-log-edit-notes" name="notes" rows="3"></textarea>
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

<?php if ( current_user_can( 'xen_borrow_items' ) ) : ?>
<!-- Borrow Modal ---------------------------------------------------------->
<div class="xen-modal" id="xen-borrow-modal" role="dialog" aria-modal="true" aria-labelledby="xen-modal-title" hidden>
    <div class="xen-modal__overlay" data-xen-close-modal></div>
    <div class="xen-modal__content">
        <h2 class="xen-modal__title" id="xen-modal-title"><?php esc_html_e( 'Borrow Item', 'xen-inventory' ); ?></h2>
        <form id="xen-borrow-form" class="xen-form" data-user-fullname="<?php echo esc_attr( $default_fullname ); ?>">
            <input type="hidden" name="item_id" id="xen-borrow-item-id" value="<?php echo (int) $item_id; ?>" />

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
                <input type="number" id="xen-borrow-quantity" name="quantity" value="1" min="1" max="<?php echo (int) $available_qty; ?>" required />
            </div>

            <div class="xen-form__group">
                <label for="xen-borrow-due"><?php esc_html_e( 'Expected Return Date', 'xen-inventory' ); ?></label>
                <input type="date" id="xen-borrow-due" name="date_due" min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
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
<?php endif; ?>

<?php get_footer(); ?>
