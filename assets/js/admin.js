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
        if ( ! window.confirm( xenInventoryAdmin.i18n.confirmReturn ) ) {
            return;
        }

        const $btn  = $( this );
        const logId = $btn.data( 'log-id' );
        const orig  = $btn.text();

        $btn.prop( 'disabled', true ).text( xenInventoryAdmin.i18n.saving );

        $.post( xenInventoryAdmin.ajaxUrl, {
            action:  'xen_return_item',
            nonce:   xenInventoryAdmin.returnNonce,
            log_id:  logId,
            notes:   '',
        } )
        .done( function ( response ) {
            if ( response.success ) {
                const $row = $btn.closest( 'tr' );
                // Update the "Returned" cell (7th td, 0-indexed = index 6).
                $row.find( 'td:eq(6)' ).html(
                    '<span class="xen-badge xen-badge--returned">' + xenInventoryAdmin.i18n.returned + '</span>'
                );
                $btn.remove();
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
