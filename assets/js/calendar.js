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
        const popContact   = document.getElementById( 'xen-pop-contact' );
        const popTags      = document.getElementById( 'xen-pop-tags' );
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
        // Helper: show/hide event popover (single event click or day modal item click)
        // ------------------------------------------------------------------

        /**
         * Populate the popover with event data.
         * @param {object} props  - { item_title, borrower, borrower_contact, borrow_tags, action, quantity, notes }
         */
        function fillPopover( props ) {
            popItem.textContent   = props.item_title || '';
            popAction.textContent = props.borrower
                ? props.borrower + ( props.action ? ' — ' + props.action : '' )
                : ( props.action || '' );
            if ( popContact ) popContact.textContent = props.borrower_contact || '—';
            if ( popTags )    popTags.textContent    = props.borrow_tags      || '—';
            popQty.textContent    = props.quantity || '';
            popNotes.textContent  = props.notes    || '—';
        }

        /**
         * Position the popover near a viewport-relative coordinate (clientX/Y)
         * and ensure it stays within the visible viewport.
         * @param {number} clientX
         * @param {number} clientY
         */
        function positionPopover( clientX, clientY ) {
            // Show off-screen first to measure its real dimensions.
            popover.style.visibility = 'hidden';
            popover.removeAttribute( 'hidden' );

            var popW = popover.offsetWidth  || 240;
            var popH = popover.offsetHeight || 150;
            var winW = window.innerWidth;
            var winH = window.innerHeight;

            var left = clientX + 14;
            var top  = clientY + 14;

            // Clamp so the popover never escapes the viewport.
            if ( left + popW > winW - 10 ) { left = clientX - popW - 14; }
            if ( top  + popH > winH - 10 ) { top  = clientY - popH - 14; }
            if ( left < 8 ) { left = 8; }
            if ( top  < 8 ) { top  = 8; }

            popover.style.left       = left + 'px';
            popover.style.top        = top  + 'px';
            popover.style.visibility = '';
        }

        function showPopover( info ) {
            fillPopover( info.event.extendedProps );
            positionPopover( info.jsEvent.clientX, info.jsEvent.clientY );
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

                html += '<li class="xen-day-modal__item xen-day-modal__item--' + statusCls + '"'
                    + ' data-log-id="'      + escHtml( logId ) + '"'
                    + ' data-item-title="'  + escHtml( props.item_title || ev.title ) + '"'
                    + ' data-borrower="'    + escHtml( props.borrower || '' ) + '"'
                    + ' data-contact="'     + escHtml( props.borrower_contact || '' ) + '"'
                    + ' data-tags="'        + escHtml( props.borrow_tags || '' ) + '"'
                    + ' data-action="'      + escHtml( props.action || '' ) + '"'
                    + ' data-qty="'         + escHtml( props.quantity || 1 ) + '"'
                    + ' data-notes="'       + escHtml( props.notes || '' ) + '"'
                    + ' style="cursor:pointer" title="Click to view details"'
                    + '>';
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

        // Clicking a day modal list item (not its action buttons) shows the popover for that item.
        if ( dayModal ) {
            dayModal.addEventListener( 'click', function ( e ) {
                // Skip if the click landed on a button inside the item.
                if ( e.target.closest( 'button' ) ) return;

                var item = e.target.closest( '.xen-day-modal__item' );
                if ( ! item ) return;

                var props = {
                    item_title:       item.dataset.itemTitle || '',
                    borrower:         item.dataset.borrower  || '',
                    borrower_contact: item.dataset.contact   || '',
                    borrow_tags:      item.dataset.tags      || '',
                    action:           item.dataset.action    || '',
                    quantity:         item.dataset.qty       || '',
                    notes:            item.dataset.notes     || '',
                };
                fillPopover( props );
                positionPopover( e.clientX, e.clientY );
                // Keep day modal open; popover appears beside/above the item.
            } );
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
        var editContact      = document.getElementById( 'xen-edit-contact' );
        var editTags         = document.getElementById( 'xen-edit-tags' );
        var editDateDue      = document.getElementById( 'xen-edit-date-due' );
        var editDateReturned = document.getElementById( 'xen-edit-date-returned' );
        var editNotes        = document.getElementById( 'xen-edit-notes' );
        var editStatus       = document.getElementById( 'xen-edit-modal-status' );
        var editForm         = document.getElementById( 'xen-edit-borrow-form' );
        var editModalClose   = document.getElementById( 'xen-edit-modal-close' );
        var editCancelBtn    = document.getElementById( 'xen-edit-cancel-btn' );
        var returnNowBtn     = document.getElementById( 'xen-return-now-btn' );
        var editModalBackdrop = editModal ? editModal.querySelector( '.xen-edit-modal__backdrop' ) : null;

        /**
         * Convert a DB/AJAX datetime string (e.g. "2024-01-15 14:30:00" or "2024-01-15")
         * to the YYYY-MM-DDTHH:MM format required by datetime-local inputs.
         */
        function toDatetimeLocalVal( str ) {
            if ( ! str ) return '';
            return str.replace( ' ', 'T' ).substring( 0, 16 );
        }

        function openEditModal( event ) {
            if ( ! editModal ) return;
            var props = event.extendedProps || {};

            editLogId.value          = event.id || props.log_id || '';
            editItemName.textContent = props.item_title || event.title || '';
            editBorrower.textContent = props.borrower   || '';
            if ( editContact ) editContact.textContent = props.borrower_contact || '—';
            if ( editTags )    editTags.textContent    = props.borrow_tags      || '—';
            editDateDue.value        = toDatetimeLocalVal( props.date_due      || '' );
            editDateReturned.value   = toDatetimeLocalVal( props.date_returned || '' );
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

        // "Return Now" — stamps the current local datetime into the returned field.
        if ( returnNowBtn ) {
            returnNowBtn.addEventListener( 'click', function () {
                var now  = new Date();
                var pad  = function ( n ) { return String( n ).padStart( 2, '0' ); };
                editDateReturned.value =
                    now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() ) +
                    'T' + pad( now.getHours() ) + ':' + pad( now.getMinutes() );
            } );
        }

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
