<?php
/**
 * Template: Add New Test page.
 *
 * Renders the complete test creation form for ElementTest Pro.
 * This file is included by ElementTest_Pro::render_new_test_page().
 *
 * @package ElementTestPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Define test type options centrally so the select and JavaScript
 * can reference the same list.
 */
$test_types = array(
	'css'        => __( 'CSS', 'elementtest-pro' ),
	'copy'       => __( 'Copy', 'elementtest-pro' ),
	'js'         => __( 'JavaScript', 'elementtest-pro' ),
	'image'      => __( 'Image', 'elementtest-pro' ),
);

$goal_trigger_types = array(
	'click'        => __( 'Click', 'elementtest-pro' ),
	'pageview'     => __( 'Page View', 'elementtest-pro' ),
	'form_submit'  => __( 'Form Submit', 'elementtest-pro' ),
	'custom_event' => __( 'Custom Event', 'elementtest-pro' ),
	'video_play'   => __( 'Video Play (YouTube)', 'elementtest-pro' ),
	'add_to_cart'  => __( 'Add to Cart (WooCommerce)', 'elementtest-pro' ),
);

// Determine edit mode from $test_data passed by render_new_test_page().
$is_edit   = ! empty( $test_data['test'] );
$test      = $is_edit ? $test_data['test'] : array();
$edit_id   = $is_edit ? absint( $test['test_id'] ) : 0;
$edit_name = $is_edit ? $test['name'] : '';
?>

