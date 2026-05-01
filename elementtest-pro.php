<?php
/**
 * Plugin Name: ElementTest Pro
 * Plugin URI: https://github.com/DougState/UX-Element-Test-Wordpress
 * Description: A/B test various elements (CSS, copy, JS, images) of your pages and track conversion data to measure performance.
 * Version: 2.4.2
 * Requires at least: 5.6
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Author: Doug Wagner
 * Author URI: https://dougstate.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elementtest-pro
 *
 * @package ElementTestPro
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// The render_*_page() and create_tables() methods below operate on the
// plugin's own custom tables (`{prefix}elementtest_tests`, `_variants`,
// `_events`, `_conversions`); table names come from the trusted
// `$wpdb->prefix` constant and are interpolated because `$wpdb->prepare()`
// does not accept identifier placeholders. `$_GET` reads in the render
// methods are read-only routing parameters and are sanitised via
// `absint()`/`sanitize_key()` before use.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
// phpcs:disable WordPress.Security.NonceVerification.Recommended

// Define plugin constants
define( 'ELEMENTTEST_VERSION', '2.4.2' );
define( 'ELEMENTTEST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELEMENTTEST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ELEMENTTEST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main ElementTest Pro Class
 */
class ElementTest_Pro {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get instance of the class
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Translations are auto-loaded by WordPress for plugins hosted on
        // WordPress.org since version 4.6, so load_plugin_textdomain() is
        // no longer needed.

        // Admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Enqueue admin styles and scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Proxy detection admin notice.
        add_action( 'admin_notices', array( $this, 'maybe_show_proxy_notice' ) );
        add_action( 'wp_ajax_elementtest_dismiss_proxy_notice', array( $this, 'dismiss_proxy_notice' ) );

        // Wire the proxy_type setting into the visitor IP filter.
        add_filter( 'elementtest_trusted_proxy_headers', array( $this, 'resolve_proxy_headers' ) );

