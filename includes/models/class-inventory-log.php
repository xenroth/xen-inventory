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
            'borrow_tags'        => '',
            'action'             => 'borrowed',
            'quantity'           => 1,
            'date_borrowed'      => current_time( 'mysql', true ),
            'date_due'           => null,
            'date_returned'      => null,
            'notes'              => '',
        ];

        $data = wp_parse_args( $data, $defaults );

        $formats = [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert( self::table(), $data, $formats );

        return $inserted ? (int) $wpdb->insert_id : false;
    }

    /**
     * Close a log entry (mark item as returned).
     *
     * @param  int    $log_id         Log row ID.
     * @param  string $return_notes   Mandatory closing note (what was observed on return).
     * @param  string $item_condition Item condition slug: good | slight_damage | total_damage.
     * @return bool
     */
    public static function close_log( int $log_id, string $return_notes = '', string $item_condition = '', string $date_returned = '' ): bool {
        global $wpdb;

        $use_date = $date_returned ?: current_time( 'mysql', true );

        // Build the SET clause dynamically so we only include item_condition when provided.
        // Also flip action to 'returned' so the Action column displays correctly.
        $set_sql = "action = 'returned', date_returned = %s, return_notes = %s";
        $args    = [ $use_date, $return_notes ];
        if ( '' !== $item_condition ) {
            $set_sql .= ', item_condition = %s';
            $args[]   = $item_condition;
        }
        $args[] = $log_id;

        // Use a raw query so we can include `AND date_returned IS NULL` in WHERE,
        // preventing a double-return from overwriting the original return timestamp.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::table() . ' SET ' . $set_sql . ' WHERE id = %d AND date_returned IS NULL',
                ...$args
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

    /**
     * Return a specific quantity from an open borrow log row.
     *
     * - Full return  (qty_returned == row quantity): delegates to close_log().
     * - Partial return (qty_returned < row quantity):
     *     1. Reduces the original row's quantity by qty_returned.
     *     2. Inserts a new 'returned' row recording what was handed back.
     *
     * @param  int    $log_id       Primary key of the open borrow row.
     * @param  int    $qty_returned How many units are being returned (≥ 1).
     * @param  string $notes        Optional return note.
     * @return bool   True on success, false on DB error or invalid input.
     */
    public static function partial_return( int $log_id, int $qty_returned, string $return_notes = '', string $item_condition = '', string $date_returned = '' ): bool {
        global $wpdb;

        if ( $qty_returned < 1 ) {
            return false;
        }

        // Fetch the original open log row.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $log = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE id = %d AND date_returned IS NULL',
                $log_id
            )
        );

        if ( ! $log ) {
            return false;
        }

        $original_qty = (int) $log->quantity;

        // Cap at original quantity — can't return more than was borrowed.
        if ( $qty_returned >= $original_qty ) {
            return self::close_log( $log_id, $return_notes, $item_condition, $date_returned );
        }

        // Reduce the outstanding quantity on the original row.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $reduced = $wpdb->update(
            self::table(),
            [ 'quantity' => $original_qty - $qty_returned ],
            [ 'id' => $log_id ],
            [ '%d' ],
            [ '%d' ]
        );

        if ( false === $reduced ) {
            return false;
        }

        // Insert a 'returned' log row for the returned portion.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            self::table(),
            [
                'item_id'            => (int) $log->item_id,
                'user_id'            => (int) $log->user_id,
                'borrower_name'      => $log->borrower_name,
                'borrower_full_name' => $log->borrower_full_name,
                'borrower_contact'   => $log->borrower_contact,
                'borrow_tags'        => $log->borrow_tags,
                'action'             => 'returned',
                'quantity'           => $qty_returned,
                'date_borrowed'      => $log->date_borrowed,
                'date_due'           => $log->date_due,
                'date_returned'      => $date_returned ?: current_time( 'mysql', true ),
                'notes'              => '',
                'return_notes'       => $return_notes,
                'item_condition'     => '' !== $item_condition ? $item_condition : null,
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        // Restore item status to 'available' if any units are now free.
        $available = self::get_available_quantity( (int) $log->item_id );
        if ( $available > 0 ) {
            update_post_meta( (int) $log->item_id, '_xen_item_status', 'available' );
        }

        return true;
    }

    /**
     * Update arbitrary fields on a log row.
     *
     * Accepts an associative array of column → value pairs. Handles nullable
     * values by using a direct query so null can be passed for date_returned.
     *
     * @param  int   $id   Primary key of the log row.
     * @param  array $data Column → value pairs to update.
     * @return bool        True on success (or no-op), false on DB error.
     */
    public static function update_log( int $id, array $data ): bool {
        global $wpdb;

        $allowed = [ 'notes', 'date_due', 'date_returned', 'action', 'return_notes', 'item_condition' ];
        $set_parts = [];
        $values    = [];

        foreach ( $allowed as $col ) {
            if ( ! array_key_exists( $col, $data ) ) {
                continue;
            }
            if ( null === $data[ $col ] ) {
                $set_parts[] = "`$col` = NULL";
            } else {
                $set_parts[] = "`$col` = %s";
                $values[]    = $data[ $col ];
            }
        }

        if ( empty( $set_parts ) ) {
            return true; // Nothing to update.
        }

        $values[] = $id;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::table() . ' SET ' . implode( ', ', $set_parts ) . ' WHERE id = %d',
                ...$values
            )
        );

        return false !== $result;
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

        // Show every 'borrowed' row whose borrow date falls within the
        // calendar view period, so that returned history always remains
        // visible on the day it was originally recorded.
        //
        // Also include still-open borrows that started before the range
        // (spanning borrows) so they appear on whichever borrow date is
        // shown in the partial-week cells at the edges of the view.
        //
        // Partial return detection: a correlated sub-query sums the
        // quantity returned via partial_return() — those rows share the
        // same item_id, entity key, and date_borrowed as the original
        // 'borrowed' row.

        $tbl = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, p.post_title AS item_title,
                        COALESCE(
                            ( SELECT SUM(sub.quantity)
                              FROM {$tbl} sub
                              WHERE sub.action      = 'returned'
                                AND sub.item_id     = l.item_id
                                AND DATE(sub.date_borrowed)  = DATE(l.date_borrowed)
                                AND LOWER(TRIM(COALESCE(NULLIF(TRIM(sub.borrower_full_name),''),NULLIF(TRIM(sub.borrower_name),''),'(unknown)')))
                                    = LOWER(TRIM(COALESCE(NULLIF(TRIM(l.borrower_full_name),''),NULLIF(TRIM(l.borrower_name),''),'(unknown)')))
                            ), 0
                        ) AS partial_qty_returned
                 FROM {$tbl} l
                 INNER JOIN {$wpdb->posts} p ON p.ID = l.item_id
                 WHERE l.action IN ('borrowed', 'returned')
                   AND p.post_status = 'publish'
                   AND l.date_borrowed <= %s
                   AND ( l.date_borrowed >= %s OR l.date_returned IS NULL )
                 ORDER BY l.date_borrowed ASC",
                $end   . ' 23:59:59',
                $start . ' 00:00:00'
            )
        );

        // Deduplicate: keep one primary row per borrow transaction.
        //  - If a 'borrowed' row exists for the group (open/partial borrow), it is primary.
        //  - If all rows are 'returned' (fully closed via close_log), the lowest id is primary.
        // This prevents partial-return detail rows (inserted as 'returned') from producing
        // a duplicate chip alongside the original 'borrowed' row on the same calendar day.
        $primary = [];
        foreach ( $rows as $row ) {
            $entity_key = strtolower( trim( $row->borrower_full_name ?: $row->borrower_name ) );
            $group_key  = $row->item_id . '|' . substr( $row->date_borrowed, 0, 10 ) . '|' . $entity_key;

            if ( ! isset( $primary[ $group_key ] ) ) {
                $primary[ $group_key ] = $row;
            } else {
                $existing = $primary[ $group_key ];
                // Prefer the active 'borrowed' row (original borrow transaction) over
                // a partial-return 'returned' detail row.
                if ( 'returned' === $existing->action && 'borrowed' === $row->action ) {
                    $primary[ $group_key ] = $row;
                // If both are 'returned', prefer the lower id — that is the original row
                // that was updated in-place by close_log() (partial-return inserts later ids).
                } elseif ( 'returned' === $existing->action && 'returned' === $row->action && (int) $row->id < (int) $existing->id ) {
                    $primary[ $group_key ] = $row;
                }
            }
        }
        $rows = array_values( $primary );

        $events = [];

        foreach ( $rows as $row ) {
            if ( $row->date_returned ) {
                $return_status = 'returned';
                $color         = '#16a34a'; // green
            } elseif ( (int) $row->partial_qty_returned > 0 ) {
                $return_status = 'partial';
                $color         = '#d97706'; // amber
            } else {
                $return_status = 'open';
                $color         = '#e74c3c'; // red
            }

            $events[] = [
                'id'    => (int) $row->id,
                'title' => sprintf(
                    /* translators: 1: item title, 2: borrower name */
                    _x( '%1$s — %2$s', 'Calendar event title', 'xen-inventory' ),
                    $row->item_title,
                    $row->borrower_full_name ?: $row->borrower_name
                ),
                'start' => $row->date_borrowed,
                // No 'end' — events display as single-day markers on the borrow date.
                'color' => $color,
                'extendedProps' => [
                    'log_id'           => (int) $row->id,
                    'item_id'          => (int) $row->item_id,
                    'item_title'       => $row->item_title,
                    'borrower'         => $row->borrower_full_name ?: $row->borrower_name,
                    'borrower_contact' => $row->borrower_contact ?? '',
                    'borrow_tags'      => $row->borrow_tags ?? '',
                    'action'           => $row->action,
                    'quantity'         => (int) $row->quantity,
                    'notes'            => $row->notes,
                    'date_due'         => $row->date_due,
                    'date_returned'    => $row->date_returned,
                    'return_status'    => $return_status,
                ],
            ];
        }

        return $events;
    }

    /**
     * Return an aggregated summary of all borrowers grouped by entity name.
     *
     * Entities are identified by the borrower_full_name entered in the borrow form,
     * normalised to lower-case so that "John Doe", "john doe", and "JOHN DOE" all
     * merge into a single row.  The display_name and contact shown are taken from
     * the most-recent log for that entity.
     *
     * @return array<int, object>
     */
    public static function get_borrowers_summary(): array {
        global $wpdb;
        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results(
            "SELECT
                 stats.entity_key,
                 COALESCE( NULLIF( TRIM( latest.borrower_full_name ), '' ), stats.entity_key ) AS display_name,
                 latest.borrower_contact,
                 stats.total_borrows,
                 stats.active_borrows,
                 stats.returned_borrows,
                 stats.overdue_borrows,
                 stats.last_borrowed
             FROM (
                 SELECT
                     LOWER( TRIM( COALESCE( NULLIF( TRIM( borrower_full_name ), '' ), NULLIF( TRIM( borrower_name ), '' ), '(unknown)' ) ) ) AS entity_key,
                     COUNT(*)                                                                                                                 AS total_borrows,
                     SUM( CASE WHEN action = 'borrowed' AND date_returned IS NULL THEN 1 ELSE 0 END )                                        AS active_borrows,
                     SUM( CASE WHEN date_returned IS NOT NULL THEN 1 ELSE 0 END )                                                            AS returned_borrows,
                     SUM( CASE WHEN action = 'borrowed' AND date_returned IS NULL AND date_due IS NOT NULL AND date_due < UTC_TIMESTAMP() THEN 1 ELSE 0 END ) AS overdue_borrows,
                     MAX( date_borrowed )                                                                                                     AS last_borrowed,
                     MAX( id )                                                                                                                AS latest_id
                 FROM {$table}
                 GROUP BY entity_key
             ) stats
             LEFT JOIN {$table} latest ON latest.id = stats.latest_id
             ORDER BY stats.last_borrowed DESC"
        );
    }

    /**
     * Get all currently open (not-returned) borrow records across all entities.
     *
     * Returns one row per open borrow, with the normalised entity_key attached,
     * so callers can quickly build an entity → active-borrows map.
     *
     * @return array<int, object>
     */
    public static function get_active_borrows_all_entities(): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results(
            "SELECT l.id, l.item_id, l.action, l.quantity, l.date_borrowed, l.date_due,
                    l.notes, l.borrower_name, l.borrower_full_name, l.borrower_contact,
                    p.post_title AS item_title,
                    LOWER( TRIM( COALESCE( NULLIF( TRIM( l.borrower_full_name ), '' ), NULLIF( TRIM( l.borrower_name ), '' ), '(unknown)' ) ) ) AS entity_key
             FROM " . self::table() . " l
             LEFT JOIN " . $wpdb->posts . " p ON p.ID = l.item_id
             WHERE l.action = 'borrowed' AND l.date_returned IS NULL
             ORDER BY l.date_due ASC, l.date_borrowed DESC"
        );
    }

    /**
     * Get all log entries for a borrower entity (matched by full name, case-insensitive).
     *
     * Aggregates records across all WP accounts that used the same borrower_full_name,
     * making the entity name — not the WP login — the source of truth for borrower identity.
     *
     * @param  string $entity_name The borrower_full_name to look up (case-insensitive).
     * @return array<int, object>
     */
    public static function get_logs_for_entity( string $entity_name ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, p.post_title AS item_title
                 FROM " . self::table() . " l
                 LEFT JOIN " . $wpdb->posts . " p ON p.ID = l.item_id
                 WHERE LOWER( TRIM( COALESCE( NULLIF( TRIM( l.borrower_full_name ), '' ), NULLIF( TRIM( l.borrower_name ), '' ), '(unknown)' ) ) ) = LOWER( %s )
                 ORDER BY l.date_borrowed DESC",
                $entity_name
            )
        );
    }

    /**
     * Delete ALL borrow log rows and record an audit entry.
     *
     * @param  string $reason     Admin-provided reason for the deletion.
     * @param  int    $deleted_by WordPress user ID performing the action.
     * @return int  Number of rows that were deleted.
     */
    public static function delete_all_logs( string $reason, int $deleted_by ): int {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Write to rolling audit log (keeps the last 50 events).
        $audit = get_option( 'xen_purge_audit_log', [] );
        if ( ! is_array( $audit ) ) {
            $audit = [];
        }
        $user    = get_userdata( $deleted_by );
        $audit[] = [
            'date'            => current_time( 'mysql', true ),
            'user_id'         => $deleted_by,
            'user_display'    => $user ? $user->display_name : '(unknown)',
            'reason'          => $reason,
            'records_deleted' => $count,
        ];
        update_option( 'xen_purge_audit_log', array_slice( $audit, -50 ) );

        return $count;
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

    /**
     * Get ALL borrow log entries (active + returned) for a specific user.
     *
     * Used on the frontend "My Borrow History" section so a user can see
     * their complete personal history — no other user's records are included.
     *
     * @param  int $user_id WordPress user ID.
     * @return array<int, object>
     */
    public static function get_all_borrows_for_user( int $user_id ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT l.*, p.post_title AS item_title
                 FROM ' . self::table() . ' l
                 INNER JOIN ' . $wpdb->posts . " p ON p.ID = l.item_id
                 WHERE l.user_id = %d
                   AND p.post_status = 'publish'
                 ORDER BY l.date_borrowed DESC
                 LIMIT 50",
                $user_id
            )
        );
    }

    /**
     * Count open (not-returned) borrow rows for a given borrower entity.
     *
     * Used to prevent deletion of an entity that still has items out.
     *
     * @param  string $entity_name The borrower_full_name / entity key to look up.
     * @return int
     */
    public static function count_active_borrows_for_entity( string $entity_name ): int {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::table() . "
                 WHERE LOWER( TRIM( COALESCE( NULLIF( TRIM( borrower_full_name ), '' ), NULLIF( TRIM( borrower_name ), '' ), '(unknown)' ) ) ) = LOWER( %s )
                   AND action = 'borrowed'
                   AND date_returned IS NULL",
                $entity_name
            )
        );
    }

    /**
     * Get published borrow log entries for a specific item, newest first.
     * Only shows borrower name to other users — full contact detail is omitted
     * to protect privacy. Admins see full details via the admin screens.
     *
     * @param  int $item_id Post ID of the xen_item.
     * @param  int $limit   Maximum rows to return (default 20).
     * @return array<int, object>
     */
    public static function get_public_logs_for_item( int $item_id, int $limit = 20 ): array {        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, item_id, user_id,
                        borrower_full_name, borrower_name, borrower_contact,
                        action, quantity, borrow_tags,
                        date_borrowed, date_due, date_returned, notes,
                        return_notes, item_condition
                 FROM ' . self::table() . "
                 WHERE item_id = %d
                   AND action = 'borrowed'
                 ORDER BY date_borrowed DESC
                 LIMIT %d",
                $item_id,
                $limit
            )
        );
    }
}
