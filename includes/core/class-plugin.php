<?php
/**
 * Main Plugin bootstrap class.
 *
 * Instantiates and wires together every subsystem of XEN Inventory.
 *
 * @package XenInventory\Core
 */

namespace XenInventory\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Plugin
 *
 * Singleton that owns the plugin's lifecycle.
 */
final class Plugin {

    /**
     * Single instance of this class.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Returns (and creates on first call) the singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }

        return self::$instance;
    }

    /** Prevent direct instantiation. */
    private function __construct() {}

    /** Prevent cloning. */
    public function __clone() {}

    /** Prevent unserialization. */
    public function __wakeup() {}

    // -----------------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------------

    /**
     * Wire up all subsystems.
     *
     * @return void
     */
    private function init(): void {
        $this->load_textdomain();
        $this->register_core();
        $this->register_admin();
        $this->register_frontend();
        $this->register_updater();
    }

    /**
     * Load plugin translations.
     *
     * @return void
     */
    private function load_textdomain(): void {
        load_plugin_textdomain(
            'xen-inventory',
            false,
            dirname( plugin_basename( XEN_INVENTORY_FILE ) ) . '/languages'
        );
    }

    /**
     * Register CPT, taxonomy, rewrite rules, and AJAX handlers.
     *
     * @return void
     */
    private function register_core(): void {
        ( new PostTypes() )->register();
        ( new Taxonomy() )->register();
        ( new RewriteRules() )->register();
        ( new AjaxHandlers() )->register();
    }

    /**
     * Register admin-only functionality (runs only in wp-admin).
     *
     * @return void
     */
    private function register_admin(): void {
        if ( ! is_admin() ) {
            return;
        }

        ( new \XenInventory\Admin\AdminMenu() )->register();
        ( new \XenInventory\Admin\MetaBoxes() )->register();
        ( new \XenInventory\Admin\Settings() )->register();
        ( new \XenInventory\Admin\Assets() )->register();
    }

    /**
     * Register frontend shortcodes, templates, and assets.
     *
     * @return void
     */
    private function register_frontend(): void {
        ( new \XenInventory\Frontend\Shortcodes() )->register();
        ( new \XenInventory\Frontend\Assets() )->register();
        ( new \XenInventory\Frontend\TemplateLoader() )->register();
    }

    /**
     * Register the GitHub-based auto/manual updater.
     *
     * Runs on every request (not just admin) because WP's update transient
     * can be set by cron (wp-cron.php) which runs outside is_admin().
     *
     * @return void
     */
    private function register_updater(): void {
        ( new Updater( XEN_INVENTORY_FILE, XEN_INVENTORY_VERSION ) )->init();
    }
}
