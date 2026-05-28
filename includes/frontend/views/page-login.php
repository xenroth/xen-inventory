<?php
/**
 * Standalone page template: /inventory/login/
 *
 * Loaded by TemplateLoader when xen_view=login.
 * Wraps the login-form partial in the active theme's header/footer.
 *
 * @package XenInventory\Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// If already logged in, redirect to the inventory.
if ( is_user_logged_in() ) {
    wp_safe_redirect( home_url( '/inventory/' ) );
    exit;
}

$redirect_url = home_url( '/inventory/' );

get_header();
?>

<main id="xen-main" class="xen-page-wrap">
    <?php include __DIR__ . '/login-form.php'; ?>
</main>

<?php get_footer();
