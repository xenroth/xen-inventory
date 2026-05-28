<?php
/**
 * Data model for the wp_xen_inventory_logs table.
 *
 * All database interactions for the borrow log are centralised here.
 *
 * @package XenInventory\Models
 */

namespace XenInventory\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class InventoryLog
 */
class InventoryLog {

    /**
     * Return the fully-qualified table name.
     *
     * @return string
     */
    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . XEN_INVENTORY_LOG_TABLE;
    }

    // -----------------------------------------------------------------------
    // Write
    // -----------------------------------------------------------------------

    /**
     * Insert a new log row.
     *
     * @param  array<string, mixed> $data Associative array matching the table columns.
     * @return int|false  Inserted row ID on success, false on failure.
     */
    public static function create_log( array $data ): int|false {
        global $wpdb;

        $defaults = [
            'item_id'            => 0,
            'user_id'            => 0,
            'borrower_name'      => '',
            'borrower_full_name' => '',
            'borrower_contact'   => '',
            'action'             => 'borrowed',
            'quantity'           => 1,
            'date_borrowed'      => current_time( 'mysql', true ),
            'date_due'           => null,
            'date_returned'      => null,
            'notes'              => '',
        ];

        $data = wp_parse_args( $data, $defaults );

        $formats = [ '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert( self::table(), $data, $formats );

        return $inserted ? (int) $wpdb->insert_id : false;
    }

    /**
     * Close a log entry (mark item as returned).
     *
     * @param  int    $log_id Log row ID.
     * @param  string $notes  Optional closing note.
     * @return bool
     */
    public static function close_log( int $log_id, string $notes = '' ): bool {
        global $wpdb;

        // Use a raw query so we can include `AND date_returned IS NULL` in WHERE,
        // preventing a double-return from overwriting the original return timestamp.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::table() . ' SET date_returned = %s, notes = %s WHERE id = %d AND date_returned IS NULL',
                current_time( 'mysql', true ),
                $notes,
                $log_id
            )
        );

        if ( $rows ) {
            // Fetch item_id from the log to update its status.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $item_id = (int) $wpdb->get_var(
                $wpdb->prepare( 'SELECT item_id FROM ' . self::table() . ' WHERE id = %d', $log_id )
            );

            if ( $item_id ) {
                // Restore to 'available' only if there are units not currently borrowed.
                $available = self::get_available_quantity( $item_id );
                if ( $available > 0 ) {
                    update_post_meta( $item_id, '_xen_item_status', 'available' );
                }
            }
        }

        return (bool) $rows;
    }

    // -----------------------------------------------------------------------
    // Read
    // -----------------------------------------------------------------------

    /**
     * Count open (not yet returned) borrow log rows for an item.
     *
     * @param  int $item_id Post ID.
     * @return int
     */
    public static function count_open_borrows( int $item_id ): int {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . self::table() . " WHERE item_id = %d AND action = 'borrowed' AND date_returned IS NULL",
                $item_id
            )
        );
    }

    /**
     * Return the number of units currently available for an item.
     *
     * Available = total stock − sum of quantities in open borrow logs.
     *
     * @param  int $item_id Post ID of the xen_item.
     * @return int  0 when fully borrowed or total quantity not set.
     */
    public static function get_available_quantity( int $item_id ): int {
        global $wpdb;

        $total = (int) get_post_meta( $item_id, '_xen_total_quantity', true );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $borrowed = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COALESCE( SUM(quantity), 0 ) FROM ' . self::table() . " WHERE item_id = %d AND action = 'borrowed' AND date_returned IS NULL",
                $item_id
            )
        );

        return max( 0, $total - $borrowed );
    }

    /**
     * Hard-delete a single log row.
     *
     * @param  int $log_id Primary key of the log entry.
     * @return bool  True on success, false if no row matched.
     */
    public static function delete_log( int $log_id ): bool {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->delete(
            self::table(),
            [ 'id' => $log_id ],
            [ '%d' ]
        );

        return (bool) $rows;
    }

    /**
     * Get all open (not-returned) borrow log rows for a given user.
     *
     * Used to populate the "My Active Borrows" section on the frontend.
     *
     * @param  int $user_id WordPress user ID.
     * @return array<int, object>
     */
    public static function get_open_borrows_for_user( int $user_id ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT l.*, p.post_title AS item_title
                 FROM ' . self::table() . ' l
                 INNER JOIN ' . $wpdb->posts . " p ON p.ID = l.item_id
                 WHERE l.user_id = %d
                   AND l.action = 'borrowed'
                   AND l.date_returned IS NULL
                   AND p.post_status = 'publish'
                 ORDER BY l.date_borrowed DESC",
                $user_id
            )
        );
    }

    /**
     * Get all log entries for a single item.
     *
     * @param  int $item_id Post ID.
     * @return array<int, object>
     */
    public static function get_logs_for_item( int $item_id ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE item_id = %d ORDER BY date_borrowed DESC',
                $item_id
            )
        );
    }

    /**
     * Fetch log events formatted for FullCalendar.
     *
     * @param  string $start ISO date (Y-m-d).
     * @param  string $end   ISO date (Y-m-d).
     * @return array<int, array<string, mixed>>
     */
    public static function get_calendar_events( string $start, string $end ): array {
        global $wpdb;

        // Basic date validation.
        $start = preg_match( '/^\d{4}-\d{2}-\d{2}/', $start ) ? $start : '';
        $end   = preg_match( '/^\d{4}-\d{2}-\d{2}/', $end )   ? $end   : '';

        if ( ! $start || ! $end ) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT l.*, p.post_title AS item_title
                 FROM ' . self::table() . ' l
                 INNER JOIN ' . $wpdb->posts . " p ON p.ID = l.item_id
                 WHERE l.date_borrowed BETWEEN %s AND %s
                   AND p.post_status = 'publish'
                 ORDER BY l.date_borrowed ASC",
                $start . ' 00:00:00',
                $end   . ' 23:59:59'
            )
        );

        $events = [];

        foreach ( $rows as $row ) {
            $color    = 'borrowed' === $row->action ? '#e74c3c' : '#27ae60';
            $events[] = [
                'id'    => (int) $row->id,
                'title' => sprintf(
                    /* translators: 1: item title, 2: borrower name */
                    _x( '%1$s — %2$s', 'Calendar event title', 'xen-inventory' ),
                    esc_html( $row->item_title ),
                    esc_html( $row->borrower_name )
                ),
                'start' => $row->date_borrowed,
                'end'   => $row->date_returned ?? $row->date_due,
                'color' => $color,
                'extendedProps' => [
                    'item_id'  => (int) $row->item_id,
                    'action'   => $row->action,
                    'quantity' => (int) $row->quantity,
                    'notes'    => $row->notes,
                ],
            ];
        }

        return $events;
    }

    /**
     * Return an aggregated summary of all borrowers.
     *
     * Each row represents one WP user account (grouped by user_id + borrower_name).
     * The borrower_full_name and borrower_contact are pulled from the most recent
     * log entry for that user (identified via MAX(id) per group).
     *
     * @return array<int, object>
     */
    public static function get_borrowers_summary(): array {
        global $wpdb;
        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results(
            "SELECT
                 stats.user_id,
                 stats.borrower_name,
                 latest.borrower_full_name,
                 latest.borrower_contact,
                 stats.total_borrows,
                 stats.active_borrows,
                 stats.returned_borrows,
                 stats.overdue_borrows,
                 stats.last_borrowed
             FROM (
                 SELECT
                     user_id,
                     borrower_name,
                     COUNT(*)                                                                                                   AS total_borrows,
                     SUM( CASE WHEN action = 'borrowed' AND date_returned IS NULL THEN 1 ELSE 0 END )                          AS active_borrows,
                     SUM( CASE WHEN date_returned IS NOT NULL THEN 1 ELSE 0 END )                                              AS returned_borrows,
                     SUM( CASE WHEN action = 'borrowed' AND date_returned IS NULL AND date_due IS NOT NULL AND date_due < UTC_TIMESTAMP() THEN 1 ELSE 0 END ) AS overdue_borrows,
                     MAX( date_borrowed )                                                                                       AS last_borrowed,
                     MAX( id )                                                                                                  AS latest_id
                 FROM {$table}
                 GROUP BY user_id, borrower_name
             ) stats
             LEFT JOIN {$table} latest ON latest.id = stats.latest_id
             ORDER BY stats.last_borrowed DESC"
        );
    }

    /**
     * Get all log entries for a specific WP user (borrower history).
     *
     * @param  int $user_id WordPress user ID.
     * @return array<int, object>
     */
    public static function get_logs_for_borrower( int $user_id ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT l.*, p.post_title AS item_title
                 FROM ' . self::table() . ' l
                 LEFT JOIN ' . $wpdb->posts . " p ON p.ID = l.item_id
                 WHERE l.user_id = %d
                 ORDER BY l.date_borrowed DESC",
                $user_id
            )
        );
    }
}
