<?php
/**
 * Custom rewrite rules for clean frontend URLs.
 *
 * Adds the following URL patterns:
 *   /inventory/             → Archive of all items   (handled by CPT has_archive).
 *   /inventory/item/{slug}  → Single item view       (handled by CPT rewrite).
 *   /inventory/calendar/    → Frontend calendar page (query var xen_view=calendar).
 *   /inventory/login/       → Frontend login page    (query var xen_view=login).
 *   /inventory/borrow/      → Borrow form page       (query var xen_view=borrow).
 *
 * @package XenInventory\Core
 */

namespace XenInventory\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class RewriteRules
 */
class RewriteRules {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'init',                   [ $this, 'add_rewrite_rules'    ] );
        add_filter( 'query_vars',             [ $this, 'add_query_vars'       ] );
        add_action( 'template_redirect',      [ $this, 'handle_xen_views'    ] );
    }

    /**
     * Add plugin-specific rewrite rules.
     *
     * @return void
     */
    public function add_rewrite_rules(): void {
        add_rewrite_rule(
            '^inventory/calendar/?$',
            'index.php?xen_view=calendar',
            'top'
        );

        add_rewrite_rule(
            '^inventory/login/?$',
            'index.php?xen_view=login',
            'top'
        );

        add_rewrite_rule(
            '^inventory/borrow/([0-9]+)/?$',
            'index.php?xen_view=borrow&xen_item_id=$matches[1]',
            'top'
        );
    }

    /**
     * Whitelist custom query vars.
     *
     * @param  string[] $vars Existing query vars.
     * @return string[]
     */
    public function add_query_vars( array $vars ): array {
        $vars[] = 'xen_view';
        $vars[] = 'xen_item_id';
        return $vars;
    }

    /**
     * Intercept custom views and load the appropriate template.
     *
     * @return void
     */
    public function handle_xen_views(): void {
        $view = get_query_var( 'xen_view' );

        if ( empty( $view ) ) {
            return;
        }

        $allowed_views = [ 'calendar', 'login', 'borrow' ];

        if ( ! in_array( $view, $allowed_views, true ) ) {
            return;
        }

        // TemplateLoader will pick up xen_view and serve the right template.
        // We set status 200 to prevent 404.
        status_header( 200 );
    }
}
