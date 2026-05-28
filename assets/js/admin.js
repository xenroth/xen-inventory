/**
 * XEN Inventory — Admin JavaScript
 *
 * Handles minor admin-side interactions:
 *  - Confirm + AJAX delete of borrow log rows.
 *
 * Depends on: jQuery, xenInventoryAdmin (wp_localize_script).
 */

/* global xenInventoryAdmin, jQuery */
( function ( $ ) {
    'use strict';

    // Delete a log entry via AJAX.
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

} )( jQuery );
