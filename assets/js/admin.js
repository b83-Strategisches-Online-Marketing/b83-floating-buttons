/**
 * b83 Floating Buttons – Backend
 *
 * Editor: Buttons hinzufügen, sortieren, duplizieren, entfernen; Icon-Auswahl,
 * Mediathek, Farbwähler und Live-Vorschau (iframe mit derselben Ausgabe wie
 * im Frontend, per admin-ajax gerendert).
 */
/* global jQuery, wp, b83FbAdmin */
( function ( $ ) {
	'use strict';

	var cfg = window.b83FbAdmin || {};
	var i18n = cfg.i18n || {};

	/* -----------------------------------------------------------------
	 * Übersicht: Löschen bestätigen (gilt auf allen Seiten des Plugins)
	 * --------------------------------------------------------------- */
	$( document ).on( 'submit', 'form[data-confirm]', function ( event ) {
		if ( ! window.confirm( $( this ).attr( 'data-confirm' ) ) ) {
			event.preventDefault();
		}
	} );

	var $form = $( '#b83-fb-form' );
	if ( ! $form.length ) {
		return;
	}

	var $items = $( '#b83-fb-items' );
	var dirty = false;
	var tempIndex = 0;

	/* -----------------------------------------------------------------
	 * Hilfen
	 * --------------------------------------------------------------- */

	/** Index im Feldnamen setzen: group[items][x][…] → group[items][n][…] */
	function setIndex( $scope, index ) {
		$scope.find( '[name^="group[items]["]' ).each( function () {
			this.name = this.name.replace( /^group\[items\]\[[^\]]*\]/, 'group[items][' + index + ']' );
		} );
		$scope.find( '[id*="__INDEX__"]' ).each( function () {
			this.id = this.id.replace( '__INDEX__', String( index ) );
		} );
		$scope.find( 'label[for*="__INDEX__"]' ).each( function () {
			this.htmlFor = this.htmlFor.replace( '__INDEX__', String( index ) );
		} );
	}

	function renumber() {
		$items.children( '.b83-fb-item' ).each( function ( i ) {
			setIndex( $( this ), i );
		} );
		$( '#b83-fb-add' ).prop( 'disabled', $items.children().length >= ( cfg.maxItems || 20 ) );
	}

	function markDirty() {
		dirty = true;
		schedulePreview();
	}

	/* -----------------------------------------------------------------
	 * Farbwähler
	 * --------------------------------------------------------------- */
	function initColors( $scope ) {
		$scope.find( 'input.b83-fb-color' ).each( function () {
			var $input = $( this );
			if ( $input.closest( '.wp-picker-container' ).length ) {
				return;
			}
			$input.wpColorPicker( {
				change: function () {
					window.setTimeout( markDirty, 0 );
				},
				clear: function () {
					window.setTimeout( markDirty, 0 );
				}
			} );
		} );
	}

	/** Farbwähler einer Kopie durch einfache Felder ersetzen (vor dem Neu-Initialisieren). */
	function resetColors( $scope ) {
		$scope.find( '.b83-fb-color-wrap' ).each( function () {
			var $wrap = $( this );
			var $old = $wrap.find( 'input.b83-fb-color' ).first();
			var $new = $( '<input type="text" class="b83-fb-color">' )
				.attr( 'name', $old.attr( 'name' ) )
				.attr( 'aria-label', $old.attr( 'aria-label' ) || '' )
				.val( $old.val() );
			$wrap.empty().append( $new );
		} );
	}

	/* -----------------------------------------------------------------
	 * Buttons
	 * --------------------------------------------------------------- */

	var linkMeta = cfg.linkTypes || {};

	function refreshHead( $item ) {
		var label = $.trim( $item.find( '[data-role="label"]' ).val() || '' );
		var type = $item.find( '[data-role="link-type"]' ).val();
		var iconType = $item.find( '[data-role="icon-type"]:checked' ).val() || 'icon';

		$item.attr( 'data-link', type ).attr( 'data-icon-type', iconType );
		$item.find( '[data-role="title"]' ).text( label || i18n.noLabel || '' );
		$item.find( '[data-role="meta"]' ).text( linkMeta[ type ] || '' );
		$item.toggleClass( 'is-inactive', ! $item.find( '[data-role="active"]' ).is( ':checked' ) );

		var $thumb = $item.find( '[data-role="thumb"]' );
		var $img = $item.find( '[data-role="image-preview"] img' );
		if ( 'image' === iconType && $img.length ) {
			$thumb.html( $( '<img alt="">' ).attr( 'src', $img.attr( 'src' ) ) );
		} else if ( 'fa' === iconType ) {
			$thumb.html( $item.find( '[data-role="fa-preview"]' ).html() );
		} else {
			$thumb.html( $item.find( '[data-role="icon-preview"]' ).html() );
		}
	}

	function addItem( $afterThis, values ) {
		if ( $items.children().length >= ( cfg.maxItems || 20 ) ) {
			window.alert( i18n.maxItems );
			return null;
		}
		var html = $( '#b83-fb-item-template' ).html();
		var $item = $( $.parseHTML( $.trim( html ) ) );
		setIndex( $item, 'n' + ( ++tempIndex ) );

		if ( values ) {
			values( $item );
		}

		if ( $afterThis && $afterThis.length ) {
			$item.insertAfter( $afterThis );
		} else {
			$items.append( $item );
		}
		renumber();
		initColors( $item );
		refreshHead( $item );
		markDirty();
		return $item;
	}

	$( '#b83-fb-add' ).on( 'click', function () {
		var $item = addItem( null, null );
		if ( $item ) {
			$item.find( '[data-role="label"]' ).trigger( 'focus' );
		}
	} );

	$items.on( 'click', '.b83-fb-item__toggle', function () {
		var $item = $( this ).closest( '.b83-fb-item' );
		$item.toggleClass( 'is-open' );
		$( this ).attr( 'aria-expanded', $item.hasClass( 'is-open' ) ? 'true' : 'false' );
	} );

	$items.on( 'click', '[data-role="remove"]', function () {
		if ( window.confirm( i18n.confirmDel ) ) {
			$( this ).closest( '.b83-fb-item' ).remove();
			renumber();
			markDirty();
		}
	} );

	$items.on( 'click', '[data-role="duplicate"]', function () {
		var $source = $( this ).closest( '.b83-fb-item' );
		if ( $items.children().length >= ( cfg.maxItems || 20 ) ) {
			window.alert( i18n.maxItems );
			return;
		}
		var $copy = $source.clone( false );
		setIndex( $copy, 'n' + ( ++tempIndex ) );
		resetColors( $copy );
		// Auswahlfelder übernimmt clone() nicht.
		$copy.find( 'select' ).each( function ( i ) {
			$( this ).val( $source.find( 'select' ).eq( i ).val() );
		} );
		$copy.find( 'input[name$="[id]"]' ).val( '' );
		$copy.addClass( 'is-open' );
		$copy.insertAfter( $source );
		renumber();
		initColors( $copy );
		refreshHead( $copy );
		markDirty();
	} );

	$items.sortable( {
		handle: '.b83-fb-item__handle',
		axis: 'y',
		placeholder: 'b83-fb-item-placeholder',
		forcePlaceholderSize: true,
		update: function () {
			renumber();
			markDirty();
		}
	} );

	$items.on( 'input change', 'input, select', function () {
		refreshHead( $( this ).closest( '.b83-fb-item' ) );
	} );

	/* -----------------------------------------------------------------
	 * Icon-Auswahl
	 * --------------------------------------------------------------- */
	var $picker = $( '#b83-fb-picker' );
	var $pickerTarget = null;

	function closePicker() {
		$picker.prop( 'hidden', true );
		$pickerTarget = null;
	}

	$items.on( 'click', '[data-role="pick-icon"]', function ( event ) {
		event.stopPropagation();
		closeFa();
		var $btn = $( this );
		if ( $pickerTarget && $pickerTarget.is( $btn.closest( '.b83-fb-item' ) ) && ! $picker.prop( 'hidden' ) ) {
			closePicker();
			return;
		}
		$pickerTarget = $btn.closest( '.b83-fb-item' );
		var current = $pickerTarget.find( '[data-role="icon"]' ).val();
		$picker.find( '.b83-fb-picker__icon' ).removeClass( 'is-current' ).filter( '[data-icon="' + current + '"]' ).addClass( 'is-current' );

		var offset = $btn.offset();
		var maxLeft = $( window ).width() - $picker.outerWidth() - 20;
		$picker.css( {
			top: offset.top + $btn.outerHeight() + 6,
			left: Math.max( 10, Math.min( offset.left, maxLeft ) )
		} ).prop( 'hidden', false );
		$picker.find( '.b83-fb-picker__search' ).val( '' ).trigger( 'input' ).trigger( 'focus' );
	} );

	$picker.on( 'click', function ( event ) {
		event.stopPropagation();
	} );

	$picker.on( 'click', '.b83-fb-picker__icon', function () {
		if ( ! $pickerTarget ) {
			return;
		}
		var $icon = $( this );
		$pickerTarget.find( '[data-role="icon"]' ).val( $icon.attr( 'data-icon' ) );
		$pickerTarget.find( '[data-role="icon-preview"]' ).html( $icon.find( 'svg' ).prop( 'outerHTML' ) );
		$pickerTarget.find( '[data-role="icon-name"]' ).text( $icon.attr( 'data-label' ) );
		refreshHead( $pickerTarget );
		markDirty();
		closePicker();
	} );

	$picker.on( 'click', '[data-role="picker-close"]', closePicker );

	$picker.on( 'input', '.b83-fb-picker__search', function () {
		var term = $.trim( $( this ).val() ).toLowerCase();
		$picker.find( '.b83-fb-picker__icon' ).each( function () {
			var hay = ( $( this ).attr( 'data-label' ) + ' ' + $( this ).attr( 'data-icon' ) ).toLowerCase();
			$( this ).toggle( '' === term || -1 !== hay.indexOf( term ) );
		} );
		$picker.find( '.b83-fb-picker__group' ).each( function () {
			$( this ).toggle( $( this ).find( '.b83-fb-picker__icon:visible' ).length > 0 );
		} );
	} );

	$( document ).on( 'click', function () {
		if ( ! $picker.prop( 'hidden' ) ) {
			closePicker();
		}
	} );

	$( document ).on( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && ! $picker.prop( 'hidden' ) ) {
			closePicker();
		}
	} );

	/* -----------------------------------------------------------------
	 * Font Awesome: Liste beim ersten Öffnen laden, im Browser filtern
	 * --------------------------------------------------------------- */
	var $fa = $( '#b83-fb-fa-picker' );
	var $faGrid = $fa.find( '[data-role="fa-grid"]' );
	var $faNote = $fa.find( '[data-role="fa-note"]' );
	var faIcons = null;
	var faRequest = null;
	var faTarget = null;
	var faTimer = null;
	var FA_LIMIT = 240;
	var SVG_NS = 'http://www.w3.org/2000/svg';
	var PATH_OK = /^[0-9MmLlHhVvCcSsQqTtAaZz.,\-\s]+$/;

	function loadFa() {
		if ( faIcons ) {
			return Promise.resolve( faIcons );
		}
		if ( ! faRequest ) {
			faRequest = window.fetch( cfg.faUrl, { credentials: 'same-origin' } )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'HTTP ' + response.status );
					}
					return response.json();
				} )
				.then( function ( json ) {
					// Zeile: Name, Stil, Bezeichnung, Suchbegriffe, Breite, Pfad.
					faIcons = ( json.i || [] ).filter( function ( row ) {
						return row.length >= 6 && PATH_OK.test( row[ 5 ] );
					} ).map( function ( row ) {
						return {
							key: row[ 1 ] + ':' + row[ 0 ],
							name: row[ 0 ],
							style: row[ 1 ],
							label: row[ 2 ],
							hay: ( row[ 0 ] + ' ' + row[ 2 ] + ' ' + row[ 3 ] ).toLowerCase(),
							width: parseInt( row[ 4 ], 10 ) || 512,
							path: row[ 5 ]
						};
					} );
					return faIcons;
				} )
				.catch( function ( error ) {
					faRequest = null;
					throw error;
				} );
		}
		return faRequest;
	}

	// SVG per DOM aufbauen (kein HTML-String aus Daten).
	function faSvg( icon ) {
		var svg = document.createElementNS( SVG_NS, 'svg' );
		svg.setAttribute( 'xmlns', SVG_NS );
		svg.setAttribute( 'viewBox', '0 0 ' + icon.width + ' 512' );
		svg.setAttribute( 'fill', 'currentColor' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );
		var path = document.createElementNS( SVG_NS, 'path' );
		path.setAttribute( 'd', icon.path );
		svg.appendChild( path );
		return svg;
	}

	function faStyleName( style ) {
		return { s: 'Solid', r: 'Regular', b: 'Brand' }[ style ] || '';
	}

	function renderFa() {
		if ( ! faIcons ) {
			return;
		}
		var term = $.trim( $fa.find( '[data-role="fa-search"]' ).val() || '' ).toLowerCase();
		var style = $fa.find( '[name="b83_fb_fa_style"]:checked' ).val() || '';
		var current = faTarget ? faTarget.find( '[data-role="fa"]' ).val() : '';
		var words = term.split( /\s+/ ).filter( Boolean );

		var hits = faIcons.filter( function ( icon ) {
			if ( style && icon.style !== style ) {
				return false;
			}
			return words.every( function ( word ) {
				return -1 !== icon.hay.indexOf( word );
			} );
		} );

		// Treffer im Namen zuerst, exakte Treffer ganz vorne.
		if ( term ) {
			hits.sort( function ( a, b ) {
				return rank( a ) - rank( b );
			} );
		}
		function rank( icon ) {
			if ( icon.name === term ) {
				return 0;
			}
			if ( 0 === icon.name.indexOf( term ) ) {
				return 1;
			}
			return -1 !== icon.name.indexOf( term ) ? 2 : 3;
		}

		var fragment = document.createDocumentFragment();
		hits.slice( 0, FA_LIMIT ).forEach( function ( icon ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'b83-fb-picker__icon' + ( icon.key === current ? ' is-current' : '' );
			btn.setAttribute( 'data-key', icon.key );
			btn.title = icon.label + ' (' + faStyleName( icon.style ) + ')';
			btn.appendChild( faSvg( icon ) );
			var span = document.createElement( 'span' );
			span.textContent = icon.label;
			btn.appendChild( span );
			fragment.appendChild( btn );
		} );
		$faGrid.empty().append( fragment );

		if ( ! hits.length ) {
			$faNote.text( i18n.faNone || '' );
		} else if ( hits.length > FA_LIMIT ) {
			$faNote.text( ( i18n.faMore || '' ).replace( '%1$d', FA_LIMIT ).replace( '%2$d', hits.length ) );
		} else {
			$faNote.text( '' );
		}
	}

	function closeFa() {
		$fa.prop( 'hidden', true );
		faTarget = null;
	}

	$items.on( 'click', '[data-role="pick-fa"]', function ( event ) {
		event.stopPropagation();
		closePicker();
		var $btn = $( this );
		if ( faTarget && faTarget.is( $btn.closest( '.b83-fb-item' ) ) && ! $fa.prop( 'hidden' ) ) {
			closeFa();
			return;
		}
		faTarget = $btn.closest( '.b83-fb-item' );

		var offset = $btn.offset();
		var maxLeft = $( window ).width() - $fa.outerWidth() - 20;
		$fa.css( {
			top: offset.top + $btn.outerHeight() + 6,
			left: Math.max( 10, Math.min( offset.left, maxLeft ) )
		} ).prop( 'hidden', false );
		$fa.find( '[data-role="fa-search"]' ).val( '' ).trigger( 'focus' );

		if ( faIcons ) {
			renderFa();
			return;
		}
		$faGrid.empty();
		$faNote.text( i18n.faLoading || '' );
		loadFa().then( renderFa ).catch( function () {
			$faNote.text( i18n.faError || '' );
		} );
	} );

	$fa.on( 'click', function ( event ) {
		event.stopPropagation();
	} );

	$fa.on( 'input', '[data-role="fa-search"]', function () {
		window.clearTimeout( faTimer );
		faTimer = window.setTimeout( renderFa, 120 );
	} );
	$fa.on( 'change', '[name="b83_fb_fa_style"]', renderFa );

	$fa.on( 'click', '.b83-fb-picker__icon', function () {
		if ( ! faTarget || ! faIcons ) {
			return;
		}
		var key = $( this ).attr( 'data-key' );
		var icon = faIcons.find( function ( entry ) {
			return entry.key === key;
		} );
		if ( ! icon ) {
			return;
		}
		faTarget.find( '[data-role="fa"]' ).val( icon.key );
		faTarget.find( '[data-role="fa-preview"]' ).empty().append( faSvg( icon ) );
		faTarget.find( '[data-role="fa-name"]' ).text( icon.label );
		faTarget.find( '[data-role="icon-type"][value="fa"]' ).prop( 'checked', true );
		refreshHead( faTarget );
		markDirty();
		closeFa();
	} );

	$fa.on( 'click', '[data-role="picker-close"]', closeFa );

	$( document ).on( 'click', function () {
		if ( ! $fa.prop( 'hidden' ) ) {
			closeFa();
		}
	} );

	$( document ).on( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && ! $fa.prop( 'hidden' ) ) {
			closeFa();
		}
	} );

	/* -----------------------------------------------------------------
	 * Mediathek
	 * --------------------------------------------------------------- */
	$items.on( 'click', '[data-role="pick-image"]', function () {
		var $item = $( this ).closest( '.b83-fb-item' );
		var frame = wp.media( {
			title: i18n.mediaTitle,
			button: { text: i18n.mediaButton },
			library: { type: 'image' },
			multiple: false
		} );
		frame.on( 'select', function () {
			var file = frame.state().get( 'selection' ).first().toJSON();
			var url = ( file.sizes && file.sizes.thumbnail ) ? file.sizes.thumbnail.url : file.url;
			$item.find( '[data-role="image-id"]' ).val( file.id );
			$item.find( '[data-role="image-preview"]' ).html( $( '<img alt="">' ).attr( 'src', url ) );
			$item.find( '[data-role="icon-type"][value="image"]' ).prop( 'checked', true );
			refreshHead( $item );
			markDirty();
		} );
		frame.open();
	} );

	$items.on( 'click', '[data-role="clear-image"]', function () {
		var $item = $( this ).closest( '.b83-fb-item' );
		$item.find( '[data-role="image-id"]' ).val( '0' );
		$item.find( '[data-role="image-preview"]' ).empty();
		var fallback = $item.find( '[data-role="fa"]' ).val() ? 'fa' : 'icon';
		$item.find( '[data-role="icon-type"][value="' + fallback + '"]' ).prop( 'checked', true );
		refreshHead( $item );
		markDirty();
	} );

	/* -----------------------------------------------------------------
	 * Position: Beschriftung der Ausrichtung, Versatz bei „Mitte“
	 * --------------------------------------------------------------- */
	function refreshPosition() {
		var pos = $form.find( '[name="group[position]"]:checked' ).val();
		var horizontal = 'top' === pos || 'bottom' === pos;
		$form.find( '[data-role="align"] span[data-v]' ).each( function () {
			$( this ).text( $( this ).attr( horizontal ? 'data-h' : 'data-v' ) );
		} );
		var center = 'center' === $form.find( '[name="group[align]"]:checked' ).val();
		$form.find( '.b83-fb-field--align_offset' ).toggleClass( 'is-muted', center );
	}
	$form.on( 'change', '[name="group[position]"], [name="group[align]"]', refreshPosition );

	/* -----------------------------------------------------------------
	 * Seitenliste filtern
	 * --------------------------------------------------------------- */
	$form.on( 'input', '.b83-fb-pagesearch', function () {
		var term = $.trim( $( this ).val() ).toLowerCase();
		$form.find( '.b83-fb-pagelist label' ).each( function () {
			$( this ).toggle( '' === term || -1 !== $( this ).text().toLowerCase().indexOf( term ) );
		} );
	} );

	/* -----------------------------------------------------------------
	 * Live-Vorschau
	 * --------------------------------------------------------------- */
	var $stage = $( '#b83-fb-stage' );
	var frame = document.getElementById( 'b83-fb-frame' );
	var timer = null;
	var request = 0;

	function deviceWidth() {
		return parseInt( $form.find( '[name="b83_fb_device"]:checked' ).attr( 'data-width' ), 10 ) || 1280;
	}

	function fitFrame() {
		if ( ! frame ) {
			return;
		}
		var width = deviceWidth();
		var available = $stage.innerWidth();
		var scale = Math.min( 1, available / width );
		var height = $stage.innerHeight();
		frame.style.width = width + 'px';
		frame.style.height = Math.round( height / scale ) + 'px';
		frame.style.transform = 'scale(' + scale + ')';
		$stage.toggleClass( 'is-mobile', width < 768 );
	}

	function loadPreview() {
		if ( ! frame ) {
			return;
		}
		renumber();
		var data = new window.FormData( $form[ 0 ] );
		data.set( 'action', 'b83_fb_preview' );
		data.set( 'nonce', cfg.nonce );
		var current = ++request;
		$stage.addClass( 'is-loading' );

		window.fetch( cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( current !== request ) {
					return;
				}
				if ( ! json || ! json.success ) {
					throw new Error( 'preview' );
				}
				frame.srcdoc = json.data.doc;
				$stage.removeClass( 'is-loading is-error' );
			} )
			.catch( function () {
				if ( current === request ) {
					$stage.removeClass( 'is-loading' ).addClass( 'is-error' ).attr( 'data-error', i18n.previewErr );
				}
			} );
	}

	function schedulePreview() {
		window.clearTimeout( timer );
		timer = window.setTimeout( loadPreview, 350 );
	}

	$form.on( 'input change', 'input, select', function ( event ) {
		if ( 'b83_fb_device' === event.target.name ) {
			fitFrame();
			return;
		}
		if ( $( event.target ).is( '.b83-fb-pagesearch, .b83-fb-picker__search, [name="b83_fb_fa_style"]' ) ) {
			return;
		}
		markDirty();
	} );

	$( window ).on( 'resize', fitFrame );

	/* -----------------------------------------------------------------
	 * Ungespeicherte Änderungen
	 * --------------------------------------------------------------- */
	$form.on( 'submit', function () {
		renumber();
		dirty = false;
	} );
	$( window ).on( 'beforeunload', function () {
		if ( dirty ) {
			return true;
		}
		return undefined;
	} );

	/* -----------------------------------------------------------------
	 * Start
	 * --------------------------------------------------------------- */
	initColors( $form );
	$items.children( '.b83-fb-item' ).each( function () {
		refreshHead( $( this ) );
	} );
	renumber();
	refreshPosition();
	fitFrame();
	loadPreview();
	dirty = false;
}( jQuery ) );