        // Load includes.
        $this->load_includes();
    }

    /**
     * Map the proxy_type setting to trusted header names.
     *
     * @since  2.2.2
     * @param  array $headers Existing trusted headers (from code-level filters).
     * @return array
     */
    public function resolve_proxy_headers( $headers ) {
        $settings   = get_option( 'elementtest_settings', array() );
        $proxy_type = isset( $settings['proxy_type'] ) ? $settings['proxy_type'] : 'none';

        switch ( $proxy_type ) {
            case 'cloudflare':
                $headers[] = 'HTTP_CF_CONNECTING_IP';
                break;
            case 'nginx':
                $headers[] = 'HTTP_X_REAL_IP';
                $headers[] = 'HTTP_X_FORWARDED_FOR';
                break;
            case 'custom':
                $custom = isset( $settings['proxy_custom_header'] ) ? $settings['proxy_custom_header'] : '';
                if ( '' !== $custom ) {
                    $headers[] = $custom;
                }
                break;
        }

        return $headers;
    }

    /**
     * Load required include files.
     *
     * @since 1.0.0
     */
    private function load_includes() {
        require_once ELEMENTTEST_PLUGIN_DIR . 'includes/class-visitor.php';
        require_once ELEMENTTEST_PLUGIN_DIR . 'includes/class-report-generator.php';
        require_once ELEMENTTEST_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        ElementTest_Ajax_Handler::get_instance();

        // Frontend variant delivery -- only on the public side.
        if ( ! is_admin() ) {
            require_once ELEMENTTEST_PLUGIN_DIR . 'includes/class-frontend.php';
            ElementTest_Frontend::get_instance();
        }

        // WP-CLI commands -- only in CLI context.
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once ELEMENTTEST_PLUGIN_DIR . 'includes/class-cli-commands.php';
            WP_CLI::add_command( 'elementtest', 'ElementTest_CLI_Commands' );
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

        // Set default options
        add_option( 'elementtest_version', ELEMENTTEST_VERSION );
        add_option( 'elementtest_db_version', '1.0' );

        // Show the proxy configuration banner until user acknowledges it.
        delete_option( 'elementtest_proxy_notice_dismissed' );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array();

        // Tests table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}elementtest_tests (
            test_id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            status varchar(20) DEFAULT 'draft',
            page_url varchar(500),
            element_selector varchar(500),
            test_type varchar(20) DEFAULT 'css',
            start_date datetime,
            end_date datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (test_id),
            KEY status (status)
        ) $charset_collate;";

        // Variants table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}elementtest_variants (
            variant_id bigint(20) NOT NULL AUTO_INCREMENT,
            test_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            changes longtext,
            traffic_percentage int(3) DEFAULT 50,
            is_control tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (variant_id),
            KEY test_id (test_id)
        ) $charset_collate;";

        // Events table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}elementtest_events (
            event_id bigint(20) NOT NULL AUTO_INCREMENT,
            test_id bigint(20) NOT NULL,
            variant_id bigint(20) NOT NULL,
            user_hash varchar(64) NOT NULL,
            event_type varchar(50) NOT NULL,
            conversion_id bigint(20) DEFAULT NULL,
            revenue decimal(10,2) DEFAULT 0.00,
            event_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id),
            KEY test_id (test_id),
            KEY variant_id (variant_id),
            KEY user_hash (user_hash),
            KEY event_type (event_type),
            KEY conversion_id (conversion_id)
        ) $charset_collate;";

        // Conversions table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}elementtest_conversions (
            conversion_id bigint(20) NOT NULL AUTO_INCREMENT,
            test_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            trigger_type varchar(50) NOT NULL,
            trigger_selector varchar(500),
            trigger_event varchar(100),
            revenue_value decimal(10,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (conversion_id),
            KEY test_id (test_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        foreach ( $tables as $table ) {
            dbDelta( $table );
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'ElementTest Pro', 'elementtest-pro' ),
            __( 'ElementTest', 'elementtest-pro' ),
            'manage_options',
            'elementtest-pro',
            array( $this, 'render_admin_page' ),
            'dashicons-analytics',
            30
        );

        add_submenu_page(
            'elementtest-pro',
            __( 'All Tests', 'elementtest-pro' ),
            __( 'All Tests', 'elementtest-pro' ),
            'manage_options',
            'elementtest-pro',
            array( $this, 'render_admin_page' )
        );

        add_submenu_page(
            'elementtest-pro',
            __( 'Add New Test', 'elementtest-pro' ),
            __( 'Add New', 'elementtest-pro' ),
            'manage_options',
            'elementtest-new',
            array( $this, 'render_new_test_page' )
        );

        add_submenu_page(
            'elementtest-pro',
            __( 'Settings', 'elementtest-pro' ),
            __( 'Settings', 'elementtest-pro' ),
            'manage_options',
            'elementtest-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our plugin pages.
        if ( strpos( $hook, 'elementtest' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'elementtest-admin',
            ELEMENTTEST_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ELEMENTTEST_VERSION
        );

        $script_deps = array( 'jquery', 'wp-util' );

        // Enqueue media uploader on the new-test page for image variant support.
        if ( strpos( $hook, 'elementtest-new' ) !== false ) {
            wp_enqueue_media();

            // Element selector assets.
            wp_enqueue_style(
                'elementtest-selector',
                ELEMENTTEST_PLUGIN_URL . 'assets/css/element-selector.css',
                array(),
                ELEMENTTEST_VERSION
            );

            wp_enqueue_script(
                'elementtest-selector',
                ELEMENTTEST_PLUGIN_URL . 'assets/js/element-selector.js',
                array( 'jquery' ),
                ELEMENTTEST_VERSION,
                true
            );
        }

        // Results dashboard styles.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( strpos( $hook, 'elementtest-pro' ) !== false && isset( $_GET['view'] ) && 'results' === $_GET['view'] ) {
            wp_enqueue_style(
                'elementtest-results',
                ELEMENTTEST_PLUGIN_URL . 'assets/css/results.css',
                array(),
                ELEMENTTEST_VERSION
            );
        }

        wp_enqueue_script(
            'elementtest-admin',
            ELEMENTTEST_PLUGIN_URL . 'assets/js/admin.js',
            $script_deps,
            ELEMENTTEST_VERSION,
            true
        );

        wp_localize_script(
            'elementtest-admin',
            'elementtestAdmin',
            array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'elementtest-admin' ),
                'i18n'     => array(
                    'confirmRemoveVariant' => __( 'Are you sure you want to remove this variant?', 'elementtest-pro' ),
                    'confirmRemoveGoal'    => __( 'Are you sure you want to remove this goal?', 'elementtest-pro' ),
                    'trafficWarning'       => __( 'Traffic percentages must add up to 100%.', 'elementtest-pro' ),
                    'saving'               => __( 'Saving...', 'elementtest-pro' ),
                    'saved'                => __( 'Test saved successfully.', 'elementtest-pro' ),
                    'saveError'            => __( 'An error occurred while saving the test.', 'elementtest-pro' ),
                    'requiredFields'       => __( 'Please fill in all required fields.', 'elementtest-pro' ),
                    'selectImage'          => __( 'Select Image', 'elementtest-pro' ),
                    'useImage'             => __( 'Use This Image', 'elementtest-pro' ),
                    'noPages'              => __( 'No pages found.', 'elementtest-pro' ),
                    'searching'            => __( 'Searching...', 'elementtest-pro' ),
                ),
                'homeUrl'  => home_url( '/' ),
            )
        );
    }

    /**
     * Render main admin page (All Tests list or Results view).
     *
     * Queries the database for all tests with aggregated stats from the
     * variants and events tables, then loads the tests-list view template.
     * If ?view=results&test_id=X is present, shows the results dashboard.
     */
    public function render_admin_page() {
        global $wpdb;

        // Check if we're viewing results for a specific test.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
        if ( isset( $_GET['view'] ) && 'results' === $_GET['view'] && ! empty( $_GET['test_id'] ) ) {
            $this->render_results_page();
            return;
        }

        $tests_table    = $wpdb->prefix . 'elementtest_tests';
        $variants_table = $wpdb->prefix . 'elementtest_variants';
        $events_table   = $wpdb->prefix . 'elementtest_events';

        // Current status filter.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, no state change.
        $current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';

        // Build WHERE clause for status filtering.
        $where          = '';
        $valid_statuses = array( 'running', 'paused', 'draft', 'completed' );
        if ( in_array( $current_status, $valid_statuses, true ) ) {
            $where = $wpdb->prepare( 'WHERE t.status = %s', $current_status );
        }

        // Query tests with aggregated variant counts and event stats.
        $tests = $wpdb->get_results(
            "SELECT t.*,
                COALESCE( v.variant_count, 0 ) AS variant_count,
                COALESCE( e.impressions, 0 )   AS impressions,
                COALESCE( e.conversions, 0 )   AS conversions,
                CASE
                    WHEN COALESCE( e.impressions, 0 ) > 0
                    THEN ROUND( ( COALESCE( e.conversions, 0 ) / COALESCE( e.impressions, 0 ) ) * 100, 2 )
                    ELSE 0
                END AS conversion_rate,
                0 AS confidence
            FROM {$tests_table} AS t
            LEFT JOIN (
                SELECT test_id,
                    COUNT(*) AS variant_count
                FROM {$variants_table}
                GROUP BY test_id
            ) AS v ON t.test_id = v.test_id
            LEFT JOIN (
                SELECT test_id,
                    SUM( CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END ) AS impressions,
                    SUM( CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END ) AS conversions
                FROM {$events_table}
                GROUP BY test_id
            ) AS e ON t.test_id = e.test_id
            {$where}
            ORDER BY t.created_at DESC"
        );

        // Build status counts for the filter tabs.
        $count_rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS cnt FROM {$tests_table} GROUP BY status"
        );

        $counts = array(
            'all'       => 0,
            'running'   => 0,
            'paused'    => 0,
            'draft'     => 0,
            'completed' => 0,
        );

        foreach ( $count_rows as $row ) {
            $key = sanitize_key( $row->status );
            if ( isset( $counts[ $key ] ) ) {
                $counts[ $key ] = absint( $row->cnt );
            }
            $counts['all'] += absint( $row->cnt );
        }

        // Load the tests list template.
        include ELEMENTTEST_PLUGIN_DIR . 'includes/views/tests-list.php';
    }

    /**
     * Render the Add New / Edit Test page.
     *
     * When a `test_id` GET parameter is present the existing test data
     * (including variants and conversion goals) is loaded from the
     * database and passed to both the PHP template and JavaScript via
     * wp_localize_script.
     *
     * @since 1.0.0
     */
    public function render_new_test_page() {
        $test_data = null;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
        $test_id = isset( $_GET['test_id'] ) ? absint( $_GET['test_id'] ) : 0;

        if ( $test_id ) {
            global $wpdb;

            $tests_table       = $wpdb->prefix . 'elementtest_tests';
            $variants_table    = $wpdb->prefix . 'elementtest_variants';
            $conversions_table = $wpdb->prefix . 'elementtest_conversions';

            $test = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$tests_table} WHERE test_id = %d", $test_id ),
                ARRAY_A
            );

            if ( $test ) {
                $variants = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$variants_table} WHERE test_id = %d ORDER BY is_control DESC, variant_id ASC",
                        $test_id
                    ),
                    ARRAY_A
                );

                $goals = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$conversions_table} WHERE test_id = %d ORDER BY conversion_id ASC",
                        $test_id
                    ),
                    ARRAY_A
                );

                $test_data = array(
                    'test'     => $test,
                    'variants' => $variants,
                    'goals'    => $goals,
                );

                // Pass edit data to JavaScript.
                wp_localize_script( 'elementtest-admin', 'elementtestEdit', $test_data );
            }
        }

        include ELEMENTTEST_PLUGIN_DIR . 'includes/views/new-test.php';
    }

    /**
     * Render the test results dashboard.
     *
     * @since 1.0.0
     */
    private function render_results_page() {
        global $wpdb;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $test_id = isset( $_GET['test_id'] ) ? absint( $_GET['test_id'] ) : 0;

        $tests_table       = $wpdb->prefix . 'elementtest_tests';
        $variants_table    = $wpdb->prefix . 'elementtest_variants';
        $events_table      = $wpdb->prefix . 'elementtest_events';
        $conversions_table = $wpdb->prefix . 'elementtest_conversions';

        // Get the test.
        $test = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$tests_table} WHERE test_id = %d", $test_id )
        );

        // Get variants with aggregated stats.
        $variants = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.*,
                    COALESCE( SUM( CASE WHEN e.event_type = 'impression' THEN 1 ELSE 0 END ), 0 ) AS impressions,
                    COALESCE( SUM( CASE WHEN e.event_type = 'conversion' THEN 1 ELSE 0 END ), 0 ) AS conversions
                FROM {$variants_table} v
                LEFT JOIN {$events_table} e ON v.variant_id = e.variant_id AND e.test_id = v.test_id
                WHERE v.test_id = %d
                GROUP BY v.variant_id
                ORDER BY v.is_control DESC, v.variant_id ASC",
                $test_id
            )
        );

        // Attach per-goal conversion counts to each variant.
        foreach ( $variants as $v ) {
            $goal_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT conversion_id, COUNT(*) AS cnt
                     FROM {$events_table}
                     WHERE variant_id = %d AND test_id = %d AND event_type = 'conversion' AND conversion_id IS NOT NULL
                     GROUP BY conversion_id",
                    absint( $v->variant_id ),
                    $test_id
                )
            );
            $v->goal_conversions = array();
            foreach ( $goal_rows as $gr ) {
                $v->goal_conversions[ absint( $gr->conversion_id ) ] = absint( $gr->cnt );
            }
        }

        // Get conversion goals.
        $goals = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$conversions_table} WHERE test_id = %d ORDER BY conversion_id ASC",
                $test_id
            )
        );

        // Get daily data for the chart.
        $daily_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(e.created_at) AS event_date, v.name AS variant_name, e.event_type, COUNT(*) AS cnt
                 FROM {$events_table} e
                 JOIN {$variants_table} v ON e.variant_id = v.variant_id
                 WHERE e.test_id = %d
                 GROUP BY event_date, v.name, e.event_type
                 ORDER BY event_date ASC",
                $test_id
            )
        );

        $daily_data = array();
        foreach ( $daily_rows as $row ) {
            $date   = $row->event_date;
            $v_name = $row->variant_name;

            if ( ! isset( $daily_data[ $date ] ) ) {
                $daily_data[ $date ] = array( 'date' => $date, 'variants' => array() );
            }
            if ( ! isset( $daily_data[ $date ]['variants'][ $v_name ] ) ) {
                $daily_data[ $date ]['variants'][ $v_name ] = array( 'impressions' => 0, 'conversions' => 0 );
            }

            if ( $row->event_type === 'impression' ) {
                $daily_data[ $date ]['variants'][ $v_name ]['impressions'] = absint( $row->cnt );
            } elseif ( $row->event_type === 'conversion' ) {
                $daily_data[ $date ]['variants'][ $v_name ]['conversions'] = absint( $row->cnt );
            }
        }

        $daily_data = array_values( $daily_data );

        include ELEMENTTEST_PLUGIN_DIR . 'includes/views/test-results.php';
    }

    /**
     * Show an admin banner prompting the user to configure proxy settings.
     *
     * Displays after activation until the user visits the settings page and
     * saves (or explicitly dismisses). This is not auto-detection — it always
     * shows until acknowledged so the user makes a conscious choice.
     *
     * @since 2.2.4
     */
    public function maybe_show_proxy_notice() {
        if ( get_option( 'elementtest_proxy_notice_dismissed' ) ) {
            return;
        }

        $settings_url = admin_url( 'admin.php?page=elementtest-settings' );
        ?>
        <div class="notice notice-warning" id="elementtest-proxy-notice" style="border-left-color: #d63638; padding: 12px 16px;">
            <p style="font-size: 14px; margin: 0 0 8px;">
                <strong><?php esc_html_e( 'ElementTest Pro — Hosting Setup Required', 'elementtest-pro' ); ?></strong>
            </p>
            <p style="margin: 0 0 10px;">
                <?php esc_html_e( 'ElementTest needs to know your hosting setup to accurately track visitors. Most managed hosts (GoDaddy, SiteGround, Kinsta, WP Engine) should select "Nginx / Managed Hosting." If you use Cloudflare, select that instead. If unsure, "Nginx / Managed Hosting" is the safest choice — it falls back gracefully.', 'elementtest-pro' ); ?>
            </p>
            <p style="margin: 0;">
                <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary"><?php esc_html_e( 'Configure Hosting Settings', 'elementtest-pro' ); ?></a>
                &nbsp;
                <a href="#" id="elementtest-dismiss-proxy-btn" style="color: #787c82; text-decoration: none; margin-left: 8px;"><?php esc_html_e( 'Dismiss (I\'ll use the default)', 'elementtest-pro' ); ?></a>
            </p>
        </div>
        <script>
        jQuery( document ).on( 'click', '#elementtest-dismiss-proxy-btn', function( e ) {
            e.preventDefault();
            jQuery( '#elementtest-proxy-notice' ).fadeOut();
            jQuery.post( ajaxurl, { action: 'elementtest_dismiss_proxy_notice', _wpnonce: '<?php echo esc_js( wp_create_nonce( 'elementtest_dismiss_proxy' ) ); ?>' } );
        } );
        </script>
        <?php
    }

    /**
     * AJAX handler to permanently dismiss the proxy detection notice.
     *
     * @since 2.2.2
     */
    public function dismiss_proxy_notice() {
        check_ajax_referer( 'elementtest_dismiss_proxy', '_wpnonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        update_option( 'elementtest_proxy_notice_dismissed', 1, false );
        wp_send_json_success();
    }

    /**
     * Register plugin settings with the WordPress Settings API.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting(
            'elementtest_settings_group',
            'elementtest_settings',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => array(
                    'cookie_days'          => 30,
                    'exclude_admins'       => 1,
                    'auto_pause_days'      => 0,
                    'data_retention_days'  => 0,
                    'ga4_enabled'          => 0,
                    'ga4_measurement_id'   => '',
                    'proxy_type'           => 'none',
                    'proxy_custom_header'  => '',
                ),
            )
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @since 1.0.0
     * @param array $input Raw settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        $sanitized['cookie_days']         = isset( $input['cookie_days'] ) ? absint( $input['cookie_days'] ) : 30;
        $sanitized['cookie_days']         = max( 1, min( 365, $sanitized['cookie_days'] ) );
        $sanitized['exclude_admins']      = ! empty( $input['exclude_admins'] ) ? 1 : 0;
        $sanitized['auto_pause_days']     = isset( $input['auto_pause_days'] ) ? absint( $input['auto_pause_days'] ) : 0;
        $sanitized['auto_pause_days']     = min( 365, $sanitized['auto_pause_days'] );
        $sanitized['data_retention_days'] = isset( $input['data_retention_days'] ) ? absint( $input['data_retention_days'] ) : 0;
        $sanitized['data_retention_days'] = min( 3650, $sanitized['data_retention_days'] );
        $sanitized['ga4_enabled']         = ! empty( $input['ga4_enabled'] ) ? 1 : 0;
        $sanitized['ga4_measurement_id']  = isset( $input['ga4_measurement_id'] )
            ? sanitize_text_field( wp_unslash( $input['ga4_measurement_id'] ) )
            : '';

        $valid_proxy_types = array( 'none', 'cloudflare', 'nginx', 'custom' );
        $sanitized['proxy_type'] = isset( $input['proxy_type'] ) && in_array( $input['proxy_type'], $valid_proxy_types, true )
            ? $input['proxy_type']
            : 'none';

        $sanitized['proxy_custom_header'] = isset( $input['proxy_custom_header'] )
            ? preg_replace( '/[^A-Za-z0-9_]/', '', str_replace( '-', '_', sanitize_text_field( wp_unslash( $input['proxy_custom_header'] ) ) ) )
            : '';

        update_option( 'elementtest_proxy_notice_dismissed', 1, false );

        return $sanitized;
    }

    /**
     * Render settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        $settings = get_option( 'elementtest_settings', array() );
        include ELEMENTTEST_PLUGIN_DIR . 'includes/views/settings.php';
    }
}

/**
 * Initialize the plugin
 */
function elementtest_pro_init() {
    return ElementTest_Pro::get_instance();
}

// Start the plugin
elementtest_pro_init();
