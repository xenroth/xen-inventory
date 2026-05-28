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
            if ( ! initLogEditModal() ) return;
        }
        $logEditId.val( data.logId || '' );
        $logEditDue.val( data.dateDue    ? data.dateDue.replace( ' ', 'T' ).substring( 0, 16 ) : '' );
        $logEditRet.val( data.dateRet    ? data.dateRet.substring( 0, 10 )                      : '' );
        $logEditNotes.val( data.notes || '' );
        $logEditBorr.text( data.borrower || data.itemTitle || '' );
        $logEditStatus.text( '' ).removeClass( 'xen-log-edit-modal__status--ok xen-log-edit-modal__status--error' );
        $logEditModal.removeAttr( 'hidden' );
        $logEditDue.trigger( 'focus' );
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
    var _dblClickTimer = null;
    $( document ).on( 'dblclick', '.xen-my-history-row', function ( e ) {
        e.preventDefault();
        var $row = $( this );
        openLogEditModal( {
            logId:     $row.data( 'log-id' ),
            dateDue:   $row.data( 'date-due' ),
            dateRet:   $row.data( 'date-returned' ),
            notes:     $row.data( 'notes' ),
            borrower:  '',
            itemTitle: $row.data( 'item-title' ),
        } );
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

} )( jQuery );
