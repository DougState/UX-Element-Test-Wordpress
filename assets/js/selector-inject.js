/**
 * ElementTest Pro — Selector Injection Script
 *
 * This script is injected INTO the proxied iframe. It highlights elements
 * on hover and sends selection data back to the parent window via postMessage.
 *
 * @package ElementTestPro
 * @since   1.0.0
 */

(function() {
	'use strict';

	// State.
	var hoveredEl   = null;
	var selectedEl  = null;
	var highlightEl = null;
	var tooltipEl   = null;
	var selectBoxEl = null;
	var locked      = false;

	// =====================================================================
	// Create highlight / tooltip overlay elements
	// =====================================================================

	function createOverlays() {
		// Highlight box (shown on hover).
		highlightEl = document.createElement( 'div' );
		highlightEl.id = 'ets-highlight';
		highlightEl.style.cssText = [
			'position: absolute',
			'pointer-events: none',
			'z-index: 2147483646',
			'border: 2px solid #2271b1',
			'background: rgba(34, 113, 177, 0.08)',
			'border-radius: 2px',
			'transition: top 0.08s, left 0.08s, width 0.08s, height 0.08s',
			'display: none'
		].join(';');
		document.body.appendChild( highlightEl );

		// Tooltip (shows tag + class).
		tooltipEl = document.createElement( 'div' );
		tooltipEl.id = 'ets-tooltip';
		tooltipEl.style.cssText = [
			'position: absolute',
			'pointer-events: none',
			'z-index: 2147483647',
			'background: #1d2327',
			'color: #f0f0f1',
			'font-family: "SF Mono", SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace',
			'font-size: 11px',
			'line-height: 1.3',
			'padding: 4px 8px',
			'border-radius: 3px',
			'white-space: nowrap',
			'display: none',
			'max-width: 400px',
			'overflow: hidden',
			'text-overflow: ellipsis'
		].join(';');
		document.body.appendChild( tooltipEl );

		// Selection box (persistent on selected element).
		selectBoxEl = document.createElement( 'div' );
		selectBoxEl.id = 'ets-selectbox';
		selectBoxEl.style.cssText = [
			'position: absolute',
			'pointer-events: none',
			'z-index: 2147483645',
			'border: 3px solid #00a32a',
			'background: rgba(0, 163, 42, 0.06)',
			'border-radius: 3px',
			'display: none',
			'box-shadow: 0 0 0 1px rgba(0,0,0,0.08), 0 2px 8px rgba(0,163,42,0.15)'
		].join(';');
		document.body.appendChild( selectBoxEl );
	}

	// =====================================================================
	// Element labelling helpers
	// =====================================================================

	/**
	 * Build a short human-readable label for an element (tag + id/class).
	 */
	function getLabel( el ) {
		if ( ! el || el === document.body || el === document.documentElement ) {
			return el ? el.tagName.toLowerCase() : '';
		}

		var tag = el.tagName.toLowerCase();
		var label = tag;

		if ( el.id ) {
			label += '#' + el.id;
		} else if ( el.className && typeof el.className === 'string' ) {
			var classes = el.className.trim().split( /\s+/ ).slice( 0, 2 );
			if ( classes.length ) {
				label += '.' + classes.join( '.' );
			}
		}

		return label;
	}

	/**
	 * Build a unique CSS selector for the element.
	 * Prefers id selectors, then falls back to nth-child paths.
	 */
	function buildSelector( el ) {
		if ( ! el || el === document.body || el === document.documentElement ) {
			return el ? el.tagName.toLowerCase() : '';
		}

		// If the element has an ID, use it directly.
		if ( el.id ) {
			return '#' + CSS.escape( el.id );
		}

		var parts = [];
		var current = el;

		while ( current && current !== document.body && current !== document.documentElement ) {
			var tag = current.tagName.toLowerCase();
			var segment = tag;

			if ( current.id ) {
				segment = '#' + CSS.escape( current.id );
				parts.unshift( segment );
				break;
			}

			// Find unique class combination.
			if ( current.className && typeof current.className === 'string' ) {
				var classes = current.className.trim().split( /\s+/ )
					.filter( function( c ) {
						// Skip dynamic/state classes.
						return c && ! /^(hover|active|focus|visited|elementtest|ets-)/.test( c );
					});

				if ( classes.length > 0 ) {
					var classSelector = tag + '.' + classes.slice( 0, 2 ).map( function( c ) {
						return CSS.escape( c );
					}).join( '.' );

					// Check if this is unique in its parent context.
					var parent = current.parentElement;
					if ( parent && parent.querySelectorAll( ':scope > ' + classSelector ).length === 1 ) {
						segment = classSelector;
					} else {
						segment = classSelector + ':nth-child(' + getChildIndex( current ) + ')';
					}
				} else {
					segment = tag + ':nth-child(' + getChildIndex( current ) + ')';
				}
			} else {
				segment = tag + ':nth-child(' + getChildIndex( current ) + ')';
			}

			parts.unshift( segment );
			current = current.parentElement;
		}

		return parts.join( ' > ' );
	}

	/**
	 * Build the path (breadcrumb) from body to the element.
	 */
	function buildPath( el ) {
		var path = [];
		var current = el;

		while ( current && current !== document.documentElement ) {
			path.unshift( getLabel( current ) );
			current = current.parentElement;
		}

		return path;
	}

	/**
	 * Get 1-based child index of an element among its siblings.
	 */
	function getChildIndex( el ) {
		var index = 1;
		var sibling = el.previousElementSibling;
		while ( sibling ) {
			index++;
			sibling = sibling.previousElementSibling;
		}
		return index;
	}

	// =====================================================================
	// Overlay positioning
	// =====================================================================

	function positionOverlay( overlay, el ) {
		var rect = el.getBoundingClientRect();
		var scrollX = window.scrollX || window.pageXOffset;
		var scrollY = window.scrollY || window.pageYOffset;

		overlay.style.top    = ( rect.top + scrollY ) + 'px';
		overlay.style.left   = ( rect.left + scrollX ) + 'px';
		overlay.style.width  = rect.width + 'px';
		overlay.style.height = rect.height + 'px';
		overlay.style.display = 'block';
	}

	function positionTooltip( el ) {
		var rect = el.getBoundingClientRect();
		var scrollX = window.scrollX || window.pageXOffset;
		var scrollY = window.scrollY || window.pageYOffset;

		tooltipEl.style.display = 'block';

		var tooltipRect = tooltipEl.getBoundingClientRect();
		var top  = rect.top + scrollY - tooltipRect.height - 8;
		var left = rect.left + scrollX;

		// If tooltip would go above viewport, show below.
		if ( top - scrollY < 0 ) {
			top = rect.bottom + scrollY + 8;
		}

		// Keep within viewport horizontally.
		if ( left + tooltipRect.width > document.documentElement.clientWidth + scrollX ) {
			left = document.documentElement.clientWidth + scrollX - tooltipRect.width - 8;
		}

		tooltipEl.style.top  = top + 'px';
		tooltipEl.style.left = Math.max( 4, left ) + 'px';
	}

	// =====================================================================
	// Event handlers
	// =====================================================================

	function isIgnored( el ) {
		if ( ! el ) { return true; }
		var tag = el.tagName.toLowerCase();
		if ( tag === 'html' || tag === 'body' ) { return true; }
		if ( el.id && el.id.indexOf( 'ets-' ) === 0 ) { return true; }
		return false;
	}

	function onMouseMove( e ) {
		if ( locked ) { return; }

		var el = e.target;
		if ( isIgnored( el ) || el === hoveredEl ) { return; }

		hoveredEl = el;

		// Position highlight.
		positionOverlay( highlightEl, el );

		// Update tooltip.
		tooltipEl.textContent = getLabel( el );
		positionTooltip( el );

		// Send hover data to parent.
		sendMessage( 'hover', {
			selector: buildSelector( el ),
			tagName:  getLabel( el )
		});
	}

	function onMouseLeave() {
		if ( locked ) { return; }
		hoveredEl = null;
		highlightEl.style.display = 'none';
		tooltipEl.style.display   = 'none';
	}

	function onClick( e ) {
		if ( locked ) { return; }

		e.preventDefault();
		e.stopPropagation();
		e.stopImmediatePropagation();

		var el = e.target;
		if ( isIgnored( el ) ) { return; }

		selectedEl = el;
		locked = true;

		// Hide hover highlight, show selection box.
		highlightEl.style.display = 'none';
		tooltipEl.style.display   = 'none';
		positionOverlay( selectBoxEl, el );

		var rect = el.getBoundingClientRect();

		sendMessage( 'select', {
			selector:   buildSelector( el ),
			path:       buildPath( el ),
			tagName:    getLabel( el ),
			dimensions: {
				width:  Math.round( rect.width ),
				height: Math.round( rect.height )
			}
		});
	}

	// =====================================================================
	// Communication with parent
	// =====================================================================

	function sendMessage( type, data ) {
		var msg = {
			source: 'elementtest-selector',
			type:   type
		};

		for ( var key in data ) {
			if ( data.hasOwnProperty( key ) ) {
				msg[ key ] = data[ key ];
			}
		}

		try {
			window.parent.postMessage( JSON.stringify( msg ), window.location.origin );
		} catch ( e ) {
			// Silently ignore.
		}
	}

	/**
	 * Listen for messages from the parent window.
	 */
	window.addEventListener( 'message', function( event ) {
		var data;
		try {
			data = ( typeof event.data === 'string' ) ? JSON.parse( event.data ) : event.data;
		} catch ( e ) {
			return;
		}

		if ( ! data || data.source !== 'elementtest-parent' ) {
			return;
		}

		if ( data.type === 'reset' ) {
			locked     = false;
			selectedEl = null;
			selectBoxEl.style.display = 'none';
		}
	}, false );

	// =====================================================================
	// Prevent all navigation inside the iframe
	// =====================================================================

	function preventNavigation() {
		// Block link clicks.
		document.addEventListener( 'click', function( e ) {
			var link = e.target.closest ? e.target.closest( 'a' ) : null;
			if ( link ) {
				e.preventDefault();
				e.stopPropagation();
			}
		}, true );

		// Block form submissions.
		document.addEventListener( 'submit', function( e ) {
			e.preventDefault();
			e.stopPropagation();
		}, true );
	}

	// =====================================================================
	// Initialization
	// =====================================================================

	function init() {
		createOverlays();
		preventNavigation();

		document.addEventListener( 'mousemove', onMouseMove, true );
		document.addEventListener( 'mouseleave', onMouseLeave, true );
		document.addEventListener( 'click', onClick, true );

		// Disable pointer events on iframes within the page (nested iframes).
		var iframes = document.querySelectorAll( 'iframe' );
		for ( var i = 0; i < iframes.length; i++ ) {
			iframes[ i ].style.pointerEvents = 'none';
		}

		// Set cursor.
		document.body.style.cursor = 'crosshair';

		// Notify parent that we are ready.
		sendMessage( 'ready', {} );
	}

	// Run when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

})();
