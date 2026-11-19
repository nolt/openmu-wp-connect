/**
 * OpenMU WP Connect - item tooltips of the character view.
 *
 * Tooltips: every equipped item (button.openmu-cv__item) points to a hidden source
 * with its tooltip lines (data-openmu-tip = id). One shared box at the end of the
 * page shows a copy of it, fixed to the viewport, so no window frame or overflow
 * clips it. It opens on mouse hover, on keyboard focus and, on touch screens, on a
 * tap (a second tap or a tap elsewhere closes it); Escape closes it too.
 * The box sits beside the item like in the game: left of it, else right of it,
 * else below or above.
 *
 * Item pictures: the server's pictures have wide transparent margins (a helm can
 * fill a quarter of its picture), so each one is cropped to its visible part once
 * loaded (smaller to download and to draw). The crop keeps the scale of the whole
 * picture: the original size goes to the stylesheet as custom properties, so a
 * helm isn't blown up to fill its socket and items keep their sizes relative to
 * each other, as in the game. Pictures from another origin can't be read and stay as they are.
 *
 * No dependencies; loaded only on pages that render a character view.
 */
( function () {
	'use strict';

	/** Gap between the item and the tooltip, and the minimum distance to the screen edge, in px. */
	var GAP = 10;
	var EDGE = 8;

	var tip = null;
	var current = null;
	// Type of the last pointer that pressed something: tells a mouse click from a tap.
	var lastPointer = 'mouse';

	/**
	 * Returns the shared tooltip box, creating it on first use.
	 *
	 * @return {HTMLElement} The box.
	 */
	function box() {
		if ( ! tip ) {
			tip = document.createElement( 'div' );
			tip.className = 'openmu-cv-tip';
			tip.setAttribute( 'role', 'tooltip' );
			tip.hidden = true;
			document.body.appendChild( tip );
		}
		return tip;
	}

	/**
	 * Returns the item button an event happened on, or null.
	 *
	 * @param {EventTarget} target Event target.
	 * @return {HTMLElement|null} The button.
	 */
	function itemOf( target ) {
		return target && target.closest ? target.closest( '.openmu-cv__item[data-openmu-tip]' ) : null;
	}

	/**
	 * Places the box beside the item: left, right, else centred below (or above).
	 *
	 * @param {HTMLElement} item The item button.
	 */
	function place( item ) {
		var r = item.getBoundingClientRect();
		var t = tip.getBoundingClientRect();
		var vw = document.documentElement.clientWidth;
		var vh = window.innerHeight;
		var x = r.left - t.width - GAP;
		var y;

		if ( x < EDGE ) {
			x = r.right + GAP;
		}
		if ( x + t.width > vw - EDGE ) {
			// No room at either side (phones): centre it under the item, or over it near the bottom.
			x = Math.min( Math.max( EDGE, r.left + r.width / 2 - t.width / 2 ), vw - t.width - EDGE );
			y = r.bottom + GAP;
			if ( y + t.height > vh - EDGE ) {
				y = r.top - t.height - GAP;
			}
		} else {
			y = Math.min( r.top, vh - t.height - EDGE );
		}

		tip.style.left = Math.round( Math.max( EDGE, x ) ) + 'px';
		tip.style.top = Math.round( Math.max( EDGE, y ) ) + 'px';
	}

	/**
	 * Shows the tooltip of an item.
	 *
	 * @param {HTMLElement} item The item button.
	 */
	function show( item ) {
		var source = document.getElementById( item.getAttribute( 'data-openmu-tip' ) );
		if ( ! source ) {
			return;
		}
		if ( current && current !== item ) {
			current.classList.remove( 'is-active' );
		}

		// A copy without ids, so the page keeps unique ids (aria-describedby points to the source).
		var copy = source.cloneNode( true );
		copy.querySelectorAll( '[id]' ).forEach( function ( node ) {
			node.removeAttribute( 'id' );
		} );
		box().replaceChildren.apply( tip, Array.prototype.slice.call( copy.childNodes ) );
		// The box lives at the end of the page, outside the view: it takes over the view's look.
		tip.classList.toggle( 'openmu-cv-tip--textured', !! item.closest( '.openmu-cv--textured' ) );
		tip.hidden = false;
		place( item );

		current = item;
		item.classList.add( 'is-active' );
	}

	/**
	 * Hides the tooltip.
	 */
	function hide() {
		if ( tip ) {
			tip.hidden = true;
		}
		if ( current ) {
			current.classList.remove( 'is-active' );
			current = null;
		}
	}

	document.addEventListener( 'pointerdown', function ( event ) {
		lastPointer = event.pointerType || 'mouse';
	}, true );

	// Mouse: show while the pointer is over the item.
	document.addEventListener( 'pointerover', function ( event ) {
		var item = itemOf( event.target );
		if ( item && 'mouse' === event.pointerType && item !== current ) {
			show( item );
		}
	} );

	document.addEventListener( 'pointerout', function ( event ) {
		var item = itemOf( event.target );
		if ( ! item || 'mouse' !== event.pointerType || item !== current || item.contains( event.relatedTarget ) ) {
			return;
		}
		// An item that has keyboard focus keeps its tooltip.
		if ( ! item.matches( ':focus-visible' ) ) {
			hide();
		}
	} );

	// Taps toggle; keyboard activation (Enter/Space, detail 0) toggles too; mouse clicks are left to hover.
	document.addEventListener( 'click', function ( event ) {
		var item = itemOf( event.target );
		if ( ! item ) {
			if ( current && 'mouse' !== lastPointer ) {
				hide();
			}
			return;
		}
		if ( 'mouse' === lastPointer && 0 !== event.detail ) {
			return;
		}
		if ( item === current ) {
			hide();
		} else {
			show( item );
		}
	} );

	// Keyboard focus shows the tooltip; focus from a tap or click doesn't (the click handles it).
	document.addEventListener( 'focusin', function ( event ) {
		var item = itemOf( event.target );
		if ( item && item.matches( ':focus-visible' ) ) {
			show( item );
		}
	} );

	document.addEventListener( 'focusout', function ( event ) {
		if ( current && itemOf( event.target ) === current && ! current.matches( ':hover' ) ) {
			hide();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && current ) {
			hide();
		}
	} );

	// The box is fixed to the viewport, so it follows its item when the page scrolls or resizes.
	function follow() {
		if ( current ) {
			place( current );
		}
	}
	window.addEventListener( 'scroll', follow, { passive: true, capture: true } );
	window.addEventListener( 'resize', follow );

	/** Alpha above which a pixel counts as part of the item, and the margin kept around it, in px. */
	var ALPHA = 16;
	var MARGIN = 2;

	/**
	 * Crops an item picture to its visible part, if the picture can be read.
	 *
	 * @param {HTMLImageElement} img The picture.
	 */
	function trim( img ) {
		var w = img.naturalWidth;
		var h = img.naturalHeight;
		if ( ! w || ! h || img.dataset.openmuTrimmed ) {
			return;
		}
		img.dataset.openmuTrimmed = '1';

		var canvas = document.createElement( 'canvas' );
		canvas.width = w;
		canvas.height = h;
		var ctx = canvas.getContext( '2d', { willReadFrequently: true } );
		var data;
		try {
			ctx.drawImage( img, 0, 0 );
			data = ctx.getImageData( 0, 0, w, h ).data;
		} catch ( e ) {
			return; // Another origin without CORS: keep the picture as it is.
		}

		var left = w, top = h, right = -1, bottom = -1;
		for ( var y = 0; y < h; y++ ) {
			for ( var x = 0; x < w; x++ ) {
				if ( data[ ( y * w + x ) * 4 + 3 ] > ALPHA ) {
					if ( x < left ) { left = x; }
					if ( x > right ) { right = x; }
					if ( y < top ) { top = y; }
					if ( y > bottom ) { bottom = y; }
				}
			}
		}
		if ( right < 0 ) {
			return; // Fully transparent: nothing to show better.
		}

		left = Math.max( 0, left - MARGIN );
		top = Math.max( 0, top - MARGIN );
		right = Math.min( w - 1, right + MARGIN );
		bottom = Math.min( h - 1, bottom + MARGIN );
		var cw = right - left + 1;
		var ch = bottom - top + 1;
		if ( cw === w && ch === h ) {
			return;
		}

		var out = document.createElement( 'canvas' );
		out.width = cw;
		out.height = ch;
		out.getContext( '2d' ).drawImage( canvas, left, top, cw, ch, 0, 0, cw, ch );
		// Original and cropped size in px: the stylesheet scales by the original, not by the crop.
		img.style.setProperty( '--ow', w );
		img.style.setProperty( '--oh', h );
		img.style.setProperty( '--cw', cw );
		img.style.setProperty( '--ch', ch );
		img.classList.add( 'is-cropped' );
		img.src = out.toDataURL( 'image/png' );
	}

	/**
	 * Crops every item picture now or when it has loaded (pictures load lazily).
	 */
	function trimAll() {
		document.querySelectorAll( '.openmu-cv__item img' ).forEach( function ( img ) {
			if ( img.complete ) {
				trim( img );
			} else {
				img.addEventListener( 'load', function () {
					trim( img );
				}, { once: true } );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', trimAll );
	} else {
		trimAll();
	}
}() );
