<?php
/**
 * WP-CLI commands for ElementTest Pro report export.
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
