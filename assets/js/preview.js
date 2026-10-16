/**
 * b83 Floating Buttons – nur in der Live-Vorschau im Backend:
 * Links nicht öffnen (die Vorschau soll nicht wegnavigieren).
 */
( function () {
	'use strict';
	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( 'a[href]' ) : null;
		if ( link && ! link.hasAttribute( 'data-b83-fb-top' ) ) {
			event.preventDefault();
		}
	}, true );
}() );
