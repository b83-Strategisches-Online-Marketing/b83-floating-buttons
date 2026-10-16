/**
 * b83 Floating Buttons – Frontend
 *
 * Maus: Ausklappen erledigt CSS (:hover mit Verzögerung).
 * Touch-Geräte (kein Hover): erstes Antippen klappt aus und zeigt den
 * Info-Text, zweites Antippen öffnet den Link. Antippen daneben oder Esc
 * schließt. In der kompakten Mobil-Ansicht und der Unten-Leiste öffnet
 * schon das erste Antippen den Link.
 */
( function () {
	'use strict';

	var noHover = window.matchMedia( '(hover: none)' );
	var mobile = window.matchMedia( '(max-width: 767px)' );
	var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	function closeAll( except ) {
		var open = document.querySelectorAll( '.b83-fb__item.is-open' );
		for ( var i = 0; i < open.length; i++ ) {
			if ( open[ i ] !== except ) {
				open[ i ].classList.remove( 'is-open' );
			}
		}
	}

	/** Hat der Button etwas zum Ausklappen (Text oder Info-Text)? */
	function expandable( item, nav ) {
		if ( mobile.matches && nav && ( 'compact' === nav.getAttribute( 'data-mobile' ) || 'bar' === nav.getAttribute( 'data-mobile' ) ) ) {
			return false;
		}
		var tip = nav && nav.classList.contains( 'b83-fb--no-tips' ) ? null : item.querySelector( '.b83-fb__tip' );
		return !! ( item.querySelector( '.b83-fb__label' ) || tip );
	}

	document.addEventListener( 'click', function ( event ) {
		var btn = event.target.closest ? event.target.closest( '.b83-fb__btn' ) : null;
		var item = btn ? btn.parentNode : null;
		closeAll( item );
		if ( ! btn ) {
			return;
		}

		var nav = btn.closest( '.b83-fb' );

		if ( noHover.matches && expandable( item, nav ) && ! item.classList.contains( 'is-open' ) ) {
			event.preventDefault();
			item.classList.add( 'is-open' );
			return;
		}

		if ( btn.hasAttribute( 'data-b83-fb-top' ) ) {
			event.preventDefault();
			window.scrollTo( { top: 0, behavior: reduce.matches ? 'auto' : 'smooth' } );
			btn.blur();
			item.classList.remove( 'is-open' );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			closeAll( null );
			var active = document.activeElement;
			if ( active && active.closest && active.closest( '.b83-fb' ) ) {
				active.blur();
			}
		}
		// Span-Buttons ohne Link (nur Info) per Tastatur ausklappen.
		if ( ( 'Enter' === event.key || ' ' === event.key ) && event.target.matches && event.target.matches( 'span.b83-fb__btn' ) ) {
			event.preventDefault();
			event.target.parentNode.classList.toggle( 'is-open' );
		}
	} );
}() );
