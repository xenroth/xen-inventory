/**
 * XEN Inventory — Admin JavaScript
 *
 * Handles:
 *  - AJAX delete of borrow log rows.
 *  - AJAX return (close) of open borrow log rows.
 *  - Copy-to-clipboard for shortcode reference panel.
 *
 * Depends on: jQuery, xenInventoryAdmin (wp_localize_script).
 */

/* global xenInventoryAdmin, jQuery */
( function ( $ ) {
    'use strict';

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
                    // Update qty cell (index 3) and edit-button data.
                    const remaining = totalQty - qtyReturned;
                    $row.find( 'td:eq(3)' ).text( remaining );
                    $btn.data( 'qty', remaining ).prop( 'disabled', false ).text( orig );
                    $row.find( '.xen-edit-log' ).data( 'date-due', $row.find( '.xen-edit-log' ).data( 'date-due' ) );
                } else {
                    // Full return — update the Returned cell and remove button.
                    $row.find( 'td:eq(6)' ).html(
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
                            '<span style="font-weight:600;display:block;">Due Date</span>' +
                            '<input type="date" name="date_due" value="' + $( '<div>' ).text( dateDue ).html() + '" style="padding:.3rem .5rem;border:1px solid #ccd0d4;border-radius:3px;" />' +
                        '</label>' +
                        '<label style="display:block;font-size:.8125rem;">' +
                            '<span style="font-weight:600;display:block;">Date Returned <small style="font-weight:400;">(blank = still out)</small></span>' +
                            '<input type="date" name="date_returned" value="' + $( '<div>' ).text( dateReturned ).html() + '" style="padding:.3rem .5rem;border:1px solid #ccd0d4;border-radius:3px;" />' +
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
                // Update the Due cell (index 5) and Returned cell (index 6).
                $dataRow.find( 'td:eq(5)' ).text( dateDue      || '—' );
                $dataRow.find( 'td:eq(6)' ).text( dateReturned || '' ).find( '.xen-badge' ).remove();
                if ( dateReturned ) {
                    $dataRow.find( 'td:eq(6)' ).text( dateReturned );
                    $dataRow.find( '.xen-return-log' ).remove();
                } else {
                    $dataRow.find( 'td:eq(6)' ).html( '<span class="xen-badge xen-badge--open">Open</span>' );
                }
                $dataRow.find( 'td:eq(7)' ).text( notes );
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

} )( jQuery );
