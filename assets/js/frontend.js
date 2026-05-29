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
    // Return Item AJAX
    // -----------------------------------------------------------------------

    $( document ).on( 'click', '.xen-return-btn', function () {
        const $btn      = $( this );
        const logId     = $btn.data( 'log-id' );
        const $row      = $btn.closest( '.xen-return-row' );
        const $notes    = $row.find( '.xen-return-notes' );
        const $qtyInput = $row.find( '.xen-return-qty' );
        const totalQty  = parseInt( $btn.data( 'qty' ) || 1, 10 );
        const qtyReturn = parseInt( $qtyInput.val() || totalQty, 10 );
        const isPartial = qtyReturn > 0 && qtyReturn < totalQty;

        if ( isPartial ) {
            var partialMsg = ( xenInventory.i18n.confirmPartialReturn || 'Return %d item(s)? The rest will remain as borrowed.' )
                .replace( '%d', qtyReturn );
            if ( ! window.confirm( partialMsg ) ) {
                return;
            }
        } else {
            if ( ! window.confirm( xenInventory.i18n.confirm ) ) {
                return;
            }
        }

        $btn.prop( 'disabled', true );

        $.post( xenInventory.ajaxUrl, {
            action:       'xen_return_item',
            nonce:        xenInventory.returnNonce,
            log_id:       logId,
            qty_returned: qtyReturn,
            notes:        $notes.val ? $notes.val() : '',
        } )
            .done( function ( response ) {
                if ( response.success ) {
                    if ( isPartial ) {
                        // Update the displayed quantity and re-enable the button.
                        const remaining = totalQty - qtyReturn;
                        $btn.data( 'qty', remaining );
                        $qtyInput.attr( 'max', remaining ).val( remaining );
                        $row.find( '.xen-return-row__qty' ).text(
                            xenInventory.i18n.qtyBorrowed
                                ? xenInventory.i18n.qtyBorrowed.replace( '%d', remaining )
                                : ( 'Qty borrowed: ' + remaining )
                        );
                        $notes.val( '' );
                        $btn.prop( 'disabled', false );
                    } else {
                        // Full return — remove the row.
                        $row.fadeOut( 300, function () { $( this ).remove(); } );

                        const $card = $btn.closest( '.xen-item-card' );
                        if ( $card.length ) {
                            $card.find( '.xen-status-badge' )
                                .removeClass( 'xen-status-badge--borrowed' )
                                .addClass( 'xen-status-badge--available' )
                                .text( xenInventory.i18n.available || 'Available' );
                        }
                    }
                } else {
                    alert( response.data.message || xenInventory.i18n.errorGeneric );
                    $btn.prop( 'disabled', false );
                }
            } )
            .fail( function () {
                alert( xenInventory.i18n.errorGeneric );
                $btn.prop( 'disabled', false );
            } );
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

    // Return button on item history table.
    $( document ).on( 'click', '.xen-item-log-return-btn', function () {
        var $btn   = $( this );
        var logId  = $btn.data( 'log-id' );
        var qty    = parseInt( $btn.data( 'qty' ) || 1, 10 );
        var answer = qty > 1
            ? window.prompt( 'How many are being returned? (1 – ' + qty + ')', qty )
            : ( window.confirm( 'Mark this item as returned?' ) ? qty : null );
        if ( null === answer ) return;
        var qtyRet = parseInt( answer, 10 );
        if ( isNaN( qtyRet ) || qtyRet < 1 || qtyRet > qty ) {
            alert( 'Please enter a number between 1 and ' + qty + '.' );
            return;
        }
        $btn.prop( 'disabled', true ).text( '…' );
        $.post( xenInventory.ajaxUrl, {
            action:       'xen_return_item',
            nonce:        xenInventory.returnNonce,
            log_id:       logId,
            qty_returned: qtyRet,
            notes:        '',
        } ).done( function ( resp ) {
            if ( resp.success ) {
                // Reload to reflect updated status.
                location.reload();
            } else {
                alert( ( resp.data && resp.data.message ) || 'Error.' );
                $btn.prop( 'disabled', false ).text( 'Return' );
            }
        } ).fail( function () {
            alert( xenInventory.i18n.errorGeneric );
            $btn.prop( 'disabled', false ).text( 'Return' );
        } );
    } );

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
            action:        'xen_update_borrow',
            nonce:         xenInventory.updateNonce,
            log_id:        $logEditId.val(),
            date_due:      $logEditDue.val(),
            date_returned: $logEditRet.val(),
            notes:         $logEditNotes.val(),
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

} )( jQuery );
