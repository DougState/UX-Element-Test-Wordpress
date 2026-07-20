<?php
/**
 * GitHub Releases updater for off–WordPress.org installs.
 *
 * Uses the plugin header `Update URI` (hostname github.com) and the
 * `update_plugins_github.com` filter (WP 5.8+) so dashboard update checks
 * can discover new versions published as GitHub Release ZIP assets.
 *
 * Expected release asset name: elementtest-pro-{version}.zip with root
 * folder elementtest-pro/.
 *
 * Private-repo support: define ELEMENTTEST_GITHUB_TOKEN in wp-config.php with a
 * fine-grained personal access token (Contents: read-only on this repo). The token
 * authenticates the release check, and ZIP downloads go through the GitHub API
 * asset endpoint (resolved to a short-lived signed URL, so the token is never
 * sent to the download CDN). Without a token, only public repos work.
 *
 * @package ElementTestPro
 * @since   2.5.11
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ElementTest_GitHub_Updater
 */
class ElementTest_GitHub_Updater {

    /**
     * GitHub owner/repo.
     *
     * @var string
     */
    const REPO = 'DougState/UX-Element-Test-Wordpress';

    /**
     * Transient cache key for the latest release payload.
     *
     * @var string
     */
    const CACHE_KEY = 'elementtest_github_release';

    /**
     * Cache TTL in seconds (12 hours).
     *
     * @var int
     */
    const CACHE_TTL = 43200;

    /**
     * Plugin slug (directory name).
     *
     * @var string
     */
    const SLUG = 'elementtest-pro';

    /**
     * wp-config.php constant holding a GitHub fine-grained PAT for private-repo access.
     *
     * @var string
     */
    const TOKEN_CONSTANT = 'ELEMENTTEST_GITHUB_TOKEN';

    /**
     * Option recording the outcome of the last real release check (not autoloaded).
     *
     * @var string
     */
    const STATUS_OPTION = 'elementtest_update_status';

    /**
     * Register hooks.
     *
     * @since 2.5.11
     */
    public static function init() {
        // Hostname comes from Update URI: https://github.com/...
        add_filter( 'update_plugins_github.com', array( __CLASS__, 'filter_update' ), 10, 4 );
        add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );

        // Authenticated download of private release assets.
        add_filter( 'upgrader_pre_download', array( __CLASS__, 'maybe_download_private_asset' ), 10, 3 );

        // Clear cached release data after a successful plugin upgrade.
        add_action( 'upgrader_process_complete', array( __CLASS__, 'after_upgrade' ), 10, 2 );

