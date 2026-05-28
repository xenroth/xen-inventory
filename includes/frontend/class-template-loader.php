<?php
/**
 * Loads custom page templates for XEN rewrite views.
 *
 * When a request matches a xen_view query var, this class intercepts
 * WordPress's template selection and serves the correct plugin template,
 * wrapped in the active theme's header/footer.
 *
 * @package XenInventory\Frontend
 */

namespace XenInventory\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TemplateLoader
 */
class TemplateLoader {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_filter( 'template_include', [ $this, 'maybe_load_xen_template' ] );
    }

    /**
     * Return a plugin template if the current request is a XEN view.
     *
     * @param  string $template Path to the template WP would normally load.
     * @return string
     */
    public function maybe_load_xen_template( string $template ): string {
        // Single item detail page — intercept before checking xen_view.
        if ( is_singular( 'xen_item' ) ) {
            $theme_override = locate_template( 'xen-inventory/page-item.php' );
            if ( $theme_override ) {
                return $theme_override;
            }
            $plugin_template = XEN_INVENTORY_PATH . 'includes/frontend/views/page-item.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }

        $view = get_query_var( 'xen_view' );

        if ( empty( $view ) ) {
            return $template;
        }

        $map = [
            'calendar' => 'page-calendar.php',
            'login'    => 'page-login.php',
            'borrow'   => 'page-borrow.php',
        ];

        if ( ! isset( $map[ $view ] ) ) {
            return $template;
        }

        // Allow themes to override: place file in {theme}/xen-inventory/{file}.
        $theme_override = locate_template( 'xen-inventory/' . $map[ $view ] );
        if ( $theme_override ) {
            return $theme_override;
        }

        $plugin_template = XEN_INVENTORY_PATH . 'includes/frontend/views/' . $map[ $view ];
        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }

        return $template;
    }
}
