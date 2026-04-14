<?php
/**
 * AJAX Handler for ElementTest Pro
 *
 * Handles all AJAX operations for tests, variants, conversions,
 * and frontend tracking endpoints.
 *
 * @package ElementTestPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ElementTest_Ajax_Handler
 *
 * Singleton class that registers and processes all AJAX requests
 * for the ElementTest Pro plugin.
 *
 * @since 1.0.0
 */
class ElementTest_Ajax_Handler {

	/**
	 * Single instance of this class.
	 *
	 * @since  1.0.0
	 * @var    ElementTest_Ajax_Handler|null
	 */
	private static $instance = null;

	/**
	 * Valid test statuses.
	 *
	 * @since  1.0.0
	 * @var    array
	 */
	private $valid_statuses = array( 'draft', 'running', 'paused', 'completed' );

	/**
	 * Valid test types.
	 *
	 * @since  1.0.0
	 * @var    array
	 */
	private $valid_test_types = array( 'css', 'copy', 'js', 'image' );

	/**
	 * Valid conversion trigger types.
	 *
	 * @since  1.0.0
	 * @var    array
	 */
	private $valid_trigger_types = array( 'click', 'pageview', 'form_submit', 'custom_event', 'video_play', 'add_to_cart' );

	/**
	 * Get the singleton instance.
	 *
	 * @since  1.0.0
	 * @return ElementTest_Ajax_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers all AJAX hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register all AJAX action hooks.
	 *
	 * Admin actions require authentication and manage_options capability.
	 * Frontend tracking actions are available to both logged-in and guest users.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		// Test CRUD (admin only).
		add_action( 'wp_ajax_elementtest_save_test', array( $this, 'save_test' ) );
		add_action( 'wp_ajax_elementtest_get_test', array( $this, 'get_test' ) );
		add_action( 'wp_ajax_elementtest_get_tests', array( $this, 'get_tests' ) );
		add_action( 'wp_ajax_elementtest_delete_test', array( $this, 'delete_test' ) );
		add_action( 'wp_ajax_elementtest_update_test_status', array( $this, 'update_test_status' ) );

		// Variant CRUD (admin only).
		add_action( 'wp_ajax_elementtest_save_variant', array( $this, 'save_variant' ) );
		add_action( 'wp_ajax_elementtest_delete_variant', array( $this, 'delete_variant' ) );
		add_action( 'wp_ajax_elementtest_get_variants', array( $this, 'get_variants' ) );

		// Additional admin actions.
		add_action( 'wp_ajax_elementtest_search_pages', array( $this, 'search_pages' ) );
		add_action( 'wp_ajax_elementtest_duplicate_test', array( $this, 'duplicate_test' ) );
		add_action( 'wp_ajax_elementtest_toggle_status', array( $this, 'toggle_status' ) );
		add_action( 'wp_ajax_elementtest_proxy_page', array( $this, 'proxy_page' ) );
		add_action( 'wp_ajax_elementtest_get_results_data', array( $this, 'get_results_data' ) );

		// Conversion goals (admin only).
		add_action( 'wp_ajax_elementtest_save_conversion', array( $this, 'save_conversion' ) );
		add_action( 'wp_ajax_elementtest_get_conversions', array( $this, 'get_conversions' ) );

		// Import/Export (admin only).
		add_action( 'wp_ajax_elementtest_export_tests', array( $this, 'export_tests' ) );
		add_action( 'wp_ajax_elementtest_import_tests', array( $this, 'import_tests' ) );

		// Report export (admin only).
		add_action( 'wp_ajax_elementtest_export_report', array( $this, 'export_report' ) );
		add_action( 'wp_ajax_elementtest_export_all_reports', array( $this, 'export_all_reports' ) );

		// Frontend tracking (logged-in and guest users).
		add_action( 'wp_ajax_elementtest_track_impression', array( $this, 'track_impression' ) );
		add_action( 'wp_ajax_nopriv_elementtest_track_impression', array( $this, 'track_impression' ) );

		add_action( 'wp_ajax_elementtest_track_conversion', array( $this, 'track_conversion' ) );
		add_action( 'wp_ajax_nopriv_elementtest_track_conversion', array( $this, 'track_conversion' ) );

		add_action( 'wp_ajax_elementtest_get_variant_assignment', array( $this, 'get_variant_assignment' ) );
		add_action( 'wp_ajax_nopriv_elementtest_get_variant_assignment', array( $this, 'get_variant_assignment' ) );
	}

	// =========================================================================
	// Security Helpers
	// =========================================================================

	/**
	 * Verify the admin nonce and check capabilities.
	 *
	 * Sends a JSON error response and terminates execution if verification fails.
	 *
	 * @since 1.0.0
	 */
	private function verify_admin_request() {
		if ( ! check_ajax_referer( 'elementtest-admin', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security verification failed.', 'elementtest-pro' ) ),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'elementtest-pro' ) ),
				403
			);
		}
	}

	/**
	 * Verify the public/frontend nonce.
	 *
	 * Sends a JSON error response and terminates execution if verification fails.
	 *
	 * @since 1.0.0
	 */
	private function verify_public_request() {
		if ( ! check_ajax_referer( 'elementtest-public', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security verification failed.', 'elementtest-pro' ) ),
				403
			);
		}
	}

	// =========================================================================
	// Test CRUD
	// =========================================================================

	/**
	 * Create or update an A/B test.
	 *
	 * Expects POST data:
	 *   - test_id        (int, optional) If present, updates the existing test.
	 *   - name           (string, required)
	 *   - description    (string, optional)
	 *   - status         (string, optional) One of: draft, running, paused, completed.
	 *   - page_url       (string, optional) Target page URL (max 500 chars).
	 *   - element_selector (string, optional) CSS selector for the target element.
	 *   - test_type      (string, optional) One of: css, copy, js, image.
	 *   - start_date     (string, optional) ISO datetime.
	 *   - end_date       (string, optional) ISO datetime.
	 *
	 * @since 1.0.0
	 */
	public function save_test() {
		$this->verify_admin_request();

		global $wpdb;
		$table = $wpdb->prefix . 'elementtest_tests';

		// Sanitize inputs.
		$test_id = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;
		// Accept both 'name' and 'test_name' field names.
		$name    = isset( $_POST['test_name'] ) ? sanitize_text_field( wp_unslash( $_POST['test_name'] ) ) : '';
		if ( empty( $name ) ) {
			$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		}

		if ( empty( $name ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Test name is required.', 'elementtest-pro' ) )
			);
		}

		$description      = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$status           = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'draft';
		$page_url         = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		$element_selector = isset( $_POST['element_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['element_selector'] ) ) : '';
		$element_selector = substr( $element_selector, 0, 500 );
		$test_type        = isset( $_POST['test_type'] ) ? sanitize_text_field( wp_unslash( $_POST['test_type'] ) ) : '';
		$start_date       = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : null;
		$end_date         = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : null;

		// Validate status.
		if ( ! in_array( $status, $this->valid_statuses, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid test status.', 'elementtest-pro' ) )
			);
		}

		// Validate test_type if provided.
		if ( ! empty( $test_type ) && ! in_array( $test_type, $this->valid_test_types, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid test type.', 'elementtest-pro' ) )
			);
		}

		// Truncate page_url to 500 characters.
		$page_url = substr( $page_url, 0, 500 );

		// Validate date formats when provided.
		if ( ! empty( $start_date ) && false === strtotime( $start_date ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid start date format.', 'elementtest-pro' ) )
			);
		}

		if ( ! empty( $end_date ) && false === strtotime( $end_date ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid end date format.', 'elementtest-pro' ) )
			);
		}

		// Handle "start immediately" flag.
		$start_immediately = isset( $_POST['start_immediately'] ) ? absint( $_POST['start_immediately'] ) : 0;
		if ( $start_immediately && 'running' === $status ) {
			$start_date = current_time( 'mysql', true );
		}

		// Format dates for MySQL or set to null.
		$start_date = ! empty( $start_date ) ? gmdate( 'Y-m-d H:i:s', strtotime( $start_date ) ) : null;
		$end_date   = ! empty( $end_date ) ? gmdate( 'Y-m-d H:i:s', strtotime( $end_date ) ) : null;

		$data = array(
			'name'             => $name,
			'description'      => $description,
			'status'           => $status,
			'page_url'         => $page_url,
			'element_selector' => $element_selector,
			'test_type'        => $test_type,
			'start_date'       => $start_date,
			'end_date'         => $end_date,
			'updated_at'       => current_time( 'mysql', true ),
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $test_id > 0 ) {
			// Update existing test.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT test_id FROM {$table} WHERE test_id = %d",
					$test_id
				)
			);

			if ( ! $exists ) {
				wp_send_json_error(
					array( 'message' => __( 'Test not found.', 'elementtest-pro' ) )
				);
			}

			$result = $wpdb->update( $table, $data, array( 'test_id' => $test_id ), $format, array( '%d' ) );

			if ( false === $result ) {
				wp_send_json_error(
					array( 'message' => __( 'Failed to update test.', 'elementtest-pro' ) )
				);
			}
		} else {
			// Create new test.
			$data['created_at'] = current_time( 'mysql', true );
			$format[]           = '%s';

			$result = $wpdb->insert( $table, $data, $format );

			if ( false === $result ) {
				if ( $wpdb->last_error ) {
					error_log( '[ElementTest] Test insert failed. DB Error: ' . $wpdb->last_error );
				}
				wp_send_json_error(
					array( 'message' => __( 'Failed to create test.', 'elementtest-pro' ) )
				);
			}

			$test_id = $wpdb->insert_id;
		}

		// Save variants if provided.
		if ( isset( $_POST['variants'] ) && is_array( $_POST['variants'] ) ) {
			$variants_table = $wpdb->prefix . 'elementtest_variants';

			// Collect submitted variant IDs to detect removals.
			$submitted_variant_ids = array();

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-field below.
			foreach ( $_POST['variants'] as $variant_data ) {
				$v_id      = isset( $variant_data['variant_id'] ) ? absint( $variant_data['variant_id'] ) : 0;
				$v_name    = isset( $variant_data['name'] ) ? sanitize_text_field( wp_unslash( $variant_data['name'] ) ) : '';
				$v_changes = isset( $variant_data['changes'] ) ? wp_kses_post( wp_unslash( $variant_data['changes'] ) ) : '';
				$v_traffic = isset( $variant_data['traffic'] ) ? absint( $variant_data['traffic'] ) : 50;
				$v_control = isset( $variant_data['is_control'] ) ? absint( $variant_data['is_control'] ) : 0;

				if ( empty( $v_name ) ) {
					continue;
				}

				$v_data = array(
					'test_id'            => $test_id,
					'name'               => $v_name,
					'changes'            => $v_changes,
					'traffic_percentage' => min( 100, max( 0, $v_traffic ) ),
					'is_control'         => $v_control ? 1 : 0,
				);

				if ( $v_id > 0 ) {
					// Update existing variant (preserves variant_id for event data).
					$wpdb->update(
						$variants_table,
						$v_data,
						array( 'variant_id' => $v_id, 'test_id' => $test_id ),
						array( '%d', '%s', '%s', '%d', '%d' ),
						array( '%d', '%d' )
					);
					$submitted_variant_ids[] = $v_id;
				} else {
					// Insert new variant.
					$v_data['created_at'] = current_time( 'mysql', true );
					$wpdb->insert(
						$variants_table,
						$v_data,
						array( '%d', '%s', '%s', '%d', '%d', '%s' )
					);
					$submitted_variant_ids[] = $wpdb->insert_id;
				}
			}

			// Remove variants that were deleted from the form.
			if ( ! empty( $submitted_variant_ids ) ) {
				$ids_placeholder = implode( ',', array_map( 'absint', $submitted_variant_ids ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are absint-sanitized.
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$variants_table} WHERE test_id = %d AND variant_id NOT IN ({$ids_placeholder})",
						$test_id
					)
				);
			}
		}

