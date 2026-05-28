<?php
/**
 * Plugin deactivation routines.
 *
 * @package XenInventory\Core
 */

namespace XenInventory\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

    /**
     * Run on plugin deactivation.
     *
     * We only flush rewrite rules here. Data and roles are left intact
     * so that re-activating the plugin restores full functionality.
     *
     * @return void
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
