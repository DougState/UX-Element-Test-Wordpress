<?php
/**
 * Frontend Variant Delivery for ElementTest Pro
 *
 * Detects active A/B tests on the current page, prepares variant
 * assignment data for the client-side engine, enqueues the frontend
 * script, and outputs an anti-flicker snippet to prevent FOOC.
 *
 * All cookie management and DOM manipulation happens in JavaScript
 * so that the page remains fully compatible with any object or page
 * cache (WP Super Cache, W3TC, LiteSpeed, etc.).
 *
 * @package ElementTestPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ElementTest_Frontend
 *
 * Singleton responsible for serving A/B test variants on the
 * public-facing side of the site.
 *
 * @since 1.0.0
 */
class ElementTest_Frontend {

	/**
	 * Single instance of this class.
	 *
	 * @since 1.0.0
	 * @var   ElementTest_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Active tests that match the current page.
	 *
	 * Populated by {@see check_active_tests()} and consumed by the
	 * asset-enqueue and anti-flicker methods later in the request.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private $active_tests = array();

	/**
	 * Running tests with pageview goals that target the current page.
	 *
	 * These are tests whose page_url does NOT match the current page,
	 * but whose pageview conversion goals DO. The frontend script uses
	 * cookie-based variant assignment to fire conversions on these pages.
	 *
	 * @since 1.0.0
	 * @var   array  Each element: { test_id, goals: [...] }
	 */
	private $pageview_goal_tests = array();

	/**
	 * Get the singleton instance.
	 *
	 * @since  1.0.0
	 * @return ElementTest_Frontend
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers all frontend hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Register WordPress hooks for frontend delivery.
	 *
	 * Uses the `wp` action (fires after the main query is set up) to
	 * detect tests, then conditionally hooks asset enqueue and the
	 * anti-flicker snippet.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'wp', array( $this, 'check_active_tests' ) );
	}

	// =========================================================================
	// Test Detection
	// =========================================================================

	/**
	 * Detect running A/B tests that target the current page.
	 *
	 * Queries for all tests with status = 'running', then compares
	 * each test's `page_url` against the current request URL using
	 * path-only matching (protocol, domain, and trailing slashes are
	 * normalised away so that stored URLs always match regardless of
	 * how they were saved in the admin).
	 *
	 * When at least one test matches, the script-enqueue and
	 * anti-flicker hooks are registered.
	 *
	 * @since 1.0.0
	 */
	public function check_active_tests() {
		// Only run on singular pages and front page.  Skip admin, AJAX,
		// REST, cron, and CLI contexts.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) ) {
			return;
		}

