<?php
/**
 * Standalone page template: /inventory/borrow/{item_id}/
 *
 * Loaded by TemplateLoader when xen_view=borrow.
 * Provides a dedicated single-item borrow page (for direct URL access,
 * QR-code scanning, etc.) separate from the inline modal on the grid.
 *
 * @package XenInventory\Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Guests cannot borrow — redirect to login.
if ( ! is_user_logged_in() ) {
    wp_safe_redirect( home_url( '/inventory/login/' ) );
    exit;
}

if ( ! current_user_can( 'xen_borrow_items' ) ) {
    wp_die( esc_html__( 'You do not have permission to borrow items.', 'xen-inventory' ) );
}

$item_id = absint( get_query_var( 'xen_item_id' ) );
$item    = $item_id ? get_post( $item_id ) : null;

// Validate: must be a published xen_item.
if ( ! $item || 'xen_item' !== $item->post_type || 'publish' !== $item->post_status ) {
    wp_safe_redirect( home_url( '/inventory/' ) );
    exit;
}

$item_status   = get_post_meta( $item_id, '_xen_item_status',   true ) ?: 'available';
$total_qty     = (int) get_post_meta( $item_id, '_xen_total_quantity', true );
$item_depts    = get_the_terms( $item_id, 'xen_department' );
$dept_names    = ( $item_depts && ! is_wp_error( $item_depts ) )
    ? implode( ', ', wp_list_pluck( $item_depts, 'name' ) )
    : '';

get_header();
?>

<main id="xen-main" class="xen-page-wrap">
    <div class="xen-borrow-page">

        <a href="<?php echo esc_url( home_url( '/inventory/' ) ); ?>" class="xen-btn xen-btn--ghost xen-back-link">
            &larr; <?php esc_html_e( 'Back to Inventory', 'xen-inventory' ); ?>
        </a>

        <div class="xen-borrow-page__header">
            <?php if ( has_post_thumbnail( $item_id ) ) : ?>
                <div class="xen-borrow-page__image">
                    <?php echo get_the_post_thumbnail( $item_id, 'medium' ); ?>
                </div>
            <?php endif; ?>

            <div class="xen-borrow-page__info">
                <h1 class="xen-borrow-page__title"><?php echo esc_html( get_the_title( $item_id ) ); ?></h1>

                <?php if ( $dept_names ) : ?>
                    <p class="xen-item-card__dept"><?php echo esc_html( $dept_names ); ?></p>
                <?php endif; ?>

                <p>
                    <span class="xen-status-badge xen-status-badge--<?php echo esc_attr( $item_status ); ?>">
                        <?php echo esc_html( ucfirst( $item_status ) ); ?>
                    </span>
                </p>

                <p class="xen-item-card__qty">
                    <?php
                    /* translators: %d: total quantity in stock */
                    printf( esc_html__( 'Total stock: %d', 'xen-inventory' ), $total_qty );
                    ?>
                </p>

                <div class="xen-item-card__description">
                    <?php echo wp_kses_post( get_the_content( null, false, $item_id ) ); ?>
                </div>
            </div>
        </div><!-- .xen-borrow-page__header -->

        <?php if ( 'available' === $item_status ) : ?>

            <div class="xen-borrow-page__form-wrap">
                <h2><?php esc_html_e( 'Borrow This Item', 'xen-inventory' ); ?></h2>

                <?php
                $current_user    = wp_get_current_user();
                $default_fullname = trim( ( $current_user->first_name ?? '' ) . ' ' . ( $current_user->last_name ?? '' ) ) ?: ( $current_user->display_name ?? '' );
                ?>
                <form id="xen-borrow-page-form" class="xen-form" novalidate>
                    <input type="hidden" name="item_id" value="<?php echo (int) $item_id; ?>" />

                    <div class="xen-form__group">
                        <label for="xen-bp-fullname">
                            <?php esc_html_e( 'Full Name / Entity', 'xen-inventory' ); ?>
                            <span class="xen-required-star" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="text"
                            id="xen-bp-fullname"
                            name="borrower_full_name"
                            value="<?php echo esc_attr( $default_fullname ); ?>"
                            required
                            placeholder="<?php esc_attr_e( 'e.g. Juan dela Cruz or IT Department', 'xen-inventory' ); ?>"
                        />
                    </div>

                    <div class="xen-form__group">
                        <label for="xen-bp-contact">
                            <?php esc_html_e( 'Contact', 'xen-inventory' ); ?>
                            <span class="xen-form__optional"><?php esc_html_e( '(mobile or Facebook — optional)', 'xen-inventory' ); ?></span>
                        </label>
                        <input
                            type="text"
                            id="xen-bp-contact"
                            name="borrower_contact"
                            placeholder="<?php esc_attr_e( '+63 912 345 6789 or fb.com/yourname', 'xen-inventory' ); ?>"
                        />
                    </div>

                    <div class="xen-form__group">
                        <label for="xen-bp-tags">
                            <?php esc_html_e( 'Purpose / Tags', 'xen-inventory' ); ?>
                            <span class="xen-form__optional"><?php esc_html_e( '(optional)', 'xen-inventory' ); ?></span>
                        </label>
                        <input
                            type="text"
                            id="xen-bp-tags"
                            name="borrow_tags"
                            placeholder="<?php esc_attr_e( 'e.g. IT Conference, Zone B, Annual Event', 'xen-inventory' ); ?>"
                        />
                        <p class="xen-form__hint"><?php esc_html_e( 'Separate multiple tags with commas.', 'xen-inventory' ); ?></p>
                    </div>

                    <div class="xen-form__group">
                        <label for="xen-bp-quantity"><?php esc_html_e( 'Quantity', 'xen-inventory' ); ?></label>
                        <input
                            type="number"
                            id="xen-bp-quantity"
                            name="quantity"
                            value="1"
                            min="1"
                            max="<?php echo (int) $total_qty; ?>"
                            required
                        />
                    </div>

                    <div class="xen-form__group">
                        <label for="xen-bp-due"><?php esc_html_e( 'Expected Return Date', 'xen-inventory' ); ?></label>
                        <input type="date" id="xen-bp-due" name="date_due" min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
                    </div>

                    <div class="xen-form__group">
                        <label for="xen-bp-notes"><?php esc_html_e( 'Notes', 'xen-inventory' ); ?></label>
                        <textarea id="xen-bp-notes" name="notes" rows="3"></textarea>
                    </div>

                    <div class="xen-form__actions">
                        <button type="submit" class="xen-btn xen-btn--primary">
                            <?php esc_html_e( 'Confirm Borrow', 'xen-inventory' ); ?>
                        </button>
                    </div>

                    <div class="xen-form__message" aria-live="polite"></div>
                </form>
            </div>

            <script>
            // Inline script intentionally scoped to this page only.
            // Full borrow logic is in assets/js/frontend.js;
            // this snippet wires the page-specific form ID.
            document.addEventListener( 'DOMContentLoaded', function () {
                var form = document.getElementById( 'xen-borrow-page-form' );
                if ( ! form || typeof xenInventory === 'undefined' ) return;

                form.addEventListener( 'submit', function ( e ) {
                    e.preventDefault();
                    var btn = form.querySelector( '[type="submit"]' );
                    var msg = form.querySelector( '.xen-form__message' );
                    btn.disabled = true;

                    var data = new FormData( form );
                    data.append( 'action', 'xen_borrow_item' );
                    data.append( 'nonce',  xenInventory.borrowNonce );

                    fetch( xenInventory.ajaxUrl, { method: 'POST', body: data } )
                        .then( function ( r ) { return r.json(); } )
                        .then( function ( res ) {
                            msg.className = 'xen-form__message ' + ( res.success ? 'xen-form__message--success' : 'xen-form__message--error' );
                            msg.textContent = res.data ? res.data.message : xenInventory.i18n.errorGeneric;
                            if ( res.success ) {
                                setTimeout( function () {
                                    window.location.href = '<?php echo esc_url( home_url( '/inventory/' ) ); ?>';
                                }, 1500 );
                            } else {
                                btn.disabled = false;
                            }
                        } )
                        .catch( function () {
                            msg.className   = 'xen-form__message xen-form__message--error';
                            msg.textContent = xenInventory.i18n.errorGeneric;
                            btn.disabled    = false;
                        } );
                } );
            } );
            </script>

        <?php else : ?>
            <p class="xen-notice xen-notice--error">
                <?php
                printf(
                    /* translators: %s: current item status */
                    esc_html__( 'This item is currently unavailable (%s).', 'xen-inventory' ),
                    esc_html( $item_status )
                );
                ?>
            </p>
        <?php endif; ?>

    </div><!-- .xen-borrow-page -->
</main>

<?php get_footer();
