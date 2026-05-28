<?php
/**
 * Audit Log — static helper for recording user actions.
 *
 * Each call to AuditLog::record() inserts one row into the
 * wp_xen_audit_log table, capturing who did what and when.
 *
 * @package XenInventory\Core
 */

namespace XenInventory\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AuditLog
 */
class AuditLog {

    /**
     * Return the fully-qualified table name.
     *
     * @return string
     */
    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . XEN_AUDIT_LOG_TABLE;
    }

    /**
     * Record a user action.
     *
     * @param  string $action      Short action key, e.g. 'borrow', 'return', 'update', 'delete', 'purge'.
     * @param  string $object_type Object category, e.g. 'item', 'log'.
     * @param  int    $object_id   Primary key of the affected object (0 if N/A).
     * @param  string $label       Human-readable label for the affected object.
     * @param  array  $details     Optional key → value pairs stored as JSON.
     * @return void
     */
    public static function record(
        string $action,
        string $object_type,
        int    $object_id,
        string $label,
        array  $details = []
    ): void {
        global $wpdb;

        $user      = wp_get_current_user();
        $user_name = $user->exists() ? $user->display_name : 'System';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            self::table(),
            [
                'user_id'     => get_current_user_id(),
                'user_name'   => $user_name,
                'action'      => $action,
                'object_type' => $object_type,
                'object_id'   => $object_id,
                'label'       => $label,
                'details'     => ! empty( $details ) ? wp_json_encode( $details ) : '',
                'ip'          => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
                'created_at'  => current_time( 'mysql', true ),
            ],
            [ '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Retrieve recent audit log entries.
     *
     * @param  int    $limit  Maximum rows to return.
     * @param  int    $offset Pagination offset.
     * @param  array  $filters Optional: user_id (int), action (string), date_from (Y-m-d), date_to (Y-m-d).
     * @return object[]
     */
    public static function get_logs( int $limit = 50, int $offset = 0, array $filters = [] ): array {
        global $wpdb;

        $where      = [];
        $where_args = [];

        if ( ! empty( $filters['user_id'] ) ) {
            $where[]      = 'user_id = %d';
            $where_args[] = (int) $filters['user_id'];
        }
        if ( ! empty( $filters['action'] ) ) {
            $where[]      = 'action = %s';
            $where_args[] = $filters['action'];
        }
        if ( ! empty( $filters['date_from'] ) ) {
            $where[]      = 'created_at >= %s';
            $where_args[] = $filters['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where[]      = 'created_at <= %s';
            $where_args[] = $filters['date_to'] . ' 23:59:59';
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . " {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge( $where_args, [ $limit, $offset ] )
            )
        );
    }

    /**
     * Count total audit log entries matching the given filters.
     *
     * @param  array $filters Same keys as get_logs().
     * @return int
     */
    public static function count_logs( array $filters = [] ): int {
        global $wpdb;

        $where      = [];
        $where_args = [];

        if ( ! empty( $filters['user_id'] ) ) {
            $where[]      = 'user_id = %d';
            $where_args[] = (int) $filters['user_id'];
        }
        if ( ! empty( $filters['action'] ) ) {
            $where[]      = 'action = %s';
            $where_args[] = $filters['action'];
        }
        if ( ! empty( $filters['date_from'] ) ) {
            $where[]      = 'created_at >= %s';
            $where_args[] = $filters['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where[]      = 'created_at <= %s';
            $where_args[] = $filters['date_to'] . ' 23:59:59';
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . self::table() . " {$where_sql}",
                $where_args
            )
        );
    }
}
