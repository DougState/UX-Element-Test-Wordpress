<?php
/**
 * WP-CLI commands for ElementTest Pro.
 *
 * Subcommands:
 *   - export             (since 2.3.0) — single-test report export
 *   - export-all         (since 2.3.0) — bulk report export
 *   - fix-variant-changes (since 2.4.3) — repair pre-2.4.2 wp_kses_post-mangled js/css variant `changes`
 *
 * Registered only when WP_CLI is defined.
 *
 * @package ElementTestPro
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ElementTest_CLI_Commands {

    /**
     * Export a single test report.
     *
     * ## OPTIONS
     *
     * <test_id>
     * : The numeric test ID to export.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: html
     * options:
     *   - html
     *   - csv
     *   - json
     * ---
     *
     * [--output=<path>]
     * : File path to write to. Defaults to ./elementtest-report-{test_id}-{date}.{ext}
     *
     * ## EXAMPLES
     *
     *     wp elementtest export 42 --format=html --output=/tmp/report.html
     *     wp elementtest export 42 --format=csv
     *     wp elementtest export 42 --format=json
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function export( $args, $assoc_args ) {
        $test_id = absint( $args[0] );
        $format  = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'html';

        if ( ! in_array( $format, array( 'html', 'csv', 'json' ), true ) ) {
            WP_CLI::error( 'Invalid format. Use --format=html, --format=csv, or --format=json.' );
        }

        $generator = new ElementTest_Report_Generator();
        $data      = $generator->get_report_data( $test_id );

        if ( is_wp_error( $data ) ) {
            WP_CLI::error( $data->get_error_message() );
        }

        if ( 'json' === $format ) {
            $content = $generator->generate_json( $data );
        } elseif ( 'csv' === $format ) {
            $content = $generator->generate_csv( $data );
        } else {
            $content = $generator->generate_html( $data );
        }

        $ext_map = array( 'html' => 'html', 'csv' => 'csv', 'json' => 'json' );
        $ext     = $ext_map[ $format ];
        $output  = isset( $assoc_args['output'] )
            ? $assoc_args['output']
            : sprintf( 'elementtest-report-%d-%s.%s', $test_id, gmdate( 'Y-m-d' ), $ext );

        $this->write_file( $output, $content );

        WP_CLI::success( sprintf( 'Report written to %s', realpath( $output ) ?: $output ) );
    }

    /**
     * Export reports for all non-draft tests.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: html
     * options:
     *   - html
     *   - csv
     *   - json
     * ---
     *
     * [--output=<dir>]
     * : Directory to write reports into. Created if it does not exist.
     *   Defaults to ./elementtest-reports-{date}/
     *
     * ## EXAMPLES
     *
     *     wp elementtest export-all --format=html --output=/tmp/reports/
     *     wp elementtest export-all --format=csv
     *     wp elementtest export-all --format=json
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function export_all( $args, $assoc_args ) {
        $format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'html';

        if ( ! in_array( $format, array( 'html', 'csv', 'json' ), true ) ) {
            WP_CLI::error( 'Invalid format. Use --format=html, --format=csv, or --format=json.' );
        }

        $dir = isset( $assoc_args['output'] )
            ? rtrim( $assoc_args['output'], '/' )
            : sprintf( 'elementtest-reports-%s', gmdate( 'Y-m-d' ) );

        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                WP_CLI::error( sprintf( 'Could not create directory: %s', $dir ) );
            }
        }

        $generator = new ElementTest_Report_Generator();
        $reports   = $generator->get_all_report_data();

        if ( empty( $reports ) ) {
            WP_CLI::warning( 'No non-draft tests found.' );
            return;
        }

        $ext_map = array( 'html' => 'html', 'csv' => 'csv', 'json' => 'json' );
        $ext     = $ext_map[ $format ];
        $count   = 0;

        foreach ( $reports as $data ) {
            if ( 'json' === $format ) {
                $content = $generator->generate_json( $data );
            } elseif ( 'csv' === $format ) {
                $content = $generator->generate_csv( $data );
            } else {
                $content = $generator->generate_html( $data );
            }
            $filename = sprintf( '%s/test-%d.%s', $dir, $data['test']['test_id'], $ext );

            $this->write_file( $filename, $content );
            $count++;

            WP_CLI::log( sprintf( '  %s', $filename ) );
        }

        WP_CLI::success( sprintf( '%d report(s) written to %s/', $count, realpath( $dir ) ?: $dir ) );
    }

    /**
     * Repair pre-2.4.2 wp_kses_post-mangled JS/CSS variant `changes` rows.
     *
     * Before v2.4.2, every variant's `changes` column was passed through
     * `wp_kses_post()` regardless of the parent test's `test_type`. For
     * `js` and `css` test types this corrupted source by re-encoding JS
     * operators (`>=` → `&gt;=`, `&&` → `&amp;&amp;`) and CSS combinators
     * (`.a > .b` → `.a &gt; .b`), producing source that either throws
     * `SyntaxError` at parse time (JS) or silently fails to match (CSS).
     * The 2.4.2 helper `sanitize_variant_changes()` only changes behavior
     * for new saves; rows already in the database remain mangled until
     * repaired.
     *
     * This command finds those rows and (in `--apply` mode) decodes the
     * five HTML entities `wp_kses_post()` produces from JS/CSS tokens:
     *
     *     &amp;   ->  &
     *     &lt;    ->  <
     *     &gt;    ->  >
     *     &quot;  ->  "
     *     &#039;  ->  '
     *
     * Other named entities (`&middot;`, `&nbsp;`, `&copy;`, etc.) are
     * intentionally left intact, because admins commonly embed those in
     * JS strings that get inserted via `.innerHTML` / `$.html()`.
     *
     * Default mode is dry-run. Always pass `--backup=<file>` with
     * `--apply` so a JSON snapshot of every modified row is written to
     * disk before any UPDATE.
     *
     * ## OPTIONS
     *
     * [--apply]
     * : Actually write the decoded content back. Default is dry-run.
     *
     * [--backup=<file>]
     * : Before `--apply`, write a JSON backup of every affected variant
     *   (variant_id, test_id, original `changes`) to this path. Strongly
     *   recommended whenever `--apply` is used.
     *
     * [--type=<type>]
     * : Restrict to a single test_type. Defaults to scanning both.
     * ---
     * default: all
     * options:
     *   - all
     *   - css
     *   - js
     * ---
     *
     * [--test-id=<id>]
     * : Restrict to variants belonging to a single test.
     *
     * [--show-diff]
     * : For each affected row, print up to 10 changed line pairs.
     *
     * ## EXAMPLES
     *
     *     # Survey what looks mangled (dry-run, default).
     *     wp elementtest fix-variant-changes
     *
     *     # Inspect with diffs.
     *     wp elementtest fix-variant-changes --type=js --show-diff
     *
     *     # Backup + repair.
     *     wp elementtest fix-variant-changes --apply \
     *         --backup=variants-backup-$(date +%Y%m%d).json
     *
     *     # Single-test surgical repair.
     *     wp elementtest fix-variant-changes --test-id=6 --apply --backup=t6.json
     *
     * @subcommand fix-variant-changes
     *
     * @param array $args       Positional arguments (unused).
     * @param array $assoc_args Named arguments.
     */
    public function fix_variant_changes( $args, $assoc_args ) {
        global $wpdb;

        $apply     = isset( $assoc_args['apply'] );
        $backup    = isset( $assoc_args['backup'] ) ? (string) $assoc_args['backup'] : '';
        $type      = isset( $assoc_args['type'] ) ? (string) $assoc_args['type'] : 'all';
        $test_id   = isset( $assoc_args['test-id'] ) ? absint( $assoc_args['test-id'] ) : 0;
        $show_diff = isset( $assoc_args['show-diff'] );

        if ( ! in_array( $type, array( 'all', 'css', 'js' ), true ) ) {
            WP_CLI::error( 'Invalid --type. Use all, css, or js.' );
        }

        $tests_table    = $wpdb->prefix . 'elementtest_tests';
        $variants_table = $wpdb->prefix . 'elementtest_variants';

        // Build the WHERE clause. test_type lives on the parent test row.
        $where  = array( "v.changes IS NOT NULL", "v.changes != ''" );
        $params = array();

        if ( 'all' === $type ) {
            $where[] = "t.test_type IN ('css','js')";
        } else {
            $where[]  = 't.test_type = %s';
            $params[] = $type;
        }

        if ( $test_id > 0 ) {
            $where[]  = 'v.test_id = %d';
            $params[] = $test_id;
        }

        $where_sql = implode( ' AND ', $where );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedPlaceholder
        $sql = "SELECT v.variant_id, v.test_id, v.name AS variant_name, v.changes,
                       t.name AS test_name, t.test_type
                FROM {$variants_table} v
                INNER JOIN {$tests_table} t ON v.test_id = t.test_id
                WHERE {$where_sql}
                ORDER BY v.test_id ASC, v.variant_id ASC";

        $rows = $params
            ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) )
            : $wpdb->get_results( $sql );
        // phpcs:enable

        if ( empty( $rows ) ) {
            WP_CLI::success( 'No variants matched the selection (no rows scanned).' );
            return;
        }

        $affected = array();
        foreach ( $rows as $row ) {
            if ( self::is_mangled_variant_changes( $row->changes ) ) {
                $affected[] = $row;
            }
        }

        WP_CLI::log( sprintf(
            'Scanned %d variant row(s) (test_type in %s%s); %d appear mangled.',
            count( $rows ),
            'all' === $type ? "css/js" : $type,
            $test_id ? sprintf( ', test_id=%d', $test_id ) : '',
            count( $affected )
        ) );

        if ( empty( $affected ) ) {
            WP_CLI::success( 'Nothing to repair.' );
            return;
        }

        if ( $show_diff ) {
            foreach ( $affected as $row ) {
                $decoded = self::decode_mangled_variant_changes( $row->changes );
                WP_CLI::log( sprintf(
                    "\n--- Test #%d \"%s\" / Variant #%d \"%s\" (%s) ---",
                    $row->test_id,
                    $row->test_name,
                    $row->variant_id,
                    $row->variant_name,
                    $row->test_type
                ) );
                self::print_compact_diff( $row->changes, $decoded );
            }
        } else {
            foreach ( $affected as $row ) {
                WP_CLI::log( sprintf(
                    '  test #%d / variant #%d  (%s)  "%s" / "%s"',
                    $row->test_id,
                    $row->variant_id,
                    $row->test_type,
                    $row->test_name,
                    $row->variant_name
                ) );
            }
        }

        if ( ! $apply ) {
            WP_CLI::log( '' );
            WP_CLI::log( 'Dry-run: no changes written.' );
            WP_CLI::log( 'Re-run with --apply to repair. Strongly recommended:' );
            WP_CLI::log( '  wp elementtest fix-variant-changes --apply --backup=variants-backup.json' );
            return;
        }

        if ( $backup ) {
            $payload = array();
            foreach ( $affected as $row ) {
                $payload[] = array(
                    'variant_id'   => (int) $row->variant_id,
                    'test_id'      => (int) $row->test_id,
                    'test_name'    => $row->test_name,
                    'test_type'    => $row->test_type,
                    'variant_name' => $row->variant_name,
                    'changes'      => $row->changes,
                );
            }

            $json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            $bytes = file_put_contents( $backup, $json );
            if ( false === $bytes ) {
                WP_CLI::error( sprintf( 'Failed to write backup to %s. Aborting before any DB writes.', $backup ) );
            }

            WP_CLI::log( sprintf(
                'Backup written: %s (%d row(s), %d bytes)',
                realpath( $backup ) ?: $backup,
                count( $affected ),
                $bytes
            ) );
        } else {
            WP_CLI::warning( 'No --backup specified. Reverting will require restoring from your DB backup.' );
        }

        $migrated = 0;
        $failed   = 0;
        foreach ( $affected as $row ) {
            $decoded = self::decode_mangled_variant_changes( $row->changes );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $result = $wpdb->update(
                $variants_table,
                array( 'changes' => $decoded ),
                array( 'variant_id' => (int) $row->variant_id ),
                array( '%s' ),
                array( '%d' )
            );

            if ( false === $result ) {
                $failed++;
                WP_CLI::warning( sprintf(
                    'Failed to update variant #%d (test #%d): %s',
                    $row->variant_id,
                    $row->test_id,
                    $wpdb->last_error
                ) );
                continue;
            }

            $migrated++;
            WP_CLI::log( sprintf(
                '  Migrated test #%d / variant #%d (%s)',
                $row->test_id,
                $row->variant_id,
                $row->test_type
            ) );
        }

        if ( $failed > 0 ) {
            WP_CLI::warning( sprintf( '%d row(s) failed to update.', $failed ) );
        }

        WP_CLI::success( sprintf(
            'Migrated %d of %d affected variant(s). Reload the test page in your browser and verify in DevTools console.',
            $migrated,
            count( $affected )
        ) );
    }

    /**
     * Returns true if a `changes` string contains the five-entity mangling
     * fingerprint left by `wp_kses_post()` on JS or CSS source.
     *
     * @param string $changes Raw `changes` value from the variants table.
     * @return bool
     */
    private static function is_mangled_variant_changes( $changes ) {
        return 1 === preg_match( '/&(?:amp|lt|gt|quot|#0?39);/', (string) $changes );
    }

    /**
     * Decode the five HTML entities `wp_kses_post()` produces from JS/CSS
     * tokens. Other named entities (`&middot;`, `&nbsp;`, etc.) are left
     * intact because they are commonly intentional inside HTML string
     * literals built up in JS variants.
     *
     * `strtr()` (not `html_entity_decode()`) is used deliberately: it does
     * not rescan the result, so a doubly-encoded `&amp;gt;` decodes once
     * to `&gt;` rather than collapsing all the way to `>` (which would
     * break legitimate user-authored entities).
     *
     * @param string $changes Mangled `changes` value.
     * @return string Repaired value.
     */
    private static function decode_mangled_variant_changes( $changes ) {
        return strtr(
            (string) $changes,
            array(
                '&amp;'  => '&',
                '&lt;'   => '<',
                '&gt;'   => '>',
                '&quot;' => '"',
                '&#039;' => "'",
                '&#39;'  => "'",
            )
        );
    }

    /**
     * Print a compact line-by-line diff of changed lines only, capped at
     * 10 changed line pairs to keep output digestible for big variants.
     *
     * @param string $before Original content.
     * @param string $after  Decoded content.
     */
    private static function print_compact_diff( $before, $after ) {
        $b_lines = preg_split( "/\r\n|\n|\r/", (string) $before );
        $a_lines = preg_split( "/\r\n|\n|\r/", (string) $after );
        $count   = max( count( $b_lines ), count( $a_lines ) );
        $shown   = 0;
        for ( $i = 0; $i < $count; $i++ ) {
            $b = isset( $b_lines[ $i ] ) ? $b_lines[ $i ] : '';
            $a = isset( $a_lines[ $i ] ) ? $a_lines[ $i ] : '';
            if ( $b === $a ) {
                continue;
            }
            WP_CLI::log( sprintf( '  -%4d  %s', $i + 1, $b ) );
            WP_CLI::log( sprintf( '  +%4d  %s', $i + 1, $a ) );
            $shown++;
            if ( $shown >= 10 ) {
                WP_CLI::log( '  ... (more changed lines suppressed; use --test-id=N to inspect a single test)' );
                break;
            }
        }
    }

    /**
     * Write content to a file, erroring on failure.
     *
     * @param string $path    File path.
     * @param string $content File content.
     */
    private function write_file( $path, $content ) {
        $dir = dirname( $path );
        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                WP_CLI::error( sprintf( 'Cannot create directory: %s', $dir ) );
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $bytes = file_put_contents( $path, $content );
        if ( false === $bytes ) {
            WP_CLI::error( sprintf( 'Failed to write file: %s', $path ) );
        }
    }
}
