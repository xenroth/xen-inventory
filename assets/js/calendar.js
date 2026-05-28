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

            // Position near the mouse click location.
            var mouseX = ( info.jsEvent.pageX || 0 );
            var mouseY = ( info.jsEvent.pageY || 0 );
            popover.style.top  = ( mouseY + 12 ) + 'px';
            popover.style.left = ( mouseX + 8  ) + 'px';

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
                    var logId      = props.log_id || ev.id || '';

                    // Date range label.
                    var startLabel = ev.start
                        ? ev.start.toLocaleDateString( locale, { month: 'short', day: 'numeric' } )
                        : '';

                    html += '<li class="xen-day-modal__item xen-day-modal__item--' + statusCls + '" data-log-id="' + escHtml( logId ) + '">';
                    html +=   '<span class="xen-day-modal__dot"></span>';
                    html +=   '<div class="xen-day-modal__item-body">';
                    html +=     '<strong class="xen-day-modal__item-name">' + escHtml( props.item_title || ev.title ) + '</strong>';
                    if ( props.borrower ) {
                        html += '<span class="xen-day-modal__item-borrower">' + escHtml( props.borrower ) + '</span>';
                    }
                    html +=     '<div class="xen-day-modal__item-meta">';
                    html +=       '<span class="xen-day-modal__item-qty">×' + escHtml( props.quantity || 1 ) + '</span>';
                    if ( startLabel ) {
                        html +=   '<span class="xen-day-modal__item-range">' + escHtml( startLabel ) + '</span>';
                    }
                    html +=       '<span class="xen-day-modal__item-status xen-day-modal__item-status--' + statusCls + '">'
                                    + escHtml( isReturned ? 'Returned' : 'Active' ) + '</span>';
                    html +=     '</div>';
                    if ( props.notes ) {
                        html += '<p class="xen-day-modal__item-notes">' + escHtml( props.notes ) + '</p>';
                    }
                    // Quick action buttons for admins.
                    if ( parseInt( xenCalendar.canEdit, 10 ) ) {
                        html += '<div class="xen-day-modal__item-actions">';
                        html +=   '<button type="button" class="xen-cal-edit-btn" data-log-id="' + escHtml( logId ) + '">Edit</button>';
                        if ( ! isReturned ) {
                            html += '<button type="button" class="xen-cal-return-btn" data-log-id="' + escHtml( logId ) + '" data-qty="' + escHtml( props.quantity || 1 ) + '">Return</button>';
                        }
                        html += '</div>';
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
        // Day modal quick actions — Edit and Return buttons.
        // ------------------------------------------------------------------

        if ( dayModal ) {
            dayModal.addEventListener( 'click', function ( e ) {
                var editBtn   = e.target.closest( '.xen-cal-edit-btn' );
                var returnBtn = e.target.closest( '.xen-cal-return-btn' );

                if ( editBtn ) {
                    var logId = editBtn.getAttribute( 'data-log-id' );
                    // Find the matching loaded calendar event by log_id.
                    var matchEvent = null;
                    calendar.getEvents().forEach( function ( ev ) {
                        if ( String( ev.extendedProps.log_id ) === String( logId ) || String( ev.id ) === String( logId ) ) {
                            matchEvent = ev;
                        }
                    } );
                    if ( matchEvent ) {
                        openEditModal( matchEvent );
                    }
                }

                if ( returnBtn ) {
                    var logId  = returnBtn.getAttribute( 'data-log-id' );
                    var qty    = parseInt( returnBtn.getAttribute( 'data-qty' ) || 1, 10 );
                    var answer = qty > 1
                        ? window.prompt( 'How many items are being returned? (1 – ' + qty + ')', qty )
                        : ( window.confirm( 'Mark this item as returned?' ) ? qty : null );

                    if ( answer === null ) return;
                    var qtyReturned = parseInt( answer, 10 );
                    if ( isNaN( qtyReturned ) || qtyReturned < 1 || qtyReturned > qty ) {
                        alert( 'Please enter a number between 1 and ' + qty + '.' );
                        return;
                    }

                    returnBtn.disabled = true;
                    returnBtn.textContent = 'Saving…';

                    var body = new URLSearchParams( {
                        action:       'xen_return_item',
                        nonce:        xenCalendar.returnNonce,
                        log_id:       logId,
                        qty_returned: qtyReturned,
                        notes:        '',
                    } );

                    fetch( xenCalendar.ajaxUrl, { method: 'POST', body: body } )
                        .then( function ( res ) { return res.json(); } )
                        .then( function ( data ) {
                            if ( data.success ) {
                                calendar.refetchEvents();
                                hideDayModal();
                            } else {
                                var msg = data.data && data.data.message ? data.data.message : 'Error.';
                                alert( msg );
                                returnBtn.disabled = false;
                                returnBtn.textContent = 'Return';
                            }
                        } )
                        .catch( function () {
                            alert( 'Network error. Please try again.' );
                            returnBtn.disabled = false;
                            returnBtn.textContent = 'Return';
                        } );
                }
            } );
        }

        // ------------------------------------------------------------------
        // Helper: edit borrow modal (double-click on calendar event)
        // Only rendered / wired if the current user has edit capability.
        // ------------------------------------------------------------------

        var editModal        = document.getElementById( 'xen-edit-borrow-modal' );
        var editLogId        = document.getElementById( 'xen-edit-log-id' );
        var editItemName     = document.getElementById( 'xen-edit-item-name' );
        var editBorrower     = document.getElementById( 'xen-edit-borrower' );
        var editDateDue      = document.getElementById( 'xen-edit-date-due' );
        var editDateReturned = document.getElementById( 'xen-edit-date-returned' );
        var editNotes        = document.getElementById( 'xen-edit-notes' );
        var editStatus       = document.getElementById( 'xen-edit-modal-status' );
        var editForm         = document.getElementById( 'xen-edit-borrow-form' );
        var editModalClose   = document.getElementById( 'xen-edit-modal-close' );
        var editCancelBtn    = document.getElementById( 'xen-edit-cancel-btn' );
        var editModalBackdrop = editModal ? editModal.querySelector( '.xen-edit-modal__backdrop' ) : null;

        function openEditModal( event ) {
            if ( ! editModal ) return;
            var props = event.extendedProps || {};

            editLogId.value        = event.id || props.log_id || '';
            editItemName.textContent = props.item_title || event.title || '';
            editBorrower.textContent = props.borrower   || '';
            // Preserve time if present; datetime-local input expects "YYYY-MM-DDTHH:MM".
            editDateDue.value        = props.date_due      ? props.date_due.replace( ' ', 'T' ).substring( 0, 16 )      : '';
            editDateReturned.value   = props.date_returned ? props.date_returned.replace( ' ', 'T' ).substring( 0, 16 ) : '';
            editNotes.value          = props.notes || '';
            editStatus.textContent   = '';
            editStatus.className     = 'xen-edit-modal__status';

            hidePopover();
            hideDayModal();
            editModal.removeAttribute( 'hidden' );
            if ( editModalClose ) editModalClose.focus();
        }

        function closeEditModal() {
            if ( editModal ) editModal.setAttribute( 'hidden', '' );
        }

        if ( editModalClose ) editModalClose.addEventListener( 'click', closeEditModal );
        if ( editCancelBtn )  editCancelBtn.addEventListener(  'click', closeEditModal );
        if ( editModalBackdrop ) editModalBackdrop.addEventListener( 'click', closeEditModal );

        document.addEventListener( 'keydown', function ( e ) {
            if ( 'Escape' === e.key ) closeEditModal();
        } );

        if ( editForm && parseInt( xenCalendar.canEdit, 10 ) ) {
            editForm.addEventListener( 'submit', function ( e ) {
                e.preventDefault();

                var saveBtn = document.getElementById( 'xen-edit-save-btn' );
                if ( saveBtn ) saveBtn.disabled = true;
                editStatus.textContent = '';

                var body = new URLSearchParams( {
                    action:         'xen_update_borrow',
                    nonce:          xenCalendar.updateNonce,
                    log_id:         editLogId.value,
                    notes:          editNotes.value,
                    date_due:       editDateDue.value,
                    date_returned:  editDateReturned.value,
                } );

                fetch( xenCalendar.ajaxUrl, { method: 'POST', body: body } )
                    .then( function ( res ) { return res.json(); } )
                    .then( function ( data ) {
                        if ( data.success ) {
                            editStatus.textContent = data.data && data.data.message ? data.data.message : 'Saved.';
                            editStatus.className   = 'xen-edit-modal__status xen-edit-modal__status--ok';
                            calendar.refetchEvents();
                            setTimeout( closeEditModal, 900 );
                        } else {
                            var msg = data.data && data.data.message ? data.data.message : 'Could not save.';
                            editStatus.textContent = msg;
                            editStatus.className   = 'xen-edit-modal__status xen-edit-modal__status--error';
                        }
                    } )
                    .catch( function () {
                        editStatus.textContent = 'Network error. Please try again.';
                        editStatus.className   = 'xen-edit-modal__status xen-edit-modal__status--error';
                    } )
                    .finally( function () {
                        if ( saveBtn ) saveBtn.disabled = false;
                    } );
            } );
        }

        // ------------------------------------------------------------------
        // Initialise FullCalendar
        // ------------------------------------------------------------------

        const calendar = new FullCalendar.Calendar( el, {
            initialView:  'dayGridMonth',
            locale:       xenCalendar.locale || 'en',
            firstDay:     parseInt( xenCalendar.firstDay, 10 ) || 0,
            // height: 'auto' prevents FullCalendar from creating internal scroll
            // containers that would overlap the grid columns with a scrollbar.
            // The outer .xen-calendar-scroller wrapper handles horizontal overflow.
            height:       'auto',
            // Expand rows to fill the available height so all days are visible.
            expandRows:   false,
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

                // Double-click detection: two clicks on the same event within 400 ms.
                var now      = Date.now();
                var clickedId = info.event.id;

                if (
                    parseInt( xenCalendar.canEdit, 10 ) &&
                    window._xenLastClickId  === clickedId &&
                    now - ( window._xenLastClickTs || 0 ) < 400
                ) {
                    // Second click — treat as double-click → open edit modal.
                    window._xenLastClickId = null;
                    window._xenLastClickTs = 0;
                    openEditModal( info.event );
                    return;
                }

                window._xenLastClickId = clickedId;
                window._xenLastClickTs = now;

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