<div class="wrap elementtest-wrap">

	<h1 class="wp-heading-inline">
		<?php
		if ( $is_edit ) {
			/* translators: %s: test name */
			printf( esc_html__( 'Edit Test: %s', 'elementtest-pro' ), esc_html( $edit_name ) );
		} else {
			esc_html_e( 'Add New Test', 'elementtest-pro' );
		}
		?>
	</h1>
	<hr class="wp-header-end">

	<p class="description" style="margin:8px 0 16px;">
		<?php esc_html_e( 'Tip: To track purchase conversions, add a Page View goal with a wildcard Trigger URL, e.g.', 'elementtest-pro' ); ?>
		<code>https://yoursite.com/checkout/order-received/*</code>
		<?php esc_html_e( 'The * matches any order number or query string that follows.', 'elementtest-pro' ); ?>
	</p>


	<div id="elementtest-new-test-form" class="elementtest-admin-wrapper" data-test-id="<?php echo esc_attr( $edit_id ); ?>">

		<?php wp_nonce_field( 'elementtest_save_test', 'elementtest_nonce' ); ?>
		<?php if ( $is_edit ) : ?>
			<input type="hidden" id="elementtest-test-id" name="test_id" value="<?php echo esc_attr( $edit_id ); ?>">
		<?php endif; ?>

		<!-- ============================================================
		     Section 1: Test Details
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Test Details', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tbody>
						<!-- Test Name -->
						<tr>
							<th scope="row">
								<label for="elementtest-test-name">
									<?php esc_html_e( 'Test Name', 'elementtest-pro' ); ?>
									<span class="required" aria-label="<?php esc_attr_e( 'Required', 'elementtest-pro' ); ?>">*</span>
								</label>
							</th>
							<td>
								<input
									type="text"
									id="elementtest-test-name"
									name="test_name"
									class="regular-text"
									required
									placeholder="<?php esc_attr_e( 'e.g. Hero Button Color Test', 'elementtest-pro' ); ?>"
									value="<?php echo esc_attr( $is_edit ? $test['name'] : '' ); ?>"
								>
							</td>
						</tr>

						<!-- Description -->
						<tr>
							<th scope="row">
								<label for="elementtest-description">
									<?php esc_html_e( 'Description', 'elementtest-pro' ); ?>
								</label>
							</th>
							<td>
								<textarea
									id="elementtest-description"
									name="description"
									rows="3"
									class="large-text"
									placeholder="<?php esc_attr_e( 'Briefly describe the hypothesis and purpose of this test.', 'elementtest-pro' ); ?>"
								><?php echo esc_textarea( $is_edit ? $test['description'] : '' ); ?></textarea>
							</td>
						</tr>

						<!-- Test Type -->
						<tr>
							<th scope="row">
								<label for="elementtest-test-type">
									<?php esc_html_e( 'Test Type', 'elementtest-pro' ); ?>
									<span class="required" aria-label="<?php esc_attr_e( 'Required', 'elementtest-pro' ); ?>">*</span>
								</label>
							</th>
							<td>
								<select id="elementtest-test-type" name="test_type" class="regular-text">
									<option value=""><?php esc_html_e( '-- Select a test type --', 'elementtest-pro' ); ?></option>
									<?php foreach ( $test_types as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $is_edit && isset( $test['test_type'] ) ? $test['test_type'] : '', $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description" id="elementtest-test-type-desc">
									<?php esc_html_e( 'Choose what kind of element change each variant will apply.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>

						<!-- Page URL -->
						<tr>
							<th scope="row">
								<label for="elementtest-page-url">
									<?php esc_html_e( 'Page URL', 'elementtest-pro' ); ?>
									<span class="required" aria-label="<?php esc_attr_e( 'Required', 'elementtest-pro' ); ?>">*</span>
								</label>
							</th>
							<td>
								<div class="elementtest-input-group">
									<input
										type="url"
										id="elementtest-page-url"
										name="page_url"
										class="regular-text"
										required
										placeholder="<?php echo esc_attr( home_url( '/example-page/' ) ); ?>"
										value="<?php echo esc_attr( $is_edit ? $test['page_url'] : '' ); ?>"
									>
									<button
										type="button"
										id="elementtest-browse-pages"
										class="button"
									>
										<?php esc_html_e( 'Browse', 'elementtest-pro' ); ?>
									</button>
								</div>
								<input type="hidden" id="elementtest-page-id" name="page_id" value="">
								<p class="description">
									<?php esc_html_e( 'The page where this A/B test will run. Click Browse to search your WordPress pages.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Page browse modal -->
		<div id="elementtest-page-modal" class="elementtest-modal" style="display:none;" role="dialog" aria-labelledby="elementtest-page-modal-title" aria-modal="true">
			<div class="elementtest-modal-backdrop"></div>
			<div class="elementtest-modal-content">
				<div class="elementtest-modal-header">
					<h3 id="elementtest-page-modal-title"><?php esc_html_e( 'Select a Page', 'elementtest-pro' ); ?></h3>
					<button type="button" class="elementtest-modal-close" aria-label="<?php esc_attr_e( 'Close', 'elementtest-pro' ); ?>">&times;</button>
				</div>
				<div class="elementtest-modal-body">
					<input
						type="search"
						id="elementtest-page-search"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'Search pages...', 'elementtest-pro' ); ?>"
					>
					<div id="elementtest-page-results" class="elementtest-page-results">
						<p class="description"><?php esc_html_e( 'Type to search your published pages.', 'elementtest-pro' ); ?></p>
					</div>
				</div>
				<div class="elementtest-modal-footer">
					<button type="button" class="button elementtest-modal-cancel">
						<?php esc_html_e( 'Cancel', 'elementtest-pro' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- ============================================================
		     Section 2: Element Selection
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Element Selection', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tbody>
						<!-- CSS Selector -->
						<tr>
							<th scope="row">
								<label for="elementtest-selector">
									<?php esc_html_e( 'Element CSS Selector', 'elementtest-pro' ); ?>
								</label>
							</th>
							<td>
								<div class="elementtest-input-group">
									<input
										type="text"
										id="elementtest-selector"
										name="element_selector"
										class="regular-text code"
										readonly
										placeholder="<?php esc_attr_e( 'Click "Select Element" to choose an element', 'elementtest-pro' ); ?>"
										value="<?php echo esc_attr( $is_edit ? $test['element_selector'] : '' ); ?>"
									>
									<button
										type="button"
										id="elementtest-select-element"
										class="button button-secondary"
									>
										<span class="dashicons dashicons-move" style="vertical-align: middle; margin-top:-2px;"></span>
										<?php esc_html_e( 'Select Element', 'elementtest-pro' ); ?>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'The CSS selector that identifies the element to test. Use the visual selector or enter manually.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>

						<!-- Element Path Preview -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Element Path', 'elementtest-pro' ); ?>
							</th>
							<td>
								<div id="elementtest-element-path" class="elementtest-element-path">
									<span class="elementtest-path-empty">
										<?php esc_html_e( 'No element selected.', 'elementtest-pro' ); ?>
									</span>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- ============================================================
		     Section 3: Variants
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Variants', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">

				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'Define the variants for this test. The Control shows the original element unchanged. Add one or more variants with modifications.', 'elementtest-pro' ); ?>
				</p>

				<div id="elementtest-variants-container">

					<!-- Control (Variant A) - always present -->
					<div class="elementtest-variant elementtest-variant-control" data-variant-index="0">
						<div class="elementtest-variant-header">
							<span class="elementtest-variant-badge elementtest-badge-control">A</span>
							<strong><?php esc_html_e( 'Control (Variant A)', 'elementtest-pro' ); ?></strong>
							<span class="elementtest-variant-label"><?php esc_html_e( 'Original (no changes)', 'elementtest-pro' ); ?></span>
						</div>
						<div class="elementtest-variant-body">
							<table class="form-table" role="presentation">
								<tbody>
									<tr>
										<th scope="row">
											<label><?php esc_html_e( 'Traffic Percentage', 'elementtest-pro' ); ?></label>
										</th>
										<td>
											<input
												type="number"
												name="variants[0][traffic]"
												class="small-text elementtest-traffic-input"
												value="50"
												min="0"
												max="100"
												step="1"
											>
											<span>%</span>
											<input type="hidden" name="variants[0][name]" value="Control">
											<input type="hidden" name="variants[0][is_control]" value="1">
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Variant B - default second variant -->
					<div class="elementtest-variant" data-variant-index="1">
						<div class="elementtest-variant-header">
							<span class="elementtest-variant-badge">B</span>
							<strong><?php esc_html_e( 'Variant B', 'elementtest-pro' ); ?></strong>
							<button type="button" class="elementtest-remove-variant button-link" aria-label="<?php esc_attr_e( 'Remove this variant', 'elementtest-pro' ); ?>" style="display:none;">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
						<div class="elementtest-variant-body">
							<table class="form-table" role="presentation">
								<tbody>
									<tr>
										<th scope="row">
											<label for="elementtest-variant-1-name">
												<?php esc_html_e( 'Variant Name', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<input
												type="text"
												id="elementtest-variant-1-name"
												name="variants[1][name]"
												class="regular-text"
												value="<?php esc_attr_e( 'Variant B', 'elementtest-pro' ); ?>"
											>
											<input type="hidden" name="variants[1][is_control]" value="0">
										</td>
									</tr>

									<!-- CSS changes -->
									<tr class="elementtest-changes-row elementtest-changes-css">
										<th scope="row">
											<label for="elementtest-variant-1-css">
												<?php esc_html_e( 'CSS Rules', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<textarea
												id="elementtest-variant-1-css"
												name="variants[1][changes_css]"
												class="large-text code elementtest-code-editor"
												rows="6"
												placeholder="<?php esc_attr_e( "/* CSS changes for this variant */\ncolor: #ff0000;\nfont-size: 18px;", 'elementtest-pro' ); ?>"
											></textarea>
											<p class="description">
												<?php esc_html_e( 'Enter CSS property declarations to apply to the selected element.', 'elementtest-pro' ); ?>
											</p>
										</td>
									</tr>

									<!-- Copy changes -->
									<tr class="elementtest-changes-row elementtest-changes-copy" style="display:none;">
										<th scope="row">
											<label for="elementtest-variant-1-copy">
												<?php esc_html_e( 'Replacement Text', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<input
												type="text"
												id="elementtest-variant-1-copy"
												name="variants[1][changes_copy]"
												class="large-text"
												placeholder="<?php esc_attr_e( 'Enter the replacement text for this variant', 'elementtest-pro' ); ?>"
											>
											<p class="description">
												<?php esc_html_e( 'The text content that will replace the original element text.', 'elementtest-pro' ); ?>
											</p>
										</td>
									</tr>

									<!-- JavaScript changes -->
									<tr class="elementtest-changes-row elementtest-changes-js" style="display:none;">
										<th scope="row">
											<label for="elementtest-variant-1-js">
												<?php esc_html_e( 'JavaScript Code', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<textarea
												id="elementtest-variant-1-js"
												name="variants[1][changes_js]"
												class="large-text code elementtest-code-editor"
												rows="6"
												placeholder="<?php esc_attr_e( "// JavaScript to modify the element\n// 'el' refers to the selected element\nel.style.backgroundColor = '#0073aa';", 'elementtest-pro' ); ?>"
											></textarea>
											<p class="description">
												<?php esc_html_e( 'JavaScript code that will be executed to modify the selected element.', 'elementtest-pro' ); ?>
											</p>
										</td>
									</tr>

									<!-- Image changes -->
									<tr class="elementtest-changes-row elementtest-changes-image" style="display:none;">
										<th scope="row">
											<label for="elementtest-variant-1-image">
												<?php esc_html_e( 'Image URL', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<div class="elementtest-input-group">
												<input
													type="url"
													id="elementtest-variant-1-image"
													name="variants[1][changes_image]"
													class="regular-text"
													placeholder="<?php esc_attr_e( 'https://example.com/image.jpg', 'elementtest-pro' ); ?>"
												>
												<button
													type="button"
													class="button elementtest-media-upload"
													data-target="elementtest-variant-1-image"
												>
													<span class="dashicons dashicons-admin-media" style="vertical-align:middle; margin-top:-2px;"></span>
													<?php esc_html_e( 'Media Library', 'elementtest-pro' ); ?>
												</button>
											</div>
											<p class="description">
												<?php esc_html_e( 'URL of the replacement image. Use the Media Library button to select from uploaded images.', 'elementtest-pro' ); ?>
											</p>
										</td>
									</tr>

									<!-- Traffic Percentage -->
									<tr>
										<th scope="row">
											<label for="elementtest-variant-1-traffic">
												<?php esc_html_e( 'Traffic Percentage', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<input
												type="number"
												id="elementtest-variant-1-traffic"
												name="variants[1][traffic]"
												class="small-text elementtest-traffic-input"
												value="50"
												min="0"
												max="100"
												step="1"
											>
											<span>%</span>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

				</div><!-- #elementtest-variants-container -->

				<!-- Traffic allocation bar -->
				<div class="elementtest-traffic-bar-wrapper">
					<label><?php esc_html_e( 'Traffic Allocation', 'elementtest-pro' ); ?></label>
					<div id="elementtest-traffic-bar" class="elementtest-traffic-bar">
						<div class="elementtest-traffic-segment elementtest-traffic-segment-control" style="width:50%;">
							<span>A: 50%</span>
						</div>
						<div class="elementtest-traffic-segment" style="width:50%;">
							<span>B: 50%</span>
						</div>
					</div>
					<div id="elementtest-traffic-warning" class="notice notice-warning inline" style="display:none;">
						<p><?php esc_html_e( 'Traffic percentages must add up to 100%.', 'elementtest-pro' ); ?></p>
					</div>
				</div>

				<p class="elementtest-section-actions">
					<button type="button" id="elementtest-add-variant" class="button">
						<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle; margin-top:-2px;"></span>
						<?php esc_html_e( 'Add Variant', 'elementtest-pro' ); ?>
					</button>
					<button type="button" id="elementtest-auto-balance" class="button" title="<?php esc_attr_e( 'Distribute traffic evenly across all variants', 'elementtest-pro' ); ?>">
						<span class="dashicons dashicons-image-flip-horizontal" style="vertical-align:middle; margin-top:-2px;"></span>
						<?php esc_html_e( 'Auto-Balance Traffic', 'elementtest-pro' ); ?>
					</button>
				</p>

			</div>
		</div>

		<!-- ============================================================
		     Section 4: Conversion Goals
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Conversion Goals', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">

				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'Define how you will measure the success of this test. Add one or more conversion goals.', 'elementtest-pro' ); ?>
				</p>

				<div id="elementtest-goals-container">

					<!-- Goal 1 (default) -->
					<div class="elementtest-goal" data-goal-index="0">
						<div class="elementtest-goal-header">
							<span class="elementtest-goal-number"><?php esc_html_e( 'Goal 1', 'elementtest-pro' ); ?></span>
							<button type="button" class="elementtest-remove-goal button-link" aria-label="<?php esc_attr_e( 'Remove this goal', 'elementtest-pro' ); ?>" style="display:none;">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
						<div class="elementtest-goal-body">
							<table class="form-table" role="presentation">
								<tbody>
									<!-- Goal Name -->
									<tr>
										<th scope="row">
											<label for="elementtest-goal-0-name">
												<?php esc_html_e( 'Goal Name', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<input
												type="text"
												id="elementtest-goal-0-name"
												name="goals[0][name]"
												class="regular-text"
												placeholder="<?php esc_attr_e( 'e.g. Button Click, Form Submission', 'elementtest-pro' ); ?>"
											>
										</td>
									</tr>

									<!-- Trigger Type -->
									<tr>
										<th scope="row">
											<label for="elementtest-goal-0-trigger-type">
												<?php esc_html_e( 'Trigger Type', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<select
												id="elementtest-goal-0-trigger-type"
												name="goals[0][trigger_type]"
												class="regular-text elementtest-trigger-type-select"
											>
												<?php foreach ( $goal_trigger_types as $value => $label ) : ?>
													<option value="<?php echo esc_attr( $value ); ?>">
														<?php echo esc_html( $label ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>

									<!-- Trigger Selector (for Click / Form Submit / Video Play) -->
									<tr class="elementtest-trigger-field elementtest-trigger-selector">
										<th scope="row">
											<label for="elementtest-goal-0-selector">
												<?php esc_html_e( 'Trigger Selector', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<input
												type="text"
												id="elementtest-goal-0-selector"
												name="goals[0][trigger_selector]"
												class="regular-text code"
												placeholder="<?php esc_attr_e( 'e.g. .cta-button, #signup-form', 'elementtest-pro' ); ?>"
											>
											<p class="description">
												<?php esc_html_e( 'CSS selector of the element to monitor. For Video Play, leave blank to track all YouTube videos or enter a container selector to scope.', 'elementtest-pro' ); ?>
											</p>
										</td>
									</tr>

									<!-- Trigger URL (for Page View) -->
									<tr class="elementtest-trigger-field elementtest-trigger-url" style="display:none;">
										<th scope="row">
											<label for="elementtest-goal-0-url">
												<?php esc_html_e( 'Trigger URL', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<input
												type="text"
												id="elementtest-goal-0-url"
												name="goals[0][trigger_url]"
												class="regular-text code"
												autocomplete="off"
												placeholder="<?php echo esc_attr( home_url( '/thank-you/' ) ); ?>"
											>
											<p class="description">
												<?php esc_html_e( 'A conversion is recorded when the visitor navigates to this URL. End the URL with * to match all URLs that start with the same prefix (e.g. WooCommerce order-received pages). Use the “Purchase & wildcard URL help” button above for a full example.', 'elementtest-pro' ); ?>
											</p>
										</td>
									</tr>

									<!-- Custom Event Name (for Custom Event) -->
									<tr class="elementtest-trigger-field elementtest-trigger-custom-event" style="display:none;">
										<th scope="row">
											<label for="elementtest-goal-0-event">
												<?php esc_html_e( 'Custom Event Name', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<input
												type="text"
												id="elementtest-goal-0-event"
												name="goals[0][custom_event]"
												class="regular-text code"
												placeholder="<?php esc_attr_e( 'e.g. purchase_complete', 'elementtest-pro' ); ?>"
											>
											<p class="description">
												<?php
												printf(
													/* translators: %s: JavaScript example code */
													esc_html__( 'The name of a custom JavaScript event. Trigger it in your code with: %s', 'elementtest-pro' ),
													'<code>window.elementtest.convert(\'purchase_complete\')</code>'
												);
												?>
											</p>
										</td>
									</tr>

									<!-- Revenue Value -->
									<tr>
										<th scope="row">
											<label for="elementtest-goal-0-revenue">
												<?php esc_html_e( 'Revenue Value', 'elementtest-pro' ); ?>
											</label>
										</th>
										<td>
											<input
												type="number"
												id="elementtest-goal-0-revenue"
												name="goals[0][revenue_value]"
												class="small-text"
												min="0"
												step="0.01"
												placeholder="0.00"
											>
											<p class="description">
												<?php esc_html_e( 'Optional. Assign a monetary value to this conversion for revenue tracking.', 'elementtest-pro' ); ?>
											</p>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

				</div><!-- #elementtest-goals-container -->

				<p class="elementtest-section-actions">
					<button type="button" id="elementtest-add-goal" class="button">
						<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle; margin-top:-2px;"></span>
						<?php esc_html_e( 'Add Goal', 'elementtest-pro' ); ?>
					</button>
				</p>

			</div>
		</div>

		<!-- ============================================================
		     Section 5: Schedule
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Schedule', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tbody>
						<!-- Start Immediately -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Timing', 'elementtest-pro' ); ?>
							</th>
							<td>
								<label for="elementtest-start-immediately">
									<input
										type="checkbox"
										id="elementtest-start-immediately"
										name="start_immediately"
										value="1"
										checked
									>
									<?php esc_html_e( 'Start immediately when the test is launched', 'elementtest-pro' ); ?>
								</label>
							</td>
						</tr>

						<!-- Start Date -->
						<tr class="elementtest-schedule-dates" style="display:none;">
							<th scope="row">
								<label for="elementtest-start-date">
									<?php esc_html_e( 'Start Date', 'elementtest-pro' ); ?>
								</label>
							</th>
							<td>
								<input
									type="date"
									id="elementtest-start-date"
									name="start_date"
									class="regular-text"
									min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"
								>
							</td>
						</tr>

						<!-- End Date -->
						<tr class="elementtest-schedule-dates" style="display:none;">
							<th scope="row">
								<label for="elementtest-end-date">
									<?php esc_html_e( 'End Date', 'elementtest-pro' ); ?>
								</label>
							</th>
							<td>
								<input
									type="date"
									id="elementtest-end-date"
									name="end_date"
									class="regular-text"
								>
								<p class="description">
									<?php esc_html_e( 'Optional. Leave empty to run the test indefinitely until manually stopped.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- ============================================================
		     Action Buttons
		     ============================================================ -->
		<div class="elementtest-form-actions">
			<div class="elementtest-actions-left">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=elementtest-pro' ) ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'elementtest-pro' ); ?>
				</a>
			</div>
			<div class="elementtest-actions-right">
				<button type="button" id="elementtest-save-draft" class="button button-secondary button-large">
					<span class="dashicons dashicons-saved" style="vertical-align:middle; margin-top:-2px;"></span>
					<?php esc_html_e( 'Save as Draft', 'elementtest-pro' ); ?>
				</button>
				<button type="button" id="elementtest-start-test" class="button button-primary button-large">
					<span class="dashicons dashicons-controls-play" style="vertical-align:middle; margin-top:-2px;"></span>
					<?php
					if ( $is_edit && in_array( $test['status'], array( 'running', 'paused' ), true ) ) {
						esc_html_e( 'Update Test', 'elementtest-pro' );
					} else {
						esc_html_e( 'Start Test', 'elementtest-pro' );
					}
					?>
				</button>
			</div>
		</div>

		<!-- Spinner for AJAX operations -->
		<div id="elementtest-saving-indicator" class="elementtest-saving-indicator" style="display:none;">
			<span class="spinner is-active"></span>
			<span class="elementtest-saving-text"><?php esc_html_e( 'Saving...', 'elementtest-pro' ); ?></span>
		</div>

	</div><!-- #elementtest-new-test-form -->

</div><!-- .wrap -->

<!-- ================================================================
     Variant template (used by JavaScript to clone new variants)
     ================================================================ -->
<script type="text/html" id="tmpl-elementtest-variant">
	<div class="elementtest-variant" data-variant-index="{{data.index}}">
		<div class="elementtest-variant-header">
			<span class="elementtest-variant-badge">{{data.letter}}</span>
			<strong>
				<?php
				/* translators: %s: variant letter */
				echo esc_html__( 'Variant', 'elementtest-pro' );
				?> {{data.letter}}
			</strong>
			<button type="button" class="elementtest-remove-variant button-link" aria-label="<?php esc_attr_e( 'Remove this variant', 'elementtest-pro' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</div>
		<div class="elementtest-variant-body">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="elementtest-variant-{{data.index}}-name">
								<?php esc_html_e( 'Variant Name', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="elementtest-variant-{{data.index}}-name"
								name="variants[{{data.index}}][name]"
								class="regular-text"
								value="<?php echo esc_attr__( 'Variant', 'elementtest-pro' ); ?> {{data.letter}}"
							>
							<input type="hidden" name="variants[{{data.index}}][is_control]" value="0">
						</td>
					</tr>

					<!-- CSS changes -->
					<tr class="elementtest-changes-row elementtest-changes-css" <# if ( data.testType !== 'css' && data.testType !== '' ) { #>style="display:none;"<# } #>>
						<th scope="row">
							<label for="elementtest-variant-{{data.index}}-css">
								<?php esc_html_e( 'CSS Rules', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<textarea
								id="elementtest-variant-{{data.index}}-css"
								name="variants[{{data.index}}][changes_css]"
								class="large-text code elementtest-code-editor"
								rows="6"
								placeholder="<?php esc_attr_e( "/* CSS changes for this variant */\ncolor: #ff0000;\nfont-size: 18px;", 'elementtest-pro' ); ?>"
							></textarea>
							<p class="description">
								<?php esc_html_e( 'Enter CSS property declarations to apply to the selected element.', 'elementtest-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- Copy changes -->
					<tr class="elementtest-changes-row elementtest-changes-copy" <# if ( data.testType !== 'copy' ) { #>style="display:none;"<# } #>>
						<th scope="row">
							<label for="elementtest-variant-{{data.index}}-copy">
								<?php esc_html_e( 'Replacement Text', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="elementtest-variant-{{data.index}}-copy"
								name="variants[{{data.index}}][changes_copy]"
								class="large-text"
								placeholder="<?php esc_attr_e( 'Enter the replacement text for this variant', 'elementtest-pro' ); ?>"
							>
							<p class="description">
								<?php esc_html_e( 'The text content that will replace the original element text.', 'elementtest-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- JavaScript changes -->
					<tr class="elementtest-changes-row elementtest-changes-js" <# if ( data.testType !== 'js' ) { #>style="display:none;"<# } #>>
						<th scope="row">
							<label for="elementtest-variant-{{data.index}}-js">
								<?php esc_html_e( 'JavaScript Code', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<textarea
								id="elementtest-variant-{{data.index}}-js"
								name="variants[{{data.index}}][changes_js]"
								class="large-text code elementtest-code-editor"
								rows="6"
								placeholder="<?php esc_attr_e( "// JavaScript to modify the element\n// 'el' refers to the selected element\nel.style.backgroundColor = '#0073aa';", 'elementtest-pro' ); ?>"
							></textarea>
							<p class="description">
								<?php esc_html_e( 'JavaScript code that will be executed to modify the selected element.', 'elementtest-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- Image changes -->
					<tr class="elementtest-changes-row elementtest-changes-image" <# if ( data.testType !== 'image' ) { #>style="display:none;"<# } #>>
						<th scope="row">
							<label for="elementtest-variant-{{data.index}}-image">
								<?php esc_html_e( 'Image URL', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<div class="elementtest-input-group">
								<input
									type="url"
									id="elementtest-variant-{{data.index}}-image"
									name="variants[{{data.index}}][changes_image]"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'https://example.com/image.jpg', 'elementtest-pro' ); ?>"
								>
								<button
									type="button"
									class="button elementtest-media-upload"
									data-target="elementtest-variant-{{data.index}}-image"
								>
									<span class="dashicons dashicons-admin-media" style="vertical-align:middle; margin-top:-2px;"></span>
									<?php esc_html_e( 'Media Library', 'elementtest-pro' ); ?>
								</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'URL of the replacement image. Use the Media Library button to select from uploaded images.', 'elementtest-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- Traffic Percentage -->
					<tr>
						<th scope="row">
							<label for="elementtest-variant-{{data.index}}-traffic">
								<?php esc_html_e( 'Traffic Percentage', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<input
								type="number"
								id="elementtest-variant-{{data.index}}-traffic"
								name="variants[{{data.index}}][traffic]"
								class="small-text elementtest-traffic-input"
								value="{{data.traffic}}"
								min="0"
								max="100"
								step="1"
							>
							<span>%</span>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</script>

<!-- ================================================================
     Goal template (used by JavaScript to clone new goals)
     ================================================================ -->
<script type="text/html" id="tmpl-elementtest-goal">
	<div class="elementtest-goal" data-goal-index="{{data.index}}">
		<div class="elementtest-goal-header">
			<span class="elementtest-goal-number">
				<?php
				/* translators: %s: goal number */
				echo esc_html__( 'Goal', 'elementtest-pro' );
				?> {{data.number}}
			</span>
			<button type="button" class="elementtest-remove-goal button-link" aria-label="<?php esc_attr_e( 'Remove this goal', 'elementtest-pro' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</div>
		<div class="elementtest-goal-body">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="elementtest-goal-{{data.index}}-name">
								<?php esc_html_e( 'Goal Name', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="elementtest-goal-{{data.index}}-name"
								name="goals[{{data.index}}][name]"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. Button Click, Form Submission', 'elementtest-pro' ); ?>"
							>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="elementtest-goal-{{data.index}}-trigger-type">
								<?php esc_html_e( 'Trigger Type', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<select
								id="elementtest-goal-{{data.index}}-trigger-type"
								name="goals[{{data.index}}][trigger_type]"
								class="regular-text elementtest-trigger-type-select"
							>
								<?php foreach ( $goal_trigger_types as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>">
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<!-- Trigger Selector -->
					<tr class="elementtest-trigger-field elementtest-trigger-selector">
						<th scope="row">
							<label for="elementtest-goal-{{data.index}}-selector">
								<?php esc_html_e( 'Trigger Selector', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="elementtest-goal-{{data.index}}-selector"
								name="goals[{{data.index}}][trigger_selector]"
								class="regular-text code"
								placeholder="<?php esc_attr_e( 'e.g. .cta-button, #signup-form', 'elementtest-pro' ); ?>"
							>
							<p class="description">
								<?php esc_html_e( 'CSS selector of the element to monitor. For Video Play, leave blank to track all YouTube videos or enter a container selector to scope.', 'elementtest-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- Trigger URL -->
					<tr class="elementtest-trigger-field elementtest-trigger-url" style="display:none;">
						<th scope="row">
							<label for="elementtest-goal-{{data.index}}-url">
								<?php esc_html_e( 'Trigger URL', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="elementtest-goal-{{data.index}}-url"
								name="goals[{{data.index}}][trigger_url]"
								class="regular-text code"
								autocomplete="off"
								placeholder="<?php echo esc_attr( home_url( '/thank-you/' ) ); ?>"
							>
							<p class="description">
								<?php esc_html_e( 'A conversion is recorded when the visitor navigates to this URL. End the URL with * to match all URLs that start with the same prefix (e.g. WooCommerce order-received pages). Use the “Purchase & wildcard URL help” button above for a full example.', 'elementtest-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- Custom Event Name -->
					<tr class="elementtest-trigger-field elementtest-trigger-custom-event" style="display:none;">
						<th scope="row">
							<label for="elementtest-goal-{{data.index}}-event">
								<?php esc_html_e( 'Custom Event Name', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="elementtest-goal-{{data.index}}-event"
								name="goals[{{data.index}}][custom_event]"
								class="regular-text code"
								placeholder="<?php esc_attr_e( 'e.g. purchase_complete', 'elementtest-pro' ); ?>"
							>
							<p class="description">
								<?php
								printf(
									/* translators: %s: JavaScript example code */
									esc_html__( 'The name of a custom JavaScript event. Trigger it in your code with: %s', 'elementtest-pro' ),
									'<code>window.elementtest.convert(\'purchase_complete\')</code>'
								);
								?>
							</p>
						</td>
					</tr>

					<!-- Revenue Value -->
					<tr>
						<th scope="row">
							<label for="elementtest-goal-{{data.index}}-revenue">
								<?php esc_html_e( 'Revenue Value', 'elementtest-pro' ); ?>
							</label>
						</th>
						<td>
							<input
								type="number"
								id="elementtest-goal-{{data.index}}-revenue"
								name="goals[{{data.index}}][revenue_value]"
								class="small-text"
								min="0"
								step="0.01"
								placeholder="0.00"
							>
							<p class="description">
								<?php esc_html_e( 'Optional. Assign a monetary value to this conversion for revenue tracking.', 'elementtest-pro' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</script>