		// Save conversion goals if provided.
		if ( isset( $_POST['goals'] ) && is_array( $_POST['goals'] ) ) {
			$conversions_table = $wpdb->prefix . 'elementtest_conversions';

			// Collect submitted conversion IDs to detect removals.
			$submitted_goal_ids = array();

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-field below.
			foreach ( $_POST['goals'] as $goal_data ) {
				$g_id      = isset( $goal_data['conversion_id'] ) ? absint( $goal_data['conversion_id'] ) : 0;
				$g_name    = isset( $goal_data['name'] ) ? sanitize_text_field( wp_unslash( $goal_data['name'] ) ) : '';
				$g_type    = isset( $goal_data['trigger_type'] ) ? sanitize_text_field( wp_unslash( $goal_data['trigger_type'] ) ) : '';
				$g_selector = isset( $goal_data['trigger_selector'] ) ? sanitize_text_field( wp_unslash( $goal_data['trigger_selector'] ) ) : '';
				$g_url     = isset( $goal_data['trigger_url'] ) ? esc_url_raw( wp_unslash( $goal_data['trigger_url'] ) ) : '';
				$g_event   = isset( $goal_data['custom_event'] ) ? sanitize_text_field( wp_unslash( $goal_data['custom_event'] ) ) : '';
				$g_revenue = isset( $goal_data['revenue_value'] ) ? floatval( $goal_data['revenue_value'] ) : 0.00;

				if ( empty( $g_name ) || empty( $g_type ) ) {
					continue;
				}

				if ( ! in_array( $g_type, $this->valid_trigger_types, true ) ) {
					continue;
				}

				// Determine trigger_event based on type.
				$trigger_event = '';
				if ( 'pageview' === $g_type ) {
					$trigger_event = $g_url;
				} elseif ( 'custom_event' === $g_type ) {
					$trigger_event = $g_event;
				}

				$g_data = array(
					'test_id'          => $test_id,
					'name'             => $g_name,
					'trigger_type'     => $g_type,
					'trigger_selector' => $g_selector,
					'trigger_event'    => $trigger_event,
					'revenue_value'    => $g_revenue,
				);

				if ( $g_id > 0 ) {
					// Update existing conversion goal.
					$wpdb->update(
						$conversions_table,
						$g_data,
						array( 'conversion_id' => $g_id, 'test_id' => $test_id ),
						array( '%d', '%s', '%s', '%s', '%s', '%f' ),
						array( '%d', '%d' )
					);
					$submitted_goal_ids[] = $g_id;
				} else {
					// Insert new conversion goal.
					$g_data['created_at'] = current_time( 'mysql', true );
					$goal_result = $wpdb->insert(
						$conversions_table,
						$g_data,
						array( '%d', '%s', '%s', '%s', '%s', '%f', '%s' )
					);
					if ( false === $goal_result ) {
						error_log( '[ElementTest] Goal insert failed. DB Error: ' . $wpdb->last_error );
						error_log( '[ElementTest] Goal data: ' . wp_json_encode( $g_data ) );
					}
					$submitted_goal_ids[] = $wpdb->insert_id;
				}
			}

			// Remove goals that were deleted from the form.
			if ( ! empty( $submitted_goal_ids ) ) {
				$ids_placeholder = implode( ',', array_map( 'absint', $submitted_goal_ids ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are absint-sanitized.
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$conversions_table} WHERE test_id = %d AND conversion_id NOT IN ({$ids_placeholder})",
						$test_id
					)
				);
			}
		}

		// Return the saved test.
		$test = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE test_id = %d",
				$test_id
			),
			ARRAY_A
		);

		wp_send_json_success(
			array(
				'message'  => __( 'Test saved successfully.', 'elementtest-pro' ),
				'test'     => $test,
				'redirect' => admin_url( 'admin.php?page=elementtest-new&test_id=' . $test_id ),
			)
		);
	}

	/**
	 * Get a single test by ID, including its variants and conversion goals.
	 *
	 * Expects GET/POST data:
	 *   - test_id (int, required)
	 *
	 * @since 1.0.0
	 */
	public function get_test() {
		$this->verify_admin_request();

		global $wpdb;

		$test_id = isset( $_REQUEST['test_id'] ) ? absint( $_REQUEST['test_id'] ) : 0;

		if ( ! $test_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID is required.', 'elementtest-pro' ) )
			);
		}

		$tests_table       = $wpdb->prefix . 'elementtest_tests';
		$variants_table    = $wpdb->prefix . 'elementtest_variants';
		$conversions_table = $wpdb->prefix . 'elementtest_conversions';

		$test = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tests_table} WHERE test_id = %d",
				$test_id
			),
			ARRAY_A
		);

		if ( ! $test ) {
			wp_send_json_error(
				array( 'message' => __( 'Test not found.', 'elementtest-pro' ) )
			);
		}

		// Fetch associated variants.
		$variants = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$variants_table} WHERE test_id = %d ORDER BY is_control DESC, variant_id ASC",
				$test_id
			),
			ARRAY_A
		);

		// The changes field is stored as a plain string (CSS, copy text,
		// JS code, or image URL) — no JSON decoding needed.

		$test['variants'] = $variants;

		// Fetch associated conversion goals.
		$conversions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$conversions_table} WHERE test_id = %d ORDER BY conversion_id ASC",
				$test_id
			),
			ARRAY_A
		);

		$test['conversions'] = $conversions;

		wp_send_json_success( array( 'test' => $test ) );
	}

	/**
	 * Get all tests, optionally filtered by status.
	 *
	 * Expects GET/POST data:
	 *   - status (string, optional) Filter by test status.
	 *
	 * @since 1.0.0
	 */
	public function get_tests() {
		$this->verify_admin_request();

		global $wpdb;
		$table = $wpdb->prefix . 'elementtest_tests';

		$status = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';

		if ( ! empty( $status ) ) {
			if ( ! in_array( $status, $this->valid_statuses, true ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Invalid status filter.', 'elementtest-pro' ) )
				);
			}

			$tests = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at DESC",
					$status
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
			$tests = $wpdb->get_results(
				"SELECT * FROM {$table} ORDER BY updated_at DESC",
				ARRAY_A
			);
		}

		wp_send_json_success( array( 'tests' => $tests ) );
	}

	/**
	 * Delete a test and all associated variants, events, and conversion goals.
	 *
	 * Expects POST data:
	 *   - test_id (int, required)
	 *
	 * @since 1.0.0
	 */
	public function delete_test() {
		$this->verify_admin_request();

		global $wpdb;

		$test_id = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;

		if ( ! $test_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID is required.', 'elementtest-pro' ) )
			);
		}

		$tests_table       = $wpdb->prefix . 'elementtest_tests';
		$variants_table    = $wpdb->prefix . 'elementtest_variants';
		$events_table      = $wpdb->prefix . 'elementtest_events';
		$conversions_table = $wpdb->prefix . 'elementtest_conversions';

		// Verify the test exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT test_id FROM {$tests_table} WHERE test_id = %d",
				$test_id
			)
		);

		if ( ! $exists ) {
			wp_send_json_error(
				array( 'message' => __( 'Test not found.', 'elementtest-pro' ) )
			);
		}

		// Delete in order: events, conversions, variants, then the test.
		$wpdb->delete( $events_table, array( 'test_id' => $test_id ), array( '%d' ) );
		$wpdb->delete( $conversions_table, array( 'test_id' => $test_id ), array( '%d' ) );
		$wpdb->delete( $variants_table, array( 'test_id' => $test_id ), array( '%d' ) );
		$result = $wpdb->delete( $tests_table, array( 'test_id' => $test_id ), array( '%d' ) );

		if ( false === $result ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to delete test.', 'elementtest-pro' ) )
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Test deleted successfully.', 'elementtest-pro' ) )
		);
	}

	/**
	 * Update the status of a test.
	 *
	 * Expects POST data:
	 *   - test_id (int, required)
	 *   - status  (string, required) One of: draft, running, paused, completed.
	 *
	 * @since 1.0.0
	 */
	public function update_test_status() {
		$this->verify_admin_request();

		global $wpdb;
		$table = $wpdb->prefix . 'elementtest_tests';

		$test_id = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $test_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID is required.', 'elementtest-pro' ) )
			);
		}

		if ( ! in_array( $status, $this->valid_statuses, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid status. Allowed values: draft, running, paused, completed.', 'elementtest-pro' ) )
			);
		}

		// Verify the test exists and get its current status.
		$current_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table} WHERE test_id = %d",
				$test_id
			)
		);

		if ( null === $current_status ) {
			wp_send_json_error(
				array( 'message' => __( 'Test not found.', 'elementtest-pro' ) )
			);
		}

		// Prevent re-running a completed test directly.
		if ( 'completed' === $current_status && 'running' === $status ) {
			wp_send_json_error(
				array( 'message' => __( 'A completed test cannot be set back to running. Please duplicate the test instead.', 'elementtest-pro' ) )
			);
		}

		$result = $wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'test_id' => $test_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to update test status.', 'elementtest-pro' ) )
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: new status */
					__( 'Test status updated to "%s".', 'elementtest-pro' ),
					$status
				),
				'status'  => $status,
			)
		);
	}

	// =========================================================================
	// Variant CRUD
	// =========================================================================

	/**
	 * Create or update a variant.
	 *
	 * Expects POST data:
	 *   - variant_id        (int, optional) If present, updates the existing variant.
	 *   - test_id           (int, required)
	 *   - name              (string, required)
	 *   - changes           (string, required) JSON-encoded changes object.
	 *   - traffic_percentage (int, optional) 0-100, defaults to 50.
	 *   - is_control        (int, optional) 1 or 0, defaults to 0.
	 *
	 * @since 1.0.0
	 */
	public function save_variant() {
		$this->verify_admin_request();

		global $wpdb;

		$variants_table = $wpdb->prefix . 'elementtest_variants';
		$tests_table    = $wpdb->prefix . 'elementtest_tests';

		$variant_id         = isset( $_POST['variant_id'] ) ? absint( $_POST['variant_id'] ) : 0;
		$test_id            = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;
		$name               = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$changes_raw        = isset( $_POST['changes'] ) ? wp_unslash( $_POST['changes'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON validated below.
		$traffic_percentage = isset( $_POST['traffic_percentage'] ) ? absint( $_POST['traffic_percentage'] ) : 50;
		$is_control         = isset( $_POST['is_control'] ) ? absint( $_POST['is_control'] ) : 0;

		// Validate required fields.
		if ( ! $test_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID is required.', 'elementtest-pro' ) )
			);
		}

		if ( empty( $name ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Variant name is required.', 'elementtest-pro' ) )
			);
		}

		// Verify the parent test exists.
		$test_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT test_id FROM {$tests_table} WHERE test_id = %d",
				$test_id
			)
		);

		if ( ! $test_exists ) {
			wp_send_json_error(
				array( 'message' => __( 'Parent test not found.', 'elementtest-pro' ) )
			);
		}

		// Validate the changes field as valid JSON.
		if ( ! empty( $changes_raw ) ) {
			$decoded = json_decode( $changes_raw, true );
			if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
				wp_send_json_error(
					array( 'message' => __( 'Changes must be valid JSON.', 'elementtest-pro' ) )
				);
			}
			// Re-encode to ensure clean, normalized JSON.
			$changes = wp_json_encode( $decoded );
		} else {
			$changes = wp_json_encode( new stdClass() );
		}

		// Clamp traffic percentage to 0-100.
		$traffic_percentage = min( 100, max( 0, $traffic_percentage ) );

		// Normalize is_control to 0 or 1.
		$is_control = $is_control ? 1 : 0;

		$data = array(
			'test_id'            => $test_id,
			'name'               => $name,
			'changes'            => $changes,
			'traffic_percentage' => $traffic_percentage,
			'is_control'         => $is_control,
		);

		$format = array( '%d', '%s', '%s', '%d', '%d' );

		if ( $variant_id > 0 ) {
			// Update existing variant.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT variant_id FROM {$variants_table} WHERE variant_id = %d AND test_id = %d",
					$variant_id,
					$test_id
				)
			);

			if ( ! $exists ) {
				wp_send_json_error(
					array( 'message' => __( 'Variant not found.', 'elementtest-pro' ) )
				);
			}

			$result = $wpdb->update(
				$variants_table,
				$data,
				array( 'variant_id' => $variant_id ),
				$format,
				array( '%d' )
			);

			if ( false === $result ) {
				wp_send_json_error(
					array( 'message' => __( 'Failed to update variant.', 'elementtest-pro' ) )
				);
			}
		} else {
			// Create new variant.
			$data['created_at'] = current_time( 'mysql', true );
			$format[]           = '%s';

			$result = $wpdb->insert( $variants_table, $data, $format );

			if ( false === $result ) {
				wp_send_json_error(
					array( 'message' => __( 'Failed to create variant.', 'elementtest-pro' ) )
				);
			}

			$variant_id = $wpdb->insert_id;
		}

		// Return the saved variant.
		$variant = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$variants_table} WHERE variant_id = %d",
				$variant_id
			),
			ARRAY_A
		);

		$variant['changes'] = json_decode( $variant['changes'], true );

		wp_send_json_success(
			array(
				'message' => __( 'Variant saved successfully.', 'elementtest-pro' ),
				'variant' => $variant,
			)
		);
	}

	/**
	 * Delete a variant.
	 *
	 * Also removes any events associated with the variant.
	 *
	 * Expects POST data:
	 *   - variant_id (int, required)
	 *
	 * @since 1.0.0
	 */
	public function delete_variant() {
		$this->verify_admin_request();

		global $wpdb;

		$variants_table = $wpdb->prefix . 'elementtest_variants';
		$events_table   = $wpdb->prefix . 'elementtest_events';

		$variant_id = isset( $_POST['variant_id'] ) ? absint( $_POST['variant_id'] ) : 0;

		if ( ! $variant_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Variant ID is required.', 'elementtest-pro' ) )
			);
		}

		// Verify the variant exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT variant_id FROM {$variants_table} WHERE variant_id = %d",
				$variant_id
			)
		);

		if ( ! $exists ) {
			wp_send_json_error(
				array( 'message' => __( 'Variant not found.', 'elementtest-pro' ) )
			);
		}

		// Delete associated events first, then the variant.
		$wpdb->delete( $events_table, array( 'variant_id' => $variant_id ), array( '%d' ) );
		$result = $wpdb->delete( $variants_table, array( 'variant_id' => $variant_id ), array( '%d' ) );

		if ( false === $result ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to delete variant.', 'elementtest-pro' ) )
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Variant deleted successfully.', 'elementtest-pro' ) )
		);
	}

	/**
	 * Get all variants for a given test.
	 *
	 * Expects GET/POST data:
	 *   - test_id (int, required)
	 *
	 * @since 1.0.0
	 */
	public function get_variants() {
		$this->verify_admin_request();

		global $wpdb;
		$table = $wpdb->prefix . 'elementtest_variants';

		$test_id = isset( $_REQUEST['test_id'] ) ? absint( $_REQUEST['test_id'] ) : 0;

		if ( ! $test_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID is required.', 'elementtest-pro' ) )
			);
		}

		$variants = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE test_id = %d ORDER BY is_control DESC, variant_id ASC",
				$test_id
			),
			ARRAY_A
		);

		// Decode JSON changes for each variant.
		foreach ( $variants as &$variant ) {
			$variant['changes'] = json_decode( $variant['changes'], true );
		}
		unset( $variant );

		wp_send_json_success( array( 'variants' => $variants ) );
	}

	// =========================================================================
	// Conversion Goals
	// =========================================================================

	/**
	 * Create or update a conversion goal.
	 *
	 * Expects POST data:
	 *   - conversion_id   (int, optional) If present, updates the existing goal.
	 *   - test_id          (int, required) Associated test ID.
	 *   - name             (string, required)
	 *   - trigger_type     (string, required) One of: click, pageview, form_submit, custom.
	 *   - trigger_selector (string, optional) CSS selector for click/form triggers.
	 *   - trigger_event    (string, optional) Custom event name.
	 *   - revenue_value    (float, optional) Monetary value per conversion.
	 *
	 * @since 1.0.0
	 */
	public function save_conversion() {
		$this->verify_admin_request();

		global $wpdb;

		$conversions_table = $wpdb->prefix . 'elementtest_conversions';
		$tests_table       = $wpdb->prefix . 'elementtest_tests';

		$conversion_id   = isset( $_POST['conversion_id'] ) ? absint( $_POST['conversion_id'] ) : 0;
		$test_id         = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;
		$name            = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$trigger_type    = isset( $_POST['trigger_type'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_type'] ) ) : '';
		$trigger_selector = isset( $_POST['trigger_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_selector'] ) ) : '';
		$trigger_event   = isset( $_POST['trigger_event'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_event'] ) ) : '';
		$revenue_value   = isset( $_POST['revenue_value'] ) ? floatval( $_POST['revenue_value'] ) : 0.00;

		// Validate required fields.
		if ( ! $test_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID is required.', 'elementtest-pro' ) )
			);
		}

		if ( empty( $name ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Conversion goal name is required.', 'elementtest-pro' ) )
			);
		}

		if ( ! in_array( $trigger_type, $this->valid_trigger_types, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid trigger type. Allowed values: click, pageview, form_submit, custom_event, video_play, add_to_cart.', 'elementtest-pro' ) )
			);
		}

		// Verify the parent test exists.
		$test_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT test_id FROM {$tests_table} WHERE test_id = %d",
				$test_id
			)
		);

		if ( ! $test_exists ) {
			wp_send_json_error(
				array( 'message' => __( 'Parent test not found.', 'elementtest-pro' ) )
			);
		}

		// Ensure revenue is not negative.
		$revenue_value = max( 0, $revenue_value );

		$data = array(
			'test_id'          => $test_id,
			'name'             => $name,
			'trigger_type'     => $trigger_type,
			'trigger_selector' => $trigger_selector,
			'trigger_event'    => $trigger_event,
			'revenue_value'    => $revenue_value,
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%f' );

		if ( $conversion_id > 0 ) {
			// Update existing conversion goal.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT conversion_id FROM {$conversions_table} WHERE conversion_id = %d AND test_id = %d",
					$conversion_id,
					$test_id
				)
			);

			if ( ! $exists ) {
				wp_send_json_error(
					array( 'message' => __( 'Conversion goal not found.', 'elementtest-pro' ) )
				);
			}

			$result = $wpdb->update(
				$conversions_table,
				$data,
				array( 'conversion_id' => $conversion_id ),
				$format,
				array( '%d' )
			);

			if ( false === $result ) {
				wp_send_json_error(
					array( 'message' => __( 'Failed to update conversion goal.', 'elementtest-pro' ) )
				);
			}
		} else {
			// Create new conversion goal.
			$data['created_at'] = current_time( 'mysql', true );
			$format[]           = '%s';

			$result = $wpdb->insert( $conversions_table, $data, $format );

			if ( false === $result ) {
				wp_send_json_error(
					array( 'message' => __( 'Failed to create conversion goal.', 'elementtest-pro' ) )
				);
			}

			$conversion_id = $wpdb->insert_id;
		}

		// Return the saved conversion goal.
		$conversion = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$conversions_table} WHERE conversion_id = %d",
				$conversion_id
			),
			ARRAY_A
		);

		wp_send_json_success(
			array(
				'message'    => __( 'Conversion goal saved successfully.', 'elementtest-pro' ),
				'conversion' => $conversion,
			)
		);
	}

	/**
	 * Get all conversion goals for a given test.
	 *
	 * Expects GET/POST data:
	 *   - test_id (int, required)
	 *
	 * @since 1.0.0
	 */
	public function get_conversions() {
		$this->verify_admin_request();

		global $wpdb;
		$table = $wpdb->prefix . 'elementtest_conversions';

		$test_id = isset( $_REQUEST['test_id'] ) ? absint( $_REQUEST['test_id'] ) : 0;

		if ( ! $test_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID is required.', 'elementtest-pro' ) )
			);
		}

		$conversions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE test_id = %d ORDER BY conversion_id ASC",
				$test_id
			),
			ARRAY_A
		);

		wp_send_json_success( array( 'conversions' => $conversions ) );
	}

	// =========================================================================
	// Frontend Tracking
	// =========================================================================

	/**
	 * Record a variant impression.
	 *
	 * Available to both authenticated and guest users. Uses a rate limit
	 * to prevent duplicate impressions for the same user/variant within
	 * a short time window.
	 *
	 * Expects POST data:
	 *   - test_id    (int, required)
	 *   - variant_id (int, required)
	 *   - user_hash  (string, required) Anonymous visitor identifier.
	 *
	 * @since 1.0.0
	 */
	public function track_impression() {
		$this->verify_public_request();

		global $wpdb;

		$events_table   = $wpdb->prefix . 'elementtest_events';
		$tests_table    = $wpdb->prefix . 'elementtest_tests';
		$variants_table = $wpdb->prefix . 'elementtest_variants';

		$test_id    = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;
		$variant_id = isset( $_POST['variant_id'] ) ? absint( $_POST['variant_id'] ) : 0;
		$user_hash  = ElementTest_Visitor::get_user_hash();

		// Validate required fields.
		if ( ! $test_id || ! $variant_id || empty( $user_hash ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID, variant ID, and user hash are required.', 'elementtest-pro' ) )
			);
		}

		// IP-based rate limit — caps total impression inserts regardless of identity.
		if ( $this->check_ip_rate_limit( $test_id, 'impression' ) ) {
			status_header( 429 );
			wp_send_json_error(
				array( 'message' => __( 'Rate limit exceeded. Please try again later.', 'elementtest-pro' ) )
			);
		}

		// Verify the test is currently running.
		$test_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$tests_table} WHERE test_id = %d",
				$test_id
			)
		);

		if ( 'running' !== $test_status ) {
			wp_send_json_error(
				array( 'message' => __( 'This test is not currently running.', 'elementtest-pro' ) )
			);
		}

		// Verify the variant belongs to the test.
		$variant_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT variant_id FROM {$variants_table} WHERE variant_id = %d AND test_id = %d",
				$variant_id,
				$test_id
			)
		);

		if ( ! $variant_exists ) {
			wp_send_json_error(
				array( 'message' => __( 'Variant does not belong to the specified test.', 'elementtest-pro' ) )
			);
		}

		// Rate limit: prevent duplicate impressions from the same user for the same
		// variant within a 30-minute window.
		$recent = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT event_id FROM {$events_table}
				WHERE test_id = %d
				  AND variant_id = %d
				  AND user_hash = %s
				  AND event_type = 'impression'
				  AND created_at > DATE_SUB( UTC_TIMESTAMP(), INTERVAL 30 MINUTE )
				LIMIT 1",
				$test_id,
				$variant_id,
				$user_hash
			)
		);

		if ( $recent ) {
			// Silently succeed -- the impression was already recorded recently.
			wp_send_json_success(
				array(
					'message'    => __( 'Impression already recorded.', 'elementtest-pro' ),
					'duplicated' => true,
				)
			);
		}

		$result = $wpdb->insert(
			$events_table,
			array(
				'test_id'    => $test_id,
				'variant_id' => $variant_id,
				'user_hash'  => $user_hash,
				'event_type' => 'impression',
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to record impression.', 'elementtest-pro' ) )
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Impression recorded.', 'elementtest-pro' ),
				'duplicated' => false,
			)
		);
	}

	/**
	 * Record a conversion event.
	 *
	 * Available to both authenticated and guest users.
	 *
	 * Expects POST data:
	 *   - test_id       (int, required)
	 *   - variant_id    (int, required)
	 *   - user_hash     (string, required) Anonymous visitor identifier.
	 *   - conversion_id (int, optional) Links to a specific conversion goal.
	 *   - revenue       (float, optional) Revenue amount for this conversion.
	 *   - page_url      (string, required for non-pageview goals) Current page URL.
	 *
	 * @since 1.0.0
	 */
	public function track_conversion() {
		$this->verify_public_request();

		global $wpdb;

		$events_table      = $wpdb->prefix . 'elementtest_events';
		$tests_table       = $wpdb->prefix . 'elementtest_tests';
		$variants_table    = $wpdb->prefix . 'elementtest_variants';
		$conversions_table = $wpdb->prefix . 'elementtest_conversions';

		$test_id       = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;
		$variant_id    = isset( $_POST['variant_id'] ) ? absint( $_POST['variant_id'] ) : 0;
		$user_hash     = ElementTest_Visitor::get_user_hash();
		$conversion_id = isset( $_POST['conversion_id'] ) ? absint( $_POST['conversion_id'] ) : 0;
		$client_revenue = isset( $_POST['revenue'] ) ? floatval( $_POST['revenue'] ) : 0.00;
		$client_page_url = isset( $_POST['page_url'] ) ? esc_url_raw( substr( wp_unslash( $_POST['page_url'] ), 0, 500 ) ) : '';
		$product_id    = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product_name  = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		$product_qty   = isset( $_POST['product_qty'] ) ? absint( $_POST['product_qty'] ) : 0;

		// Validate required fields.
		if ( ! $test_id || ! $variant_id || empty( $user_hash ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID, variant ID, and user hash are required.', 'elementtest-pro' ) )
			);
		}

		// IP-based rate limit — caps total conversion inserts regardless of identity.
		if ( $this->check_ip_rate_limit( $test_id, 'conversion' ) ) {
			status_header( 429 );
			wp_send_json_error(
				array( 'message' => __( 'Rate limit exceeded. Please try again later.', 'elementtest-pro' ) )
			);
		}

		// Verify the test is currently running.
		$test = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, page_url FROM {$tests_table} WHERE test_id = %d",
				$test_id
			),
			ARRAY_A
		);

		if ( empty( $test ) || 'running' !== $test['status'] ) {
			wp_send_json_error(
				array( 'message' => __( 'This test is not currently running.', 'elementtest-pro' ) )
			);
		}

		$test_page_url = isset( $test['page_url'] ) ? esc_url_raw( $test['page_url'] ) : '';

		// Verify the variant belongs to the test.
		$variant_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT variant_id FROM {$variants_table} WHERE variant_id = %d AND test_id = %d",
				$variant_id,
				$test_id
			)
		);

		if ( ! $variant_exists ) {
			wp_send_json_error(
				array( 'message' => __( 'Variant does not belong to the specified test.', 'elementtest-pro' ) )
			);
		}

		$conversion_trigger_type = '';
		$db_revenue_value        = 0.00;

		// If a conversion_id is specified, verify it belongs to the test.
		if ( $conversion_id > 0 ) {
			$conversion = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT conversion_id, trigger_type, revenue_value
					FROM {$conversions_table}
					WHERE conversion_id = %d AND test_id = %d",
					$conversion_id,
					$test_id
				),
				ARRAY_A
			);

			if ( empty( $conversion ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Conversion goal not found for this test.', 'elementtest-pro' ) )
				);
			}

			$conversion_trigger_type = isset( $conversion['trigger_type'] ) ? sanitize_key( $conversion['trigger_type'] ) : '';
			$db_revenue_value        = isset( $conversion['revenue_value'] ) ? floatval( $conversion['revenue_value'] ) : 0.00;
		}

		// Enforce page-scoped conversion tracking at the write boundary.
		// Pageview goals are intentionally allowed to track cross-page destinations.
		if ( 'pageview' !== $conversion_trigger_type && ! $this->conversion_page_matches( $test_page_url, $client_page_url ) ) {
			status_header( 400 );
			wp_send_json_error(
				array( 'message' => __( 'Conversion event is not scoped to the test page.', 'elementtest-pro' ) )
			);
		}

		// Rate limit: prevent duplicate conversions from the same user for the same
		// goal within a 60-second window.
		//
		// For add_to_cart goals, dedupe by product identity so shoppers can add
		// multiple different items in quick succession without losing events.
		$dedup_conversion = $conversion_id > 0 ? $conversion_id : 0;
		$recent_conversion = false;

		if ( 'add_to_cart' === $conversion_trigger_type ) {
			$current_product_key = $this->build_add_to_cart_dedupe_key( $product_id, $product_name );

			$recent_conversions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT event_data
					FROM {$events_table}
					WHERE test_id = %d
					  AND variant_id = %d
					  AND user_hash = %s
					  AND event_type = 'conversion'
					  AND conversion_id = %d
					  AND created_at > DATE_SUB( UTC_TIMESTAMP(), INTERVAL 60 SECOND )
					ORDER BY event_id DESC
					LIMIT 20",
					$test_id,
					$variant_id,
					$user_hash,
					$dedup_conversion
				)
			);

			foreach ( $recent_conversions as $existing ) {
				$existing_event_data = json_decode( $existing->event_data, true );
				if ( ! is_array( $existing_event_data ) ) {
					continue;
				}

				$existing_product_id = isset( $existing_event_data['product_id'] ) ? absint( $existing_event_data['product_id'] ) : 0;
				$existing_product_name = isset( $existing_event_data['product_name'] ) ? sanitize_text_field( $existing_event_data['product_name'] ) : '';
				$existing_product_key = $this->build_add_to_cart_dedupe_key( $existing_product_id, $existing_product_name );

				if ( '' !== $current_product_key && $existing_product_key === $current_product_key ) {
					$recent_conversion = true;
					break;
				}
			}
		} else {
			$recent_conversion = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT event_id FROM {$events_table}
					WHERE test_id = %d
					  AND variant_id = %d
					  AND user_hash = %s
					  AND event_type = 'conversion'
					  AND ( conversion_id = %d OR ( %d = 0 AND conversion_id IS NULL ) )
					  AND created_at > DATE_SUB( UTC_TIMESTAMP(), INTERVAL 60 SECOND )
					LIMIT 1",
					$test_id,
					$variant_id,
					$user_hash,
					$dedup_conversion,
					$dedup_conversion
				)
			);
		}

		if ( $recent_conversion ) {
			wp_send_json_success(
				array(
					'message'    => __( 'Conversion already recorded.', 'elementtest-pro' ),
					'duplicated' => true,
				)
			);
		}

		// Revenue hardening: derive the canonical revenue from the DB-stored
		// goal value instead of trusting the client-supplied amount.
		if ( 'custom_event' === $conversion_trigger_type ) {
			$max_revenue = (float) apply_filters( 'elementtest_max_revenue', 10000.00 );
			$revenue     = min( max( 0, $client_revenue ), $max_revenue );
		} else {
			$revenue = max( 0, $db_revenue_value );
		}

		// Build event_data JSON, including WooCommerce product info when present.
		$event_data_array = array(
			'conversion_id'  => $conversion_id > 0 ? $conversion_id : null,
			'client_revenue' => $client_revenue,
		);

		if ( isset( $_POST['product_id'] ) ) {
			$event_data_array['product_id'] = $product_id;
		}
		if ( isset( $_POST['product_name'] ) ) {
			$event_data_array['product_name'] = $product_name;
		}
		if ( isset( $_POST['product_qty'] ) ) {
			$event_data_array['product_qty'] = $product_qty;
		}

		$event_data = wp_json_encode( $event_data_array );

		$insert_data = array(
			'test_id'       => $test_id,
			'variant_id'    => $variant_id,
			'user_hash'     => $user_hash,
			'event_type'    => 'conversion',
			'conversion_id' => $conversion_id > 0 ? $conversion_id : null,
			'revenue'       => $revenue,
			'event_data'    => $event_data,
			'created_at'    => current_time( 'mysql', true ),
		);

		$insert_format = array( '%d', '%d', '%s', '%s', '%d', '%f', '%s', '%s' );

		if ( 0 === $conversion_id ) {
			unset( $insert_data['conversion_id'] );
			$insert_format = array( '%d', '%d', '%s', '%s', '%f', '%s', '%s' );
		}

		$result = $wpdb->insert(
			$events_table,
			$insert_data,
			$insert_format
		);

		if ( false === $result ) {
			if ( $wpdb->last_error ) {
				error_log( '[ElementTest] Conversion insert failed. DB Error: ' . $wpdb->last_error );
			}
			wp_send_json_error(
				array( 'message' => __( 'Failed to record conversion.', 'elementtest-pro' ) )
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Conversion recorded.', 'elementtest-pro' ) )
		);
	}

	/**
	 * Validate that a conversion request originated from the test page URL.
	 *
	 * Supports simple wildcard prefixes (trailing `*`) in stored test URLs.
	 *
	 * @since 2.2.7
	 * @param string $test_page_url   Stored test page URL.
	 * @param string $client_page_url Current page URL provided by frontend tracking.
	 * @return bool
	 */
	private function conversion_page_matches( $test_page_url, $client_page_url ) {
		$test_page_url   = trim( (string) $test_page_url );
		$client_page_url = trim( (string) $client_page_url );

		if ( '' === $test_page_url || '' === $client_page_url ) {
			return false;
		}

		$client_normalized = $this->normalize_conversion_url( $client_page_url );
		if ( '' === $client_normalized ) {
			return false;
		}

		$is_wildcard = '*' === substr( $test_page_url, -1 );
		$target_url  = $is_wildcard ? substr( $test_page_url, 0, -1 ) : $test_page_url;
		$target_normalized = $this->normalize_conversion_url( $target_url );

		if ( '' === $target_normalized ) {
			return false;
		}

		if ( $is_wildcard ) {
			if ( $client_normalized === $target_normalized ) {
				return true;
			}
			return 0 === strpos( $client_normalized, $target_normalized . '/' );
		}

		return $client_normalized === $target_normalized;
	}

	/**
	 * Normalize a URL into a stable host[:port]/path form for equality checks.
	 *
	 * Query strings and fragments are intentionally ignored.
	 *
	 * @since 2.2.7
	 * @param string $url Raw URL from request or DB.
	 * @return string Normalized URL key, or empty string when invalid.
	 */
	private function normalize_conversion_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$url_parts = wp_parse_url( $url );
		if ( false === $url_parts ) {
			return '';
		}

		$home_parts = wp_parse_url( home_url() );
		if ( false === $home_parts ) {
			$home_parts = array();
		}

		$host = isset( $url_parts['host'] ) ? strtolower( (string) $url_parts['host'] ) : ( isset( $home_parts['host'] ) ? strtolower( (string) $home_parts['host'] ) : '' );
		if ( '' === $host ) {
			return '';
		}

		$port = '';
		if ( isset( $url_parts['port'] ) ) {
			$port = ':' . absint( $url_parts['port'] );
		} elseif ( isset( $home_parts['port'] ) ) {
			$port = ':' . absint( $home_parts['port'] );
		}

		$path = isset( $url_parts['path'] ) ? (string) $url_parts['path'] : '/';
		$path = '/' . ltrim( $path, '/' );
		$path = '/' === $path ? '/' : untrailingslashit( $path );

		return $host . $port . strtolower( $path );
	}

	/**
	 * Check per-IP rate limit for a tracking action.
	 *
	 * Uses WordPress transients to count requests per IP/test/event-type
	 * within a rolling window. Returns true when the limit is exceeded.
	 *
	 * @since  2.2.0
	 * @param  int    $test_id    The test being tracked.
	 * @param  string $event_type Either 'impression' or 'conversion'.
	 * @return bool   True if the request should be rejected (limit exceeded).
	 */
	private function check_ip_rate_limit( $test_id, $event_type ) {
		$defaults = array(
			'impression' => 50,
			'conversion' => 20,
		);

		$limits = apply_filters( 'elementtest_rate_limits', $defaults );
		$max    = isset( $limits[ $event_type ] ) ? absint( $limits[ $event_type ] ) : 50;

		$ip  = ElementTest_Visitor::get_visitor_ip();
		$raw = $ip . '|' . $test_id . '|' . $event_type;
		$key = 'etrl_' . substr( hash( 'sha256', $raw ), 0, 32 );

		$window_seconds = HOUR_IN_SECONDS;
		$now            = time();
		$bucket         = get_transient( $key );

		if ( false === $bucket ) {
			set_transient(
				$key,
				array(
					'count'      => 1,
					'expires_at' => $now + $window_seconds,
				),
				$window_seconds
			);
			return false;
		}

		$count      = 0;
		$expires_at = 0;

		if ( is_array( $bucket ) ) {
			$count      = isset( $bucket['count'] ) ? (int) $bucket['count'] : 0;
			$expires_at = isset( $bucket['expires_at'] ) ? (int) $bucket['expires_at'] : 0;
		} else {
			// Backward compatibility with older scalar-only transient values.
			$count      = (int) $bucket;
			$expires_at = (int) get_option( '_transient_timeout_' . $key );
			if ( $expires_at <= 0 ) {
				$expires_at = $now + $window_seconds;
			}
		}

		// If the stored bucket is malformed or stale, restart the window.
		if ( $expires_at <= $now ) {
			set_transient(
				$key,
				array(
					'count'      => 1,
					'expires_at' => $now + $window_seconds,
				),
				$window_seconds
			);
			return false;
		}

		if ( (int) $count >= $max ) {
			return true;
		}

		$remaining_ttl = max( 1, $expires_at - $now );
		set_transient(
			$key,
			array(
				'count'      => (int) $count + 1,
				'expires_at' => $expires_at,
			),
			$remaining_ttl
		);
		return false;
	}

	/**
	 * Build a stable dedupe key for add-to-cart conversions.
	 *
	 * Prefer product_id when available. Fall back to product_name so goals still
	 * dedupe when the button payload does not include an ID. Returns an empty
	 * string when neither identifier is available so callers can skip dedup
	 * rather than falsely matching unrelated products.
	 *
	 * @since 2.1.0
	 * @param int    $product_id   WooCommerce product ID.
	 * @param string $product_name WooCommerce product name.
	 * @return string
	 */
	private function build_add_to_cart_dedupe_key( $product_id, $product_name ) {
		$product_id = absint( $product_id );
		if ( $product_id > 0 ) {
			return 'id:' . $product_id;
		}

		$product_name = strtolower( trim( (string) $product_name ) );
		if ( '' !== $product_name ) {
			return 'name:' . $product_name;
		}

		return '';
	}

	/**
	 * Get or create a variant assignment for a visitor.
	 *
	 * Determines which variant a user should see based on the test's
	 * traffic split configuration. If the user already has an impression
	 * for this test, they receive the same variant (sticky assignment).
	 * Otherwise, a new assignment is made using weighted random selection.
	 *
	 * Available to both authenticated and guest users.
	 *
	 * Expects POST data:
	 *   - test_id   (int, required)
	 *   - user_hash (string, required) Anonymous visitor identifier.
	 *
	 * @since 1.0.0
	 */
	public function get_variant_assignment() {
		$this->verify_public_request();

		global $wpdb;

		$events_table   = $wpdb->prefix . 'elementtest_events';
		$tests_table    = $wpdb->prefix . 'elementtest_tests';
		$variants_table = $wpdb->prefix . 'elementtest_variants';

		$test_id   = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;
		$user_hash = ElementTest_Visitor::get_user_hash();

		// Validate required fields.
		if ( ! $test_id || empty( $user_hash ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Test ID and user hash are required.', 'elementtest-pro' ) )
			);
		}

		// Verify the test is currently running.
		$test_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$tests_table} WHERE test_id = %d",
				$test_id
			)
		);

		if ( 'running' !== $test_status ) {
			wp_send_json_error(
				array( 'message' => __( 'This test is not currently running.', 'elementtest-pro' ) )
			);
		}

		// Check for existing assignment (sticky sessions).
		$existing_variant_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT variant_id FROM {$events_table}
				WHERE test_id = %d
				  AND user_hash = %s
				  AND event_type = 'impression'
				ORDER BY created_at ASC
				LIMIT 1",
				$test_id,
				$user_hash
			)
		);

		if ( $existing_variant_id ) {
			// Verify the variant still exists (it may have been deleted).
			$variant = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT variant_id, name, changes, is_control FROM {$variants_table}
					WHERE variant_id = %d AND test_id = %d",
					$existing_variant_id,
					$test_id
				),
				ARRAY_A
			);

			if ( $variant ) {
				$variant['changes'] = json_decode( $variant['changes'], true );

				wp_send_json_success(
					array(
						'variant'      => $variant,
						'is_new'       => false,
					)
				);
			}

			// If the variant was deleted, fall through to assign a new one.
		}

		// Fetch all variants for the test.
		$variants = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT variant_id, name, changes, traffic_percentage, is_control
				FROM {$variants_table}
				WHERE test_id = %d
				ORDER BY is_control DESC, variant_id ASC",
				$test_id
			),
			ARRAY_A
		);

		if ( empty( $variants ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No variants configured for this test.', 'elementtest-pro' ) )
			);
		}

		// Weighted random selection based on traffic_percentage.
		$assigned_variant = $this->select_variant_by_weight( $variants );

		if ( ! $assigned_variant ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to assign a variant.', 'elementtest-pro' ) )
			);
		}

		$assigned_variant['changes'] = json_decode( $assigned_variant['changes'], true );

		// Remove traffic_percentage from the response -- it is internal data.
		unset( $assigned_variant['traffic_percentage'] );

		wp_send_json_success(
			array(
				'variant' => $assigned_variant,
				'is_new'  => true,
			)
		);
	}

	/**
	 * Select a variant using weighted random selection.
	 *
	 * Each variant's traffic_percentage acts as its weight in the random
	 * selection. A total weight is computed and a random number is generated
	 * within that range. The variant whose cumulative weight range contains
	 * the random number is selected.
	 *
	 * @since  1.0.0
	 * @param  array $variants Array of variant rows, each with a 'traffic_percentage' key.
	 * @return array|null      The selected variant row, or null on failure.
	 */
	private function select_variant_by_weight( $variants ) {
		$total_weight = 0;

		foreach ( $variants as $variant ) {
			$total_weight += (int) $variant['traffic_percentage'];
		}

		if ( $total_weight <= 0 ) {
			// Fallback: equal distribution when all weights are zero.
			return $variants[ array_rand( $variants ) ];
		}

		$random    = wp_rand( 1, $total_weight );
		$cumulative = 0;

		foreach ( $variants as $variant ) {
			$cumulative += (int) $variant['traffic_percentage'];
			if ( $random <= $cumulative ) {
				return $variant;
			}
		}

		// Fallback -- should not be reached.
		return end( $variants );
	}

	// =================================================================
	// Additional admin actions
	// =================================================================

	/**
	 * Search WordPress pages/posts for the page browse modal.
	 *
	 * @since 1.0.0
	 */
	public function search_pages() {
		$this->verify_admin_request();

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$args = array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query   = new WP_Query( $args );
		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$results[] = array(
					'id'    => get_the_ID(),
					'title' => get_the_title(),
					'url'   => get_permalink(),
				);
			}
			wp_reset_postdata();
		}

		wp_send_json_success( $results );
	}

	/**
	 * Duplicate an existing test and its variants.
	 *
	 * @since 1.0.0
	 */
	public function duplicate_test() {
		$this->verify_admin_request();

		global $wpdb;

		$test_id = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;

		if ( ! $test_id ) {
			wp_send_json_error( __( 'Invalid test ID.', 'elementtest-pro' ) );
		}

		$tests_table    = $wpdb->prefix . 'elementtest_tests';
		$variants_table = $wpdb->prefix . 'elementtest_variants';

		// Get the original test.
		$test = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$tests_table} WHERE test_id = %d", $test_id ),
			ARRAY_A
		);

		if ( ! $test ) {
			wp_send_json_error( __( 'Test not found.', 'elementtest-pro' ) );
		}

		// Insert duplicated test.
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$tests_table,
			array(
				'name'             => $test['name'] . ' (Copy)',
				'description'      => $test['description'],
				'status'           => 'draft',
				'page_url'         => $test['page_url'],
				'element_selector' => $test['element_selector'],
				'test_type'        => $test['test_type'],
				'start_date'       => null,
				'end_date'         => null,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', null, null, '%s', '%s' )
		);

		$new_test_id = $wpdb->insert_id;

		if ( ! $new_test_id ) {
			wp_send_json_error( __( 'Failed to duplicate test.', 'elementtest-pro' ) );
		}

		// Duplicate variants.
		$variants = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$variants_table} WHERE test_id = %d", $test_id ),
			ARRAY_A
		);

		foreach ( $variants as $variant ) {
			$wpdb->insert(
				$variants_table,
				array(
					'test_id'            => $new_test_id,
					'name'               => $variant['name'],
					'changes'            => $variant['changes'],
					'traffic_percentage' => $variant['traffic_percentage'],
					'is_control'         => $variant['is_control'],
					'created_at'         => $now,
				),
				array( '%d', '%s', '%s', '%d', '%d', '%s' )
			);
		}

		wp_send_json_success( array(
			'test_id' => $new_test_id,
			'message' => __( 'Test duplicated successfully.', 'elementtest-pro' ),
		) );
	}

	/**
	 * Toggle test status (start/pause).
	 *
	 * @since 1.0.0
	 */
	public function toggle_status() {
		$this->verify_admin_request();

		global $wpdb;

		$test_id    = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;
		$new_status = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';

		if ( ! $test_id ) {
			wp_send_json_error( __( 'Invalid test ID.', 'elementtest-pro' ) );
		}

		if ( ! in_array( $new_status, $this->valid_statuses, true ) ) {
			wp_send_json_error( __( 'Invalid status.', 'elementtest-pro' ) );
		}

		$tests_table = $wpdb->prefix . 'elementtest_tests';

		$updated = $wpdb->update(
			$tests_table,
			array(
				'status'     => $new_status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'test_id' => $test_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( __( 'Failed to update test status.', 'elementtest-pro' ) );
		}

		wp_send_json_success( array(
			'test_id' => $test_id,
			'status'  => $new_status,
			'message' => __( 'Test status updated.', 'elementtest-pro' ),
		) );
	}

	/**
	 * Proxy a page's HTML for the visual element selector iframe.
	 *
	 * Fetches the page HTML from the local WordPress site,
	 * injects the selector interaction script, and outputs
	 * the modified HTML directly.
	 *
	 * @since 1.0.0
	 */
	public function proxy_page() {
		$this->verify_admin_request();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$url = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';

		if ( empty( $url ) ) {
			wp_die( esc_html__( 'No URL provided.', 'elementtest-pro' ), 400 );
		}

		// Only allow proxying URLs from this WordPress site.
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$target = wp_parse_url( $url, PHP_URL_HOST );

		if ( $target !== $home ) {
			wp_die( esc_html__( 'Only pages from this site can be loaded.', 'elementtest-pro' ), 403 );
		}

		// Fetch the page HTML. Only forward WordPress authentication cookies
		// needed for the logged-in page render, not the full $_COOKIE jar.
		$forward_cookies = array();
		foreach ( $_COOKIE as $name => $value ) {
			if ( strpos( $name, 'wordpress_logged_in_' ) === 0 ) {
				$forward_cookies[] = new WP_Http_Cookie( array(
					'name'  => $name,
					'value' => $value,
				) );
			}
		}

		$response = wp_remote_get( $url, array(
			'timeout'   => 15,
			'sslverify' => apply_filters( 'elementtest_proxy_sslverify', true ),
			'cookies'   => $forward_cookies,
		) );

		if ( is_wp_error( $response ) ) {
			wp_die( esc_html( $response->get_error_message() ), 500 );
		}

		$html = wp_remote_retrieve_body( $response );

		if ( empty( $html ) ) {
			wp_die( esc_html__( 'Failed to load page content.', 'elementtest-pro' ), 500 );
		}

		// Inject the selector script before </body>.
		$inject_url = ELEMENTTEST_PLUGIN_URL . 'assets/js/selector-inject.js?v=' . ELEMENTTEST_VERSION;
		$script_tag = '<script src="' . esc_url( $inject_url ) . '"></script>';

		// Also inject a base tag to fix relative URLs.
		$base_tag = '<base href="' . esc_url( $url ) . '">';

		// Insert base tag after <head>.
		$html = preg_replace( '/(<head[^>]*>)/i', '$1' . $base_tag, $html, 1 );

		// Insert script before </body>.
		if ( stripos( $html, '</body>' ) !== false ) {
			$html = str_ireplace( '</body>', $script_tag . '</body>', $html );
		} else {
			$html .= $script_tag;
		}

		// Output the modified HTML.
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: same-origin' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- proxied page HTML.
		exit;
	}

	/**
	 * Get results data for a test (daily breakdown).
	 *
	 * Returns daily impression/conversion counts per variant
	 * for the timeline chart on the results dashboard.
	 *
	 * @since 1.0.0
	 */
	public function get_results_data() {
		$this->verify_admin_request();

		global $wpdb;

		$test_id = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;

		if ( ! $test_id ) {
			wp_send_json_error( __( 'Invalid test ID.', 'elementtest-pro' ) );
		}

		$events_table   = $wpdb->prefix . 'elementtest_events';
		$variants_table = $wpdb->prefix . 'elementtest_variants';

		// Get variant names.
		$variants = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT variant_id, name FROM {$variants_table} WHERE test_id = %d ORDER BY is_control DESC, variant_id ASC",
				$test_id
			)
		);

		$variant_map = array();
		foreach ( $variants as $v ) {
			$variant_map[ $v->variant_id ] = $v->name;
		}

		// Get daily event counts.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS event_date, variant_id, event_type, COUNT(*) AS cnt
				 FROM {$events_table}
				 WHERE test_id = %d
				 GROUP BY event_date, variant_id, event_type
				 ORDER BY event_date ASC",
				$test_id
			)
		);

		// Build structured daily data.
		$daily = array();
		foreach ( $rows as $row ) {
			$date       = $row->event_date;
			$variant_id = absint( $row->variant_id );
			$v_name     = isset( $variant_map[ $variant_id ] ) ? $variant_map[ $variant_id ] : 'Unknown';
			$count      = absint( $row->cnt );

			if ( ! isset( $daily[ $date ] ) ) {
				$daily[ $date ] = array( 'date' => $date, 'variants' => array() );
			}

			if ( ! isset( $daily[ $date ]['variants'][ $v_name ] ) ) {
				$daily[ $date ]['variants'][ $v_name ] = array(
					'impressions' => 0,
					'conversions' => 0,
				);
			}

			if ( $row->event_type === 'impression' ) {
				$daily[ $date ]['variants'][ $v_name ]['impressions'] = $count;
			} elseif ( $row->event_type === 'conversion' ) {
				$daily[ $date ]['variants'][ $v_name ]['conversions'] = $count;
			}
		}

		wp_send_json_success( array_values( $daily ) );
	}

	// =========================================================================
	// Import / Export
	// =========================================================================

	/**
	 * Export one or more tests as a portable JSON structure.
	 *
	 * Accepts POST data:
	 *   - test_ids (array, optional) Array of test IDs. If empty, exports all.
	 *
	 * @since 1.0.0
	 */
	public function export_tests() {
		$this->verify_admin_request();

		global $wpdb;

		$tests_table       = $wpdb->prefix . 'elementtest_tests';
		$variants_table    = $wpdb->prefix . 'elementtest_variants';
		$conversions_table = $wpdb->prefix . 'elementtest_conversions';

		$test_ids = isset( $_POST['test_ids'] ) && is_array( $_POST['test_ids'] )
			? array_map( 'absint', $_POST['test_ids'] )
			: array();

		if ( ! empty( $test_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $test_ids ), '%d' ) );
			$tests = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$tests_table} WHERE test_id IN ($placeholders) ORDER BY test_id ASC",
					...$test_ids
				),
				ARRAY_A
			);
		} else {
			$tests = $wpdb->get_results(
				"SELECT * FROM {$tests_table} ORDER BY test_id ASC",
				ARRAY_A
			);
		}

		if ( empty( $tests ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No tests found to export.', 'elementtest-pro' ) )
			);
		}

		$export = array(
			'elementtest_version' => '1.0.0',
			'exported_at'         => gmdate( 'c' ),
			'tests'               => array(),
		);

		foreach ( $tests as $test ) {
			$tid = absint( $test['test_id'] );

			$variants = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT name, changes, traffic_percentage, is_control FROM {$variants_table} WHERE test_id = %d ORDER BY is_control DESC, variant_id ASC",
					$tid
				),
				ARRAY_A
			);

			foreach ( $variants as &$v ) {
				$v['traffic_percentage'] = absint( $v['traffic_percentage'] );
				$v['is_control']         = absint( $v['is_control'] );
			}
			unset( $v );

			$goals = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT name, trigger_type, trigger_selector, trigger_event, revenue_value FROM {$conversions_table} WHERE test_id = %d ORDER BY conversion_id ASC",
					$tid
				),
				ARRAY_A
			);

			foreach ( $goals as &$g ) {
				$g['revenue_value'] = floatval( $g['revenue_value'] );
			}
			unset( $g );

			$export['tests'][] = array(
				'name'             => $test['name'],
				'description'      => $test['description'],
				'page_url'         => $test['page_url'],
				'element_selector' => $test['element_selector'],
				'test_type'        => $test['test_type'],
				'variants'         => $variants,
				'goals'            => $goals,
			);
		}

		wp_send_json_success( $export );
	}

	/**
	 * Import tests from a JSON payload.
	 *
	 * Accepts POST data:
	 *   - json_data (string, required) The JSON export string.
	 *
	 * All imported tests are created as drafts with fresh timestamps.
	 *
	 * @since 1.0.0
	 */
	public function import_tests() {
		$this->verify_admin_request();

		global $wpdb;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized per-field below.
		$json_data = isset( $_POST['json_data'] ) ? wp_unslash( $_POST['json_data'] ) : '';

		if ( empty( $json_data ) || ! is_string( $json_data ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No JSON data provided.', 'elementtest-pro' ) )
			);
		}

		$data = json_decode( $json_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid JSON format.', 'elementtest-pro' ) . ' ' . json_last_error_msg() )
			);
		}

		if ( empty( $data['tests'] ) || ! is_array( $data['tests'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'JSON must contain a "tests" array.', 'elementtest-pro' ) )
			);
		}

		$tests_table       = $wpdb->prefix . 'elementtest_tests';
		$variants_table    = $wpdb->prefix . 'elementtest_variants';
		$conversions_table = $wpdb->prefix . 'elementtest_conversions';
		$now               = current_time( 'mysql', true );
		$imported          = 0;
		$errors            = array();

		foreach ( $data['tests'] as $index => $test ) {
			$test_num = $index + 1;

			$name = isset( $test['name'] ) ? sanitize_text_field( $test['name'] ) : '';
			if ( empty( $name ) ) {
				$errors[] = sprintf(
					/* translators: %d: test number in the import file */
					__( 'Test #%d: name is required. Skipped.', 'elementtest-pro' ),
					$test_num
				);
				continue;
			}

			$test_type = isset( $test['test_type'] ) ? sanitize_text_field( $test['test_type'] ) : 'css';
			if ( ! in_array( $test_type, $this->valid_test_types, true ) ) {
				$test_type = 'css';
			}

			$result = $wpdb->insert(
				$tests_table,
				array(
					'name'             => $name,
					'description'      => isset( $test['description'] ) ? sanitize_textarea_field( $test['description'] ) : '',
					'status'           => 'draft',
					'page_url'         => isset( $test['page_url'] ) ? esc_url_raw( substr( $test['page_url'], 0, 500 ) ) : '',
					'element_selector' => isset( $test['element_selector'] ) ? sanitize_text_field( $test['element_selector'] ) : '',
					'test_type'        => $test_type,
					'start_date'       => null,
					'end_date'         => null,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', null, null, '%s', '%s' )
			);

			if ( false === $result ) {
				$errors[] = sprintf(
					/* translators: %d: test number, %s: test name */
					__( 'Test #%1$d "%2$s": database insert failed. Skipped.', 'elementtest-pro' ),
					$test_num,
					$name
				);
				continue;
			}

			$new_test_id = $wpdb->insert_id;

			// Import variants.
			if ( ! empty( $test['variants'] ) && is_array( $test['variants'] ) ) {
				foreach ( $test['variants'] as $variant ) {
					$v_name = isset( $variant['name'] ) ? sanitize_text_field( $variant['name'] ) : '';
					if ( empty( $v_name ) ) {
						continue;
					}

					$wpdb->insert(
						$variants_table,
						array(
							'test_id'            => $new_test_id,
							'name'               => $v_name,
							'changes'            => isset( $variant['changes'] ) ? wp_kses_post( $variant['changes'] ) : '',
							'traffic_percentage' => isset( $variant['traffic_percentage'] ) ? min( 100, max( 0, absint( $variant['traffic_percentage'] ) ) ) : 50,
							'is_control'         => isset( $variant['is_control'] ) ? absint( $variant['is_control'] ) : 0,
							'created_at'         => $now,
						),
						array( '%d', '%s', '%s', '%d', '%d', '%s' )
					);
				}
			}

			// Import conversion goals.
			if ( ! empty( $test['goals'] ) && is_array( $test['goals'] ) ) {
				foreach ( $test['goals'] as $goal ) {
					$g_name = isset( $goal['name'] ) ? sanitize_text_field( $goal['name'] ) : '';
					if ( empty( $g_name ) ) {
						continue;
					}

					$trigger_type = isset( $goal['trigger_type'] ) ? sanitize_text_field( $goal['trigger_type'] ) : '';
					if ( ! in_array( $trigger_type, $this->valid_trigger_types, true ) ) {
						continue;
					}

					$wpdb->insert(
						$conversions_table,
						array(
							'test_id'          => $new_test_id,
							'name'             => $g_name,
							'trigger_type'     => $trigger_type,
							'trigger_selector' => isset( $goal['trigger_selector'] ) ? sanitize_text_field( $goal['trigger_selector'] ) : '',
							'trigger_event'    => isset( $goal['trigger_event'] ) ? sanitize_text_field( $goal['trigger_event'] ) : '',
							'revenue_value'    => isset( $goal['revenue_value'] ) ? floatval( $goal['revenue_value'] ) : 0.00,
							'created_at'       => $now,
						),
						array( '%d', '%s', '%s', '%s', '%s', '%f', '%s' )
					);
				}
			}

			$imported++;
		}

		if ( 0 === $imported ) {
			wp_send_json_error(
				array(
					'message' => __( 'No tests were imported.', 'elementtest-pro' ),
					'errors'  => $errors,
				)
			);
		}

		wp_send_json_success( array(
			'message'  => sprintf(
				/* translators: %d: number of tests imported */
				_n( '%d test imported as draft.', '%d tests imported as drafts.', $imported, 'elementtest-pro' ),
				$imported
			),
			'imported' => $imported,
			'errors'   => $errors,
		) );
	}

	// =========================================================================
	// Report Export
	// =========================================================================

	/**
	 * Export a single test report as HTML or CSV.
	 *
	 * Returns the file content as a JSON payload so the client can trigger
	 * a blob download (same pattern as export_tests).
	 *
	 * @since 2.3.0
	 */
	public function export_report() {
		$this->verify_admin_request();

		$test_id = isset( $_POST['test_id'] ) ? absint( $_POST['test_id'] ) : 0;
		$format  = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : 'html';

		if ( ! in_array( $format, array( 'html', 'csv' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid format.', 'elementtest-pro' ) ) );
		}

		$generator = new ElementTest_Report_Generator();
		$data      = $generator->get_report_data( $test_id );

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ) );
		}

		$content  = 'csv' === $format ? $generator->generate_csv( $data ) : $generator->generate_html( $data );
		$ext      = 'csv' === $format ? 'csv' : 'html';
		$mimetype = 'csv' === $format ? 'text/csv' : 'text/html';
		$filename = sprintf( 'elementtest-report-%d-%s.%s', $test_id, gmdate( 'Y-m-d' ), $ext );

		wp_send_json_success( array(
			'content'  => $content,
			'filename' => $filename,
			'mimetype' => $mimetype,
		) );
	}

	/**
	 * Export reports for all non-draft tests.
	 *
	 * If ZipArchive is available, returns a base64-encoded zip.
	 * Otherwise returns an array of individual file contents.
	 *
	 * @since 2.3.0
	 */
	public function export_all_reports() {
		$this->verify_admin_request();

		$format = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : 'html';

		if ( ! in_array( $format, array( 'html', 'csv' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid format.', 'elementtest-pro' ) ) );
		}

		$generator = new ElementTest_Report_Generator();
		$reports   = $generator->get_all_report_data();

		if ( empty( $reports ) ) {
			wp_send_json_error( array( 'message' => __( 'No non-draft tests found.', 'elementtest-pro' ) ) );
		}

		$ext      = 'csv' === $format ? 'csv' : 'html';
		$mimetype = 'csv' === $format ? 'text/csv' : 'text/html';
		$files    = array();

		foreach ( $reports as $data ) {
			$content  = 'csv' === $format ? $generator->generate_csv( $data ) : $generator->generate_html( $data );
			$filename = sprintf( 'test-%d.%s', $data['test']['test_id'], $ext );
			$files[]  = array(
				'filename' => $filename,
				'content'  => $content,
			);
		}

		if ( class_exists( 'ZipArchive' ) ) {
			$tmp = wp_tempnam( 'elementtest-reports' );
			$zip = new ZipArchive();

			if ( true === $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
				foreach ( $files as $f ) {
					$zip->addFromString( $f['filename'], $f['content'] );
				}
				$zip->close();

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$zip_content = file_get_contents( $tmp );
				wp_delete_file( $tmp );

				wp_send_json_success( array(
					'zip'      => true,
					'content'  => base64_encode( $zip_content ),
					'filename' => sprintf( 'elementtest-reports-%s.zip', gmdate( 'Y-m-d' ) ),
					'mimetype' => 'application/zip',
				) );
			}

			wp_delete_file( $tmp );
		}

		// Fallback: return individual files for sequential download.
		$file_payloads = array();
		foreach ( $files as $f ) {
			$file_payloads[] = array(
				'filename' => $f['filename'],
				'content'  => $f['content'],
				'mimetype' => $mimetype,
			);
		}

		wp_send_json_success( array(
			'zip'   => false,
			'files' => $file_payloads,
		) );
	}
}
