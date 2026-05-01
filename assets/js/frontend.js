/**
 * ElementTest Pro - Frontend A/B Testing Engine
 *
 * Client-side engine that applies variant changes to page elements and tracks
 * impressions and conversion events. Receives test configuration from
 * wp_localize_script via the global elementtestFrontend object.
 *
 * This script is designed to:
 * - Work with page caching (no server-side variant logic in rendered HTML)
 * - Be lightweight with minimal DOM operations
 * - Apply changes as early as possible to minimize flicker
 * - Never throw errors that break the host page
 * - Handle dynamically loaded elements via MutationObserver
 *
 * @package ElementTestPro
 * @since   1.0.0
 */

/* global elementtestFrontend */

try {
(function() {
	'use strict';

	// Bail early if configuration is missing.
	if ( typeof elementtestFrontend === 'undefined' || ! elementtestFrontend.tests ) {
		return;
	}

	// =========================================================================
	// Constants & State
	// =========================================================================

	var VERSION = '2.4.4';
	var OBSERVER_TIMEOUT = 8000; // Max time (ms) to wait for elements via MutationObserver.
	var ANTIFLICKER_TIMEOUT = 3000; // Max time (ms) before forcing anti-flicker removal.

	/**
	 * Tracks which tests have already recorded an impression this page load.
	 * Keyed by test_id.
	 *
	 * @type {Object.<number, boolean>}
	 */
	var impressionsSent = {};

	/**
	 * Tracks which conversions have already fired this page load.
	 * Keyed by "testId_conversionId".
	 *
	 * @type {Object.<string, boolean>}
	 */
	var conversionsSent = {};

	/**
	 * Stores the active variant assignment per test for the public API.
	 * Keyed by test_id.
	 *
	 * @type {Object.<number, Object>}
	 */
	var activeVariants = {};

	// =========================================================================
	// Cookie Management
	// =========================================================================

	/**
	 * Retrieve a cookie value by name.
	 *
	 * @param {string} name Cookie name.
	 * @return {string|null} Cookie value or null if not found.
	 */
	function getCookie( name ) {
		var nameEQ = encodeURIComponent( name ) + '=';
		var cookies = document.cookie.split( ';' );

		for ( var i = 0; i < cookies.length; i++ ) {
			var c = cookies[ i ];

			// Trim leading whitespace.
			while ( c.charAt( 0 ) === ' ' ) {
				c = c.substring( 1 );
			}

			if ( c.indexOf( nameEQ ) === 0 ) {
				return decodeURIComponent( c.substring( nameEQ.length ) );
			}
		}

		return null;
	}

	/**
	 * Read a query-string parameter from the current URL.
	 *
	 * Used by the admin-only ?et_force= override so QA can preview a specific
	 * variant (or Control) without waiting on a random roll. Returns null when
	 * the parameter is absent or the URL cannot be parsed.
	 *
	 * @param {string} name Parameter name.
	 * @return {string|null} Parameter value (decoded), or null if absent.
	 */
	function getQueryParam( name ) {
		try {
			if ( typeof URLSearchParams === 'function' ) {
				var params = new URLSearchParams( window.location.search );
				return params.has( name ) ? params.get( name ) : null;
			}
		} catch ( e ) {}

		// Fallback for very old browsers without URLSearchParams.
		var pattern = new RegExp(
			'[?&]' + name.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '=([^&]*)'
		);
		var match = pattern.exec( window.location.search );
		return match ? decodeURIComponent( match[ 1 ].replace( /\+/g, ' ' ) ) : null;
	}

	/**
	 * Set a cookie with the given name, value, and expiry in days.
	 *
	 * @param {string} name  Cookie name.
	 * @param {string} value Cookie value.
	 * @param {number} days  Number of days until expiry.
	 */
	function setCookie( name, value, days ) {
		var expires = '';

		if ( days ) {
			var date = new Date();
			date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
			expires = '; expires=' + date.toUTCString();
		}

		var secure = ( window.location.protocol === 'https:' ) ? '; Secure' : '';
		document.cookie = encodeURIComponent( name ) + '=' + encodeURIComponent( value ) +
			expires + '; path=/; SameSite=Lax' + secure;
	}

	// =========================================================================
	// Variant Assignment
	// =========================================================================

	/**
	 * Select a variant from the test's variants array using weighted random.
	 *
	 * If a cookie already exists for this test, return the matching variant.
	 * Otherwise, perform weighted random selection based on traffic_percentage
	 * and set the cookie for sticky sessions.
	 *
	 * @param {Object} test Test configuration with variants array.
	 * @return {Object|null} The selected variant object, or null if none available.
	 */
	function assignVariant( test ) {
		var testId = test.test_id;
		var variants = test.variants;
		var cookieName = 'elementtest_variant_' + testId;
		var cookieDays = parseInt( elementtestFrontend.cookieDays, 10 ) || 30;

		if ( ! variants || variants.length === 0 ) {
			return null;
		}

		// -----------------------------------------------------------------
		// Admin override: ?et_force=control or ?et_force=<variant_id>
		//
		// Lets logged-in admins (manage_options) deterministically preview
		// any variant without waiting on random rolls or repeatedly clearing
		// cookies. The forced assignment is written to the cookie so it
		// sticks across navigation; remove the cookie to resume normal
		// random assignment. Ignored for non-admin visitors so it cannot be
		// used to bias real test data via shared URLs.
		// -----------------------------------------------------------------
		if ( elementtestFrontend.isAdmin ) {
			var forced = getQueryParam( 'et_force' );

			if ( forced ) {
				var forcedMatch = null;

				if ( 'control' === forced ) {
					for ( var f = 0; f < variants.length; f++ ) {
						if ( parseInt( variants[ f ].is_control, 10 ) === 1 ) {
							forcedMatch = variants[ f ];
							break;
						}
					}
				} else {
					for ( var g = 0; g < variants.length; g++ ) {
						if ( String( variants[ g ].variant_id ) === String( forced ) ) {
							forcedMatch = variants[ g ];
							break;
						}
					}
				}

				if ( forcedMatch ) {
					setCookie( cookieName, forcedMatch.variant_id, cookieDays );
					if ( typeof console !== 'undefined' && console.info ) {
						console.info(
							'[ElementTest] Test ' + testId + ' forced to "' +
							forcedMatch.name + '" (variant_id ' + forcedMatch.variant_id +
							') via ?et_force=' + forced
						);
					}
					return forcedMatch;
				}

				if ( typeof console !== 'undefined' && console.warn ) {
					console.warn(
						'[ElementTest] Test ' + testId + ' has no variant matching ?et_force=' +
						forced + '; falling back to normal assignment.'
					);
				}
			}
		}

		// Check for existing cookie (sticky session).
		var existingCookie = getCookie( cookieName );

		if ( existingCookie !== null ) {
			// Find the variant matching the cookie.
			for ( var i = 0; i < variants.length; i++ ) {
				if ( String( variants[ i ].variant_id ) === String( existingCookie ) ) {
					return variants[ i ];
				}
			}
			// Cookie references a variant that no longer exists; reassign.
		}

		// Weighted random selection.
		var totalWeight = 0;
		for ( var j = 0; j < variants.length; j++ ) {
			totalWeight += parseInt( variants[ j ].traffic_percentage, 10 ) || 0;
		}

		if ( totalWeight <= 0 ) {
			// Fallback: pick first variant.
			var fallback = variants[ 0 ];
			setCookie( cookieName, fallback.variant_id, cookieDays );
			return fallback;
		}

		var random = Math.floor( Math.random() * totalWeight ) + 1;
		var cumulative = 0;

		for ( var k = 0; k < variants.length; k++ ) {
			cumulative += parseInt( variants[ k ].traffic_percentage, 10 ) || 0;
			if ( random <= cumulative ) {
				setCookie( cookieName, variants[ k ].variant_id, cookieDays );
				return variants[ k ];
			}
		}

		// Fallback.
		var last = variants[ variants.length - 1 ];
		setCookie( cookieName, last.variant_id, cookieDays );
		return last;
	}

	// =========================================================================
	// Variant Application
	// =========================================================================

	/**
	 * Apply CSS changes for a variant.
	 *
	 * Supports two formats:
	 * 1. Plain rules (e.g., "color: red; font-size: 20px;") - wraps with selector.
	 * 2. Full CSS blocks (already contains selectors/braces) - injects as-is.
	 *
	 * @param {string} selector CSS selector for the target element.
	 * @param {string} changes  CSS rules or full CSS block.
	 * @param {number} testId   Test ID (used for the style tag ID).
	 */
	function applyCssChanges( selector, changes, testId ) {
		var style = document.createElement( 'style' );
		style.id = 'elementtest-css-' + testId;
		style.setAttribute( 'data-elementtest', testId );

		// Determine whether changes is a full CSS block (contains braces) or
		// plain rules that need to be wrapped with the selector.
		if ( changes.indexOf( '{' ) !== -1 ) {
			style.textContent = changes;
		} else {
			style.textContent = selector + ' { ' + changes + ' }';
		}

		var head = document.head || document.getElementsByTagName( 'head' )[ 0 ];
		head.appendChild( style );
	}

	/**
	 * Apply copy (text/HTML) changes for a variant.
	 *
	 * Uses innerHTML if the changes string contains HTML tags, otherwise uses
	 * textContent for safety and performance.
	 *
	 * @param {Element} element Target DOM element.
	 * @param {string}  changes Replacement text or HTML content.
	 */
	function applyCopyChanges( element, changes ) {
		// Check if the changes string contains HTML tags.
		var hasHtml = /<[a-z][\s\S]*>/i.test( changes );

		if ( hasHtml ) {
			// Strip <script> tags client-side as an extra safety layer
			// (server already applies wp_kses_post).
			var cleaned = changes.replace( /<script[\s\S]*?<\/script>/gi, '' );
			element.innerHTML = cleaned;
		} else {
			element.textContent = changes;
		}
	}

	/**
	 * Apply JavaScript changes for a variant.
	 *
	 * Creates a script element and executes the code in a try/catch wrapper
	 * so variant JS errors cannot break the host page. The target element is
	 * made available to the script as `elementtestElement`.
	 *
	 * @param {Element|null} element Target DOM element (may be null).
	 * @param {string}       changes JavaScript code to execute.
	 * @param {number}       testId  Test ID (used for the script tag ID).
	 */
	function applyJsChanges( element, changes, testId ) {
		var script = document.createElement( 'script' );
		script.id = 'elementtest-js-' + testId;
		script.setAttribute( 'data-elementtest', testId );

		// Wrap in try/catch and provide the element reference.
		var wrappedCode =
			'try {\n' +
			'  var elementtestElement = document.querySelector(' +
				JSON.stringify( element ? ( element.id ? '#' + element.id : null ) : null ) +
			');\n' +
			'  ' + changes + '\n' +
			'} catch (e) {\n' +
			'  console.warn("[ElementTest] Variant JS error (test ' + testId + '):", e);\n' +
			'}';

		// If the element does not have a reliable ID selector, use a data attribute
		// to re-find it from within the script.
		if ( element && ! element.id ) {
			var marker = 'elementtest-target-' + testId;
			element.setAttribute( 'data-elementtest-target', marker );

			wrappedCode =
				'try {\n' +
				'  var elementtestElement = document.querySelector(' +
					'"[data-elementtest-target=\'' + marker + '\']"' +
				');\n' +
				'  ' + changes + '\n' +
				'} catch (e) {\n' +
				'  console.warn("[ElementTest] Variant JS error (test ' + testId + '):", e);\n' +
				'}';
		}

		script.textContent = wrappedCode;
		document.body.appendChild( script );
	}

	/**
	 * Apply image changes for a variant.
	 *
	 * Handles both <img> elements (changes src, clears srcset) and elements
	 * with CSS background images (changes backgroundImage).
	 *
	 * @param {Element} element Target DOM element.
	 * @param {string}  changes New image URL.
	 */
	function applyImageChanges( element, changes ) {
		var tagName = element.tagName.toLowerCase();

		if ( tagName === 'img' ) {
			element.src = changes;

			// Clear srcset to prevent the browser from choosing a different
			// source than the one we just set.
			if ( element.srcset ) {
				element.srcset = '';
			}

			// Also clear the sizes attribute since srcset is gone.
			if ( element.sizes ) {
				element.sizes = '';
			}
		} else {
			// Assume it is a background-image element.
			element.style.backgroundImage = 'url(' + changes + ')';
		}
	}

	/**
	 * Apply variant changes to the page based on test type.
	 *
	 * Orchestrates calling the correct change function (CSS, copy, JS, image)
	 * for a given test configuration. CSS changes do not require finding the
	 * DOM element since they are applied via a style tag.
	 *
	 * @param {Object}       test    Test configuration object.
	 * @param {Element|null} element Target DOM element (null for CSS-only changes).
	 */
	function applyVariantChanges( test, element ) {
		var variant = test.variant;
		var changes = variant.changes;
		var selector = test.element_selector;

		if ( ! changes && changes !== '' ) {
			return;
		}

		switch ( test.test_type ) {
			case 'css':
				applyCssChanges( selector, changes, test.test_id );
				break;

			case 'copy':
				if ( element ) {
					applyCopyChanges( element, changes );
				}
				break;

			case 'js':
				applyJsChanges( element, changes, test.test_id );
				break;

			case 'image':
				if ( element ) {
					applyImageChanges( element, changes );
				}
				break;

			default:
				// Unknown test type; skip silently.
				break;
		}
	}

	// =========================================================================
	// Anti-Flicker Management
	// =========================================================================

	/**
	 * Remove the anti-flicker styling.
	 *
	 * Two strategies:
	 * 1. Remove a <style> tag with id "elementtest-antiflicker" if it exists.
	 * 2. Set opacity to 1 with a smooth transition on elements that may have
	 *    been hidden.
	 */
	function removeAntiFlicker() {
		// Strategy 1: Remove the anti-flicker style tag.
		var antiFlickerStyle = document.getElementById( 'elementtest-antiflicker' );
		if ( antiFlickerStyle ) {
			antiFlickerStyle.parentNode.removeChild( antiFlickerStyle );
		}

		// Strategy 2: Restore opacity on any elements hidden by anti-flicker.
		var hiddenElements = document.querySelectorAll( '[data-elementtest-hidden]' );
		for ( var i = 0; i < hiddenElements.length; i++ ) {
			hiddenElements[ i ].style.transition = 'opacity 0.1s';
			hiddenElements[ i ].style.opacity = '1';
			hiddenElements[ i ].removeAttribute( 'data-elementtest-hidden' );
		}
	}

	// =========================================================================
	// Tracking: Impressions
	// =========================================================================

	/**
	 * Send an impression tracking request for a test/variant pair.
	 *
	 * Only one impression per test per page load is recorded. Duplicate calls
	 * for the same test_id are silently ignored.
	 *
	 * @param {number} testId    Test ID.
	 * @param {number} variantId Variant ID.
	 */
	function trackImpression( testId, variantId ) {
		// Prevent duplicate impressions within the same page load.
		if ( impressionsSent[ testId ] ) {
			return;
		}
		impressionsSent[ testId ] = true;

		var data = {
			action: 'elementtest_track_impression',
			nonce: elementtestFrontend.nonce,
			test_id: testId,
			variant_id: variantId,
			user_hash: elementtestFrontend.userHash
		};

		sendTrackingRequest( data );
	}

	// =========================================================================
	// Tracking: Conversions
	// =========================================================================

	/**
	 * Send a conversion tracking request.
	 *
	 * Uses navigator.sendBeacon when available for reliability during page
	 * unload scenarios (e.g., form submissions that navigate away). Falls back
	 * to XMLHttpRequest.
	 *
	 * @param {number} testId       Test ID.
	 * @param {number} variantId    Variant ID.
	 * @param {number} conversionId Conversion goal ID.
	 * @param {number} revenue      Revenue value (defaults to 0).
	 */
	function trackConversion( testId, variantId, conversionId, revenue ) {
		// Build a deduplication key.
		var dedupeKey = testId + '_' + conversionId;

		// Prevent duplicate conversions within the same page load.
		if ( conversionsSent[ dedupeKey ] ) {
			return;
		}
		conversionsSent[ dedupeKey ] = true;

		var data = {
			action: 'elementtest_track_conversion',
			nonce: elementtestFrontend.nonce,
			test_id: testId,
			variant_id: variantId,
			user_hash: elementtestFrontend.userHash,
			conversion_id: conversionId,
			revenue: revenue || 0,
			page_url: window.location.href
		};

		sendTrackingRequest( data );
	}

	/**
	 * Send a tracking request using the best available transport.
	 *
	 * Prefers navigator.sendBeacon for reliability, especially during page
	 * unload events. Falls back to XMLHttpRequest when sendBeacon is not
	 * available.
	 *
	 * @param {Object} data Key-value pairs to send as POST data.
	 */
	function sendTrackingRequest( data ) {
		var url = elementtestFrontend.ajaxUrl;

		if ( navigator.sendBeacon ) {
			var formData = new FormData();
			for ( var key in data ) {
				if ( data.hasOwnProperty( key ) ) {
					formData.append( key, data[ key ] );
				}
			}
			navigator.sendBeacon( url, formData );
		} else {
			// Fallback: XMLHttpRequest (vanilla, no jQuery dependency).
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', url, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );

			var params = [];
			for ( var k in data ) {
				if ( data.hasOwnProperty( k ) ) {
					params.push(
						encodeURIComponent( k ) + '=' + encodeURIComponent( data[ k ] )
					);
				}
			}
			xhr.send( params.join( '&' ) );
		}
	}

	// =========================================================================
	// Conversion Goal Listeners
	// =========================================================================

	/**
	 * Set up event listeners for all conversion goals of a test.
	 *
	 * Uses event delegation on the document for click and form_submit goals
	 * so that dynamically added elements are automatically handled.
	 *
	 * @param {Object} test Test configuration object.
	 */
	function setupGoalListeners( test ) {
		var goals = test.goals;

		if ( ! goals || ! goals.length ) {
			return;
		}

		var testId = test.test_id;
		var variantId = test.variant.variant_id;

		for ( var i = 0; i < goals.length; i++ ) {
			setupSingleGoalListener( testId, variantId, goals[ i ] );
		}
	}

	/**
	 * Set up a listener for a single conversion goal.
	 *
	 * @param {number} testId    Test ID.
	 * @param {number} variantId Variant ID.
	 * @param {Object} goal      Goal configuration object.
	 */
	function setupSingleGoalListener( testId, variantId, goal ) {
		var conversionId = goal.conversion_id;
		var revenue = goal.revenue_value || 0;

		switch ( goal.trigger_type ) {
			case 'click':
				setupClickGoal( testId, variantId, conversionId, revenue, goal.trigger_selector );
				break;

			case 'pageview':
				setupPageviewGoal( testId, variantId, conversionId, revenue, goal.trigger_event );
				break;

			case 'form_submit':
				setupFormSubmitGoal( testId, variantId, conversionId, revenue, goal.trigger_selector );
				break;

			case 'custom_event':
				setupCustomEventGoal( testId, variantId, conversionId, revenue, goal.trigger_event );
				break;

			case 'video_play':
				setupVideoPlayGoal( testId, variantId, conversionId, revenue, goal.trigger_selector );
				break;

			case 'add_to_cart':
				setupAddToCartGoal( testId, variantId, conversionId, revenue, goal.trigger_selector );
				break;

			default:
				break;
		}
	}

	/**
	 * Set up a click-based conversion goal using event delegation.
	 *
	 * @param {number} testId          Test ID.
	 * @param {number} variantId       Variant ID.
	 * @param {number} conversionId    Conversion goal ID.
	 * @param {number} revenue         Revenue value.
	 * @param {string} triggerSelector CSS selector for the clickable element.
	 */
	function setupClickGoal( testId, variantId, conversionId, revenue, triggerSelector ) {
		if ( ! triggerSelector ) {
			return;
		}

		document.addEventListener( 'click', function( event ) {
			var target = event.target;

			// Walk up the DOM tree to find a match (event delegation).
			while ( target && target !== document ) {
				if ( target.matches && target.matches( triggerSelector ) ) {
					trackConversion( testId, variantId, conversionId, revenue );
					return;
				}
				target = target.parentNode;
			}
		}, false );
	}

	/**
	 * Set up a pageview-based conversion goal.
	 *
	 * Checks if the current page URL/path matches the trigger_event value.
	 * Supports exact path match, full URL match, and pattern matching with
	 * a trailing wildcard.
	 *
	 * @param {number} testId       Test ID.
	 * @param {number} variantId    Variant ID.
	 * @param {number} conversionId Conversion goal ID.
	 * @param {number} revenue      Revenue value.
	 * @param {string} triggerUrl   URL or path to match.
	 */
	function setupPageviewGoal( testId, variantId, conversionId, revenue, triggerUrl ) {
		if ( ! triggerUrl ) {
			return;
		}

		var currentPath = window.location.pathname;
		var currentUrl = window.location.href;
		var matched = false;

		// Normalize the trigger URL by trimming whitespace.
		triggerUrl = triggerUrl.replace( /^\s+|\s+$/g, '' );

		// Check for wildcard match (e.g., "/thank-you/*").
		// Enforce path boundary so /shop/* does not match /shopping.
		if ( triggerUrl.charAt( triggerUrl.length - 1 ) === '*' ) {
			var prefix = triggerUrl.substring( 0, triggerUrl.length - 1 );
			var prefixPath;

			try {
				prefixPath = new URL( prefix, window.location.origin ).pathname;
			} catch ( e ) {
				prefixPath = prefix;
			}

			var prefixNoSlash = prefixPath.replace( /\/+$/, '' ) || '/';
			matched = ( currentPath === prefixNoSlash )
				|| ( prefixNoSlash === '/' )
				|| ( currentPath.indexOf( prefixNoSlash + '/' ) === 0 );

			if ( ! matched && ( prefix.indexOf( '?' ) !== -1 || prefix.indexOf( '#' ) !== -1 ) ) {
				matched = currentUrl.indexOf( prefix ) === 0;
			}
		} else {
			// Exact match against path or full URL.
			matched = ( currentPath === triggerUrl ) || ( currentUrl === triggerUrl );
		}

		if ( matched ) {
			trackConversion( testId, variantId, conversionId, revenue );
		}
	}

	/**
	 * Set up a form-submit-based conversion goal using event delegation.
	 *
	 * Uses navigator.sendBeacon where available to ensure the tracking request
	 * completes even if the form submission navigates the page away.
	 *
	 * @param {number} testId          Test ID.
	 * @param {number} variantId       Variant ID.
	 * @param {number} conversionId    Conversion goal ID.
	 * @param {number} revenue         Revenue value.
	 * @param {string} triggerSelector CSS selector for the form element.
	 */
	function setupFormSubmitGoal( testId, variantId, conversionId, revenue, triggerSelector ) {
		if ( ! triggerSelector ) {
			return;
		}

		document.addEventListener( 'submit', function( event ) {
			var target = event.target;

			// Walk up the DOM tree to find a matching form.
			while ( target && target !== document ) {
				if ( target.matches && target.matches( triggerSelector ) ) {
					trackConversion( testId, variantId, conversionId, revenue );
					return;
				}
				target = target.parentNode;
			}
		}, false );
	}

	/**
	 * Set up a custom-event-based conversion goal.
	 *
	 * Listens for a custom DOM event dispatched by the site's own JavaScript.
	 * Example: document.dispatchEvent( new Event( 'my-purchase' ) );
	 *
	 * @param {number} testId       Test ID.
	 * @param {number} variantId    Variant ID.
	 * @param {number} conversionId Conversion goal ID.
	 * @param {number} revenue      Revenue value.
	 * @param {string} triggerEvent Custom event name to listen for.
	 */
	function setupCustomEventGoal( testId, variantId, conversionId, revenue, triggerEvent ) {
		if ( ! triggerEvent ) {
			return;
		}

		document.addEventListener( triggerEvent, function( event ) {
			// Allow revenue to be overridden via event detail.
			var eventRevenue = revenue;
			if ( event.detail && typeof event.detail.revenue !== 'undefined' ) {
				eventRevenue = parseFloat( event.detail.revenue ) || 0;
			}

			trackConversion( testId, variantId, conversionId, eventRevenue );
		}, false );
	}

	// =========================================================================
	// Video Play Detection (YouTube)
	// =========================================================================

	/**
	 * Whether the global YouTube postMessage listener has been registered.
	 * @type {boolean}
	 */
	var videoListenerInitialized = false;

	/**
	 * Registered video play goal callbacks.
	 * Each entry: { testId, variantId, conversionId, revenue, scope }
	 * where scope is an optional CSS selector limiting which iframes to watch.
	 *
	 * @type {Array}
	 */
	var videoPlayGoals = [];

	/**
	 * Set of iframe contentWindows we have already sent the "listening"
	 * handshake to, to avoid duplicate messages.
	 *
	 * @type {Array}
	 */
	var listeningWindows = [];

	/**
	 * Set up a video-play-based conversion goal.
	 *
	 * Finds YouTube iframes on the page (optionally scoped to a container
	 * selector), enables the YouTube JS API on each, and registers a
	 * postMessage listener that fires the conversion when a video starts
	 * playing (YouTube player state 1).
	 *
	 * @param {number} testId          Test ID.
	 * @param {number} variantId       Variant ID.
	 * @param {number} conversionId    Conversion goal ID.
	 * @param {number} revenue         Revenue value.
	 * @param {string} triggerSelector Optional CSS selector to scope the search.
	 */
	function setupVideoPlayGoal( testId, variantId, conversionId, revenue, triggerSelector ) {
		videoPlayGoals.push({
			testId: testId,
			variantId: variantId,
			conversionId: conversionId,
			revenue: revenue,
			scope: triggerSelector || null
		});

		if ( ! videoListenerInitialized ) {
			videoListenerInitialized = true;
			window.addEventListener( 'message', handleYouTubeMessage, false );
			startYouTubeIframeObserver();
		}

		prepareYouTubeIframes( triggerSelector || null );
	}

	/**
	 * Find YouTube iframes and ensure they have enablejsapi=1.
	 * Attaches load listeners so the "listening" handshake is sent
	 * after the iframe finishes loading (or immediately if already loaded).
	 *
	 * @param {string|null} scopeSelector Optional CSS selector for a container.
	 */
	function prepareYouTubeIframes( scopeSelector ) {
		var root = document;
		if ( scopeSelector ) {
			var scopeEl = document.querySelector( scopeSelector );
			if ( scopeEl ) {
				root = scopeEl;
			}
		}

		var iframes = root.querySelectorAll( 'iframe[src*="youtube.com"], iframe[src*="youtu.be"]' );

		for ( var i = 0; i < iframes.length; i++ ) {
			activateYouTubeIframe( iframes[ i ] );
		}
	}

	/**
	 * Activate a single YouTube iframe: add enablejsapi=1 if missing,
	 * attach a load listener, and send the listening handshake.
	 *
	 * @param {HTMLIFrameElement} iframe The YouTube iframe element.
	 */
	function activateYouTubeIframe( iframe ) {
		if ( iframe.getAttribute( 'data-elementtest-yt' ) ) {
			return;
		}
		iframe.setAttribute( 'data-elementtest-yt', '1' );

		var src = iframe.src || '';
		if ( ! src ) {
			return;
		}

		// Add enablejsapi=1 if not already present. This causes a reload.
		if ( src.indexOf( 'enablejsapi' ) === -1 ) {
			var separator = src.indexOf( '?' ) === -1 ? '?' : '&';
			iframe.src = src + separator + 'enablejsapi=1';
		}

		// Send handshake once the iframe loads (or right now if already loaded).
		iframe.addEventListener( 'load', function() {
			// After a navigation (e.g. src changed to add enablejsapi=1), the
			// contentWindow object reference stays the same, so clear any prior
			// entry to ensure the handshake is re-sent to the new document.
			var win = iframe.contentWindow;
			for ( var i = listeningWindows.length - 1; i >= 0; i-- ) {
				if ( listeningWindows[ i ] === win ) {
					listeningWindows.splice( i, 1 );
				}
			}
			sendListeningHandshake( iframe );
		}, false );

		// Also try immediately in case it already loaded.
		sendListeningHandshake( iframe );
	}

	/**
	 * Send the YouTube "listening" postMessage handshake to an iframe.
	 * YouTube requires this before it will emit state-change events.
	 *
	 * @param {HTMLIFrameElement} iframe The YouTube iframe.
	 */
	function sendListeningHandshake( iframe ) {
		try {
			var win = iframe.contentWindow;
			if ( ! win ) {
				return;
			}

			// Avoid duplicate handshakes for the same window.
			for ( var i = 0; i < listeningWindows.length; i++ ) {
				if ( listeningWindows[ i ] === win ) {
					return;
				}
			}
			listeningWindows.push( win );

			win.postMessage( JSON.stringify({
				event: 'listening',
				id: 'elementtest',
				channel: 'elementtest'
			}), '*' );

			// YouTube sometimes needs the command format too.
			win.postMessage( JSON.stringify({
				event: 'command',
				func: 'addEventListener',
				args: [ 'onStateChange' ],
				id: 'elementtest',
				channel: 'elementtest'
			}), '*' );
		} catch ( e ) {
			// Cross-origin or not-ready; will retry on load event.
		}
	}

	/**
	 * Watch for dynamically-added YouTube iframes (e.g., sliders that
	 * lazy-load video slides) via MutationObserver.
	 */
	function startYouTubeIframeObserver() {
		if ( typeof MutationObserver === 'undefined' ) {
			return;
		}

		var observer = new MutationObserver( function( mutations ) {
			for ( var m = 0; m < mutations.length; m++ ) {
				var added = mutations[ m ].addedNodes;
				for ( var n = 0; n < added.length; n++ ) {
					var node = added[ n ];
					if ( node.nodeType !== 1 ) {
						continue;
					}

					// Check if the added node IS a YouTube iframe.
					if ( node.tagName === 'IFRAME' && node.src &&
						( node.src.indexOf( 'youtube.com' ) !== -1 || node.src.indexOf( 'youtu.be' ) !== -1 ) ) {
						activateYouTubeIframe( node );
					}

					// Check children of the added node for YouTube iframes.
					if ( node.querySelectorAll ) {
						var childIframes = node.querySelectorAll( 'iframe[src*="youtube.com"], iframe[src*="youtu.be"]' );
						for ( var c = 0; c < childIframes.length; c++ ) {
							activateYouTubeIframe( childIframes[ c ] );
						}
					}
				}
			}
		});

		observer.observe( document.documentElement, { childList: true, subtree: true } );
	}

	/**
	 * Handle postMessage events from YouTube iframes.
	 *
	 * YouTube uses two message formats depending on the embed setup:
	 * 1. event:"onStateChange" with info:1 (standard IFrame API)
	 * 2. event:"infoDelivery" with info.playerState:1 (lightweight delivery)
	 *
	 * Both indicate playerState 1 = playing.
	 *
	 * @param {MessageEvent} event The postMessage event.
	 */
	function handleYouTubeMessage( event ) {
		if ( ! event.origin || ! /^https?:\/\/([a-z0-9-]+\.)?youtube\.com$/i.test( event.origin ) ) {
			return;
		}

		var data;
		try {
			data = typeof event.data === 'string' ? JSON.parse( event.data ) : event.data;
		} catch ( e ) {
			return;
		}

		if ( ! data ) {
			return;
		}

		// Detect "playing" state from either message format.
		var isPlaying = false;

		if ( data.event === 'onStateChange' && data.info === 1 ) {
			isPlaying = true;
		} else if ( data.event === 'infoDelivery' && data.info && data.info.playerState === 1 ) {
			isPlaying = true;
		}

		if ( ! isPlaying ) {
			return;
		}

		for ( var i = 0; i < videoPlayGoals.length; i++ ) {
			var goal = videoPlayGoals[ i ];
			var shouldFire = true;

			// If the goal is scoped to a container, verify the source iframe
			// is inside that container.
			if ( goal.scope && event.source ) {
				shouldFire = false;
				var scopeEl = document.querySelector( goal.scope );
				if ( scopeEl ) {
					var scopedIframes = scopeEl.querySelectorAll( 'iframe' );
					for ( var j = 0; j < scopedIframes.length; j++ ) {
						try {
							if ( scopedIframes[ j ].contentWindow === event.source ) {
								shouldFire = true;
								break;
							}
						} catch ( e ) {
							// Cross-origin comparison may fail; allow it.
							shouldFire = true;
							break;
						}
					}
				}
			}

			if ( shouldFire ) {
				trackConversion( goal.testId, goal.variantId, goal.conversionId, goal.revenue );
			}
		}
	}

	// =========================================================================
	// WooCommerce Variation Lifecycle Handler
	// =========================================================================

	/**
	 * Whether the WooCommerce variation visibility handler has been set up.
	 * @type {boolean}
	 */
	var variationHandlerInitialized = false;

	/**
	 * Selectors from tests that target elements inside WooCommerce variation
	 * forms. Used by the variation event handler to re-ensure visibility.
	 *
	 * @type {Array.<string>}
	 */
	var variationTestSelectors = [];

	/**
	 * Set up WooCommerce variation lifecycle handling for a test selector.
	 *
	 * When an A/B test targets an element inside a WooCommerce variable
	 * product form, the parent container's visibility is managed by
	 * WooCommerce's add-to-cart-variation.js (slideUp/slideDown, class
	 * toggling with a 300 ms delay). The plugin's anti-flicker CSS
	 * (opacity: 0 !important) can leave the tested element invisible if
	 * it is removed while WooCommerce still has the parent hidden, or if
	 * WooCommerce's subsequent show_variation cycle does not clear residual
	 * inline opacity left by earlier anti-flicker recovery attempts.
	 *
	 * This handler hooks into WooCommerce's jQuery variation events to
	 * guarantee the tested element is visible once WooCommerce finishes
	 * its show/hide cycle.
	 *
	 * @param {string} selector CSS selector for the A/B test element.
	 */
	function setupWooCommerceVariationHandler( selector ) {
		if ( typeof jQuery === 'undefined' || ! selector ) {
			return;
		}

		var variationKeywords = [
			'single_variation_wrap',
			'variations_form',
			'woocommerce-variation-add-to-cart',
			'single_add_to_cart_button',
			'variations_button'
		];

		var isVariationRelated = false;
		for ( var i = 0; i < variationKeywords.length; i++ ) {
			if ( selector.indexOf( variationKeywords[ i ] ) !== -1 ) {
				isVariationRelated = true;
				break;
			}
		}

		if ( ! isVariationRelated ) {
			var el = document.querySelector( selector );
			if ( el && el.closest && el.closest( '.variations_form' ) ) {
				isVariationRelated = true;
			}
		}

		if ( ! isVariationRelated ) {
			return;
		}

		variationTestSelectors.push( selector );

		if ( variationHandlerInitialized ) {
			return;
		}
		variationHandlerInitialized = true;

		function restoreVariationElements() {
			for ( var i = 0; i < variationTestSelectors.length; i++ ) {
				var targetEl = document.querySelector( variationTestSelectors[ i ] );
				if ( ! targetEl ) {
					continue;
				}
				if ( targetEl.style.opacity === '0' ) {
					targetEl.style.opacity = '';
				}
				var computed = window.getComputedStyle( targetEl );
				if ( computed.opacity === '0' ) {
					targetEl.style.opacity = '1';
				}
			}
		}

		jQuery( document.body ).on( 'show_variation', '.single_variation_wrap', function() {
			setTimeout( restoreVariationElements, 50 );
		});

		jQuery( document.body ).on( 'found_variation', '.variations_form', function() {
			setTimeout( restoreVariationElements, 600 );
		});
	}

	// =========================================================================
	// WooCommerce Add-to-Cart Detection
	// =========================================================================

	/**
	 * Whether the WooCommerce add-to-cart listeners have been initialized.
	 * @type {boolean}
	 */
	var addToCartListenerInitialized = false;

	/**
	 * Registered add-to-cart goal callbacks.
	 * Each entry: { testId, variantId, conversionId, revenue, scope }
	 *
	 * @type {Array}
	 */
	var addToCartGoals = [];

	/**
	 * Set up an add-to-cart conversion goal for WooCommerce.
	 *
	 * Listens for WooCommerce add-to-cart events via:
	 * 1. jQuery `added_to_cart` event (AJAX add-to-cart on archive pages)
	 * 2. Click on single-product `.single_add_to_cart_button` (form-based add-to-cart)
	 * 3. Optional scoping via a CSS selector (trigger_selector)
	 *
	 * @param {number} testId          Test ID.
	 * @param {number} variantId       Variant ID.
	 * @param {number} conversionId    Conversion goal ID.
	 * @param {number} revenue         Revenue value.
	 * @param {string} triggerSelector  Optional CSS selector to scope.
	 */
	function setupAddToCartGoal( testId, variantId, conversionId, revenue, triggerSelector ) {
		addToCartGoals.push({
			testId: testId,
			variantId: variantId,
			conversionId: conversionId,
			revenue: revenue,
			scope: triggerSelector || null
		});

		if ( addToCartListenerInitialized ) {
			return;
		}
		addToCartListenerInitialized = true;

		// Strategy 1: WooCommerce AJAX add-to-cart (archive/shop pages).
		// WooCommerce triggers a jQuery `added_to_cart` event on `document.body`.
		if ( typeof jQuery !== 'undefined' ) {
			jQuery( document.body ).on( 'added_to_cart', function( event, fragments, cartHash, $button ) {
				var buttonEl = $button && $button.length ? $button[0] : null;
				var productId = buttonEl ? ( buttonEl.getAttribute( 'data-product_id' ) || '' ) : '';
				var productQty = buttonEl ? ( buttonEl.getAttribute( 'data-quantity' ) || '1' ) : '1';
				var productName = buttonEl ? ( buttonEl.getAttribute( 'data-product_name' ) || '' ) : '';

				fireAddToCartGoals( buttonEl, productId, productName, productQty );
			});
		}

		// Strategy 2: Single product page button click.
		// Uses capture phase so it fires before any intermediate handler
		// (theme, swatch plugin, etc.) can call stopPropagation().
		document.addEventListener( 'click', function( event ) {
			var target = event.target;

			while ( target && target !== document ) {
				if ( target.matches && target.matches( '.single_add_to_cart_button' ) ) {
					fireAddToCartFromButton( target );
					return;
				}
				target = target.parentNode;
			}
		}, true );

		// Strategy 4: WooCommerce cart form submission.
		// Catches add-to-cart even when theme/plugin JS prevents click
		// propagation (e.g., variation swatch plugins, AJAX add-to-cart
		// overrides). Uses capture phase for the same reason as Strategy 2.
		document.addEventListener( 'submit', function( event ) {
			var form = event.target;
			if ( ! form || ! form.classList ) {
				return;
			}
			if ( ! form.classList.contains( 'cart' ) ) {
				return;
			}
			var button = form.querySelector( '.single_add_to_cart_button' );
			fireAddToCartFromButton( button || form );
		}, true );

		// Strategy 3: WooCommerce custom jQuery event on body for any add-to-cart.
		if ( typeof jQuery !== 'undefined' ) {
			jQuery( document.body ).on( 'wc_cart_button_updated', function( event, $button ) {
				if ( ! $button || ! $button.length ) {
					return;
				}
				var buttonEl = $button[0];
				var productId = buttonEl.getAttribute( 'data-product_id' ) || '';
				fireAddToCartGoals( buttonEl, productId, '', '1' );
			});
		}
	}

	/**
	 * Extract product details from a button/form and fire add-to-cart goals.
	 *
	 * Shared by the click handler (Strategy 2) and the form submit handler
	 * (Strategy 4) to avoid duplicated extraction logic.
	 *
	 * @param {Element} element The button or form element.
	 */
	function fireAddToCartFromButton( element ) {
		var form = element.closest ? element.closest( 'form.cart' ) : null;
		var productId = '';
		var productName = '';
		var productQty = '1';

		if ( form ) {
			var pidInput = form.querySelector( 'input[name="add-to-cart"]' ) ||
			               form.querySelector( 'input[name="product_id"]' );
			if ( pidInput ) {
				productId = pidInput.value;
			}
			var qtyInput = form.querySelector( 'input[name="quantity"]' );
			if ( qtyInput ) {
				productQty = qtyInput.value || '1';
			}
		}

		if ( ! productId && element.getAttribute && element.getAttribute( 'value' ) ) {
			productId = element.getAttribute( 'value' );
		}

		fireAddToCartGoals( element, productId, productName, productQty );
	}

	/**
	 * Fire all registered add-to-cart goals, respecting scope selectors.
	 *
	 * @param {Element|null} buttonEl    The button element that triggered the action.
	 * @param {string}       productId   WooCommerce product ID.
	 * @param {string}       productName Product name (may be empty).
	 * @param {string}       productQty  Quantity added.
	 */
	function fireAddToCartGoals( buttonEl, productId, productName, productQty ) {
		for ( var i = 0; i < addToCartGoals.length; i++ ) {
			var goal = addToCartGoals[ i ];
			var shouldFire = true;

			if ( goal.scope ) {
				shouldFire = false;
				if ( buttonEl ) {
					var scopeEl = document.querySelector( goal.scope );
					if ( scopeEl && scopeEl.contains( buttonEl ) ) {
						shouldFire = true;
					}
				}
			}

			if ( shouldFire ) {
				trackAddToCartConversion(
					goal.testId,
					goal.variantId,
					goal.conversionId,
					goal.revenue,
					productId,
					productName,
					productQty
				);
			}
		}
	}

	/**
	 * Send an add-to-cart conversion tracking request with product metadata.
	 *
	 * @param {number} testId       Test ID.
	 * @param {number} variantId    Variant ID.
	 * @param {number} conversionId Conversion goal ID.
	 * @param {number} revenue      Revenue value.
	 * @param {string} productId    WooCommerce product ID.
	 * @param {string} productName  Product name.
	 * @param {string} productQty   Quantity added.
	 */
	function trackAddToCartConversion( testId, variantId, conversionId, revenue, productId, productName, productQty ) {
		var dedupeKey = testId + '_' + conversionId + '_atc_' + productId;

		if ( conversionsSent[ dedupeKey ] ) {
			return;
		}
		conversionsSent[ dedupeKey ] = true;

		var data = {
			action: 'elementtest_track_conversion',
			nonce: elementtestFrontend.nonce,
			test_id: testId,
			variant_id: variantId,
			user_hash: elementtestFrontend.userHash,
			conversion_id: conversionId,
			revenue: revenue || 0,
			page_url: window.location.href,
			product_id: productId || '',
			product_name: productName || '',
			product_qty: productQty || 1
		};

		sendTrackingRequest( data );
	}

	// =========================================================================
	// Element Discovery with MutationObserver
	// =========================================================================

	/**
	 * Attempt to find an element by selector, falling back to a
	 * MutationObserver if the element is not yet in the DOM.
	 *
	 * @param {string}   selector CSS selector.
	 * @param {Function} callback Called with the found element, or null on timeout.
	 * @param {number}   timeout  Maximum wait time in milliseconds.
	 */
	function findElement( selector, callback, timeout ) {
		// First, try to find the element immediately.
		var element = document.querySelector( selector );
		if ( element ) {
			callback( element );
			return;
		}

		// Element not found yet. Set up a MutationObserver to watch for it.
		if ( typeof MutationObserver === 'undefined' ) {
			// MutationObserver not supported; give up after a short delay.
			callback( null );
			return;
		}

		var resolved = false;

		var observer = new MutationObserver( function() {
			if ( resolved ) {
				return;
			}

			var el = document.querySelector( selector );
			if ( el ) {
				resolved = true;
				observer.disconnect();
				callback( el );
			}
		});

		observer.observe( document.documentElement, {
			childList: true,
			subtree: true
		});

		// Safety timeout: stop observing and proceed without the element.
		setTimeout( function() {
			if ( ! resolved ) {
				resolved = true;
				observer.disconnect();
				callback( null );
			}
		}, timeout || OBSERVER_TIMEOUT );
	}

	// =========================================================================
	// Test Processing
	// =========================================================================

	/**
	 * Process a single test: manage cookies, apply changes, track impressions,
	 * and set up conversion goal listeners.
	 *
	 * @param {Object} test Test configuration object from elementtestFrontend.tests.
	 */
	function processTest( test ) {
		var testId = test.test_id;

		// Assign a variant (from cookie or weighted random).
		var variant = assignVariant( test );

		if ( ! variant ) {
			// No variants configured; skip this test.
			removeAntiFlicker();
			return;
		}

		var variantId = variant.variant_id;
		var isControl = parseInt( variant.is_control, 10 ) === 1;

		// Attach the assigned variant to the test object for downstream use.
		test.variant = variant;

		// Store variant info for the public API.
		activeVariants[ testId ] = {
			test_id: testId,
			variant_id: variantId,
			variant_name: variant.name,
			is_control: isControl
		};

		// Register WooCommerce variation visibility handler so the tested
		// element is re-shown after WooCommerce's delayed show_variation
		// event fires, even if the anti-flicker has already been removed.
		setupWooCommerceVariationHandler( test.element_selector );

		// -----------------------------------------------------------------
		// Apply Variant Changes
		// -----------------------------------------------------------------

		if ( isControl ) {
			// Control group: no changes to apply, but still track and set up goals.
			removeAntiFlicker();
			trackImpression( testId, variantId );
			setupGoalListeners( test );
			return;
		}

		// CSS changes do not require finding the DOM element.
		if ( test.test_type === 'css' ) {
			applyVariantChanges( test, null );
			removeAntiFlicker();
			trackImpression( testId, variantId );
			setupGoalListeners( test );
			return;
		}

		// For copy, JS, and image changes we need the target element.
		if ( ! test.element_selector ) {
			// No selector configured; skip gracefully.
			removeAntiFlicker();
			trackImpression( testId, variantId );
			setupGoalListeners( test );
			return;
		}

		findElement( test.element_selector, function( element ) {
			if ( element ) {
				applyVariantChanges( test, element );
			}

			// Remove anti-flicker regardless of whether the element was found.
			removeAntiFlicker();

			// Track impression after changes are applied.
			trackImpression( testId, variantId );

			// Set up conversion goal listeners.
			setupGoalListeners( test );
		}, OBSERVER_TIMEOUT );
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Trigger a conversion for all tests that have a matching custom_event goal.
	 *
	 * This provides a programmatic way for site owners to fire conversions:
	 *   window.elementtest.convert( 'my-purchase', 49.99 );
	 *
	 * @param {string} eventName The custom event name to match against goals.
	 * @param {number} revenue   Optional revenue value.
	 */
	function publicConvert( eventName, revenue ) {
		if ( ! eventName ) {
			return;
		}

		var tests = elementtestFrontend.tests;

		for ( var i = 0; i < tests.length; i++ ) {
			var test = tests[ i ];
			var goals = test.goals;
			var variantId = test.variant.variant_id;

			if ( ! goals ) {
				continue;
			}

			for ( var g = 0; g < goals.length; g++ ) {
				var goal = goals[ g ];

				if ( goal.trigger_type === 'custom_event' && goal.trigger_event === eventName ) {
					trackConversion(
						test.test_id,
						variantId,
						goal.conversion_id,
						revenue || goal.revenue_value || 0
					);
				}
			}
		}
	}

	/**
	 * Get the current variant assignment for a specific test.
	 *
	 * @param {number} testId Test ID.
	 * @return {Object|null} Variant info object or null if not found.
	 */
	function publicGetVariant( testId ) {
		return activeVariants[ testId ] || null;
	}

	// Expose the public API on window.elementtest.
	window.elementtest = {
		convert: publicConvert,
		getVariant: publicGetVariant,
		version: VERSION
	};

	// Also expose the convert function as a global convenience method.
	window.elementtestConvert = publicConvert;

	// =========================================================================
	// Initialization
	// =========================================================================

	/**
	 * Initialize the testing engine.
	 *
	 * Processes all tests provided in the configuration. Sets a safety timeout
	 * for anti-flicker removal in case element discovery takes too long.
	 */
	function init() {
		var tests = elementtestFrontend.tests;

		if ( ! tests || ! tests.length ) {
			removeAntiFlicker();
			return;
		}

		// Safety net: remove anti-flicker after timeout even if something goes wrong.
		setTimeout( removeAntiFlicker, ANTIFLICKER_TIMEOUT );

		// Process each test.
		for ( var i = 0; i < tests.length; i++ ) {
			try {
				processTest( tests[ i ] );
			} catch ( err ) {
				// Log but never break the page.
				if ( typeof console !== 'undefined' && console.warn ) {
					console.warn( '[ElementTest] Error processing test ' + tests[ i ].test_id + ':', err );
				}
			}
		}
	}

	/**
	 * Process conversion-only tests (cross-page pageview goals).
	 *
	 * When a user lands on a page that matches a pageview goal from a test
	 * running on a different page, the server sends minimal test data here.
	 * We read the variant assignment from the cookie and fire the conversion.
	 */
	function processConversionOnlyTests() {
		var tests = elementtestFrontend.conversionOnlyTests;

		if ( ! tests || ! tests.length ) {
			return;
		}

		for ( var i = 0; i < tests.length; i++ ) {
			try {
				var test = tests[ i ];
				var testId = test.test_id;
				var cookieName = 'elementtest_variant_' + testId;
				var assignedVariantId = getCookie( cookieName );

				if ( ! assignedVariantId ) {
					continue;
				}

				var validVariant = false;
				for ( var v = 0; v < test.variant_ids.length; v++ ) {
					if ( String( test.variant_ids[ v ] ) === String( assignedVariantId ) ) {
						validVariant = true;
						break;
					}
				}

				assignedVariantId = parseInt( assignedVariantId, 10 );

				if ( ! validVariant ) {
					continue;
				}

				var goals = test.goals;
				for ( var g = 0; g < goals.length; g++ ) {
					var goal = goals[ g ];
					trackConversion(
						testId,
						assignedVariantId,
						goal.conversion_id,
						goal.revenue_value || 0
					);
				}
			} catch ( err ) {
				if ( typeof console !== 'undefined' && console.warn ) {
					console.warn( '[ElementTest] Error processing conversion-only test:', err );
				}
			}
		}
	}

	// Run initialization.
	// If the DOM is already interactive/complete, run immediately.
	// Otherwise, wait for DOMContentLoaded. This handles both early script
	// loading and deferred/async loading scenarios.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			init();
			processConversionOnlyTests();
		});
	} else {
		init();
		processConversionOnlyTests();
	}

})();
} catch ( fatalError ) {
	// Top-level safety net: never let ElementTest break the host page.
	if ( typeof console !== 'undefined' && console.warn ) {
		console.warn( '[ElementTest] Fatal error:', fatalError );
	}

	// Attempt to remove anti-flicker even on fatal error.
	try {
		var s = document.getElementById( 'elementtest-antiflicker' );
		if ( s && s.parentNode ) {
			s.parentNode.removeChild( s );
		}
	} catch ( e ) {
		// Nothing more we can do.
	}
}