		// Respect the "exclude admins" setting.
		$settings = get_option( 'elementtest_settings', array() );
		if ( ! empty( $settings['exclude_admins'] ) && current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$tests_table = $wpdb->prefix . 'elementtest_tests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$running_tests = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				"SELECT * FROM {$tests_table} WHERE status = %s",
				'running'
			)
		);

		if ( empty( $running_tests ) ) {
			return;
		}

		$current_path = $this->get_current_path();

		foreach ( $running_tests as $test ) {
			if ( empty( $test->page_url ) ) {
				continue;
			}

			$test_path = $this->normalise_path( $test->page_url );

			if ( $test_path === $current_path ) {
				$this->active_tests[] = $test;
			}
		}

		// Check for cross-page pageview goals: running tests whose page_url
		// is a different page but have a pageview goal targeting THIS page.
		$this->detect_pageview_goal_tests( $running_tests, $current_path );

		if ( empty( $this->active_tests ) && empty( $this->pageview_goal_tests ) ) {
			return;
		}

		// Register hooks when there are active tests or conversion-only matches.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		if ( ! empty( $this->active_tests ) ) {
			add_action( 'wp_head', array( $this, 'output_antiflicker_css' ), 1 );
		}
	}

	/**
	 * Detect running tests with pageview goals that target the current page.
	 *
	 * For each running test that is NOT already in $this->active_tests,
	 * check whether any of its pageview conversion goals match the
	 * current path. If so, add it to $this->pageview_goal_tests so the
	 * frontend can fire conversions on this page.
	 *
	 * @since 1.0.0
	 * @param array  $running_tests All currently running tests.
	 * @param string $current_path  Normalised current page path.
	 */
	private function detect_pageview_goal_tests( $running_tests, $current_path ) {
		global $wpdb;

		$active_test_ids = array();
		foreach ( $this->active_tests as $t ) {
			$active_test_ids[] = absint( $t->test_id );
		}

		$conversions_table = $wpdb->prefix . 'elementtest_conversions';
		$variants_table    = $wpdb->prefix . 'elementtest_variants';
		$current_url       = ( is_ssl() ? 'https' : 'http' ) . '://'
			. ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' )
			. ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );

		foreach ( $running_tests as $test ) {
			$tid = absint( $test->test_id );

			if ( in_array( $tid, $active_test_ids, true ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$pageview_goals = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT conversion_id, trigger_event, revenue_value
					 FROM {$conversions_table}
					 WHERE test_id = %d AND trigger_type = 'pageview'",
					$tid
				)
			);

			if ( empty( $pageview_goals ) ) {
				continue;
			}

			$matching_goals = array();
			foreach ( $pageview_goals as $goal ) {
				$trigger = trim( $goal->trigger_event );
				if ( empty( $trigger ) ) {
					continue;
				}

				$matched = false;

				if ( substr( $trigger, -1 ) === '*' ) {
					$prefix = substr( $trigger, 0, -1 );
					$trigger_path = $this->normalise_path( $prefix );
					$matched = strpos( $current_path, $trigger_path ) === 0;
					if ( ! $matched ) {
						$matched = strpos( $current_url, $prefix ) === 0;
					}
				} else {
					$trigger_path = $this->normalise_path( $trigger );
					$matched = ( $current_path === $trigger_path );
				}

				if ( $matched ) {
					$matching_goals[] = array(
						'conversion_id' => absint( $goal->conversion_id ),
						'trigger_type'  => 'pageview',
						'trigger_event' => sanitize_text_field( $goal->trigger_event ),
						'revenue_value' => floatval( $goal->revenue_value ),
					);
				}
			}

			if ( ! empty( $matching_goals ) ) {
				// Fetch variant IDs so the JS can read the cookie assignment.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$variant_ids = $wpdb->get_col(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						"SELECT variant_id FROM {$variants_table} WHERE test_id = %d ORDER BY variant_id ASC",
						$tid
					)
				);

				$this->pageview_goal_tests[] = array(
					'test_id'     => $tid,
					'variant_ids' => array_map( 'absint', $variant_ids ),
					'goals'       => $matching_goals,
				);
			}
		}
	}

	/**
	 * Get the normalised path of the current request.
	 *
	 * @since  1.0.0
	 * @return string Lowercase path without trailing slash (or '/' for root).
	 */
	private function get_current_path() {
		// Use the WordPress home URL as the base so that subdirectory
		// installs are handled correctly.
		$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
		$home_path = $home_path ? untrailingslashit( $home_path ) : '';

		// Build current URL from server globals.
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';

		// Strip query strings so that UTM parameters and similar
		// fragments do not break matching.
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path = $request_path ? $request_path : '/';

		// Remove the WP home path prefix so that comparisons work the
		// same way regardless of whether WP lives in a subdirectory.
		if ( '' !== $home_path && strpos( $request_path, $home_path ) === 0 ) {
			$request_path = substr( $request_path, strlen( $home_path ) );
		}

		return $this->normalise_path( $request_path );
	}

	/**
	 * Normalise a URL or path for comparison.
	 *
	 * Strips the protocol, domain, query string, and fragment, converts
	 * to lowercase, removes trailing slashes, and returns a clean path.
	 * The root path is always returned as '/'.
	 *
	 * @since  1.0.0
	 * @param  string $url Full URL or relative path.
	 * @return string      Normalised path string.
	 */
	private function normalise_path( $url ) {
		// If it's a full URL, extract just the path component.
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = $path ? $path : '/';

		// If the site lives in a subdirectory, strip that prefix so
		// stored URLs like https://example.com/subdir/about match
		// the request path /about.
		$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
		$home_path = $home_path ? untrailingslashit( $home_path ) : '';

		if ( '' !== $home_path && strpos( $path, $home_path ) === 0 ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		// Lowercase, strip trailing slash, ensure leading slash.
		$path = strtolower( $path );
		$path = untrailingslashit( $path );

		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		return $path;
	}

	// =========================================================================
	// Frontend Asset Enqueue
	// =========================================================================

	/**
	 * Enqueue the frontend JavaScript and pass test configuration.
	 *
	 * Builds a structured array of test data (including the assigned
	 * or assignable variants and conversion goals) and passes it to
	 * the client via `wp_localize_script`.
	 *
	 * Cookie creation, variant DOM application, and impression tracking
	 * all happen in the JS layer to remain cache-friendly.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend_assets() {
		if ( empty( $this->active_tests ) && empty( $this->pageview_goal_tests ) ) {
			return;
		}

		wp_enqueue_script(
			'elementtest-frontend',
			ELEMENTTEST_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			ELEMENTTEST_VERSION,
			true
		);

		$tests_data = $this->build_tests_data();
		$settings   = get_option( 'elementtest_settings', array() );

		$localize_data = array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'elementtest-public' ),
			'tests'      => $tests_data,
			'cookieDays' => isset( $settings['cookie_days'] ) ? absint( $settings['cookie_days'] ) : 30,
			'userHash'   => $this->get_user_hash(),
		);

		if ( ! empty( $this->pageview_goal_tests ) ) {
			$localize_data['conversionOnlyTests'] = $this->build_conversion_only_data();
		}

		wp_localize_script(
			'elementtest-frontend',
			'elementtestFrontend',
			$localize_data
		);
	}

	/**
	 * Build the test configuration array passed to the frontend JS.
	 *
	 * For each active test the method fetches all variants and all
	 * conversion goals, then assembles them into a structure the
	 * frontend engine can consume directly.
	 *
	 * Variant assignment itself is NOT done here on the server; the
	 * full variant list (with traffic weights) is sent to the client
	 * so that JavaScript can handle assignment and cookie management.
	 * This keeps the HTML response identical for every visitor, which
	 * is essential for page-cache compatibility.
	 *
	 * @since  1.0.0
	 * @return array Numerically indexed array of test config arrays.
	 */
	private function build_tests_data() {
		global $wpdb;

		$variants_table    = $wpdb->prefix . 'elementtest_variants';
		$conversions_table = $wpdb->prefix . 'elementtest_conversions';

		$tests_data = array();

		foreach ( $this->active_tests as $test ) {
			$test_id = absint( $test->test_id );

			// Fetch all variants for this test.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$variants = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT variant_id, test_id, name, changes, traffic_percentage, is_control
					 FROM {$variants_table}
					 WHERE test_id = %d
					 ORDER BY is_control DESC, variant_id ASC",
					$test_id
				)
			);

			// Skip tests that have no variants configured.
			if ( empty( $variants ) ) {
				continue;
			}

			// Format variants for the JS payload.
			$variants_data = array();
			foreach ( $variants as $variant ) {
				$variants_data[] = array(
					'variant_id'         => absint( $variant->variant_id ),
					'name'               => sanitize_text_field( $variant->name ),
					'changes'            => $variant->changes,
					'traffic_percentage' => absint( $variant->traffic_percentage ),
					'is_control'         => absint( $variant->is_control ),
				);
			}

			// Fetch conversion goals for this test.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$conversions = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT conversion_id, trigger_type, trigger_selector, trigger_event, revenue_value
					 FROM {$conversions_table}
					 WHERE test_id = %d
					 ORDER BY conversion_id ASC",
					$test_id
				)
			);

			$goals_data = array();
			foreach ( $conversions as $conversion ) {
				$goals_data[] = array(
					'conversion_id'   => absint( $conversion->conversion_id ),
					'trigger_type'    => sanitize_key( $conversion->trigger_type ),
					'trigger_selector' => sanitize_text_field( $conversion->trigger_selector ),
					'trigger_event'   => sanitize_text_field( $conversion->trigger_event ),
					'revenue_value'   => floatval( $conversion->revenue_value ),
				);
			}

			$tests_data[] = array(
				'test_id'          => $test_id,
				'element_selector' => sanitize_text_field( $test->element_selector ),
				'test_type'        => sanitize_key( $test->test_type ),
				'variants'         => $variants_data,
				'goals'            => $goals_data,
			);
		}

		return $tests_data;
	}

	/**
	 * Build the conversion-only test data for cross-page pageview goals.
	 *
	 * Returns a lightweight array that the frontend uses to fire conversions
	 * on pages that are not the test's primary page but match a pageview goal.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private function build_conversion_only_data() {
		$data = array();
		foreach ( $this->pageview_goal_tests as $entry ) {
			$data[] = array(
				'test_id'     => $entry['test_id'],
				'variant_ids' => $entry['variant_ids'],
				'goals'       => $entry['goals'],
			);
		}
		return $data;
	}

	// =========================================================================
	// User Hash (Privacy-Friendly Visitor Identifier)
	// =========================================================================

	/**
	 * Generate a privacy-friendly hash for the current visitor.
	 *
	 * Delegates to the shared ElementTest_Visitor utility so that the
	 * server-side AJAX endpoints and the localized JS value always
	 * produce the same hash for a given request.
	 *
	 * @since  1.0.0
	 * @return string 64-character hexadecimal SHA-256 hash.
	 */
	public function get_user_hash() {
		return ElementTest_Visitor::get_user_hash();
	}

	/**
	 * Retrieve the visitor's IP address, accounting for reverse proxies.
	 *
	 * Delegates to ElementTest_Visitor for a single source of truth.
	 *
	 * @since  1.0.0
	 * @return string Sanitised IP address string.
	 */
	private function get_visitor_ip() {
		return ElementTest_Visitor::get_visitor_ip();
	}

	// =========================================================================
	// Anti-Flicker Snippet
	// =========================================================================

	/**
	 * Output an inline anti-flicker CSS + JS snippet in <head>.
	 *
	 * Hides every element targeted by an active test (opacity: 0) to
	 * prevent a "Flash Of Original Content" (FOOC) while the frontend
	 * JS loads and applies variant changes.
	 *
	 * A 2-second safety timeout guarantees that elements become visible
	 * even if the JS fails to load, preventing a permanently hidden
	 * page section.
	 *
	 * This output is purely CSS and a tiny bit of vanilla JS -- it
	 * does not vary per visitor, so it is safe to cache.
	 *
	 * @since 1.0.0
	 */
	public function output_antiflicker_css() {
		if ( empty( $this->active_tests ) ) {
			return;
		}

		// Collect unique selectors from all active tests.
		$selectors = array();
		foreach ( $this->active_tests as $test ) {
			if ( ! empty( $test->element_selector ) ) {
				$selectors[] = $test->element_selector;
			}
		}

		if ( empty( $selectors ) ) {
			return;
		}

		// Escape selectors for safe embedding inside a <style> tag.
		// Strip characters that could break out of the style context
		// or enable CSS injection (e.g., @import, expression(), url()).
		$safe_selectors = array();
		foreach ( $selectors as $selector ) {
			$cleaned = str_replace( array( '<', '>', '"', "'", '`', '{', '}', ';' ), '', $selector );
			$cleaned = preg_replace( '/@import/i', '', $cleaned );
			$cleaned = preg_replace( '/expression\s*\(/i', '', $cleaned );
			$cleaned = preg_replace( '/url\s*\(/i', '', $cleaned );
			$cleaned = trim( $cleaned );
			if ( '' !== $cleaned ) {
				$safe_selectors[] = $cleaned;
			}
		}

		if ( empty( $safe_selectors ) ) {
			return;
		}

		$css_selector = implode( ', ', $safe_selectors );

		?>
<!-- ElementTest Pro: Anti-flicker snippet -->
<style id="elementtest-antiflicker">
<?php echo $css_selector; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selectors are sanitised above. ?> {
	opacity: 0 !important;
	transition: opacity 0.15s ease-in-out;
}
</style>
<script>
(function(){
	/* Safety timeout: force-show elements if JS has not loaded after 2 s. */
	var t = setTimeout(function(){
		var s = document.getElementById('elementtest-antiflicker');
		if(s){ s.parentNode.removeChild(s); }
	}, 2000);
	/* Expose the timeout ID so frontend.js can clear it once ready. */
	window.elementtestAntiflickerTimeout = t;
})();
</script>
<!-- / ElementTest Pro: Anti-flicker snippet -->
		<?php
	}
}
