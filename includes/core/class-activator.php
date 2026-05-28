<?php
/**
 * Plugin activation routines.
 *
 * @package XenInventory\Core
 */

namespace XenInventory\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Activator
 *
 * Runs once on plugin activation: creates the custom DB table,
 * flushes rewrite rules, and stores the plugin version.
 */
class Activator {

    /**
     * Run on plugin activation.
     *
     * @return void
     */
    public static function activate(): void {
        self::create_log_table();
        self::add_roles_and_capabilities();

        // Store version so we can run upgrade routines later.
        update_option( 'xen_inventory_version', XEN_INVENTORY_VERSION );

        // Register CPT/taxonomy before flushing so the rules are complete.
        ( new PostTypes() )->register();
        ( new Taxonomy() )->register();
        flush_rewrite_rules();
    }

    // -----------------------------------------------------------------------
    // Database
    // -----------------------------------------------------------------------

    /**
     * Create the wp_xen_inventory_logs table.
     *
     * Schema:
     *   id              — Primary key.
     *   item_id         — References the xen_item post ID.
     *   user_id         — WP user ID (0 = guest / inventory-only profile).
     *   borrower_name   — Display name stored at time of log (denormalized for history accuracy).
     *   action          — 'borrowed' | 'returned' | 'maintenance' | 'note'.
     *   quantity        — How many units were involved.
     *   date_borrowed   — UTC datetime the action was recorded.
     *   date_due        — Optional expected return date.
     *   date_returned   — Actual return datetime (NULL until returned).
     *   notes           — Freeform staff note.
     *   created_at      — Row creation timestamp.
     *
     * @return void
     */
    private static function create_log_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . XEN_INVENTORY_LOG_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id                 BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            item_id            BIGINT(20) UNSIGNED NOT NULL,
            user_id            BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            borrower_name      VARCHAR(200)        NOT NULL DEFAULT '',
            borrower_full_name VARCHAR(200)        NOT NULL DEFAULT '',
            borrower_contact   VARCHAR(200)        NOT NULL DEFAULT '',
            borrow_tags        VARCHAR(500)        NOT NULL DEFAULT '',
            action             VARCHAR(50)         NOT NULL DEFAULT 'borrowed',
            quantity      INT(11) UNSIGNED    NOT NULL DEFAULT 1,
            date_borrowed DATETIME            NOT NULL DEFAULT '0000-00-00 00:00:00',
            date_due      DATETIME                     DEFAULT NULL,
            date_returned DATETIME                     DEFAULT NULL,
            notes         TEXT,
            created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY user_id (user_id),
            KEY date_borrowed (date_borrowed)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Run any pending schema upgrades.
     *
     * Called on every plugin init. The `dbDelta` inside `create_log_table()`
     * is idempotent — it only ALTERs the table when columns are missing — so
     * the version guard is just an optimisation to skip the check on every
     * request once the DB is already up-to-date.
     *
     * @return void
     */
    public static function maybe_upgrade(): void {
        $stored = get_option( 'xen_inventory_version', '0.0.0' );
        if ( version_compare( $stored, XEN_INVENTORY_VERSION, '<' ) ) {
            self::create_log_table(); // dbDelta handles ADD COLUMN automatically.
            update_option( 'xen_inventory_version', XEN_INVENTORY_VERSION );
        }
    }

    // -----------------------------------------------------------------------
    // Roles & Capabilities
    // -----------------------------------------------------------------------

    /**
     * Add a custom 'xen_staff' role with limited inventory capabilities,
     * and grant full inventory caps to administrators.
     *
     * @return void
     */
    private static function add_roles_and_capabilities(): void {
        // Add the staff role if it doesn't already exist.
        if ( ! get_role( 'xen_staff' ) ) {
            add_role(
                'xen_staff',
                __( 'Inventory Staff', 'xen-inventory' ),
                [
                    'read'                   => true,
                    'xen_view_inventory'     => true,
                    'xen_borrow_items'       => true,
                    'xen_return_items'       => true,
                ]
            );
        }

        // Give administrators all inventory capabilities.
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $caps = [
                'xen_view_inventory',
                'xen_borrow_items',
                'xen_return_items',
                'xen_manage_inventory',
                'xen_manage_departments',
            ];
            foreach ( $caps as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }
}
