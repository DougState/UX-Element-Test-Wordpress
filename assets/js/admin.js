/**
 * ElementTest Pro - Admin JavaScript
 *
 * Handles all interactions on the "Add New Test" page including:
 * - Test type switching (show/hide variant change fields)
 * - Adding / removing variants
 * - Traffic auto-balancing and validation
 * - Adding / removing conversion goals
 * - Goal trigger type switching
 * - Page browse modal with AJAX search
 * - Visual element selector placeholder
 * - WordPress media library integration
 * - Schedule toggle
 * - Form collection and AJAX submission
 *
 * @package ElementTestPro
 * @since   1.0.0
 */

/* global jQuery, elementtestAdmin, wp */

(function( $ ) {
	'use strict';

	/**
	 * Return the variant letter for a given zero-based index.
	 * 0 = A, 1 = B, 2 = C, etc.
	 *
	 * @param {number} index
	 * @return {string}
	 */
	function getVariantLetter( index ) {
		return String.fromCharCode( 65 + index );
	}

	/**
	 * Variant colors used by the traffic allocation bar (must match CSS).
	 */
	var VARIANT_COLORS = [
		'#00a32a', // A (control)
		'#2271b1', // B
		'#9b59b6', // C
		'#e67e22', // D
		'#1abc9c', // E
		'#e74c3c', // F
		'#7f8c8d'  // G+
	];

	// =====================================================================
	// Initialization
	// =====================================================================

	$( document ).ready( function() {

		// Results page: pause/start action buttons.
		$( document ).on( 'click', '.etr-action-btn', function() {
			var $btn    = $( this );
			var action  = $btn.data( 'action' );
			var testId  = $btn.data( 'test-id' );
			var status  = ( action === 'pause' ) ? 'paused' : 'running';

			$btn.prop( 'disabled', true ).text( 'Updating...' );

			$.post( elementtestAdmin.ajaxUrl, {
				action:     'elementtest_toggle_status',
				test_id:    testId,
				new_status: status,
				nonce:      elementtestAdmin.nonce
			}, function( response ) {
				if ( response.success ) {
					location.reload();
				} else {
					window.alert( response.data || 'Failed to update status.' );
					$btn.prop( 'disabled', false );
				}
			});
		});

		// Only run new-test form logic on pages that have it.
		if ( ! $( '#elementtest-new-test-form' ).length ) {
			return;
		}

		initTestTypeToggle();
		initVariants();
		initGoals();
		initScheduleToggle();
		initPageBrowseModal();
		initSelectElement();
		initMediaUploads();
		initFormSubmission();

		// If editing an existing test, populate the form with saved data.
		if ( typeof window.elementtestEdit !== 'undefined' && elementtestEdit.test ) {
			populateEditForm( elementtestEdit );
		}

		// Trigger initial traffic bar render.
		updateTrafficBar();
	});

	// =====================================================================
	// 1. Test Type Toggle
	// =====================================================================

	function initTestTypeToggle() {
		$( '#elementtest-test-type' ).on( 'change', function() {
			var type = $( this ).val();
			showChangesFieldsForType( type );
		});
	}

	/**
	 * Show the correct changes input for the selected test type across
	 * all variants, hiding the others.
	 *
	 * @param {string} type  One of: css, copy, javascript, image, or ''
	 */
	function showChangesFieldsForType( type ) {
		var $variants = $( '#elementtest-variants-container .elementtest-variant' );

		$variants.each( function() {
			var $variant = $( this );

			// Skip control variant (index 0) - no change fields.
			if ( $variant.data( 'variant-index' ) === 0 ) {
				return;
			}

			$variant.find( '.elementtest-changes-row' ).hide();

			if ( type ) {
				$variant.find( '.elementtest-changes-' + type ).show();
			}
		});
	}

	// =====================================================================
	// 2. Variants
	// =====================================================================

	function initVariants() {

		// Add variant.
		$( '#elementtest-add-variant' ).on( 'click', addVariant );

		// Remove variant (delegated).
		$( '#elementtest-variants-container' ).on( 'click', '.elementtest-remove-variant', function( e ) {
			e.preventDefault();
			var $variant = $( this ).closest( '.elementtest-variant' );
			removeVariant( $variant );
		});

		// Auto-balance button.
		$( '#elementtest-auto-balance' ).on( 'click', autoBalanceTraffic );

		// Update traffic bar on percentage input change (delegated).
		$( '#elementtest-variants-container' ).on( 'input change', '.elementtest-traffic-input', function() {
			updateTrafficBar();
		});

		// Update remove button visibility based on initial count.
		updateRemoveButtonVisibility();
	}

	/**
	 * Add a new variant to the form.
	 */
	function addVariant() {
		var $container = $( '#elementtest-variants-container' );
		var currentCount = $container.children( '.elementtest-variant' ).length;
		var newIndex = currentCount;
		var testType = $( '#elementtest-test-type' ).val() || 'css';

		// WordPress uses wp.template (Underscore.js template syntax).
		var template = wp.template( 'elementtest-variant' );

		var html = template({
			index:    newIndex,
			letter:   getVariantLetter( newIndex ),
			traffic:  0,
			testType: testType
		});

		$container.append( html );

		// Auto-balance all traffic.
		autoBalanceTraffic();

		// Update remove button visibility.
		updateRemoveButtonVisibility();
	}

	/**
	 * Remove a variant.
	 *
	 * @param {jQuery} $variant The variant element to remove.
	 */
	function removeVariant( $variant ) {
		if ( ! window.confirm( elementtestAdmin.i18n.confirmRemoveVariant ) ) {
			return;
		}

		$variant.slideUp( 200, function() {
			$( this ).remove();
			reindexVariants();
			autoBalanceTraffic();
			updateRemoveButtonVisibility();
		});
	}

	/**
	 * Re-index all variants after one is removed so the name attributes
	 * and data-variant-index stay sequential.
	 */
	function reindexVariants() {
		$( '#elementtest-variants-container .elementtest-variant' ).each( function( i ) {
			var $v = $( this );
			var letter = getVariantLetter( i );

			$v.attr( 'data-variant-index', i );

			// Update badge.
			$v.find( '.elementtest-variant-badge' ).text( letter );

			// Update header label.
			if ( i === 0 ) {
				$v.find( '.elementtest-variant-header strong' ).text( elementtestAdmin.i18n ? 'Control (Variant A)' : 'Control (Variant A)' );
			} else {
				$v.find( '.elementtest-variant-header strong' ).first().text( 'Variant ' + letter );
			}

			// Update name attributes to use new index.
			$v.find( '[name]' ).each( function() {
				var name = $( this ).attr( 'name' );
				$( this ).attr( 'name', name.replace( /variants\[\d+\]/, 'variants[' + i + ']' ) );
			});

			// Update IDs and for attributes.
			$v.find( '[id]' ).each( function() {
				var id = $( this ).attr( 'id' );
				$( this ).attr( 'id', id.replace( /variant-\d+/, 'variant-' + i ) );
			});
			$v.find( 'label[for]' ).each( function() {
				var forAttr = $( this ).attr( 'for' );
				$( this ).attr( 'for', forAttr.replace( /variant-\d+/, 'variant-' + i ) );
			});
			$v.find( '.elementtest-media-upload' ).each( function() {
				var target = $( this ).data( 'target' );
				if ( target ) {
					$( this ).data( 'target', target.replace( /variant-\d+/, 'variant-' + i ) );
					$( this ).attr( 'data-target', target.replace( /variant-\d+/, 'variant-' + i ) );
				}
			});
		});
	}

	/**
	 * Show / hide remove buttons.
	 * The control (A) can never be removed.
	 * Variant B can only be removed if there are 3+ variants.
	 */
	function updateRemoveButtonVisibility() {
		var $variants = $( '#elementtest-variants-container .elementtest-variant' );
		var total = $variants.length;

		$variants.each( function( i ) {
			var $btn = $( this ).find( '.elementtest-remove-variant' );

			if ( i === 0 ) {
				// Control - never removable.
				$btn.hide();
			} else if ( total <= 2 ) {
				// Must have at least A + B.
				$btn.hide();
			} else {
				$btn.show();
			}
		});
	}

	/**
	 * Distribute traffic evenly across all variants.
	 */
	function autoBalanceTraffic() {
		var $inputs = $( '#elementtest-variants-container .elementtest-traffic-input' );
		var count = $inputs.length;

		if ( count === 0 ) {
			return;
		}

		var base = Math.floor( 100 / count );
		var remainder = 100 - ( base * count );

		$inputs.each( function( i ) {
			// Give the remainder to the last variant(s).
			var value = base + ( i >= count - remainder ? 1 : 0 );
			$( this ).val( value );
		});

		updateTrafficBar();
	}

	/**
	 * Redraw the traffic allocation bar and validate total = 100.
	 */
	function updateTrafficBar() {
		var $bar = $( '#elementtest-traffic-bar' );
		var $warning = $( '#elementtest-traffic-warning' );
		var $inputs = $( '#elementtest-variants-container .elementtest-traffic-input' );
		var total = 0;
		var segments = [];

		$inputs.each( function( i ) {
			var val = parseInt( $( this ).val(), 10 ) || 0;
			total += val;
			segments.push({
				letter: getVariantLetter( i ),
				value:  val,
				index:  i
			});
		});

		// Build segments HTML.
		var html = '';
		for ( var s = 0; s < segments.length; s++ ) {
			var seg = segments[ s ];
			var cls = 'elementtest-traffic-segment';
			if ( s === 0 ) {
				cls += ' elementtest-traffic-segment-control';
			}
			var width = total > 0 ? ( ( seg.value / Math.max( total, 100 ) ) * 100 ) : 0;

			html += '<div class="' + cls + '" style="width:' + width + '%;">';
			html += '<span>' + seg.letter + ': ' + seg.value + '%</span>';
			html += '</div>';
		}

		$bar.html( html );

		// Show warning if total != 100.
		if ( total !== 100 ) {
			$warning.show();
		} else {
			$warning.hide();
		}
	}

	// =====================================================================
	// 3. Goals
	// =====================================================================

	function initGoals() {

		// Add goal.
		$( '#elementtest-add-goal' ).on( 'click', addGoal );

		// Remove goal (delegated).
		$( '#elementtest-goals-container' ).on( 'click', '.elementtest-remove-goal', function( e ) {
			e.preventDefault();
			var $goal = $( this ).closest( '.elementtest-goal' );
			removeGoal( $goal );
		});

		// Trigger type change (delegated).
		$( '#elementtest-goals-container' ).on( 'change', '.elementtest-trigger-type-select', function() {
			var $goal = $( this ).closest( '.elementtest-goal' );
			updateGoalTriggerFields( $goal, $( this ).val() );
		});
	}

	/**
	 * Add a new conversion goal.
	 */
	function addGoal() {
		var $container = $( '#elementtest-goals-container' );
		var currentCount = $container.children( '.elementtest-goal' ).length;
		var newIndex = currentCount;

		var template = wp.template( 'elementtest-goal' );

		var html = template({
			index:  newIndex,
			number: newIndex + 1
		});

		$container.append( html );
		updateGoalRemoveVisibility();
	}

	/**
	 * Remove a conversion goal.
	 *
	 * @param {jQuery} $goal
	 */
	function removeGoal( $goal ) {
		if ( ! window.confirm( elementtestAdmin.i18n.confirmRemoveGoal ) ) {
			return;
		}

		$goal.slideUp( 200, function() {
			$( this ).remove();
			reindexGoals();
			updateGoalRemoveVisibility();
		});
	}

	/**
	 * Re-index goals after removal.
	 */
	function reindexGoals() {
		$( '#elementtest-goals-container .elementtest-goal' ).each( function( i ) {
			var $g = $( this );

			$g.attr( 'data-goal-index', i );
			$g.find( '.elementtest-goal-number' ).text( 'Goal ' + ( i + 1 ) );

			// Update name attributes.
			$g.find( '[name]' ).each( function() {
				var name = $( this ).attr( 'name' );
				$( this ).attr( 'name', name.replace( /goals\[\d+\]/, 'goals[' + i + ']' ) );
			});

			// Update IDs.
			$g.find( '[id]' ).each( function() {
				var id = $( this ).attr( 'id' );
				$( this ).attr( 'id', id.replace( /goal-\d+/, 'goal-' + i ) );
			});
			$g.find( 'label[for]' ).each( function() {
				var forAttr = $( this ).attr( 'for' );
				$( this ).attr( 'for', forAttr.replace( /goal-\d+/, 'goal-' + i ) );
			});
		});
	}

	/**
	 * Show/hide remove buttons on goals.
	 * Must have at least one goal.
	 */
	function updateGoalRemoveVisibility() {
		var $goals = $( '#elementtest-goals-container .elementtest-goal' );
		var total = $goals.length;

		$goals.each( function() {
			var $btn = $( this ).find( '.elementtest-remove-goal' );
			if ( total <= 1 ) {
				$btn.hide();
			} else {
				$btn.show();
			}
		});
	}

	/**
	 * Show/hide trigger-specific fields based on trigger type.
	 *
	 * @param {jQuery} $goal       The goal container.
	 * @param {string} triggerType The selected trigger type value.
	 */
	function updateGoalTriggerFields( $goal, triggerType ) {

		// Hide all trigger-specific fields first.
		$goal.find( '.elementtest-trigger-field' ).hide();

		switch ( triggerType ) {
			case 'click':
			case 'form_submit':
				$goal.find( '.elementtest-trigger-selector' ).show();
				break;
			case 'pageview':
				$goal.find( '.elementtest-trigger-url' ).show();
				break;
			case 'custom_event':
				$goal.find( '.elementtest-trigger-custom-event' ).show();
				break;
			case 'video_play':
			case 'add_to_cart':
				$goal.find( '.elementtest-trigger-selector' ).show();
				break;
		}
	}

	// =====================================================================
	// 4. Schedule Toggle
	// =====================================================================

	function initScheduleToggle() {
		var $checkbox = $( '#elementtest-start-immediately' );
		var $dateRows = $( '.elementtest-schedule-dates' );

		$checkbox.on( 'change', function() {
			if ( $( this ).is( ':checked' ) ) {
				$dateRows.hide();
			} else {
				$dateRows.show();
			}
		});
	}

	// =====================================================================
	// 5. Page Browse Modal
	// =====================================================================

	function initPageBrowseModal() {
		var $modal = $( '#elementtest-page-modal' );
		var searchTimer = null;

		// Open modal.
		$( '#elementtest-browse-pages' ).on( 'click', function() {
			$modal.show();
			$( '#elementtest-page-search' ).val( '' ).trigger( 'focus' );
			$( '#elementtest-page-results' ).html(
				'<p class="description">' + ( elementtestAdmin.i18n.searching ? elementtestAdmin.i18n.searching.replace( 'Searching...', 'Type to search your published pages.' ) : 'Type to search your published pages.' ) + '</p>'
			);
		});

		// Close modal.
		$modal.on( 'click', '.elementtest-modal-close, .elementtest-modal-cancel, .elementtest-modal-backdrop', function() {
			$modal.hide();
		});

		// ESC to close.
		$( document ).on( 'keydown', function( e ) {
			if ( e.key === 'Escape' && $modal.is( ':visible' ) ) {
				$modal.hide();
			}
		});

		// Search pages with debounce.
		$( '#elementtest-page-search' ).on( 'input', function() {
			var query = $( this ).val();

			clearTimeout( searchTimer );

			if ( query.length < 2 ) {
				$( '#elementtest-page-results' ).html(
					'<p class="description">Type at least 2 characters to search.</p>'
				);
				return;
			}

			$( '#elementtest-page-results' ).html(
				'<p class="description">' + elementtestAdmin.i18n.searching + '</p>'
			);

			searchTimer = setTimeout( function() {
				searchPages( query );
			}, 300 );
		});

		// Select a page result (delegated).
		$( '#elementtest-page-results' ).on( 'click', '.elementtest-page-result-item', function() {
			var url = $( this ).data( 'url' );
			var pageId = $( this ).data( 'page-id' );

			$( '#elementtest-page-url' ).val( url );
			$( '#elementtest-page-id' ).val( pageId );
			$modal.hide();
		});

		// Keyboard navigation within results.
		$( '#elementtest-page-results' ).on( 'keydown', '.elementtest-page-result-item', function( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				$( this ).trigger( 'click' );
			}
		});
	}

	/**
	 * AJAX search for WordPress pages.
	 *
	 * @param {string} query Search term.
	 */
	function searchPages( query ) {
		$.ajax({
			url:  elementtestAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'elementtest_search_pages',
				nonce:  elementtestAdmin.nonce,
				search: query
			},
			success: function( response ) {
				if ( response.success && response.data.length > 0 ) {
					var html = '';
					for ( var i = 0; i < response.data.length; i++ ) {
						var page = response.data[ i ];
						html += '<div class="elementtest-page-result-item" tabindex="0" ';
						html += 'data-url="' + escAttr( page.url ) + '" ';
						html += 'data-page-id="' + escAttr( page.id ) + '">';
						html += '<span class="elementtest-page-result-title">' + escHtml( page.title ) + '</span>';
						html += '<span class="elementtest-page-result-url">' + escHtml( page.url ) + '</span>';
						html += '</div>';
					}
					$( '#elementtest-page-results' ).html( html );
				} else {
					$( '#elementtest-page-results' ).html(
						'<p class="description">' + elementtestAdmin.i18n.noPages + '</p>'
					);
				}
			},
			error: function() {
				$( '#elementtest-page-results' ).html(
					'<p class="description">An error occurred. Please try again.</p>'
				);
			}
		});
	}

	// =====================================================================
	// 6. Visual Element Selector (placeholder)
	// =====================================================================

	function initSelectElement() {
		$( '#elementtest-select-element' ).on( 'click', function() {
			var pageUrl = $( '#elementtest-page-url' ).val();

			if ( ! pageUrl ) {
				window.alert( 'Please enter a Page URL first.' );
				return;
			}

			// Check if the visual selector is available.
			if ( typeof window.ElementTestSelector !== 'undefined' ) {
				window.ElementTestSelector.open( pageUrl, function( result ) {
					// Populate the selector input.
					$( '#elementtest-selector' ).val( result.selector );

					// Update element path preview.
					var pathHtml = '';
					for ( var i = 0; i < result.path.length; i++ ) {
						if ( i > 0 ) {
							pathHtml += '<span class="elementtest-path-separator">&rsaquo;</span>';
						}
						var cls = ( i === result.path.length - 1 ) ? 'elementtest-path-node' : 'elementtest-path-node';
						pathHtml += '<span class="' + cls + '">' + escHtml( result.path[ i ] ) + '</span>';
					}
					$( '#elementtest-element-path' ).html( pathHtml );
				});
			} else {
				// Fallback: manual entry.
				$( '#elementtest-selector' ).prop( 'readonly', false ).trigger( 'focus' );
			}
		});
	}

	// =====================================================================
	// 7. WordPress Media Library
	// =====================================================================

	function initMediaUploads() {
		$( document ).on( 'click', '.elementtest-media-upload', function( e ) {
			e.preventDefault();

			var $button = $( this );
			var targetId = $button.data( 'target' );
			var $input = $( '#' + targetId );

			// Create a WP media frame.
			var frame = wp.media({
				title:    elementtestAdmin.i18n.selectImage,
				button:   { text: elementtestAdmin.i18n.useImage },
				multiple: false,
				library:  { type: 'image' }
			});

			frame.on( 'select', function() {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$input.val( attachment.url );
			});

			frame.open();
		});
	}

	// =====================================================================
	// 8. Form Collection & AJAX Submission
	// =====================================================================

	function initFormSubmission() {
		$( '#elementtest-save-draft' ).on( 'click', function() {
			submitTest( 'draft' );
		});

		$( '#elementtest-start-test' ).on( 'click', function() {
			submitTest( 'running' );
		});
	}

	/**
	 * Validate the form, collect data, and submit via AJAX.
	 *
	 * @param {string} status  The test status to save: 'draft' or 'running'.
	 */
	function submitTest( status ) {

		// Basic validation.
		var testName = $( '#elementtest-test-name' ).val().trim();
		var testType = $( '#elementtest-test-type' ).val();
		var pageUrl  = $( '#elementtest-page-url' ).val().trim();

		if ( ! testName || ! testType || ! pageUrl ) {
			showNotice( elementtestAdmin.i18n.requiredFields, 'error' );
			highlightRequiredFields();
			return;
		}

		// Validate traffic total.
		var trafficTotal = 0;
		$( '.elementtest-traffic-input' ).each( function() {
			trafficTotal += parseInt( $( this ).val(), 10 ) || 0;
		});
		if ( trafficTotal !== 100 ) {
			showNotice( elementtestAdmin.i18n.trafficWarning, 'error' );
			return;
		}

		// Collect test data.
		var testId = $( '#elementtest-test-id' ).val() || 0;

		var data = {
			action:           'elementtest_save_test',
			nonce:            elementtestAdmin.nonce,
			test_id:          testId,
			status:           status,
			test_name:        testName,
			description:      $( '#elementtest-description' ).val(),
			test_type:        testType,
			page_url:         pageUrl,
			page_id:          $( '#elementtest-page-id' ).val(),
			element_selector: $( '#elementtest-selector' ).val(),
			start_immediately: $( '#elementtest-start-immediately' ).is( ':checked' ) ? 1 : 0,
			start_date:       $( '#elementtest-start-date' ).val(),
			end_date:         $( '#elementtest-end-date' ).val(),
			variants:         collectVariants( testType ),
			goals:            collectGoals()
		};

		// Show saving indicator.
		showSavingIndicator();

		$.ajax({
			url:     elementtestAdmin.ajaxUrl,
			type:    'POST',
			data:    data,
			success: function( response ) {
				if ( response.success ) {
					showNotice( elementtestAdmin.i18n.saved, 'success' );

					// Redirect to edit page or test list after a short delay.
					if ( response.data && response.data.redirect ) {
						setTimeout( function() {
							window.location.href = response.data.redirect;
						}, 1000 );
					}
				} else {
					var msg = ( response.data && response.data.message )
						? response.data.message
						: elementtestAdmin.i18n.saveError;
					showNotice( msg, 'error' );
				}
			},
			error: function() {
				showNotice( elementtestAdmin.i18n.saveError, 'error' );
			},
			complete: function() {
				hideSavingIndicator();
			}
		});
	}

	/**
	 * Collect variant data from the form.
	 *
	 * @param {string} testType The active test type.
	 * @return {Array} Array of variant objects.
	 */
	function collectVariants( testType ) {
		var variants = [];

		$( '#elementtest-variants-container .elementtest-variant' ).each( function( i ) {
			var $v = $( this );
			var variant = {
				name:       $v.find( 'input[name$="[name]"]' ).val() || getVariantLetter( i ),
				is_control: parseInt( $v.find( 'input[name$="[is_control]"]' ).val(), 10 ) || 0,
				traffic:    parseInt( $v.find( '.elementtest-traffic-input' ).val(), 10 ) || 0,
				changes:    ''
			};

			// Include variant_id for upsert when editing.
			var variantId = $v.find( 'input[name$="[variant_id]"]' ).val();
			if ( variantId ) {
				variant.variant_id = parseInt( variantId, 10 );
			}

			// Only collect changes for non-control variants.
			if ( ! variant.is_control ) {
				switch ( testType ) {
					case 'css':
						variant.changes = $v.find( 'textarea[name$="[changes_css]"]' ).val() || '';
						break;
					case 'copy':
						variant.changes = $v.find( 'input[name$="[changes_copy]"]' ).val() || '';
						break;
					case 'js':
						variant.changes = $v.find( 'textarea[name$="[changes_js]"]' ).val() || '';
						break;
					case 'image':
						variant.changes = $v.find( 'input[name$="[changes_image]"]' ).val() || '';
						break;
				}
			}

			variants.push( variant );
		});

		return variants;
	}

	/**
	 * Collect goal data from the form.
	 *
	 * @return {Array} Array of goal objects.
	 */
	function collectGoals() {
		var goals = [];

		$( '#elementtest-goals-container .elementtest-goal' ).each( function() {
			var $g = $( this );
			var triggerType = $g.find( 'select[name$="[trigger_type]"]' ).val();

			var goal = {
				name:             $g.find( 'input[name$="[name]"]' ).val() || '',
				trigger_type:     triggerType,
				trigger_selector: '',
				trigger_url:      '',
				custom_event:     '',
				revenue_value:    parseFloat( $g.find( 'input[name$="[revenue_value]"]' ).val() ) || 0
			};

			// Include conversion_id for upsert when editing.
			var conversionId = $g.find( 'input[name$="[conversion_id]"]' ).val();
			if ( conversionId ) {
				goal.conversion_id = parseInt( conversionId, 10 );
			}

			switch ( triggerType ) {
				case 'click':
				case 'form_submit':
					goal.trigger_selector = $g.find( 'input[name$="[trigger_selector]"]' ).val() || '';
					break;
				case 'pageview':
					goal.trigger_url = $g.find( 'input[name$="[trigger_url]"]' ).val() || '';
					break;
				case 'custom_event':
					goal.custom_event = $g.find( 'input[name$="[custom_event]"]' ).val() || '';
					break;
				case 'video_play':
				case 'add_to_cart':
					goal.trigger_selector = $g.find( 'input[name$="[trigger_selector]"]' ).val() || '';
					break;
			}

			goals.push( goal );
		});

		return goals;
	}

	// =====================================================================
	// 9. Edit Mode — Populate Form with Existing Data
	// =====================================================================

	/**
	 * Populate the test form with existing test data for editing.
	 *
	 * Rebuilds the variants and goals sections dynamically to match
	 * the saved data, including control traffic and variant changes.
	 *
	 * @param {Object} editData  Object with test, variants, and goals arrays.
	 */
	function populateEditForm( editData ) {
		var test     = editData.test;
		var variants = editData.variants || [];
		var goals    = editData.goals || [];
		var testType = test.test_type || '';

		// Show the correct changes fields for the saved test type.
		showChangesFieldsForType( testType );

		// --- Rebuild variants ---
		var $container = $( '#elementtest-variants-container' );

		// Remove the default Variant B (index 1) if it exists — we'll rebuild from data.
		$container.children( '.elementtest-variant' ).not( '.elementtest-variant-control' ).remove();

		// Populate the control variant (always index 0).
		var controlVariant = null;
		var nonControlVariants = [];

		for ( var i = 0; i < variants.length; i++ ) {
			if ( parseInt( variants[ i ].is_control, 10 ) === 1 ) {
				controlVariant = variants[ i ];
			} else {
				nonControlVariants.push( variants[ i ] );
			}
		}

		if ( controlVariant ) {
			var $control = $container.find( '.elementtest-variant-control' );
			$control.find( '.elementtest-traffic-input' ).val( controlVariant.traffic_percentage );
			// Store variant_id for upsert.
			if ( controlVariant.variant_id ) {
				$control.append( '<input type="hidden" name="variants[0][variant_id]" value="' + escAttr( controlVariant.variant_id ) + '">' );
			}
		}

		// Add non-control variants.
		for ( var v = 0; v < nonControlVariants.length; v++ ) {
			var vData   = nonControlVariants[ v ];
			var vIndex  = v + 1; // 0 is control.
			var vLetter = getVariantLetter( vIndex );

			var template = wp.template( 'elementtest-variant' );
			var html = template({
				index:    vIndex,
				letter:   vLetter,
				traffic:  vData.traffic_percentage || 0,
				testType: testType
			});

			$container.append( html );

			// Get the just-appended variant.
			var $newVariant = $container.children( '.elementtest-variant' ).last();

			// Set variant name.
			$newVariant.find( 'input[name$="[name]"]' ).val( vData.name || vLetter );

			// Store variant_id for upsert.
			if ( vData.variant_id ) {
				$newVariant.append( '<input type="hidden" name="variants[' + vIndex + '][variant_id]" value="' + escAttr( vData.variant_id ) + '">' );
			}

			// Populate changes field for the appropriate test type.
			var changes = vData.changes || '';
			switch ( testType ) {
				case 'css':
					$newVariant.find( 'textarea[name$="[changes_css]"]' ).val( changes );
					break;
				case 'copy':
					$newVariant.find( 'input[name$="[changes_copy]"]' ).val( changes );
					break;
				case 'js':
					$newVariant.find( 'textarea[name$="[changes_js]"]' ).val( changes );
					break;
				case 'image':
					$newVariant.find( 'input[name$="[changes_image]"]' ).val( changes );
					break;
			}
		}

		updateRemoveButtonVisibility();

		// --- Rebuild goals ---
		var $goalsContainer = $( '#elementtest-goals-container' );

		if ( goals.length > 0 ) {
			// Remove the default empty goal.
			$goalsContainer.children( '.elementtest-goal' ).remove();

			for ( var g = 0; g < goals.length; g++ ) {
				var gData = goals[ g ];

				var goalTemplate = wp.template( 'elementtest-goal' );
				var goalHtml = goalTemplate({
					index:  g,
					number: g + 1
				});

				$goalsContainer.append( goalHtml );

				var $newGoal = $goalsContainer.children( '.elementtest-goal' ).last();

				// Populate goal fields.
				$newGoal.find( 'input[name$="[name]"]' ).val( gData.name || '' );

				var triggerType = gData.trigger_type || 'click';
				$newGoal.find( 'select[name$="[trigger_type]"]' ).val( triggerType );
				updateGoalTriggerFields( $newGoal, triggerType );

				// Fill trigger-specific fields.
				$newGoal.find( 'input[name$="[trigger_selector]"]' ).val( gData.trigger_selector || '' );

				// trigger_event stores the URL or custom event name.
				if ( triggerType === 'pageview' ) {
					$newGoal.find( 'input[name$="[trigger_url]"]' ).val( gData.trigger_event || '' );
				} else if ( triggerType === 'custom_event' ) {
					$newGoal.find( 'input[name$="[custom_event]"]' ).val( gData.trigger_event || '' );
				}

				$newGoal.find( 'input[name$="[revenue_value]"]' ).val( parseFloat( gData.revenue_value ) || '' );

				// Store conversion_id for upsert.
				if ( gData.conversion_id ) {
					$newGoal.append( '<input type="hidden" name="goals[' + g + '][conversion_id]" value="' + escAttr( gData.conversion_id ) + '">' );
				}
			}

			updateGoalRemoveVisibility();
		}

		// --- Schedule ---
		if ( test.start_date && test.start_date !== '0000-00-00 00:00:00' ) {
			$( '#elementtest-start-immediately' ).prop( 'checked', false ).trigger( 'change' );
			// Format date to YYYY-MM-DD for date input.
			$( '#elementtest-start-date' ).val( test.start_date.substring( 0, 10 ) );
		}
		if ( test.end_date && test.end_date !== '0000-00-00 00:00:00' ) {
			$( '#elementtest-end-date' ).val( test.end_date.substring( 0, 10 ) );
		}

		// Redraw the traffic bar with the loaded values.
		updateTrafficBar();
	}

	// =====================================================================
	// UI Helpers
	// =====================================================================

	/**
	 * Highlight required fields that are empty.
	 */
	function highlightRequiredFields() {
		var fields = [
			'#elementtest-test-name',
			'#elementtest-test-type',
			'#elementtest-page-url'
		];

		for ( var i = 0; i < fields.length; i++ ) {
			var $field = $( fields[ i ] );
			if ( ! $field.val() ) {
				$field.css( 'border-color', '#d63638' );

				// Remove highlight after user interacts.
				$field.one( 'input change', function() {
					$( this ).css( 'border-color', '' );
				});
			}
		}

		// Focus the first empty required field.
		for ( var j = 0; j < fields.length; j++ ) {
			if ( ! $( fields[ j ] ).val() ) {
				$( fields[ j ] ).trigger( 'focus' );
				break;
			}
		}
	}

	/**
	 * Display a temporary notice message.
	 *
	 * @param {string} message The message text.
	 * @param {string} type    'success' or 'error'.
	 */
	function showNotice( message, type ) {
		// Remove any existing notices we added.
		$( '.elementtest-ajax-notice' ).remove();

		var cls = ( type === 'error' ) ? 'notice-error' : 'notice-success';
		var $notice = $(
			'<div class="notice ' + cls + ' is-dismissible elementtest-ajax-notice">' +
			'<p>' + escHtml( message ) + '</p>' +
			'<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
			'</div>'
		);

		$( '.elementtest-wrap .wp-header-end' ).after( $notice );

		// Dismiss button.
		$notice.find( '.notice-dismiss' ).on( 'click', function() {
			$notice.fadeOut( 200, function() {
				$( this ).remove();
			});
		});

		// Auto-dismiss after 6 seconds.
		setTimeout( function() {
			$notice.fadeOut( 200, function() {
				$( this ).remove();
			});
		}, 6000 );

		// Scroll to top so the notice is visible.
		$( 'html, body' ).animate({ scrollTop: 0 }, 300 );
	}

	/**
	 * Show the saving indicator spinner.
	 */
	function showSavingIndicator() {
		$( '#elementtest-saving-indicator' )
			.removeClass( 'elementtest-save-success elementtest-save-error' )
			.show();

		// Disable submit buttons.
		$( '#elementtest-save-draft, #elementtest-start-test' ).prop( 'disabled', true );
	}

	/**
	 * Hide the saving indicator spinner.
	 */
	function hideSavingIndicator() {
		$( '#elementtest-saving-indicator' ).fadeOut( 300 );

		// Re-enable submit buttons.
		$( '#elementtest-save-draft, #elementtest-start-test' ).prop( 'disabled', false );
	}

	/**
	 * Minimal HTML escaping for output in JS-generated markup.
	 *
	 * @param {string} str
	 * @return {string}
	 */
	function escHtml( str ) {
		if ( ! str ) {
			return '';
		}
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	/**
	 * Escape a value for use in an HTML attribute.
	 *
	 * @param {*} val
	 * @return {string}
	 */
	function escAttr( val ) {
		return escHtml( String( val ) ).replace( /"/g, '&quot;' );
	}

})( jQuery );
