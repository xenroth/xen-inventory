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

        const dayModal     = document.getElementById( 'xen-day-modal' );
        const dayModalBody = document.getElementById( 'xen-day-modal-body' );
        const dayModalTitle = document.getElementById( 'xen-day-modal-title' );
        const dayModalClose = document.getElementById( 'xen-day-modal-close' );
        const dayModalBackdrop = dayModal ? dayModal.querySelector( '.xen-day-modal__backdrop' ) : null;

        // ------------------------------------------------------------------
        // Utility: safe HTML escape
        // ------------------------------------------------------------------

        function escHtml( str ) {
            var div = document.createElement( 'div' );
            div.appendChild( document.createTextNode( String( str || '' ) ) );
            return div.innerHTML;
        }

        // ------------------------------------------------------------------
        // Helper: show/hide event popover (single event click)
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
            if ( 'Escape' === e.key ) {
                hidePopover();
                hideDayModal();
            }
        } );

        // ------------------------------------------------------------------
        // Helper: day detail modal (date cell click)
        // ------------------------------------------------------------------

        function showDayModal( dateStr, events ) {
            if ( ! dayModal ) return;

            // Format the date heading using the locale supplied by WordPress.
            var date = new Date( dateStr + 'T12:00:00' );
            var locale = ( xenCalendar.locale || 'en' ).replace( '_', '-' );
            dayModalTitle.textContent = date.toLocaleDateString( locale, {
                weekday: 'long',
                year:    'numeric',
                month:   'long',
                day:     'numeric',
            } );

            if ( ! events || events.length === 0 ) {
                dayModalBody.innerHTML = '<p class="xen-day-modal__empty">No borrows recorded for this day.</p>';
            } else {
                var html = '<ul class="xen-day-modal__list">';
                events.forEach( function ( ev ) {
                    var props     = ev.extendedProps || {};
                    var isReturned = !! props.date_returned;
                    var statusCls  = isReturned ? 'returned' : 'borrowed';

                    // Date range label.
                    var startLabel = ev.start
                        ? ev.start.toLocaleDateString( locale, { month: 'short', day: 'numeric' } )
                        : '';
                    var endLabel   = ev.end && ! isReturned
                        ? ev.end.toLocaleDateString( locale, { month: 'short', day: 'numeric' } )
                        : '';
                    var rangeLabel = startLabel + ( endLabel && endLabel !== startLabel ? ' – ' + endLabel : '' );

                    html += '<li class="xen-day-modal__item xen-day-modal__item--' + statusCls + '">';
                    html +=   '<span class="xen-day-modal__dot"></span>';
                    html +=   '<div class="xen-day-modal__item-body">';
                    html +=     '<strong class="xen-day-modal__item-name">' + escHtml( props.item_title || ev.title ) + '</strong>';
                    if ( props.borrower ) {
                        html += '<span class="xen-day-modal__item-borrower">' + escHtml( props.borrower ) + '</span>';
                    }
                    html +=     '<div class="xen-day-modal__item-meta">';
                    html +=       '<span class="xen-day-modal__item-qty">×' + escHtml( props.quantity || 1 ) + '</span>';
                    if ( rangeLabel ) {
                        html +=   '<span class="xen-day-modal__item-range">' + escHtml( rangeLabel ) + '</span>';
                    }
                    html +=       '<span class="xen-day-modal__item-status xen-day-modal__item-status--' + statusCls + '">'
                                    + escHtml( isReturned ? 'Returned' : 'Active' ) + '</span>';
                    html +=     '</div>';
                    if ( props.notes ) {
                        html += '<p class="xen-day-modal__item-notes">' + escHtml( props.notes ) + '</p>';
                    }
                    html +=   '</div>';
                    html += '</li>';
                } );
                html += '</ul>';
                dayModalBody.innerHTML = html;
            }

            hidePopover();
            dayModal.removeAttribute( 'hidden' );
            if ( dayModalClose ) dayModalClose.focus();
        }

        function hideDayModal() {
            if ( dayModal ) dayModal.setAttribute( 'hidden', '' );
        }

        if ( dayModalClose ) {
            dayModalClose.addEventListener( 'click', hideDayModal );
        }
        if ( dayModalBackdrop ) {
            dayModalBackdrop.addEventListener( 'click', hideDayModal );
        }

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
                hideDayModal(); // close day modal if open
                showPopover( info );
            },
            dateClick: function ( info ) {
                hidePopover(); // close event popover if open

                // Collect all loaded events that overlap the clicked day.
                var dayStart = new Date( info.dateStr + 'T00:00:00' );
                var dayEnd   = new Date( info.dateStr + 'T23:59:59' );

                var dayEvents = calendar.getEvents().filter( function ( ev ) {
                    var evStart = ev.start;
                    var evEnd   = ev.end || ev.start;
                    return evStart <= dayEnd && evEnd >= dayStart;
                } );

                showDayModal( info.dateStr, dayEvents );
            },
            // Close overlays when navigating months.
            datesSet: function () {
                hidePopover();
                hideDayModal();
            },
        } );

        calendar.render();
    } );
} )();
