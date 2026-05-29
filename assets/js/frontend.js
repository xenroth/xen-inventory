/**
 * XEN Inventory — Frontend JavaScript
 *
 * Handles:
 *  - Borrow modal open/close.
 *  - Borrow form AJAX submission.
 *  - Return item AJAX.
 *
 * Depends on: jQuery (loaded by WordPress), xenInventory (wp_localize_script).
 */

/* global xenInventory, jQuery */
( function ( $ ) {
    'use strict';

    // -----------------------------------------------------------------------
    // Modal
    // -----------------------------------------------------------------------

    const $modal    = $( '#xen-borrow-modal' );
    const $itemId   = $( '#xen-borrow-item-id' );
    const $form     = $( '#xen-borrow-form' );
    const $message  = $form.find( '.xen-form__message' );

    /**
     * Open the borrow modal for a given item.
     *
     * @param {number} itemId
     * @param {string} itemTitle
     */
    function openModal( itemId, itemTitle ) {
        $itemId.val( itemId );
        $modal.find( '.xen-modal__title' ).text(
            /* translators: placeholder is item name */
            xenInventory.i18n.borrowTitle ? xenInventory.i18n.borrowTitle.replace( '%s', itemTitle ) : itemTitle
        );
        $message.text( '' ).removeClass( 'xen-form__message--error xen-form__message--success' );

        // Pre-fill borrower full name from WP user profile if not already set.
        const $fullname = $form.find( '[name="borrower_full_name"]' );
        if ( ! $fullname.val() ) {
            $fullname.val( $form.data( 'user-fullname' ) || '' );
        }

        $modal.removeAttr( 'hidden' );
        $modal.find( '#xen-borrow-fullname' ).trigger( 'focus' );
    }

    function closeModal() {
        $modal.attr( 'hidden', '' );
        $form[ 0 ].reset();
    }

    // Open on "Borrow" button click.
    $( document ).on( 'click', '.xen-borrow-btn', function () {
        const itemId    = $( this ).data( 'item-id' );
        const itemTitle = $( this ).data( 'item-title' );
        openModal( itemId, itemTitle );
    } );

    // Close on overlay / cancel.
    $( document ).on( 'click', '[data-xen-close-modal]', closeModal );

    // Close on Escape key.
    $( document ).on( 'keydown', function ( e ) {
        if ( 'Escape' === e.key && ! $modal.attr( 'hidden' ) ) {
            closeModal();
        }
    } );

    // -----------------------------------------------------------------------
    // Borrow AJAX
    // -----------------------------------------------------------------------

    $form.on( 'submit', function ( e ) {
        e.preventDefault();

        const $btn = $form.find( 'button[type="submit"]' );
        $btn.prop( 'disabled', true ).text( xenInventory.i18n.saving || 'Saving…' );
        $message.text( '' ).removeClass( 'xen-form__message--error xen-form__message--success' );

        const data = {
            action:              'xen_borrow_item',
            nonce:               xenInventory.borrowNonce,
            item_id:             $itemId.val(),
            borrower_full_name:  $form.find( '[name="borrower_full_name"]' ).val(),
            borrower_contact:    $form.find( '[name="borrower_contact"]' ).val(),
            borrow_tags:         $form.find( '[name="borrow_tags"]' ).val(),
            quantity:            $form.find( '[name="quantity"]' ).val(),
            date_due:            $form.find( '[name="date_due"]' ).val(),
            notes:               $form.find( '[name="notes"]' ).val(),
        };

        $.post( xenInventory.ajaxUrl, data )
            .done( function ( response ) {
                if ( response.success ) {
                    $message
                        .addClass( 'xen-form__message--success' )
                        .text( response.data.message || xenInventory.i18n.borrowSuccess );
                    setTimeout( function () {
                        closeModal();
                        // Refresh item card status visually.
                        location.reload();
                    }, 1200 );
                } else {
                    var errData = response.data || {};
                    // Qty exceeded: adjust the quantity field and show an informative warning.
                    if ( errData.code === 'qty_exceeded' && errData.available ) {
                        var $qtyField = $form.find( '[name="quantity"]' );
                        $qtyField.val( errData.available ).attr( 'max', errData.available );
                    }
                    $message
                        .addClass( 'xen-form__message--error' )
                        .text( errData.message || xenInventory.i18n.errorGeneric );
                }
            } )
            .fail( function () {
                $message
                    .addClass( 'xen-form__message--error' )
                    .text( xenInventory.i18n.errorGeneric );
            } )
            .always( function () {
                $btn.prop( 'disabled', false ).text( xenInventory.i18n.confirmBorrow || 'Confirm Borrow' );
            } );
    } );

    // -----------------------------------------------------------------------
    // Return Item — open the return confirmation modal.
    // -----------------------------------------------------------------------

    var conditionLabels = {
        'good':         'In condition / Usable',
        'slight_damage':'Slightly damaged / torn',
        'total_damage': 'Totally damaged / unusable'
    };
    function conditionLabel( val ) {
        return val ? ( conditionLabels[ val ] || val ) : '';
    }

    function openReturnModal( logId, totalQty, qtyReturn, itemTitle ) {
        var $retModal = $( '#xen-return-confirm-modal' );
        if ( ! $retModal.length ) return;
        $retModal.data( 'log-id',     logId )
                 .data( 'total-qty',  totalQty )
                 .data( 'qty-return', qtyReturn );
        $( '#xen-return-confirm-item-name' ).text( itemTitle || 'Log #' + logId );
        $( '#xen-return-confirm-condition' ).val( '' );
        $( '#xen-return-confirm-notes' ).val( '' );
        $( '#xen-return-confirm-status' ).text( '' );
        $( '#xen-return-confirm-submit' ).prop( 'disabled', false ).text( xenInventory.i18n.confirmReturn || 'Confirm Return' );
        if ( totalQty > 1 ) {
            $( '#xen-return-confirm-qty' ).val( qtyReturn ).attr( 'max', totalQty );
            $( '#xen-return-confirm-qty-max-label' ).text( '(max ' + totalQty + ')' );
            $( '#xen-return-confirm-qty-wrap' ).show();
        } else {
            $( '#xen-return-confirm-qty-wrap' ).hide();
        }
        $retModal.css( 'display', 'flex' );
        $( '#xen-return-confirm-condition' ).trigger( 'focus' );
    }

    function closeReturnModal() {
        $( '#xen-return-confirm-modal' ).css( 'display', 'none' );
    }

    $( document ).on( 'click', '.xen-return-btn', function () {
        var $btn      = $( this );
        var logId     = $btn.data( 'log-id' );
        var $row      = $btn.closest( '.xen-return-row' );
        var totalQty  = parseInt( $btn.data( 'qty' ) || 1, 10 );
        var $qtyInput = $row.find( '.xen-return-qty' );
        var qtyReturn = parseInt( $qtyInput.val() || totalQty, 10 );
        var itemTitle = $row.data( 'item-title' ) || '';
        openReturnModal( logId, totalQty, qtyReturn, itemTitle );
    } );

    $( document ).on( 'click', '#xen-return-confirm-submit', function () {
        var $retModal  = $( '#xen-return-confirm-modal' );
        var logId      = $retModal.data( 'log-id' );
        var totalQty   = $retModal.data( 'total-qty' );
        var condition  = $( '#xen-return-confirm-condition' ).val();
        var notes      = $.trim( $( '#xen-return-confirm-notes' ).val() );
        var $status    = $( '#xen-return-confirm-status' );
        var $submit    = $( this );

        var qtyReturned = totalQty;
        if ( $( '#xen-return-confirm-qty-wrap' ).is( ':visible' ) ) {
            qtyReturned = parseInt( $( '#xen-return-confirm-qty' ).val(), 10 );
            if ( isNaN( qtyReturned ) || qtyReturned < 1 || qtyReturned > totalQty ) {
                $status.css( 'color', '#c00' ).text( 'Please enter a valid quantity (1–' + totalQty + ').' );
                return;
            }
        }
        if ( ! condition ) {
            $status.css( 'color', '#c00' ).text( xenInventory.i18n.conditionRequired || 'Please select the item condition.' );
            return;
        }
        if ( ! notes ) {
            $status.css( 'color', '#c00' ).text( xenInventory.i18n.returnNotesRequired || 'Return remarks are required.' );
            return;
        }

        $submit.prop( 'disabled', true ).text( xenInventory.i18n.saving || 'Saving…' );
        $status.text( '' );

        $.post( xenInventory.ajaxUrl, {
            action:         'xen_return_item',
            nonce:          xenInventory.returnNonce,
            log_id:         logId,
            qty_returned:   qtyReturned,
            return_notes:   notes,
            item_condition: condition,
        } ).done( function ( resp ) {
            if ( resp.success ) {
                closeReturnModal();
                location.reload();
            } else {
                $status.css( 'color', '#c00' ).text( ( resp.data && resp.data.message ) || xenInventory.i18n.errorGeneric );
                $submit.prop( 'disabled', false ).text( xenInventory.i18n.confirmReturn || 'Confirm Return' );
            }
        } ).fail( function () {
            $status.css( 'color', '#c00' ).text( xenInventory.i18n.errorGeneric );
            $submit.prop( 'disabled', false ).text( xenInventory.i18n.confirmReturn || 'Confirm Return' );
        } );
    } );

    $( document ).on( 'click', '#xen-return-confirm-cancel, #xen-return-confirm-close, #xen-return-confirm-backdrop', closeReturnModal );
    $( document ).on( 'keydown.xenReturnModal', function ( e ) {
        if ( 'Escape' === e.key && $( '#xen-return-confirm-modal' ).is( ':visible' ) ) { closeReturnModal(); }
    } );

    // Return button on item history table.
    $( document ).on( 'click', '.xen-item-log-return-btn', function () {
        var $btn      = $( this );
        var logId     = $btn.data( 'log-id' );
        var qty       = parseInt( $btn.data( 'qty' ) || 1, 10 );
        var $row      = $btn.closest( 'tr' );
        var itemTitle = $row.data( 'item-title' ) || '';
        openReturnModal( logId, qty, qty, itemTitle );
    } );

    // -----------------------------------------------------------------------
    // Borrow Log Edit Modal (item history + my borrow history)
    // Shared handlers for #xen-log-edit-modal present on page-item.php
    // and inventory-display.php.
    // -----------------------------------------------------------------------

    var $logEditModal  = null;
    var $logEditId     = null;
    var $logEditDue    = null;
    var $logEditRet    = null;
    var $logEditNotes  = null;
    var $logEditStatus = null;
    var $logEditBorr   = null;

    function initLogEditModal() {
        $logEditModal  = $( '#xen-log-edit-modal' );
        if ( ! $logEditModal.length ) return false;
        $logEditId     = $( '#xen-log-edit-id' );
        $logEditDue    = $( '#xen-log-edit-due' );
        $logEditRet    = $( '#xen-log-edit-returned' );
        $logEditNotes  = $( '#xen-log-edit-notes' );
        $logEditStatus = $( '#xen-log-edit-status' );
        $logEditBorr   = $( '#xen-log-edit-borrower' );
        return true;
    }

    function openLogEditModal( data ) {
        if ( ! $logEditModal || ! $logEditModal.length ) {
            if ( ! initLogEditModal() ) { return false; }
        }
        $logEditId.val( data.logId || '' );
        $logEditDue.val( data.dateDue ? data.dateDue.replace( ' ', 'T' ).substring( 0, 16 ) : '' );
        $logEditRet.val( data.dateRet ? data.dateRet.replace( ' ', 'T' ).substring( 0, 16 ) : '' );
        $logEditNotes.val( data.notes || '' );

        // Read-only info fields (new in updated modal markup).
        var $item     = $( '#xen-log-edit-item-title' );
        var $entity   = $( '#xen-log-edit-entity' );
        var $contact  = $( '#xen-log-edit-contact' );
        var $tags     = $( '#xen-log-edit-tags' );
        var $qty      = $( '#xen-log-edit-qty' );
        var $borrowed = $( '#xen-log-edit-borrowed' );
        if ( $item.length )     $item.text( data.itemTitle                    || '—' );
        if ( $entity.length )   $entity.text( data.borrowerFullName || data.borrower || '—' );
        if ( $contact.length )  $contact.text( data.borrowerContact           || '—' );
        if ( $tags.length )     $tags.text( data.borrowTags                   || '—' );
        if ( $qty.length )      $qty.text( data.qty ? String( data.qty )      : '—' );
        if ( $borrowed.length ) $borrowed.text( data.dateBorrowed              || '—' );

        // Condition and return notes — display + form fields.
        var $condDisplay   = $( '#xen-log-edit-condition-display' );
        var $rnDisplay     = $( '#xen-log-edit-return-notes-display' );
        var $condSelect    = $( '#xen-log-edit-condition' );
        var $retNotesInput = $( '#xen-log-edit-return-notes' );
        if ( $condDisplay.length )   $condDisplay.text( conditionLabel( data.itemCondition ) || '—' );
        if ( $rnDisplay.length )     $rnDisplay.text( data.returnNotes || '—' );
        if ( $condSelect.length )    $condSelect.val( data.itemCondition || '' );
        if ( $retNotesInput.length ) $retNotesInput.val( data.returnNotes || '' );

        $logEditStatus.text( '' ).removeClass( 'xen-log-edit-modal__status--ok xen-log-edit-modal__status--error' );
        $logEditModal.removeAttr( 'hidden' );
        $logEditDue.trigger( 'focus' );
        return true;
    }

    function closeLogEditModal() {
        if ( $logEditModal ) $logEditModal.attr( 'hidden', '' );
    }

    $( document )
        .on( 'click', '#xen-log-edit-close, #xen-log-edit-cancel', closeLogEditModal )
        .on( 'click', '.xen-log-edit-modal__overlay', closeLogEditModal )
        .on( 'keydown', function ( e ) {
            if ( 'Escape' === e.key ) closeLogEditModal();
        } );

    // Edit button on item history table.
    $( document ).on( 'click', '.xen-item-log-edit-btn', function () {
        var $row = $( this ).closest( 'tr' );
        openLogEditModal( {
            logId:      $row.data( 'log-id' ),
            dateDue:    $row.data( 'date-due' ),
            dateRet:    $row.data( 'date-returned' ),
            notes:      $row.data( 'notes' ),
            borrower:   $row.data( 'borrower' ),
            itemTitle:  '',
        } );
    } );

    // Return button on item history table — now handled above (openReturnModal).
    // (Old confirm-based handler removed in v1.6.1)

    // Double-click on My Borrow History row → open detail/edit modal.
    $( document ).on( 'dblclick', '.xen-my-history-row', function ( e ) {
        e.preventDefault();
        var $row = $( this );
        var d    = $row.data();
        var opened = openLogEditModal( {
            logId:            d.logId,
            dateDue:          d.dateDue,
            dateRet:          d.dateReturned,
            notes:            d.notes,
            returnNotes:      d.returnNotes,
            itemCondition:    d.itemCondition,
            borrower:         d.borrowerName,
            borrowerFullName: d.borrowerFullName,
            borrowerContact:  d.borrowerContact,
            borrowTags:       d.borrowTags,
            qty:              d.qty,
            dateBorrowed:     d.dateBorrowed,
            itemTitle:        d.itemTitle,
        } );
        // Non-admin users: edit modal doesn't render — fall back to read-only detail modal.
        if ( ! opened ) {
            showBorrowDetail( {
                itemTitle:        d.itemTitle,
                borrowerFullName: d.borrowerFullName,
                borrowerContact:  d.borrowerContact,
                borrowTags:       d.borrowTags,
                qty:              d.qty,
                dateBorrowed:     d.dateBorrowed,
                dateDue:          d.dateDue,
                dateReturned:     d.dateReturned,
                notes:            d.notes,
                returnNotes:      d.returnNotes,
                itemCondition:    d.itemCondition,
            } );
        }
    } );

    // Save handler for the log edit form.
    $( document ).on( 'submit', '#xen-log-edit-form', function ( e ) {
        e.preventDefault();
        if ( ! initLogEditModal() ) return;

        var $saveBtn = $( '#xen-log-edit-save' );
        $saveBtn.prop( 'disabled', true );
        $logEditStatus.text( '' ).removeClass( 'xen-log-edit-modal__status--ok xen-log-edit-modal__status--error' );

        $.post( xenInventory.ajaxUrl, {
            action:         'xen_update_borrow',
            nonce:          xenInventory.updateNonce,
            log_id:         $logEditId.val(),
            date_due:       $logEditDue.val(),
            date_returned:  $logEditRet.val(),
            notes:          $logEditNotes.val(),
            item_condition: $( '#xen-log-edit-condition' ).val() || '',
            return_notes:   $( '#xen-log-edit-return-notes' ).val() || '',
        } ).done( function ( resp ) {
            if ( resp.success ) {
                $logEditStatus.text( ( resp.data && resp.data.message ) || 'Saved.' )
                    .addClass( 'xen-log-edit-modal__status--ok' );
                setTimeout( function () {
                    closeLogEditModal();
                    location.reload();
                }, 900 );
            } else {
                $logEditStatus.text( ( resp.data && resp.data.message ) || 'Could not save.' )
                    .addClass( 'xen-log-edit-modal__status--error' );
            }
        } ).fail( function () {
            $logEditStatus.text( xenInventory.i18n.errorGeneric )
                .addClass( 'xen-log-edit-modal__status--error' );
        } ).always( function () {
            $saveBtn.prop( 'disabled', false );
        } );
    } );

    // -----------------------------------------------------------------------
    // "Return Now" button inside the log edit modal.
    // -----------------------------------------------------------------------

    $( document ).on( 'click', '.xen-log-edit-return-now', function () {
        var now = new Date();
        var pad = function ( n ) { return String( n ).padStart( 2, '0' ); };
        var val = now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() )
                + 'T' + pad( now.getHours() ) + ':' + pad( now.getMinutes() );
        $( '#xen-log-edit-returned' ).val( val );
    } );

    // -----------------------------------------------------------------------
    // Read-only borrow detail modal (for active borrows dblclick + non-admin
    // borrow history dblclick).
    // -----------------------------------------------------------------------

    function xenFrontEsc( str ) {
        return $( '<div>' ).text( String( str ) ).html();
    }

    function showBorrowDetail( d ) {
        var $modal = $( '#xen-active-detail-modal' );
        if ( ! $modal.length ) return;

        $( '#xen-active-detail-title' ).text( d.itemTitle || 'Borrow Details' );

        var fields = [
            [ 'Item',          d.itemTitle        ],
            [ 'Entity / Name', d.borrowerFullName ],
            [ 'Contact',       d.borrowerContact  ],
            [ 'Tags',          d.borrowTags       ],
            [ 'Quantity',      d.qty              ],
            [ 'Borrowed',      d.dateBorrowed     ],
            [ 'Due',           d.dateDue          ],
            [ 'Returned',      d.dateReturned     ],
            [ 'Notes',         d.notes            ],
            [ 'Condition',     d.itemCondition ? conditionLabel( d.itemCondition ) : '' ],
            [ 'Return Notes',  d.returnNotes      ],
        ];

        var html = '<table style="width:100%;border-collapse:collapse;font-size:.9rem;">';
        fields.forEach( function ( f ) {
            if ( ! f[1] ) return;
            html += '<tr style="border-bottom:1px solid #f0f0f0;">'
                  + '<th style="text-align:left;padding:.4rem .5rem .4rem 0;width:36%;font-weight:600;color:#555;vertical-align:top;">' + xenFrontEsc( f[0] ) + '</th>'
                  + '<td style="padding:.4rem 0;vertical-align:top;">' + xenFrontEsc( String( f[1] ) ) + '</td>'
                  + '</tr>';
        } );
        html += '</table>';

        $( '#xen-active-detail-body' ).html( html );
        $modal.css( 'display', 'flex' );
    }

    $( document ).on( 'click', '#xen-active-detail-close, #xen-active-detail-backdrop', function () {
        $( '#xen-active-detail-modal' ).css( 'display', 'none' );
    } );

    $( document ).on( 'keydown.xenActiveDetail', function ( e ) {
        if ( 'Escape' === e.key ) {
            $( '#xen-active-detail-modal' ).css( 'display', 'none' );
        }
    } );

    // -----------------------------------------------------------------------
    // My Active Borrows — double-click row for full details.
    // -----------------------------------------------------------------------

    $( document ).on( 'dblclick', '.xen-return-row', function ( e ) {
        // Ignore if the click landed on an interactive control inside the row.
        if ( $( e.target ).closest( 'button, input, label' ).length ) return;

        var d = $( this ).data();
        showBorrowDetail( {
            itemTitle:        d.itemTitle        || '',
            borrowerFullName: d.borrowerFullName || '',
            borrowerContact:  d.borrowerContact  || '',
            borrowTags:       d.borrowTags       || '',
            qty:              d.qty              || '',
            dateBorrowed:     d.dateBorrowed     || '',
            dateDue:          d.dateDue          || '',
            dateReturned:     '',
            notes:            d.notes            || '',
        } );
    } );

    // -----------------------------------------------------------------------
    // My Active Borrows — client-side filter + pagination.
    // -----------------------------------------------------------------------

    ( function () {
        var PER_PAGE     = 5;
        var $list        = $( '.xen-borrows-list' );
        if ( ! $list.length ) return;

        var $filter      = $( '#xen-borrows-filter' );
        var $pagination  = $( '#xen-borrows-pagination' );
        var $count       = $( '#xen-borrows-count' );
        var currentPage  = 1;

        function getMatching() {
            var q = $filter.val().toLowerCase().trim();
            return $list.find( '.xen-return-row' ).filter( function () {
                return ! q || ( $( this ).data( 'item-title' ) || '' ).toLowerCase().indexOf( q ) !== -1;
            } );
        }

        function render() {
            var $all  = $list.find( '.xen-return-row' );
            var $rows = getMatching();
            var total = $rows.length;

            $all.hide();
            $rows.slice( ( currentPage - 1 ) * PER_PAGE, currentPage * PER_PAGE ).show();

            if ( $count.length ) {
                $count.text( total ? ( total + ' item' + ( total !== 1 ? 's' : '' ) ) : 'No items found' );
            }

            if ( $pagination.length ) {
                $pagination.empty();
                var pages = Math.ceil( total / PER_PAGE );
                if ( pages > 1 ) {
                    for ( var i = 1; i <= pages; i++ ) {
                        ( function ( page ) {
                            $( '<button type="button" class="xen-btn xen-btn--ghost xen-borrows-page-btn">' )
                                .text( page )
                                .css( 'font-weight', page === currentPage ? '700' : '' )
                                .toggleClass( 'xen-btn--active', page === currentPage )
                                .on( 'click', function () { currentPage = page; render(); } )
                                .appendTo( $pagination );
                        } )( i );
                    }
                }
            }
        }

        $filter.on( 'input', function () { currentPage = 1; render(); } );
        render();
    } )();

    // -----------------------------------------------------------------------
    // Item detail page (.xen-item-log-row) — double-click to view/edit.
    // -----------------------------------------------------------------------

    $( document ).on( 'dblclick', '.xen-item-log-row', function ( e ) {
        e.preventDefault();
        var $row = $( this );
        var d    = $row.data();
        var opened = openLogEditModal( {
            logId:            d.logId,
            dateDue:          d.dateDue,
            dateRet:          d.dateReturned,
            notes:            d.notes,
            returnNotes:      d.returnNotes,
            itemCondition:    d.itemCondition,
            borrower:         d.borrower,
            borrowerFullName: d.borrowerFullName,
            borrowerContact:  d.borrowerContact,
            borrowTags:       d.borrowTags,
            qty:              d.qty,
            dateBorrowed:     d.dateBorrowed,
            itemTitle:        d.itemTitle,
        } );
        if ( ! opened ) {
            showBorrowDetail( {
                itemTitle:        d.itemTitle,
                borrowerFullName: d.borrowerFullName || d.borrower,
                borrowerContact:  d.borrowerContact,
                borrowTags:       d.borrowTags,
                qty:              d.qty,
                dateBorrowed:     d.dateBorrowed,
                dateDue:          d.dateDue,
                dateReturned:     d.dateReturned,
                notes:            d.notes,
                returnNotes:      d.returnNotes,
                itemCondition:    d.itemCondition,
            } );
        }
    } );

    // -----------------------------------------------------------------------
    // My Borrow History — client-side filter + pagination.
    // -----------------------------------------------------------------------

    ( function () {
        var PER_PAGE    = 10;
        var $rows       = $( '.xen-my-history-row' );
        if ( ! $rows.length ) return;

        var $filter     = $( '#xen-history-filter' );
        var $pagination = $( '#xen-history-pagination' );
        var $count      = $( '#xen-history-count' );
        var currentPage = 1;

        function getMatching() {
            var q = $filter.length ? $filter.val().toLowerCase().trim() : '';
            return $rows.filter( function () {
                var $r = $( this );
                return ! q
                    || ( $r.data( 'item-title' ) || '' ).toLowerCase().indexOf( q ) !== -1
                    || ( $r.data( 'borrow-tags' ) || '' ).toLowerCase().indexOf( q ) !== -1;
            } );
        }

        function render() {
            $rows.hide();
            var $matched = getMatching();
            var total    = $matched.length;
            $matched.slice( ( currentPage - 1 ) * PER_PAGE, currentPage * PER_PAGE ).show();

            if ( $count.length ) {
                $count.text( total ? ( total + ' record' + ( total !== 1 ? 's' : '' ) ) : 'No records found' );
            }

            if ( $pagination.length ) {
                $pagination.empty();
                var pages = Math.ceil( total / PER_PAGE );
                if ( pages > 1 ) {
                    for ( var i = 1; i <= pages; i++ ) {
                        ( function ( page ) {
                            $( '<button type="button" class="xen-btn xen-btn--ghost xen-borrows-page-btn">' )
                                .text( page )
                                .css( 'font-weight', page === currentPage ? '700' : '' )
                                .toggleClass( 'xen-btn--active', page === currentPage )
                                .on( 'click', function () { currentPage = page; render(); } )
                                .appendTo( $pagination );
                        } )( i );
                    }
                }
            }
        }

        if ( $filter.length ) { $filter.on( 'input', function () { currentPage = 1; render(); } ); }
        render();
    } )();

    // -----------------------------------------------------------------------
    // Item detail borrow history (.xen-item-log-row) — filter + pagination.
    // -----------------------------------------------------------------------

    ( function () {
        var PER_PAGE    = 10;
        var $rows       = $( '.xen-item-log-row' );
        if ( ! $rows.length ) return;

        var $filter     = $( '#xen-item-history-filter' );
        var $pagination = $( '#xen-item-history-pagination' );
        var $count      = $( '#xen-item-history-count' );
        var currentPage = 1;

        function getMatching() {
            var q = $filter.length ? $filter.val().toLowerCase().trim() : '';
            return $rows.filter( function () {
                var $r = $( this );
                return ! q
                    || ( $r.data( 'borrower' ) || '' ).toLowerCase().indexOf( q ) !== -1
                    || ( $r.data( 'borrower-full-name' ) || '' ).toLowerCase().indexOf( q ) !== -1
                    || ( $r.data( 'borrow-tags' ) || '' ).toLowerCase().indexOf( q ) !== -1;
            } );
        }

        function render() {
            $rows.hide();
            var $matched = getMatching();
            var total    = $matched.length;
            $matched.slice( ( currentPage - 1 ) * PER_PAGE, currentPage * PER_PAGE ).show();

            if ( $count.length ) {
                $count.text( total ? ( total + ' record' + ( total !== 1 ? 's' : '' ) ) : 'No records found' );
            }

            if ( $pagination.length ) {
                $pagination.empty();
                var pages = Math.ceil( total / PER_PAGE );
                if ( pages > 1 ) {
                    for ( var i = 1; i <= pages; i++ ) {
                        ( function ( page ) {
                            $( '<button type="button" class="xen-btn xen-btn--ghost xen-borrows-page-btn">' )
                                .text( page )
                                .css( 'font-weight', page === currentPage ? '700' : '' )
                                .toggleClass( 'xen-btn--active', page === currentPage )
                                .on( 'click', function () { currentPage = page; render(); } )
                                .appendTo( $pagination );
                        } )( i );
                    }
                }
            }
        }

        if ( $filter.length ) { $filter.on( 'input', function () { currentPage = 1; render(); } ); }
        render();
    } )();

    // -----------------------------------------------------------------------
    // Entity name autocomplete — Full Name / Entity field on borrow forms.
    // -----------------------------------------------------------------------

    /**
     * Attach entity-name autocomplete to a Full Name / Entity input.
     *
     * Wraps the input in a relative-positioned span, appends a dropdown list,
     * and wires input / blur / keyboard handlers. Requires xenInventory.entityNonce.
     *
     * @param {jQuery} $input  The text input to enhance.
     */
    function initEntityAutocomplete( $input ) {
        if ( ! $input.length ) { return; }

        // Wrap input so the dropdown can be absolutely positioned below it.
        $input.wrap( '<span class="xen-entity-suggest-wrap"></span>' );
        var $list = $( '<ul class="xen-entity-suggest-list" role="listbox" aria-label="Suggestions"></ul>' )
            .appendTo( $input.parent() );

        var timer;

        function hideSuggestions() {
            $list.empty().hide();
        }

        function showSuggestions( names ) {
            $list.empty();
            if ( ! names || ! names.length ) { $list.hide(); return; }
            names.forEach( function ( name ) {
                $( '<li role="option"></li>' ).text( name )
                    .on( 'mousedown', function ( e ) {
                        e.preventDefault(); // keep focus on input
                        $input.val( name );
                        hideSuggestions();
                    } )
                    .appendTo( $list );
            } );
            $list.show();
        }

        $input.on( 'input', function () {
            clearTimeout( timer );
            var term = $.trim( $( this ).val() );
            if ( term.length < 2 ) { hideSuggestions(); return; }
            timer = setTimeout( function () {
                if ( typeof xenInventory === 'undefined' || ! xenInventory.entityNonce ) { return; }
                $.post( xenInventory.ajaxUrl, {
                    action: 'xen_get_entity_suggestions',
                    nonce:  xenInventory.entityNonce,
                    term:   term,
                } ).done( function ( resp ) {
                    if ( resp.success ) { showSuggestions( resp.data ); }
                } );
            }, 280 );
        } );

        // Hide after blur — delayed so mousedown fires first.
        $input.on( 'blur', function () {
            setTimeout( hideSuggestions, 180 );
        } );

        // Keyboard navigation: ArrowDown / ArrowUp / Enter / Escape.
        $input.on( 'keydown', function ( e ) {
            var $items  = $list.find( 'li' );
            if ( ! $items.length ) { return; }
            var $active = $items.filter( '.xen-suggest-active' );
            var idx     = $items.index( $active );

            if ( 'ArrowDown' === e.key ) {
                e.preventDefault();
                $items.removeClass( 'xen-suggest-active' );
                $items.eq( idx < $items.length - 1 ? idx + 1 : 0 ).addClass( 'xen-suggest-active' );
            } else if ( 'ArrowUp' === e.key ) {
                e.preventDefault();
                $items.removeClass( 'xen-suggest-active' );
                $items.eq( idx > 0 ? idx - 1 : $items.length - 1 ).addClass( 'xen-suggest-active' );
            } else if ( 'Enter' === e.key && $active.length ) {
                e.preventDefault();
                $input.val( $active.text() );
                hideSuggestions();
            } else if ( 'Escape' === e.key ) {
                hideSuggestions();
            }
        } );
    }

    // Borrow modal on inventory display page and single item page.
    initEntityAutocomplete( $( '#xen-borrow-fullname' ) );
    // Standalone borrow page.
    initEntityAutocomplete( $( '#xen-bp-fullname' ) );

} )( jQuery );