        // Update-source status: Plugins-row indicator and failure notice (admin only).
        add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
        add_action( 'admin_notices', array( __CLASS__, 'maybe_admin_notice' ) );
    }

    /**
     * GitHub access token, if configured.
     *
     * @since  2.5.11
     * @return string Empty when not configured.
     */
    private static function token() {
        $token = defined( self::TOKEN_CONSTANT ) ? constant( self::TOKEN_CONSTANT ) : '';

        /**
         * Filter the GitHub access token used for release checks and downloads.
         *
         * @since 2.5.11
         * @param string $token Token from the ELEMENTTEST_GITHUB_TOKEN constant, or ''.
         */
        $token = apply_filters( 'elementtest_github_token', $token );

        return is_string( $token ) ? trim( $token ) : '';
    }

    /**
     * Common headers for GitHub API requests, with auth when a token is configured.
     *
     * @since  2.5.11
     * @param  string $accept Accept header value.
     * @return array
     */
    private static function api_headers( $accept = 'application/vnd.github+json' ) {
        $headers = array(
            'Accept'     => $accept,
            'User-Agent' => 'ElementTest-Pro-WordPress/' . ELEMENTTEST_VERSION,
        );

        $token = self::token();
        if ( '' !== $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Provide update data for this plugin only.
     *
     * @since  2.5.11
     * @param  array|false $update      Default false.
     * @param  array       $plugin_data Plugin headers.
     * @param  string      $plugin_file Plugin basename (e.g. elementtest-pro/elementtest-pro.php).
     * @param  string[]    $locales     Installed locales.
     * @return array|false
     */
    public static function filter_update( $update, $plugin_data, $plugin_file, $locales ) {
        unset( $locales );

        if ( ELEMENTTEST_PLUGIN_BASENAME !== $plugin_file ) {
            return $update;
        }

        $remote = self::get_latest_release();
        if ( empty( $remote['version'] ) || empty( $remote['package'] ) ) {
            return $update;
        }

        $current = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : ELEMENTTEST_VERSION;
        if ( ! version_compare( $remote['version'], $current, '>' ) ) {
            return $update;
        }

        return array(
            'id'            => 'https://github.com/' . self::REPO,
            'slug'          => self::SLUG,
            'version'       => $remote['version'],
            'url'           => 'https://github.com/' . self::REPO,
            'package'       => $remote['package'],
            'tested'        => isset( $plugin_data['Tested up to'] ) ? $plugin_data['Tested up to'] : '',
            'requires_php'  => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : ( isset( $plugin_data['Requires PHP'] ) ? $plugin_data['Requires PHP'] : '7.4' ),
            'requires'      => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : ( isset( $plugin_data['Requires at least'] ) ? $plugin_data['Requires at least'] : '5.6' ),
            'icons'         => array(),
            'banners'       => array(),
            'banners_rtl'   => array(),
            'translations'  => array(),
        );
    }

    /**
     * "View version details" modal content for the Plugins screen.
     *
     * @since  2.5.11
     * @param  false|object|array $result Existing result.
     * @param  string             $action API action.
     * @param  object             $args   Request args.
     * @return false|object|array
     */
    public static function plugins_api( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( empty( $args->slug ) || self::SLUG !== $args->slug ) {
            return $result;
        }

        $remote = self::get_latest_release();
        if ( empty( $remote['version'] ) ) {
            return $result;
        }

        $sections = array(
            'description' => '<p>' . esc_html__( 'A/B test CSS, copy, JS, and images on WordPress pages. Updates are delivered from GitHub Releases.', 'elementtest-pro' ) . '</p>',
            'changelog'   => ! empty( $remote['changelog'] )
                ? wp_kses_post( wpautop( $remote['changelog'] ) )
                : '<p>' . esc_html__( 'See the GitHub release notes for this version.', 'elementtest-pro' ) . '</p>',
        );

        return (object) array(
            'name'           => 'ElementTest Pro',
            'slug'           => self::SLUG,
            'version'        => $remote['version'],
            'author'         => '<a href="https://elimstat.com">Elimstat Dev Ops</a>',
            'homepage'       => 'https://github.com/' . self::REPO,
            'requires'       => '5.6',
            'requires_php'   => '7.4',
            'tested'         => '7.0',
            'download_link'  => isset( $remote['package'] ) ? $remote['package'] : '',
            'trunk'          => 'https://github.com/' . self::REPO,
            'last_updated'   => isset( $remote['published_at'] ) ? $remote['published_at'] : '',
            'sections'       => $sections,
            'banners'        => array(),
            'icons'          => array(),
            'external'       => true,
        );
    }

    /**
     * Drop the release cache after this plugin is updated.
     *
     * @since 2.5.11
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array       $options  Upgrade options.
     */
    public static function after_upgrade( $upgrader, $options ) {
        unset( $upgrader );

        if ( empty( $options['action'] ) || 'update' !== $options['action'] ) {
            return;
        }
        if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
            return;
        }

        $plugins = array();
        if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
            $plugins = $options['plugins'];
        } elseif ( ! empty( $options['plugin'] ) ) {
            $plugins = array( $options['plugin'] );
        }

        if ( in_array( ELEMENTTEST_PLUGIN_BASENAME, $plugins, true ) ) {
            delete_transient( self::CACHE_KEY );
        }
    }

    /**
     * Fetch and cache the latest non-prerelease GitHub release with a ZIP asset.
     *
     * @since  2.5.11
     * @return array {
     *     @type string $version      Semver without leading v.
     *     @type string $package      Download URL for the ZIP (API asset endpoint when a token is set).
     *     @type string $changelog    Release body markdown/text.
     *     @type string $published_at ISO8601 publish time.
     * }
     */
    public static function get_latest_release() {
        $cached = get_transient( self::CACHE_KEY );
        if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
            return $cached;
        }

        $url = 'https://api.github.com/repos/' . self::REPO . '/releases/latest';

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'headers' => self::api_headers(),
            )
        );

        if ( is_wp_error( $response ) ) {
            self::record_status( 'network_error', $response->get_error_message() );
            return array();
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            self::record_status( 'http_' . $code );
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) ) {
            self::record_status( 'bad_payload' );
            return array();
        }

        // Skip drafts / prereleases (API latest usually excludes them; belt-and-suspenders).
        if ( ! empty( $body['draft'] ) || ! empty( $body['prerelease'] ) ) {
            self::record_status( 'no_release' );
            return array();
        }

        $tag = isset( $body['tag_name'] ) ? (string) $body['tag_name'] : '';
        $version = ltrim( $tag, 'vV' );
        if ( '' === $version || ! preg_match( '/^\d+\.\d+\.\d+/', $version ) ) {
            self::record_status( 'bad_tag', $tag );
            return array();
        }

        $asset = self::find_zip_asset( $body, $version );
        if ( empty( $asset['browser'] ) && empty( $asset['api'] ) ) {
            self::record_status( 'no_asset', $version );
            return array();
        }

        // With a token, download via the API asset endpoint (browser_download_url
        // is not reachable on private repos); otherwise use the public URL.
        if ( '' !== self::token() && ! empty( $asset['api'] ) ) {
            $package = $asset['api'];
        } else {
            $package = $asset['browser'];
        }
        if ( '' === $package ) {
            return array();
        }

        $payload = array(
            'version'      => $version,
            'package'      => $package,
            'changelog'    => isset( $body['body'] ) ? (string) $body['body'] : '',
            'published_at' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
        );

        set_transient( self::CACHE_KEY, $payload, self::CACHE_TTL );
        self::record_status( 'ok', '', $version );

        return $payload;
    }

    /**
     * Record the outcome of the last real release check (cache hits are not recorded).
     *
     * @since 2.5.12
     * @param string $result  'ok', 'http_<code>', 'network_error', 'bad_payload', 'no_release', 'bad_tag', or 'no_asset'.
     * @param string $detail  Optional extra context (error message, tag).
     * @param string $version Latest version found, when $result is 'ok'.
     */
    private static function record_status( $result, $detail = '', $version = '' ) {
        update_option(
            self::STATUS_OPTION,
            array(
                'time'    => time(),
                'result'  => $result,
                'detail'  => $detail,
                'version' => $version,
            ),
            false
        );
    }

    /**
     * Append an update-source status line to this plugin's row on the Plugins screen.
     *
     * Shows configuration and last-check state only — never the token value.
     *
     * @since  2.5.12
     * @param  string[] $plugin_meta Row meta links/text.
     * @param  string   $plugin_file Plugin basename being rendered.
     * @return string[]
     */
    public static function plugin_row_meta( $plugin_meta, $plugin_file ) {
        if ( ELEMENTTEST_PLUGIN_BASENAME !== $plugin_file ) {
            return $plugin_meta;
        }

        $status    = get_option( self::STATUS_OPTION );
        $has_token = '' !== self::token();

        if ( ! is_array( $status ) || empty( $status['result'] ) ) {
            $text  = $has_token
                ? __( 'Updates: GitHub (token configured, no check yet)', 'elementtest-pro' )
                : __( 'Updates: GitHub (token not configured, no check yet)', 'elementtest-pro' );
            $color = '#996800';
        } elseif ( 'ok' === $status['result'] ) {
            $text  = sprintf(
                /* translators: 1: latest available version, 2: human-readable time since last check. */
                __( 'Updates: GitHub ✓ latest %1$s, checked %2$s ago', 'elementtest-pro' ),
                $status['version'],
                human_time_diff( (int) $status['time'] )
            );
            $color = '#008a20';
        } elseif ( ! $has_token ) {
            $text  = __( 'Updates: GitHub — disabled, token not configured (see wp-config ELEMENTTEST_GITHUB_TOKEN)', 'elementtest-pro' );
            $color = '#b32d2e';
        } else {
            $text  = sprintf(
                /* translators: 1: failure code, 2: human-readable time since last check. */
                __( 'Updates: GitHub — check failing (%1$s, %2$s ago), token may be expired', 'elementtest-pro' ),
                $status['result'],
                human_time_diff( (int) $status['time'] )
            );
            $color = '#b32d2e';
        }

        $plugin_meta[] = '<span style="color:' . esc_attr( $color ) . ';">' . esc_html( $text ) . '</span>';

        return $plugin_meta;
    }

    /**
     * Warn on the Plugins screen when updates are silently dormant or failing.
     *
     * @since 2.5.12
     */
    public static function maybe_admin_notice() {
        global $pagenow;

        if ( 'plugins.php' !== $pagenow || ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        $status = get_option( self::STATUS_OPTION );

        // Healthy or not yet checked: nothing to warn about.
        if ( ! is_array( $status ) || empty( $status['result'] ) || 'ok' === $status['result'] ) {
            return;
        }

        if ( '' === self::token() ) {
            $message = __( 'ElementTest Pro: automatic updates are disabled — the GitHub repository is private and no access token is configured. Define ELEMENTTEST_GITHUB_TOKEN in wp-config.php (fine-grained token, Contents: read-only).', 'elementtest-pro' );
        } else {
            $message = sprintf(
                /* translators: %s: failure code from the last release check. */
                __( 'ElementTest Pro: the GitHub update check is failing (%s). The access token in ELEMENTTEST_GITHUB_TOKEN may be expired or revoked — rotate it to restore automatic updates.', 'elementtest-pro' ),
                $status['result']
            );
        }

        echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
    }

    /**
     * Prefer a release asset named elementtest-pro-{version}.zip
     * (or elementtest-pro.zip). Matching is case-insensitive.
     *
     * @since  2.5.11
     * @param  array  $release GitHub release JSON as array.
     * @param  string $version Normalized version string.
     * @return array { @type string $browser Public download URL. @type string $api API asset endpoint. }
     */
    private static function find_zip_asset( $release, $version ) {
        $none = array(
            'browser' => '',
            'api'     => '',
        );

        if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
            return $none;
        }

        $preferred = array(
            self::SLUG . '-' . $version . '.zip',
            self::SLUG . '.zip',
        );

        $by_name = array();
        foreach ( $release['assets'] as $asset ) {
            if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
                continue;
            }
            $name = (string) $asset['name'];
            if ( ! preg_match( '/\.zip$/i', $name ) ) {
                continue;
            }
            $by_name[ strtolower( $name ) ] = array(
                'browser' => (string) $asset['browser_download_url'],
                'api'     => isset( $asset['url'] ) ? (string) $asset['url'] : '',
            );
        }

        foreach ( $preferred as $want ) {
            $key = strtolower( $want );
            if ( isset( $by_name[ $key ] ) ) {
                return $by_name[ $key ];
            }
        }

        // Any elementtest-pro*.zip asset.
        foreach ( $by_name as $name => $urls ) {
            if ( 0 === strpos( $name, strtolower( self::SLUG ) ) ) {
                return $urls;
            }
        }

        // Last resort: single zip asset on the release.
        if ( 1 === count( $by_name ) ) {
            return reset( $by_name );
        }

        return $none;
    }

    /**
     * Download a private release asset with authentication.
     *
     * Only handles packages pointing at this repo's API asset endpoint. Resolves
     * the endpoint to GitHub's short-lived signed CDN URL first (redirects are
     * not followed), so the Authorization header is never sent to the CDN host.
     *
     * @since  2.5.11
     * @param  bool|WP_Error $reply    Whether to short-circuit the download (default false).
     * @param  string        $package  Package URL.
     * @param  WP_Upgrader   $upgrader Upgrader instance.
     * @return bool|string|WP_Error Local file path, WP_Error, or $reply untouched.
     */
    public static function maybe_download_private_asset( $reply, $package, $upgrader ) {
        unset( $upgrader );

        if ( false !== $reply ) {
            return $reply;
        }

        if ( ! is_string( $package ) ) {
            return $reply;
        }

        $prefix = 'https://api.github.com/repos/' . self::REPO . '/releases/assets/';
        if ( 0 !== strpos( $package, $prefix ) ) {
            return $reply;
        }

        $token = self::token();
        if ( '' === $token ) {
            return new WP_Error(
                'elementtest_github_token_missing',
                __( 'A GitHub access token is required to download this update. Define ELEMENTTEST_GITHUB_TOKEN in wp-config.php.', 'elementtest-pro' )
            );
        }

        // Ask the API for the asset; redirection => 0 returns the 302 with the signed URL.
        $response = wp_remote_get(
            $package,
            array(
                'timeout'     => 30,
                'redirection' => 0,
                'headers'     => self::api_headers( 'application/octet-stream' ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code   = (int) wp_remote_retrieve_response_code( $response );
        $signed = wp_remote_retrieve_header( $response, 'location' );

        if ( $code < 300 || $code >= 400 || empty( $signed ) ) {
            return new WP_Error(
                'elementtest_github_download_failed',
                sprintf(
                    /* translators: %d: HTTP status code. */
                    __( 'Could not resolve the GitHub release download (HTTP %d). Check that ELEMENTTEST_GITHUB_TOKEN is valid and has read access to the repository.', 'elementtest-pro' ),
                    $code
                )
            );
        }

        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // The signed URL needs no auth and expires quickly; download it now.
        return download_url( $signed, 300 );
    }
}
