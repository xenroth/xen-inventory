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

</div><!-- .xen-item-detail -->
</main>

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
