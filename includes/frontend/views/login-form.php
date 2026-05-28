<?php
/**
 * Frontend View: Login Form ([xen_inventory_login] shortcode).
 *
 * Uses wp_login_form() for standards-compliant, CSRF-protected login.
 *
 * Variables from Shortcodes::render_login_form():
 *   $redirect_url  string   URL to redirect after successful login.
 *
 * @package XenInventory\Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="xen-login-wrap" id="xen-login">

    <div class="xen-login-card">
        <h2 class="xen-login-card__title"><?php esc_html_e( 'Inventory Login', 'xen-inventory' ); ?></h2>

        <?php
        // Display any WordPress login errors passed as query arg.
        $error = sanitize_text_field( $_GET['xen_login_error'] ?? '' );
        if ( $error ) :
        ?>
            <div class="xen-notice xen-notice--error" role="alert">
                <?php echo esc_html( urldecode( $error ) ); ?>
            </div>
        <?php endif; ?>

        <?php
        wp_login_form( [
            'redirect'       => esc_url( $redirect_url ),
            'form_id'        => 'xen-login-form',
            'label_username' => __( 'Username or Email', 'xen-inventory' ),
            'label_password' => __( 'Password',          'xen-inventory' ),
            'label_remember' => __( 'Remember Me',       'xen-inventory' ),
            'label_log_in'   => __( 'Log In',            'xen-inventory' ),
            'remember'       => true,
        ] );
        ?>

        <?php if ( get_option( 'users_can_register' ) ) : ?>
            <p class="xen-login-card__register">
                <a href="<?php echo esc_url( wp_registration_url() ); ?>">
                    <?php esc_html_e( 'Create an account', 'xen-inventory' ); ?>
                </a>
            </p>
        <?php endif; ?>

        <p class="xen-login-card__forgot">
            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
                <?php esc_html_e( 'Forgot your password?', 'xen-inventory' ); ?>
            </a>
        </p>
    </div>

</div><!-- .xen-login-wrap -->
