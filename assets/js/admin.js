/**
 * XEN Inventory — Admin JavaScript
 *
 * Handles:
 *  - AJAX delete of borrow log rows.
 *  - AJAX return (close) of open borrow log rows.
 *  - Copy-to-clipboard for shortcode reference panel.
 *  - Inline edit of borrow log entries (edit row / Return Now).
 *  - Double-click detail modal for borrow history rows.
 *  - Double-click detail modal for audit log rows.
 *
 * Depends on: jQuery, xenInventoryAdmin (wp_localize_script).
 */

/* global xenInventoryAdmin, jQuery */
( function ( $ ) {
    'use strict';

    // -----------------------------------------------------------------------
    // Helper: convert a DB datetime string to datetime-local input value.
    // "2024-01-15 14:30:00" or "2024-01-15" → "2024-01-15T14:30"
    // "2024-01-15T14:30"    → "2024-01-15T14:30" (already correct)
    // -----------------------------------------------------------------------
    function toDatetimeLocal( str ) {
        if ( ! str ) return '';
        return str.replace( ' ', 'T' ).substring( 0, 16 );
    }

    // -----------------------------------------------------------------------
    // Helper: format a datetime string for display (replace T with space).
    // -----------------------------------------------------------------------
    function formatDisplay( str ) {
        if ( ! str ) return '—';
        return str.replace( 'T', ' ' );
    }

    // -----------------------------------------------------------------------
    // Delete a log entry via AJAX.
    // -----------------------------------------------------------------------

    $( document ).on( 'click', '.xen-delete-log', function () {
        if ( ! window.confirm( xenInventoryAdmin.i18n.confirmDelete ) ) {
            return;
        }

        const $btn  = $( this );
        const logId = $btn.data( 'log-id' );

        $btn.prop( 'disabled', true );

        $.post( xenInventoryAdmin.ajaxUrl, {
            action:  'xen_delete_log',
            nonce:   xenInventoryAdmin.nonce,
            log_id:  logId,
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $btn.closest( 'tr' ).fadeOut( 300, function () {
                    $( this ).remove();
                } );
            } else {
                alert( response.data ? response.data.message : xenInventoryAdmin.i18n.confirmDelete );
                $btn.prop( 'disabled', false );
            }
        } )
        .fail( function () {
            alert( xenInventoryAdmin.i18n.confirmDelete );
            $btn.prop( 'disabled', false );
        } );
    } );

    // -----------------------------------------------------------------------
    // Mark a borrow log entry as returned via AJAX.
    // -----------------------------------------------------------------------

    $( document ).on( 'click', '.xen-return-log', function () {
        const $btn    = $( this );
        const logId   = $btn.data( 'log-id' );
        const totalQty = parseInt( $btn.data( 'qty' ) || 1, 10 );
        const orig    = $btn.text();

        // When qty > 1, prompt for how many to return.
        var qtyReturned = totalQty;
        if ( totalQty > 1 ) {
            var answer = window.prompt(
                'How many items are being returned? (1 – ' + totalQty + ')',
                totalQty
            );
            if ( answer === null ) {
                return; // Cancelled.
            }
            qtyReturned = parseInt( answer, 10 );
            if ( isNaN( qtyReturned ) || qtyReturned < 1 || qtyReturned > totalQty ) {
                alert( 'Please enter a number between 1 and ' + totalQty + '.' );
                return;
            }
        } else {
            if ( ! window.confirm( xenInventoryAdmin.i18n.confirmReturn ) ) {
                return;
            }
        }

        $btn.prop( 'disabled', true ).text( xenInventoryAdmin.i18n.saving );

        $.post( xenInventoryAdmin.ajaxUrl, {
            action:       'xen_return_item',
            nonce:        xenInventoryAdmin.returnNonce,
            log_id:       logId,
            qty_returned: qtyReturned,
            notes:        '',
        } )
        .done( function ( response ) {
            if ( response.success ) {
                const $row       = $btn.closest( 'tr' );
                const isPartial  = qtyReturned < totalQty;
                if ( isPartial ) {
                    // Update qty cell and edit-button data.
                    const remaining = totalQty - qtyReturned;
                    $row.find( '.xen-log-qty-cell' ).text( remaining );
                    $btn.data( 'qty', remaining ).prop( 'disabled', false ).text( orig );
                    $row.find( '.xen-edit-log' ).data( 'date-due', $row.find( '.xen-edit-log' ).data( 'date-due' ) );
                } else {
                    // Full return — update the Returned cell and remove button.
                    $row.find( '.xen-log-returned-cell' ).html(
                        '<span class="xen-badge xen-badge--returned">' + xenInventoryAdmin.i18n.returned + '</span>'
                    );
                    $btn.remove();
                }
            } else {
                alert( response.data ? response.data.message : 'Error.' );
                $btn.prop( 'disabled', false ).text( orig );
            }
        } )
        .fail( function () {
            alert( 'Error.' );
            $btn.prop( 'disabled', false ).text( orig );
        } );
    } );

    // -----------------------------------------------------------------------
    // Copy shortcode to clipboard.
    // -----------------------------------------------------------------------

    $( document ).on( 'click', '.xen-copy-shortcode', function () {
        const $btn      = $( this );
        const shortcode = $btn.data( 'shortcode' );
        const orig      = $btn.text();

        if ( navigator.clipboard ) {
            navigator.clipboard.writeText( shortcode ).then( function () {
                $btn.text( xenInventoryAdmin.i18n.copied ).prop( 'disabled', true );
                setTimeout( function () {
                    $btn.text( orig ).prop( 'disabled', false );
                }, 1500 );
            } );
        } else {
            // Fallback for older browsers.
            const $tmp = $( '<input>' );
            $( 'body' ).append( $tmp );
            $tmp.val( shortcode ).trigger( 'select' );
            document.execCommand( 'copy' );
            $tmp.remove();
            $btn.text( xenInventoryAdmin.i18n.copied ).prop( 'disabled', true );
            setTimeout( function () {
                $btn.text( orig ).prop( 'disabled', false );
            }, 1500 );
        }
    } );

    // -----------------------------------------------------------------------
    // Inline edit of a borrow log entry (meta-box "Edit" button).
    // Opens a collapsible edit row directly below the clicked row.
    // -----------------------------------------------------------------------

    $( document ).on( 'click', '.xen-edit-log', function () {
        const $btn          = $( this );
        const logId         = $btn.data( 'log-id' );
        const dateDue       = $btn.data( 'date-due' )       || '';
        const dateReturned  = $btn.data( 'date-returned' )  || '';
        const notes         = $btn.data( 'notes' )          || '';
        const $row          = $btn.closest( 'tr' );
        const colSpan       = $row.find( 'td' ).length;
        const editRowId     = 'xen-edit-row-' + logId;

        // Toggle: if the edit row already exists, remove it.
        if ( $( '#' + editRowId ).length ) {
            $( '#' + editRowId ).remove();
            return;
        }

        const html =
            '<tr id="' + editRowId + '" class="xen-inline-edit-row">' +
                '<td colspan="' + colSpan + '" style="padding:.75rem 1rem;background:#f9f9f9;border-top:none;">' +
                    '<form class="xen-inline-edit-form" style="display:flex;flex-wrap:wrap;gap:.5rem .75rem;align-items:flex-end;">' +
                        '<label style="display:block;font-size:.8125rem;">' +
                            '<span style="font-weight:600;display:block;">Due Date &amp; Time</span>' +
                            '<input type="datetime-local" name="date_due" value="' + toDatetimeLocal( dateDue ) + '" style="padding:.3rem .5rem;border:1px solid #ccd0d4;border-radius:3px;" />' +
                        '</label>' +
                        '<label style="display:block;font-size:.8125rem;">' +
                            '<span style="font-weight:600;display:block;">Date &amp; Time Returned <small style="font-weight:400;">(blank = still out)</small></span>' +
                            '<div style="display:flex;gap:.4rem;align-items:center;">' +
                                '<input type="datetime-local" name="date_returned" value="' + toDatetimeLocal( dateReturned ) + '" style="padding:.3rem .5rem;border:1px solid #ccd0d4;border-radius:3px;" />' +
                                '<button type="button" class="button button-small xen-return-now-inline" title="Set to current date &amp; time" style="white-space:nowrap;">Now</button>' +
                            '</div>' +
                        '</label>' +
                        '<label style="display:block;flex:1 1 220px;font-size:.8125rem;">' +
                            '<span style="font-weight:600;display:block;">Notes</span>' +
                            '<input type="text" name="notes" value="' + $( '<div>' ).text( notes ).html() + '" style="width:100%;padding:.3rem .5rem;border:1px solid #ccd0d4;border-radius:3px;" />' +
                        '</label>' +
                        '<button type="submit" class="button button-primary" style="align-self:flex-end;">Save</button>' +
                        '<button type="button" class="button xen-inline-edit-cancel" style="align-self:flex-end;">Cancel</button>' +
                        '<span class="xen-inline-edit-status" style="align-self:flex-end;font-size:.8125rem;"></span>' +
                    '</form>' +
                '</td>' +
            '</tr>';

        $row.after( html );
    } );

    $( document ).on( 'click', '.xen-inline-edit-cancel', function () {
        $( this ).closest( '.xen-inline-edit-row' ).remove();
    } );

    $( document ).on( 'submit', '.xen-inline-edit-form', function ( e ) {
        e.preventDefault();

        const $form        = $( this );
        const $editRow     = $form.closest( '.xen-inline-edit-row' );
        const $dataRow     = $editRow.prev( 'tr' );
        const logId        = $dataRow.find( '.xen-edit-log' ).data( 'log-id' );
        const $saveBtn     = $form.find( '[type="submit"]' );
        const $status      = $form.find( '.xen-inline-edit-status' );
        const dateDue      = $form.find( '[name="date_due"]' ).val();
        const dateReturned = $form.find( '[name="date_returned"]' ).val();
        const notes        = $form.find( '[name="notes"]' ).val();

        $saveBtn.prop( 'disabled', true ).text( xenInventoryAdmin.i18n.saving );
        $status.text( '' );

        $.post( xenInventoryAdmin.ajaxUrl, {
            action:         'xen_update_borrow',
            nonce:          xenInventoryAdmin.updateNonce,
            log_id:         logId,
            date_due:       dateDue,
            date_returned:  dateReturned,
            notes:          notes,
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $status.css( 'color', '#16a34a' ).text( xenInventoryAdmin.i18n.saved );
                // Update data-* attributes on the Edit button so re-opens are accurate.
                const $editBtn = $dataRow.find( '.xen-edit-log' );
                $editBtn.data( 'date-due',      dateDue );
                $editBtn.data( 'date-returned', dateReturned );
                $editBtn.data( 'notes',         notes );
                // Update the Due and Returned cells by named class.
                $dataRow.find( '.xen-log-due-cell' ).text( dateDue      ? formatDisplay( dateDue )      : '—' );
                $dataRow.find( '.xen-log-notes-cell' ).text( notes );
                if ( dateReturned ) {
                    $dataRow.find( '.xen-log-returned-cell' ).text( formatDisplay( dateReturned ) );
                    $dataRow.find( '.xen-return-log' ).remove();
                } else {
                    $dataRow.find( '.xen-log-returned-cell' ).html( '<span class="xen-badge xen-badge--open">Open</span>' );
                }
                setTimeout( function () { $editRow.remove(); }, 700 );
            } else {
                var msg = response.data && response.data.message ? response.data.message : 'Error.';
                $status.css( 'color', '#dc2626' ).text( msg );
                $saveBtn.prop( 'disabled', false ).text( 'Save' );
            }
        } )
        .fail( function () {
            $status.css( 'color', '#dc2626' ).text( 'Network error.' );
            $saveBtn.prop( 'disabled', false ).text( 'Save' );
        } );
    } );

    // -----------------------------------------------------------------------
    // Danger Zone: enable purge button only when confirmation text matches.
    // -----------------------------------------------------------------------

    ( function () {
        const confirmInput = document.getElementById( 'xen_purge_confirm' );
        const purgeBtn     = document.getElementById( 'xen-purge-btn' );

        if ( ! confirmInput || ! purgeBtn ) { return; }

        confirmInput.addEventListener( 'input', function () {
            purgeBtn.disabled = ( this.value !== 'CONFIRM DELETION' );
        } );

        // Double-guard: require explicit submit confirmation.
        document.getElementById( 'xen-purge-form' ).addEventListener( 'submit', function ( e ) {
            if ( confirmInput.value !== 'CONFIRM DELETION' ) {
                e.preventDefault();
                return;
            }
            if ( ! window.confirm( 'This will permanently delete ALL borrow and return records. Are you absolutely sure?' ) ) {
                e.preventDefault();
            }
        } );
    } )();

    // -----------------------------------------------------------------------
    // "Return Now" button inside the inline edit row.
    // -----------------------------------------------------------------------

    $( document ).on( 'click', '.xen-return-now-inline', function () {
        var now = new Date();
        var pad = function ( n ) { return String( n ).padStart( 2, '0' ); };
        var val = now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() ) +
                  'T' + pad( now.getHours() ) + ':' + pad( now.getMinutes() );
        $( this ).closest( 'label, div' ).find( 'input[name="date_returned"]' ).val( val );
    } );

    // -----------------------------------------------------------------------
    // Shared details modal (used by borrow history rows & audit log rows).
    // -----------------------------------------------------------------------

    function ensureDetailModal() {
        if ( $( '#xen-detail-modal' ).length ) { return; }

        $( 'body' ).append(
            '<div id="xen-detail-modal" style="display:none;position:fixed;inset:0;z-index:100060;align-items:center;justify-content:center;">' +
            '  <div id="xen-detail-modal-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.55);"></div>' +
            '  <div style="position:relative;background:#fff;border-radius:4px;padding:1.5rem 1.75rem;' +
            '       width:560px;max-width:95vw;max-height:85vh;overflow-y:auto;' +
            '       box-shadow:0 8px 32px rgba(0,0,0,.28);">' +
            '    <div style="display:flex;align-items:center;justify-content:space-between;' +
            '         margin-bottom:1rem;border-bottom:1px solid #ddd;padding-bottom:.75rem;">' +
            '      <h3 id="xen-detail-modal-heading" style="margin:0;font-size:1rem;"></h3>' +
            '      <button type="button" id="xen-detail-modal-close" class="button" ' +
            '              style="min-width:auto;padding:0 .5rem;font-size:1.1rem;line-height:1.6;"' +
            '              aria-label="Close">&times;</button>' +
            '    </div>' +
            '    <div id="xen-detail-modal-body"></div>' +
            '  </div>' +
            '</div>'
        );

        $( document ).on( 'click', '#xen-detail-modal-close, #xen-detail-modal-backdrop', function () {
            $( '#xen-detail-modal' ).css( 'display', 'none' );
        } );

        $( document ).on( 'keydown.xenDetailModal', function ( e ) {
            if ( 'Escape' === e.key ) { $( '#xen-detail-modal' ).css( 'display', 'none' ); }
        } );
    }

    function showDetailModal( heading, rows ) {
        ensureDetailModal();
        $( '#xen-detail-modal-heading' ).text( heading );
        var html = '<table style="width:100%;border-collapse:collapse;font-size:.875rem;">';
        rows.forEach( function ( row ) {
            if ( row[1] === '' || row[1] === null || row[1] === undefined ) { return; }
            html += '<tr style="border-bottom:1px solid #eee;">' +
                    '<th style="text-align:left;padding:.45rem .5rem .45rem 0;width:38%;font-weight:600;color:#555;vertical-align:top;">' +
                    escHtmlStr( row[0] ) + '</th>' +
                    '<td style="padding:.45rem 0;vertical-align:top;">' + escHtmlStr( String( row[1] ) ) + '</td>' +
                    '</tr>';
        } );
        html += '</table>';
        $( '#xen-detail-modal-body' ).html( html );
        $( '#xen-detail-modal' ).css( 'display', 'flex' );
    }

    function escHtmlStr( str ) {
        return $( '<div>' ).text( str ).html();
    }

    // -----------------------------------------------------------------------
    // Double-click on borrow history rows → show read-only detail modal.
    // -----------------------------------------------------------------------

    $( document ).on( 'dblclick', '.xen-history-row', function () {
        var d = $( this ).data();
        showDetailModal( 'Borrow Record Details', [
            [ 'Item',           d.itemTitle        || '—' ],
            [ 'Borrower (WP)',  d.borrowerName     || '—' ],
            [ 'Entity / Name',  d.borrowerFullName || '—' ],
            [ 'Contact',        d.borrowerContact  || '—' ],
            [ 'Tags',           d.borrowTags       || '—' ],
            [ 'Action',         d.action           || '—' ],
            [ 'Quantity',       d.qty              || '—' ],
            [ 'Borrowed',       d.dateBorrowed     || '—' ],
            [ 'Due',            d.dateDue          || '—' ],
            [ 'Returned',       d.dateReturned     || '—' ],
            [ 'Notes',          d.notes            || '—' ],
        ] );
    } );

    // -----------------------------------------------------------------------
    // Double-click on audit log rows → show detail modal.
    // -----------------------------------------------------------------------

    $( document ).on( 'dblclick', '.xen-audit-row', function () {
        var d           = $( this ).data();
        var detailsObj  = {};
        try {
            if ( typeof d.details === 'object' && d.details !== null ) {
                detailsObj = d.details;
            } else if ( d.details ) {
                detailsObj = JSON.parse( d.details );
            }
        } catch ( ex ) {
            detailsObj = {};
        }

        var rows = [
            [ 'Date / Time',  d.createdAt  || '—' ],
            [ 'User',         d.userName   || '—' ],
            [ 'User ID',      d.userId     || '—' ],
            [ 'Action',       d.action     || '—' ],
            [ 'Object Type',  d.objectType || '—' ],
            [ 'Label',        d.label      || '—' ],
            [ 'IP Address',   d.ip         || '—' ],
        ];

        // Append each detail key/value as its own row.
        if ( detailsObj && typeof detailsObj === 'object' ) {
            Object.keys( detailsObj ).forEach( function ( k ) {
                var v = detailsObj[ k ];
                if ( v !== null && v !== undefined && v !== '' ) {
                    rows.push( [ '↳ ' + k, String( v ) ] );
                }
            } );
        }

        showDetailModal( 'Audit Log Details', rows );
    } );

} )( jQuery );
