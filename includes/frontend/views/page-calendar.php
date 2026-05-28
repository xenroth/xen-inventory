<?php
/**
 * Standalone page template: /inventory/calendar/
 *
 * Loaded by TemplateLoader when xen_view=calendar.
 * Wraps the calendar partial in the active theme's header/footer.
 *
 * @package XenInventory\Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$settings          = get_option( 'xen_inventory_settings', [] );
$allow_guest       = ! empty( $settings['allow_guest_calendar'] );
$user_can_view     = current_user_can( 'xen_view_inventory' );
?>

<main id="xen-main" class="xen-page-wrap">
    <?php if ( ! $allow_guest && ! $user_can_view ) : ?>
        <div class="xen-notice">
            <?php
            $login_url = home_url( '/inventory/login/' );
            printf(
                /* translators: %s: login URL */
                wp_kses(
                    __( 'Please <a href="%s">log in</a> to view the inventory calendar.', 'xen-inventory' ),
                    [ 'a' => [ 'href' => [] ] ]
                ),
                esc_url( $login_url )
            );
            ?>
        </div>
    <?php else : ?>
        <?php include __DIR__ . '/inventory-calendar.php'; ?>
    <?php endif; ?>
</main>

<?php get_footer();
