/**
 * ElementTest Pro — Visual Element Selector
 *
 * Full-screen overlay with an iframe that lets users point-and-click
 * to select DOM elements on their page. Communicates with the injected
 * selector script via postMessage.
 *
 * @package ElementTestPro
 * @since   1.0.0
 */

/* global jQuery, elementtestAdmin */

(function( $ ) {
	'use strict';

	// State.
	var $overlay    = null;
	var $iframe     = null;
	var isOpen      = false;
	var onSelect    = null; // callback( { selector, path, tagName, dimensions } )
	var currentUrl  = '';

	// =====================================================================
	// Build the overlay DOM (once)
	// =====================================================================

	function buildOverlay() {
		if ( $overlay ) {
			return;
		}

		var html = '' +
			'<div id="elementtest-selector-overlay" class="ets-overlay" style="display:none;">' +
				'<div class="ets-chrome">' +

					/* Top bar */
					'<div class="ets-topbar">' +
						'<div class="ets-topbar-left">' +
							'<span class="ets-logo">' +
								'<span class="ets-logo-icon dashicons dashicons-move"></span>' +
								'Element Selector' +
							'</span>' +
							'<span class="ets-status" id="ets-status">Click an element on the page</span>' +
						'</div>' +
						'<div class="ets-topbar-center">' +
							'<div class="ets-url-bar">' +
								'<span class="ets-url-icon dashicons dashicons-admin-links"></span>' +
								'<span class="ets-url-text" id="ets-url-text"></span>' +
							'</div>' +
						'</div>' +
						'<div class="ets-topbar-right">' +
							'<button type="button" class="ets-btn ets-btn-cancel" id="ets-cancel">Cancel</button>' +
						'</div>' +
					'</div>' +

					/* Iframe viewport */
					'<div class="ets-viewport">' +
						'<div class="ets-loading" id="ets-loading">' +
							'<div class="ets-loading-spinner"></div>' +
							'<span>Loading page&hellip;</span>' +
						'</div>' +
						'<iframe id="ets-iframe" class="ets-iframe" sandbox="allow-same-origin allow-scripts"></iframe>' +
					'</div>' +

					/* Bottom panel — selected element info */
					'<div class="ets-bottom-panel" id="ets-bottom-panel" style="display:none;">' +
						'<div class="ets-selected-info">' +
							'<div class="ets-info-row">' +
								'<span class="ets-info-label">Selector</span>' +
								'<code class="ets-info-value" id="ets-selected-selector"></code>' +
							'</div>' +
							'<div class="ets-info-row">' +
								'<span class="ets-info-label">Path</span>' +
								'<span class="ets-info-value ets-path-breadcrumb" id="ets-selected-path"></span>' +
							'</div>' +
							'<div class="ets-info-row">' +
								'<span class="ets-info-label">Element</span>' +
								'<span class="ets-info-value" id="ets-selected-tag"></span>' +
							'</div>' +
						'</div>' +
						'<div class="ets-selected-actions">' +
							'<button type="button" class="ets-btn ets-btn-reselect" id="ets-reselect">' +
								'<span class="dashicons dashicons-image-rotate"></span> Reselect' +
							'</button>' +
							'<button type="button" class="ets-btn ets-btn-confirm" id="ets-confirm">' +
								'<span class="dashicons dashicons-yes-alt"></span> Use This Element' +
							'</button>' +
						'</div>' +
					'</div>' +

				'</div>' +
			'</div>';

		$overlay = $( html ).appendTo( 'body' );
		$iframe  = $( '#ets-iframe' );

		// Bind events.
		$( '#ets-cancel' ).on( 'click', close );
		$( '#ets-confirm' ).on( 'click', confirmSelection );
		$( '#ets-reselect' ).on( 'click', resetSelection );

		// Escape key.
		$( document ).on( 'keydown.ets', function( e ) {
			if ( e.key === 'Escape' && isOpen ) {
				close();
			}
		});

		// Listen for messages from the injected selector script.
		window.addEventListener( 'message', handleMessage, false );
	}

	// =====================================================================
	// Open / Close
	// =====================================================================

	function open( url, callback ) {
		buildOverlay();

		currentUrl = url;
		onSelect   = callback;
		isOpen     = true;

		// Set URL display.
		$( '#ets-url-text' ).text( url );

		// Reset state.
		resetSelection();
		$( '#ets-loading' ).show();
		$( '#ets-bottom-panel' ).hide();
		$( '#ets-status' ).text( 'Loading page\u2026' );

		// Show overlay.
		$overlay.css( 'display', 'flex' );

		// Prevent body scroll.
		$( 'body' ).css( 'overflow', 'hidden' );

		// Load the page through a proxy endpoint.
		var proxyUrl = elementtestAdmin.ajaxUrl +
			'?action=elementtest_proxy_page' +
			'&nonce=' + encodeURIComponent( elementtestAdmin.nonce ) +
			'&url=' + encodeURIComponent( url );

		$iframe.attr( 'src', proxyUrl );

		// Listen for iframe load.
		$iframe.off( 'load.ets' ).on( 'load.ets', function() {
			$( '#ets-loading' ).fadeOut( 200 );
			$( '#ets-status' ).text( 'Click an element on the page' );
		});
	}

	function close() {
		if ( ! $overlay ) {
			return;
		}

		isOpen = false;
		$overlay.hide();
		$( 'body' ).css( 'overflow', '' );
		$iframe.attr( 'src', 'about:blank' );
	}

	// =====================================================================
	// Message handling (from injected selector script)
	// =====================================================================

	var pendingSelection = null;

	function handleMessage( event ) {
		var data;

		try {
			data = ( typeof event.data === 'string' ) ? JSON.parse( event.data ) : event.data;
		} catch ( e ) {
			return;
		}

		if ( ! data || data.source !== 'elementtest-selector' ) {
			return;
		}

		switch ( data.type ) {
			case 'hover':
				$( '#ets-status' ).html(
					'<code>' + escHtml( data.selector ) + '</code>'
				);
				break;

			case 'select':
				pendingSelection = data;
				showSelection( data );
				break;

			case 'ready':
				$( '#ets-loading' ).fadeOut( 200 );
				$( '#ets-status' ).text( 'Click an element on the page' );
				break;
		}
	}

	function showSelection( data ) {
		$( '#ets-selected-selector' ).text( data.selector );
		$( '#ets-selected-tag' ).html(
			'<code>' + escHtml( data.tagName ) + '</code>' +
			( data.dimensions ? ' &mdash; ' + data.dimensions.width + ' &times; ' + data.dimensions.height + 'px' : '' )
		);

		// Build breadcrumb path.
		var pathHtml = '';
		if ( data.path && data.path.length ) {
			for ( var i = 0; i < data.path.length; i++ ) {
				if ( i > 0 ) {
					pathHtml += '<span class="ets-path-sep">\u203A</span>';
				}
				var cls = ( i === data.path.length - 1 ) ? 'ets-path-node ets-path-active' : 'ets-path-node';
				pathHtml += '<span class="' + cls + '">' + escHtml( data.path[ i ] ) + '</span>';
			}
		}
		$( '#ets-selected-path' ).html( pathHtml );

		$( '#ets-bottom-panel' ).slideDown( 200 );
		$( '#ets-status' ).html( 'Selected: <code>' + escHtml( data.selector ) + '</code>' );
	}

	function resetSelection() {
		pendingSelection = null;
		$( '#ets-bottom-panel' ).slideUp( 150 );
		$( '#ets-status' ).text( 'Click an element on the page' );

		// Tell the iframe to reset highlighting.
		if ( $iframe && $iframe[0].contentWindow ) {
			try {
				$iframe[0].contentWindow.postMessage(
					JSON.stringify({ source: 'elementtest-parent', type: 'reset' }),
					'*'
				);
			} catch ( e ) {
				// Silently ignore cross-origin errors.
			}
		}
	}

	function confirmSelection() {
		if ( ! pendingSelection || typeof onSelect !== 'function' ) {
			return;
		}

		onSelect({
			selector:   pendingSelection.selector,
			path:       pendingSelection.path || [],
			tagName:    pendingSelection.tagName || '',
			dimensions: pendingSelection.dimensions || null
		});

		close();
	}

	// =====================================================================
	// Utilities
	// =====================================================================

	function escHtml( str ) {
		if ( ! str ) { return ''; }
		var el = document.createElement( 'div' );
		el.appendChild( document.createTextNode( str ) );
		return el.innerHTML;
	}

	// =====================================================================
	// Public API
	// =====================================================================

	window.ElementTestSelector = {
		open:  open,
		close: close
	};

})( jQuery );
