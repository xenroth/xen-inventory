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
                    $message
                        .addClass( 'xen-form__message--error' )
                        .text( response.data.message || xenInventory.i18n.errorGeneric );
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

} )( jQuery );
