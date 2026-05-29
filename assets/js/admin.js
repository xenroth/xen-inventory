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
    // Delete a borrower entity via AJAX.
    // -----------------------------------------------------------------------

    $( document ).on( 'click', '.xen-delete-borrower', function () {
        if ( ! window.confirm( xenInventoryAdmin.i18n.confirmDeleteBorrower ) ) {
            return;
        }

        const $btn        = $( this );
        const entityName  = $btn.data( 'entity-name' );
        const $row        = $btn.closest( 'tr' );

        $btn.prop( 'disabled', true ).text( xenInventoryAdmin.i18n.deleting );

        $.post( xenInventoryAdmin.ajaxUrl, {
            action:       'xen_delete_borrower',
            nonce:        xenInventoryAdmin.deleteBorrowerNonce,
            entity_name:  entityName,
        } )
        .done( function ( response ) {
            if ( response.success ) {
                // Replace the Actions cell content with a Deleted badge.
                $btn.closest( 'td' ).html( '<span class="xen-badge xen-badge--deleted">Deleted</span>' );
            } else {
                alert( response.data ? response.data.message : xenInventoryAdmin.i18n.confirmDeleteBorrower );
                $btn.prop( 'disabled', false ).text( 'Delete' );
            }
        } )
        .fail( function ( jqXHR ) {
            var msg = xenInventoryAdmin.i18n.confirmDeleteBorrower;
            try {
                var resp = jqXHR.responseJSON || JSON.parse( jqXHR.responseText );
                if ( resp && resp.data && resp.data.message ) { msg = resp.data.message; }
            } catch (e) {}
            alert( msg );
            $btn.prop( 'disabled', false ).text( 'Delete' );
        } );
    } );

    // -----------------------------------------------------------------------
    // Mark a borrow log entry as returned via AJAX — admin return modal.
    // -----------------------------------------------------------------------

    var conditionLabels = {
        'good':         'In condition / Usable',
        'slight_damage':'Slightly damaged / torn',
        'total_damage': 'Totally damaged / unusable'
    };
    function conditionLabel( val ) {
        return val ? ( conditionLabels[ val ] || val ) : '';
    }

    function ensureReturnModal() {
        if ( $( '#xen-admin-return-modal' ).length ) { return; }

        $( 'body' ).append(
            '<div id="xen-admin-return-modal" style="display:none;position:fixed;inset:0;z-index:100070;align-items:center;justify-content:center;" role="dialog" aria-modal="true">' +
            '  <div id="xen-admin-return-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.6);"></div>' +
            '  <div style="position:relative;background:#fff;border-radius:6px;padding:1.5rem 1.75rem;width:480px;max-width:95vw;max-height:85vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.3);">' +
            '    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;border-bottom:2px solid #f0f0f0;padding-bottom:.75rem;">' +
            '      <h3 style="margin:0;font-size:1rem;font-weight:700;">Return Item</h3>' +
            '      <button type="button" id="xen-admin-return-close" class="button" style="min-width:auto;padding:.1rem .5rem;font-size:1.1rem;line-height:1.6;" aria-label="Close">&times;</button>' +
            '    </div>' +
            '    <p style="font-size:.9rem;margin:.25rem 0 1rem;">Returning: <strong id="xen-admin-return-item-name"></strong></p>' +
            '    <div id="xen-admin-return-qty-wrap" style="display:none;margin-bottom:1rem;">' +
            '      <label for="xen-admin-return-qty" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">Qty Returning <span id="xen-admin-return-qty-max" style="font-weight:400;color:#666;"></span></label>' +
            '      <input type="number" id="xen-admin-return-qty" min="1" style="width:6rem;padding:.3rem .5rem;border:1px solid #ccc;border-radius:4px;" />' +
            '    </div>' +
            '    <div style="margin-bottom:1rem;">' +
            '      <label for="xen-admin-return-date" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">Return Date &amp; Time <small style="font-weight:400;color:#666;">(optional, defaults to now)</small></label>' +
            '      <div style="display:flex;gap:.4rem;align-items:center;">' +
            '        <input type="datetime-local" id="xen-admin-return-date" style="padding:.3rem .5rem;border:1px solid #ccc;border-radius:4px;" />' +
            '        <button type="button" id="xen-admin-return-date-now" class="button button-small" title="Set to current date &amp; time">Now</button>' +
            '      </div>' +
            '    </div>' +
            '    <div style="margin-bottom:1rem;">' +
            '      <label for="xen-admin-return-condition" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">Item Condition on Return <span style="color:#c00;" aria-hidden="true">*</span></label>' +
            '      <select id="xen-admin-return-condition" required style="width:100%;padding:.4rem .5rem;border:1px solid #ccc;border-radius:4px;">' +
            '        <option value="">— Select condition —</option>' +
            '        <option value="good">✅ In condition / Usable</option>' +
            '        <option value="slight_damage">⚠️ Slightly damaged / torn</option>' +
            '        <option value="total_damage">❌ Totally damaged / unusable</option>' +
            '      </select>' +
            '    </div>' +
            '    <div style="margin-bottom:1.25rem;">' +
            '      <label for="xen-admin-return-notes" style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.3rem;">Return Remarks <span style="color:#c00;" aria-hidden="true">*</span></label>' +
            '      <textarea id="xen-admin-return-notes" rows="3" required placeholder="e.g. Returned in good condition. Checked by staff." style="width:100%;padding:.4rem .5rem;border:1px solid #ccc;border-radius:4px;resize:vertical;box-sizing:border-box;"></textarea>' +
            '    </div>' +
            '    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">' +
            '      <button type="button" id="xen-admin-return-submit" class="button button-primary">Confirm Return</button>' +
            '      <button type="button" id="xen-admin-return-cancel" class="button">Cancel</button>' +
            '    </div>' +
            '    <p id="xen-admin-return-status" style="margin-top:.75rem;font-size:.875rem;min-height:1.2em;" aria-live="polite"></p>' +
            '  </div>' +
            '</div>'
        );

        $( document ).on( 'click', '#xen-admin-return-close, #xen-admin-return-cancel, #xen-admin-return-backdrop', function () {
            $( '#xen-admin-return-modal' ).css( 'display', 'none' );
        } );

        $( document ).on( 'keydown.xenAdminReturnModal', function ( e ) {
            if ( 'Escape' === e.key ) { $( '#xen-admin-return-modal' ).css( 'display', 'none' ); }
        } );

        $( document ).on( 'click', '#xen-admin-return-date-now', function () {
            var now = new Date();
            var pad = function ( n ) { return String( n ).padStart( 2, '0' ); };
            $( '#xen-admin-return-date' ).val(
                now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() ) +
                'T' + pad( now.getHours() ) + ':' + pad( now.getMinutes() )
            );
        } );

        $( document ).on( 'click', '#xen-admin-return-submit', function () {
            var $modal     = $( '#xen-admin-return-modal' );
            var logId      = $modal.data( 'log-id' );
            var totalQty   = $modal.data( 'total-qty' );
            var $origBtn   = $modal.data( 'orig-btn' );
            var origText   = $modal.data( 'orig-text' );
            var condition  = $( '#xen-admin-return-condition' ).val();
            var notes      = $.trim( $( '#xen-admin-return-notes' ).val() );
            var $status    = $( '#xen-admin-return-status' );
            var $submit    = $( this );

            var qtyReturned = totalQty;
            if ( $modal.find( '#xen-admin-return-qty-wrap' ).is( ':visible' ) ) {
                qtyReturned = parseInt( $( '#xen-admin-return-qty' ).val(), 10 );
                if ( isNaN( qtyReturned ) || qtyReturned < 1 || qtyReturned > totalQty ) {
                    $status.css( 'color', '#c00' ).text( 'Please enter a valid quantity (1–' + totalQty + ').' );
                    return;
                }
            }

            if ( ! condition ) {
                $status.css( 'color', '#c00' ).text( 'Please select the item condition.' );
                return;
            }
            if ( ! notes ) {
                $status.css( 'color', '#c00' ).text( 'Return remarks are required.' );
                return;
            }

            $submit.prop( 'disabled', true ).text( xenInventoryAdmin.i18n.saving );
            $status.text( '' );

            $.post( xenInventoryAdmin.ajaxUrl, {
                action:         'xen_return_item',
                nonce:          xenInventoryAdmin.returnNonce,
                log_id:         logId,
                qty_returned:   qtyReturned,
                return_notes:   notes,
                item_condition: condition,
                date_returned:  $( '#xen-admin-return-date' ).val() || '',
            } )
            .done( function ( response ) {
                if ( response.success ) {
                    $modal.css( 'display', 'none' );
                    var isPartial = qtyReturned < totalQty;
                    var $row = $origBtn.closest( 'tr' );
                    if ( isPartial ) {
                        var remaining = totalQty - qtyReturned;
                        $row.find( '.xen-log-qty-cell' ).text( remaining );
                        $origBtn.data( 'qty', remaining ).prop( 'disabled', false ).text( origText );
                    } else {
                        $row.find( '.xen-log-returned-cell' ).html(
                            '<span class="xen-badge xen-badge--returned">' + xenInventoryAdmin.i18n.returned + '</span>'
                        );
                        $origBtn.remove();
                    }
                } else {
                    $status.css( 'color', '#c00' ).text( response.data ? response.data.message : 'Error.' );
                    $submit.prop( 'disabled', false ).text( 'Confirm Return' );
                }
            } )
            .fail( function () {
                $status.css( 'color', '#c00' ).text( 'Network error.' );
                $submit.prop( 'disabled', false ).text( 'Confirm Return' );
            } );
        } );
    }

    function openAdminReturnModal( logId, totalQty, itemTitle, $origBtn ) {
        ensureReturnModal();
        var $modal = $( '#xen-admin-return-modal' );
        $modal.data( 'log-id',    logId )
              .data( 'total-qty', totalQty )
              .data( 'orig-btn',  $origBtn )
              .data( 'orig-text', $origBtn.text() );
        $( '#xen-admin-return-item-name' ).text( itemTitle || 'Log #' + logId );
        $( '#xen-admin-return-condition' ).val( '' );
        $( '#xen-admin-return-notes' ).val( '' );
        $( '#xen-admin-return-date' ).val( '' );
        $( '#xen-admin-return-status' ).text( '' );
        $( '#xen-admin-return-submit' ).prop( 'disabled', false ).text( 'Confirm Return' );
        if ( totalQty > 1 ) {
            $( '#xen-admin-return-qty' ).val( totalQty ).attr( 'max', totalQty );
            $( '#xen-admin-return-qty-max' ).text( '(max ' + totalQty + ')' );
            $( '#xen-admin-return-qty-wrap' ).show();
        } else {
            $( '#xen-admin-return-qty-wrap' ).hide();
        }
        $modal.css( 'display', 'flex' );
    }

    $( document ).on( 'click', '.xen-return-log', function () {
        var $btn      = $( this );
        var logId     = $btn.data( 'log-id' );
        var totalQty  = parseInt( $btn.data( 'qty' ) || 1, 10 );
        var itemTitle = $btn.data( 'item-title' ) || $btn.closest( 'tr' ).data( 'item-title' ) || '';
        openAdminReturnModal( logId, totalQty, itemTitle, $btn );
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
            [ 'Condition',      conditionLabel( d.itemCondition ) || '—' ],
            [ 'Return Notes',   d.returnNotes      || '—' ],
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

    // -----------------------------------------------------------------------
    // Double-click on borrower list rows → show borrower summary modal.
    // -----------------------------------------------------------------------

    function ensureBorrowerModal() {
        if ( $( '#xen-borrower-modal' ).length ) { return; }

        $( 'body' ).append(
            '<div id="xen-borrower-modal" style="display:none;position:fixed;inset:0;z-index:100065;align-items:center;justify-content:center;" role="dialog" aria-modal="true">' +
            '  <div id="xen-borrower-modal-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.55);"></div>' +
            '  <div style="position:relative;background:#fff;border-radius:6px;padding:1.5rem 1.75rem;width:540px;max-width:95vw;max-height:85vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.28);">' +
            '    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;border-bottom:2px solid #f0f0f0;padding-bottom:.75rem;">' +
            '      <h3 id="xen-borrower-modal-name" style="margin:0;font-size:1rem;font-weight:700;"></h3>' +
            '      <button type="button" id="xen-borrower-modal-close" class="button" style="min-width:auto;padding:.1rem .5rem;font-size:1.1rem;line-height:1.6;" aria-label="Close">&times;</button>' +
            '    </div>' +
            '    <div id="xen-borrower-modal-body"></div>' +
            '  </div>' +
            '</div>'
        );

        $( document ).on( 'click', '#xen-borrower-modal-close, #xen-borrower-modal-backdrop', function () {
            $( '#xen-borrower-modal' ).css( 'display', 'none' );
        } );

        $( document ).on( 'keydown.xenBorrowerModal', function ( e ) {
            if ( 'Escape' === e.key ) { $( '#xen-borrower-modal' ).css( 'display', 'none' ); }
        } );
    }

    function showBorrowerModal( data, activeBorrows, detailUrl ) {
        ensureBorrowerModal();
        $( '#xen-borrower-modal-name' ).text( data.displayName || '(unknown)' );

        var html = '<table style="width:100%;border-collapse:collapse;font-size:.875rem;margin-bottom:1rem;">';
        function row( label, val ) {
            if ( ! val && val !== 0 ) { return; }
            html += '<tr style="border-bottom:1px solid #eee;">' +
                    '<th style="text-align:left;padding:.4rem .5rem .4rem 0;width:40%;font-weight:600;color:#555;vertical-align:top;">' + escHtmlStr( label ) + '</th>' +
                    '<td style="padding:.4rem 0;vertical-align:top;">' + escHtmlStr( String( val ) ) + '</td>' +
                    '</tr>';
        }
        row( 'Contact',      data.contact );
        row( 'Total Borrows', data.total );
        row( 'Active',        data.active );
        row( 'Overdue',       data.overdue );
        row( 'Returned',      data.returned );
        row( 'Last Borrowed', data.lastBorrowed );
        html += '</table>';

        if ( activeBorrows && activeBorrows.length ) {
            html += '<h4 style="margin:0 0 .5rem;font-size:.875rem;font-weight:700;">Active Borrows</h4>';
            html += '<table style="width:100%;border-collapse:collapse;font-size:.8125rem;">' +
                    '<thead><tr style="background:#f9f9f9;">' +
                    '<th style="text-align:left;padding:.35rem .4rem;border-bottom:1px solid #ddd;">Item</th>' +
                    '<th style="text-align:center;padding:.35rem .4rem;border-bottom:1px solid #ddd;">Qty</th>' +
                    '<th style="text-align:left;padding:.35rem .4rem;border-bottom:1px solid #ddd;">Due</th>' +
                    '<th style="text-align:left;padding:.35rem .4rem;border-bottom:1px solid #ddd;">Actions</th>' +
                    '</tr></thead><tbody>';
            activeBorrows.forEach( function ( b ) {
                var bId  = parseInt( b.id,  10 ) || 0;
                var bQty = parseInt( b.qty, 10 ) || 1;
                html += '<tr style="border-bottom:1px solid #f0f0f0;">' +
                        '<td style="padding:.35rem .4rem;">' + escHtmlStr( b.item_title || '—' ) + '</td>' +
                        '<td style="text-align:center;padding:.35rem .4rem;">' + bQty + '</td>' +
                        '<td style="padding:.35rem .4rem;">' + escHtmlStr( b.date_due || '—' ) + '</td>' +
                        '<td style="padding:.35rem .4rem;">' +
                        '<button type="button" class="button button-small button-primary xen-return-log" ' +
                        'data-log-id="' + bId + '" data-qty="' + bQty + '" ' +
                        'data-item-title="' + escHtmlStr( b.item_title || '' ) + '" ' +
                        'style="margin-right:.3rem;" onclick="jQuery(\'#xen-borrower-modal\').css(\'display\',\'none\');">Return</button>' +
                        '</td>' +
                        '</tr>';
            } );
            html += '</tbody></table>';
        }

        html += '<div style="margin-top:1rem;padding-top:.75rem;border-top:1px solid #eee;">' +
                '<a href="' + escHtmlStr( detailUrl ) + '" class="button">' +
                'View Full History &rarr;</a></div>';

        $( '#xen-borrower-modal-body' ).html( html );
        $( '#xen-borrower-modal' ).css( 'display', 'flex' );
    }

    $( document ).on( 'dblclick', '.xen-borrowers-list-row', function () {
        var $row    = $( this );
        var data    = {
            displayName:  $row.data( 'display-name' ) || '—',
            contact:      $row.data( 'contact' )      || '',
            total:        $row.data( 'total' )         || 0,
            active:       $row.data( 'active' )        || 0,
            overdue:      $row.data( 'overdue' )       || 0,
            returned:     $row.data( 'returned' )      || 0,
            lastBorrowed: $row.data( 'last-borrowed' ) || '',
        };
        var activeBorrows = [];
        try {
            var raw = $row.data( 'active-borrows' );
            if ( typeof raw === 'string' && raw ) {
                activeBorrows = JSON.parse( raw );
            } else if ( Array.isArray( raw ) ) {
                activeBorrows = raw;
            }
        } catch ( ex ) {}

        var entityKey = $row.data( 'entity-key' ) || $row.data( 'display-name' ) || '';
        var detailUrl = 'admin.php?page=xen-borrowers&xen_entity=' + encodeURIComponent( entityKey );
        showBorrowerModal( data, activeBorrows, detailUrl );
    } );

    // -----------------------------------------------------------------------
    // Borrower detail view history — client-side filter + pagination.
    // -----------------------------------------------------------------------

    ( function () {
        var PER_PAGE    = 20;
        var currentPage = 1;
        var visibleRows = [];
        var tbody       = document.getElementById( 'xen-borrower-detail-history-tbody' );
        var paginEl     = document.getElementById( 'xen-borrower-detail-history-pagination' );
        var countEl     = document.getElementById( 'xen-detail-history-count' );
        var searchEl    = document.getElementById( 'xen-detail-history-search' );
        var actionEl    = document.getElementById( 'xen-detail-history-action' );
        var statusEl    = document.getElementById( 'xen-detail-history-status' );

        if ( ! tbody ) { return; }

        function allRows() {
            return Array.prototype.slice.call( tbody.querySelectorAll( 'tr' ) );
        }

        function renderPagination() {
            if ( ! paginEl ) { return; }
            var totalPages = Math.ceil( visibleRows.length / PER_PAGE ) || 1;
            paginEl.innerHTML = '';
            if ( totalPages <= 1 ) { return; }
            function mkBtn( label, pg, disabled, active ) {
                var btn = document.createElement( 'button' );
                btn.type = 'button';
                btn.textContent = label;
                btn.className = 'button button-small' + ( active ? ' button-primary' : '' );
                btn.disabled = disabled;
                btn.addEventListener( 'click', function () { currentPage = pg; applyPage(); } );
                return btn;
            }
            paginEl.appendChild( mkBtn( '«', 1, currentPage === 1, false ) );
            paginEl.appendChild( mkBtn( '‹', currentPage - 1, currentPage === 1, false ) );
            var start = Math.max( 1, currentPage - 2 );
            var end   = Math.min( totalPages, currentPage + 2 );
            for ( var p = start; p <= end; p++ ) {
                paginEl.appendChild( mkBtn( p, p, false, p === currentPage ) );
            }
            paginEl.appendChild( mkBtn( '›', currentPage + 1, currentPage === totalPages, false ) );
            paginEl.appendChild( mkBtn( '»', totalPages, currentPage === totalPages, false ) );
            var info = document.createElement( 'span' );
            info.style.cssText = 'font-size:.8125rem;color:#666;margin-left:.5rem;';
            info.textContent = 'Page ' + currentPage + ' of ' + totalPages + ' (' + visibleRows.length + ' records)';
            paginEl.appendChild( info );
        }

        function applyPage() {
            var start = ( currentPage - 1 ) * PER_PAGE;
            var end   = start + PER_PAGE;
            allRows().forEach( function ( r ) { r.style.display = 'none'; } );
            visibleRows.forEach( function ( r, i ) {
                r.style.display = ( i >= start && i < end ) ? '' : 'none';
            } );
            if ( countEl ) { countEl.textContent = visibleRows.length; }
            renderPagination();
        }

        function applyFilters() {
            var q       = searchEl ? searchEl.value.toLowerCase().trim() : '';
            var action  = actionEl ? actionEl.value.toLowerCase().trim() : '';
            var status  = statusEl ? statusEl.value.toLowerCase().trim() : '';

            visibleRows = allRows().filter( function ( r ) {
                var matchQ      = ! q      || ( r.dataset.filter  || '' ).indexOf( q )      > -1;
                var matchAction = ! action || ( r.dataset.action  || '' ) === action;
                var matchStatus = ! status || ( r.dataset.statusFilter || '' ) === status;
                return matchQ && matchAction && matchStatus;
            } );

            currentPage = 1;
            applyPage();
        }

        if ( searchEl ) { searchEl.addEventListener( 'input',  applyFilters ); }
        if ( actionEl ) { actionEl.addEventListener( 'change', applyFilters ); }
        if ( statusEl ) { statusEl.addEventListener( 'change', applyFilters ); }
        applyFilters();
    } )();

    // -----------------------------------------------------------------------
    // Meta-box borrow history — client-side filter + pagination.
    // -----------------------------------------------------------------------

    ( function () {
        var PER_PAGE    = 15;
        var currentPage = 1;
        var visibleRows = [];
        var tbody       = document.getElementById( 'xen-meta-box-history-tbody' );
        var paginEl     = document.getElementById( 'xen-meta-box-history-pagination' );
        var searchEl    = document.getElementById( 'xen-meta-box-history-search' );
        var statusEl    = document.getElementById( 'xen-meta-box-history-status' );

        if ( ! tbody ) { return; }

        function allRows() {
            return Array.prototype.slice.call( tbody.querySelectorAll( 'tr' ) );
        }

        function renderPagination() {
            if ( ! paginEl ) { return; }
            var totalPages = Math.ceil( visibleRows.length / PER_PAGE ) || 1;
            paginEl.innerHTML = '';
            if ( totalPages <= 1 ) { return; }
            function mkBtn( label, pg, disabled, active ) {
                var btn = document.createElement( 'button' );
                btn.type = 'button';
                btn.textContent = label;
                btn.className = 'button button-small' + ( active ? ' button-primary' : '' );
                btn.disabled = disabled;
                btn.addEventListener( 'click', function () { currentPage = pg; applyPage(); } );
                return btn;
            }
            paginEl.appendChild( mkBtn( '«', 1, currentPage === 1, false ) );
            paginEl.appendChild( mkBtn( '‹', currentPage - 1, currentPage === 1, false ) );
            var start = Math.max( 1, currentPage - 2 );
            var end   = Math.min( totalPages, currentPage + 2 );
            for ( var p = start; p <= end; p++ ) {
                paginEl.appendChild( mkBtn( p, p, false, p === currentPage ) );
            }
            paginEl.appendChild( mkBtn( '›', currentPage + 1, currentPage === totalPages, false ) );
            paginEl.appendChild( mkBtn( '»', totalPages, currentPage === totalPages, false ) );
            var info = document.createElement( 'span' );
            info.style.cssText = 'font-size:.8125rem;color:#666;margin-left:.5rem;';
            info.textContent = 'Page ' + currentPage + ' of ' + totalPages + ' (' + visibleRows.length + ' records)';
            paginEl.appendChild( info );
        }

        function applyPage() {
            var start = ( currentPage - 1 ) * PER_PAGE;
            var end   = start + PER_PAGE;
            allRows().forEach( function ( r ) { r.style.display = 'none'; } );
            visibleRows.forEach( function ( r, i ) {
                r.style.display = ( i >= start && i < end ) ? '' : 'none';
            } );
            renderPagination();
        }

        function applyFilters() {
            var q      = searchEl ? searchEl.value.toLowerCase().trim() : '';
            var status = statusEl ? statusEl.value.toLowerCase().trim() : '';

            visibleRows = allRows().filter( function ( r ) {
                var matchQ      = ! q      || ( r.dataset.filter  || '' ).indexOf( q )      > -1;
                var matchStatus = ! status || ( r.dataset.statusMb || '' ) === status;
                return matchQ && matchStatus;
            } );

            currentPage = 1;
            applyPage();
        }

        if ( searchEl ) { searchEl.addEventListener( 'input',  applyFilters ); }
        if ( statusEl ) { statusEl.addEventListener( 'change', applyFilters ); }
        applyFilters();
    } )();

} )( jQuery );
