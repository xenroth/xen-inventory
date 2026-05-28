/**
 * XEN Inventory — FullCalendar Integration
 *
 * Initialises FullCalendar on #xen-fullcalendar and fetches borrow events
 * from the xen_get_calendar_events AJAX action.
 *
 * Depends on: FullCalendar (global), xenCalendar (wp_localize_script).
 */

/* global FullCalendar, xenCalendar */
( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        const el = document.getElementById( 'xen-fullcalendar' );

        if ( ! el || typeof FullCalendar === 'undefined' ) {
            return;
        }

        const popover      = document.getElementById( 'xen-event-popover' );
        const popClose     = document.getElementById( 'xen-popover-close' );
        const popItem      = document.getElementById( 'xen-pop-item' );
        const popAction    = document.getElementById( 'xen-pop-action' );
        const popQty       = document.getElementById( 'xen-pop-qty' );
        const popNotes     = document.getElementById( 'xen-pop-notes' );

        // ------------------------------------------------------------------
        // Helper: show/hide popover
        // ------------------------------------------------------------------

        function showPopover( info ) {
            const props = info.event.extendedProps;

            // Use the dedicated item_title prop; fall back to the full event title.
            popItem.textContent   = props.item_title || info.event.title;
            popAction.textContent = props.borrower   ? props.borrower + ' — ' + ( props.action || '' ) : ( props.action || '' );
            popQty.textContent    = props.quantity   || '';
            popNotes.textContent  = props.notes      || '—';

            // Position near the clicked element.
            const rect = info.el.getBoundingClientRect();
            popover.style.top  = ( rect.bottom + window.scrollY + 8 ) + 'px';
            popover.style.left = ( rect.left   + window.scrollX     ) + 'px';

            popover.removeAttribute( 'hidden' );
        }

        function hidePopover() {
            popover.setAttribute( 'hidden', '' );
        }

        if ( popClose ) {
            popClose.addEventListener( 'click', hidePopover );
        }

        document.addEventListener( 'keydown', function ( e ) {
            if ( 'Escape' === e.key ) hidePopover();
        } );

        // ------------------------------------------------------------------
        // Initialise FullCalendar
        // ------------------------------------------------------------------

        const calendar = new FullCalendar.Calendar( el, {
            initialView:  'dayGridMonth',
            locale:       xenCalendar.locale || 'en',
            firstDay:     parseInt( xenCalendar.firstDay, 10 ) || 0,
            headerToolbar: {
                left:   'prev,next today',
                center: 'title',
                right:  'dayGridMonth,listMonth',
            },
            events: function ( fetchInfo, successCallback, failureCallback ) {
                const params = new URLSearchParams( {
                    action: 'xen_get_calendar_events',
                    nonce:  xenCalendar.nonce,
                    start:  fetchInfo.startStr.split( 'T' )[ 0 ],
                    end:    fetchInfo.endStr.split( 'T' )[ 0 ],
                } );

                fetch( xenCalendar.ajaxUrl + '?' + params.toString() )
                    .then( function ( res ) { return res.json(); } )
                    .then( function ( data ) {
                        if ( data.success ) {
                            successCallback( data.data );
                        } else {
                            failureCallback( data );
                        }
                    } )
                    .catch( failureCallback );
            },
            eventClick: function ( info ) {
                info.jsEvent.preventDefault();
                showPopover( info );
            },
            // Close popover when navigating months.
            datesSet: hidePopover,
        } );

        calendar.render();
    } );
} )();
