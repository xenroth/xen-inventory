<?php
/**
 * Plugin Name:       XEN Inventory
 * Plugin URI:        https://xenroth.com/xen-inventory
 * Description:       A robust inventory management system for WordPress. Manage departments, items, borrow logs, and availability from a clean admin and frontend interface.
 * Version:           1.7.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Richard C. Cupal, LPT — Xenroth Digital Innovations
 * Author URI:        https://xenroth.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       xen-inventory
 * Domain Path:       /languages
 *
 * @package XenInventory
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define( 'XEN_INVENTORY_VERSION',    '1.7.2' );
define( 'XEN_INVENTORY_FILE',       __FILE__ );
define( 'XEN_INVENTORY_PATH',       plugin_dir_path( __FILE__ ) );
define( 'XEN_INVENTORY_URL',        plugin_dir_url( __FILE__ ) );
define( 'XEN_INVENTORY_ASSETS_URL', XEN_INVENTORY_URL . 'assets/' );
define( 'XEN_INVENTORY_LOG_TABLE',  'xen_inventory_logs' );
define( 'XEN_AUDIT_LOG_TABLE',      'xen_audit_log' );

// ---------------------------------------------------------------------------
// Autoloader
// ---------------------------------------------------------------------------

/**
 * Simple PSR-4-style autoloader for the XenInventory namespace.
 *
 * Maps XenInventory\<Segment>\<Class> to:
 *   includes/<segment-lowercase>/<class-file>.php
 *
 * Example:
 *   XenInventory\Admin\MetaBoxes  →  includes/admin/class-meta-boxes.php
 *   XenInventory\Core\PostTypes   →  includes/core/class-post-types.php
 */
spl_autoload_register( function ( string $class_name ): void {
    $namespace = 'XenInventory\\';

    if ( strncmp( $namespace, $class_name, strlen( $namespace ) ) !== 0 ) {
        return;
    }

    $relative = substr( $class_name, strlen( $namespace ) );
    $parts    = explode( '\\', $relative );

    // Convert each segment: StudlyCaps → kebab-case.
    $file_parts = array_map(
        fn( string $part ): string => 'class-' . strtolower(
            preg_replace( '/(?<!^)[A-Z]/', '-$0', $part )
        ) . '.php',
        $parts
    );

    // Rebuild as path: first segment becomes directory, last becomes file.
    if ( count( $file_parts ) > 1 ) {
        $dir      = strtolower( $parts[0] );
        $filename = end( $file_parts );
        $path     = XEN_INVENTORY_PATH . 'includes/' . $dir . '/' . $filename;
    } else {
        $path = XEN_INVENTORY_PATH . 'includes/' . reset( $file_parts );
    }

    if ( file_exists( $path ) ) {
        require_once $path;
    }
} );

// ---------------------------------------------------------------------------
// Activation / Deactivation / Uninstall hooks
// ---------------------------------------------------------------------------

register_activation_hook(   __FILE__, [ 'XenInventory\\Core\\Activator',   'activate'   ] );
register_deactivation_hook( __FILE__, [ 'XenInventory\\Core\\Deactivator', 'deactivate' ] );

// Uninstall logic lives in uninstall.php (loaded by WP automatically).

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

/**
 * Returns the single Plugin instance.
 *
 * @return XenInventory\Core\Plugin
 */
function xen_inventory(): \XenInventory\Core\Plugin {
    return \XenInventory\Core\Plugin::instance();
}

// Kick everything off after all plugins are loaded.
add_action( 'plugins_loaded', 'xen_inventory' );
