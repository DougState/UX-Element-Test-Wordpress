<?php
/**
 * Report Generator for ElementTest Pro.
 *
 * Produces structured report data and renders it as standalone HTML
 * or CSV for export via WP-CLI or admin download.
 *
 * @package ElementTestPro
 * @since   2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ElementTest_Report_Generator {

    /**
     * Build a structured report array for a single test.
     *
     * @param int $test_id Test ID.
     * @return array|WP_Error Report data or error.
     */
    public function get_report_data( $test_id ) {
        global $wpdb;

        $test_id = absint( $test_id );

        $tests_table       = $wpdb->prefix . 'elementtest_tests';
        $variants_table    = $wpdb->prefix . 'elementtest_variants';
        $events_table      = $wpdb->prefix . 'elementtest_events';
        $conversions_table = $wpdb->prefix . 'elementtest_conversions';

        $test = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$tests_table} WHERE test_id = %d", $test_id )
        );

        if ( ! $test ) {
            return new WP_Error( 'not_found', __( 'Test not found.', 'elementtest-pro' ) );
        }

        // Variants with aggregated impressions and conversions.
        $variants_raw = $wpdb->get_results(
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

        // Per-goal conversion counts per variant.
        foreach ( $variants_raw as $v ) {
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

        // Conversion goals.
        $goals = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$conversions_table} WHERE test_id = %d ORDER BY conversion_id ASC",
                $test_id
            )
        );

        // Daily rows.
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

        // Totals.
        $total_impressions = 0;
        $total_conversions = 0;
        $control_rate      = 0;

        foreach ( $variants_raw as $v ) {
            $total_impressions += absint( $v->impressions );
            $total_conversions += absint( $v->conversions );
            if ( ! empty( $v->is_control ) && absint( $v->impressions ) > 0 ) {
                $control_rate = round( ( absint( $v->conversions ) / absint( $v->impressions ) ) * 100, 2 );
            }
        }

        $overall_rate = $total_impressions > 0
            ? round( ( $total_conversions / $total_impressions ) * 100, 2 )
            : 0;

        // Duration.
        $start = ! empty( $test->start_date ) ? strtotime( $test->start_date ) : strtotime( $test->created_at );
        $days  = max( 1, ceil( ( time() - $start ) / DAY_IN_SECONDS ) );

        // Compute per-variant statistics.
        $variants = array();
        foreach ( $variants_raw as $v ) {
            $variants[] = $this->compute_variant_stats( $v, $control_rate, $total_impressions, $total_conversions, $variants_raw );
        }

        // Structure daily data as flat rows.
        $daily = $this->flatten_daily_data( $daily_rows );

        // Structure goals.
        $goals_out = array();
        foreach ( $goals as $g ) {
            $goals_out[] = array(
                'conversion_id'  => absint( $g->conversion_id ),
                'name'           => $g->name,
                'trigger_type'   => $g->trigger_type,
                'trigger_detail' => ! empty( $g->trigger_selector ) ? $g->trigger_selector : ( ! empty( $g->trigger_event ) ? $g->trigger_event : '' ),
                'revenue_value'  => floatval( $g->revenue_value ),
            );
        }

        return array(
            'test'      => array(
                'test_id'     => absint( $test->test_id ),
                'name'        => $test->name,
                'description' => $test->description,
                'status'      => $test->status,
                'test_type'   => strtoupper( $test->test_type ),
                'page_url'    => $test->page_url,
                'start_date'  => $test->start_date,
                'end_date'    => $test->end_date,
                'created_at'  => $test->created_at,
            ),
            'variants'  => $variants,
            'goals'     => $goals_out,
            'daily'     => $daily,
            'summary'   => array(
                'total_impressions' => $total_impressions,
                'total_conversions' => $total_conversions,
                'overall_rate'      => $overall_rate,
                'control_rate'      => $control_rate,
                'duration_days'     => $days,
            ),
            'generated' => gmdate( 'c' ),
        );
    }

    /**
     * Build report data for all non-draft tests.
     *
     * @return array List of report data arrays.
     */
    public function get_all_report_data() {
        global $wpdb;

        $tests_table = $wpdb->prefix . 'elementtest_tests';
        $ids         = $wpdb->get_col( "SELECT test_id FROM {$tests_table} WHERE status != 'draft' ORDER BY test_id ASC" );
        $reports     = array();

        foreach ( $ids as $id ) {
            $data = $this->get_report_data( absint( $id ) );
            if ( ! is_wp_error( $data ) ) {
                $reports[] = $data;
            }
        }

        return $reports;
    }

    /**
     * Render a standalone HTML report.
     *
     * @param array $data Report data from get_report_data().
     * @return string Complete HTML document.
     */
    public function generate_html( $data ) {
        ob_start();
        $report = $data;
        include ELEMENTTEST_PLUGIN_DIR . 'includes/views/report-html.php';
        return ob_get_clean();
    }

    /**
     * Render a JSON string from report data.
     *
     * @param array $data Report data from get_report_data().
     * @return string JSON content.
     */
    public function generate_json( $data ) {
        return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    }

    /**
     * Render a CSV string from report data.
     *
     * Two sections separated by a blank row:
     *   1. Variant Summary
     *   2. Daily Breakdown
     *
     * @param array $data Report data from get_report_data().
     * @return string CSV content.
     */
    public function generate_csv( $data ) {
        $handle = fopen( 'php://temp', 'r+' );

        // Header row: test metadata.
        fputcsv( $handle, array( 'Test', $data['test']['name'] ) );
        fputcsv( $handle, array( 'Status', $data['test']['status'] ) );
        fputcsv( $handle, array( 'Type', $data['test']['test_type'] ) );
        fputcsv( $handle, array( 'Page URL', $data['test']['page_url'] ) );
        fputcsv( $handle, array( 'Generated', $data['generated'] ) );
        fputcsv( $handle, array() );

        // Summary KPIs.
        fputcsv( $handle, array( 'Total Impressions', $data['summary']['total_impressions'] ) );
        fputcsv( $handle, array( 'Total Conversions', $data['summary']['total_conversions'] ) );
        fputcsv( $handle, array( 'Overall Rate (%)', $data['summary']['overall_rate'] ) );
        fputcsv( $handle, array( 'Control Rate (%)', $data['summary']['control_rate'] ) );
        fputcsv( $handle, array( 'Duration (days)', $data['summary']['duration_days'] ) );
        fputcsv( $handle, array() );

        // Variant summary.
        fputcsv( $handle, array( 'Variant', 'Control', 'Impressions', 'Conversions', 'Rate (%)', 'Lift (%)', 'Confidence (%)', 'Verdict' ) );
        foreach ( $data['variants'] as $v ) {
            fputcsv( $handle, array(
                $v['name'],
                $v['is_control'] ? 'Yes' : 'No',
                $v['impressions'],
                $v['conversions'],
                $v['rate'],
                $v['lift'],
                $v['confidence'],
                $v['verdict'],
            ) );
        }
        fputcsv( $handle, array() );

        // Goals breakdown (if any).
        if ( ! empty( $data['goals'] ) ) {
            $goal_header = array( 'Goal', 'Type', 'Trigger' );
            foreach ( $data['variants'] as $v ) {
                $goal_header[] = $v['name'];
            }
            fputcsv( $handle, $goal_header );

            foreach ( $data['goals'] as $g ) {
                $row = array(
                    $g['name'],
                    ucfirst( str_replace( '_', ' ', $g['trigger_type'] ) ),
                    $g['trigger_detail'],
                );
                foreach ( $data['variants'] as $v ) {
                    $row[] = isset( $v['goal_conversions'][ $g['conversion_id'] ] )
                        ? $v['goal_conversions'][ $g['conversion_id'] ]
                        : 0;
                }
                fputcsv( $handle, $row );
            }
            fputcsv( $handle, array() );
        }

        // Daily breakdown.
        fputcsv( $handle, array( 'Date', 'Variant', 'Impressions', 'Conversions', 'Rate (%)' ) );
        foreach ( $data['daily'] as $row ) {
            fputcsv( $handle, array(
                $row['date'],
                $row['variant_name'],
                $row['impressions'],
                $row['conversions'],
                $row['rate'],
            ) );
        }

        rewind( $handle );
        $csv = stream_get_contents( $handle );
        fclose( $handle );

        return $csv;
    }

    /**
     * Compute stats for a single variant (lift, confidence, verdict).
     *
     * Extracted from test-results.php so both the admin view and exports
     * use identical math.
     *
     * @param object $v                Raw variant row with impressions/conversions.
     * @param float  $control_rate     Control conversion rate (percentage).
     * @param int    $total_impressions Total impressions across all variants.
     * @param int    $total_conversions Total conversions across all variants.
     * @param array  $all_variants     All variant rows (index 0 = control).
     * @return array Structured variant data.
     */
    private function compute_variant_stats( $v, $control_rate, $total_impressions, $total_conversions, $all_variants ) {
        $impressions = absint( $v->impressions );
        $conversions = absint( $v->conversions );
        $rate        = $impressions > 0 ? round( ( $conversions / $impressions ) * 100, 2 ) : 0;
        $is_control  = ! empty( $v->is_control );

        $lift       = 0;
        $confidence = 0;
        $verdict    = '';

        if ( ! $is_control && $control_rate > 0 ) {
            $lift = round( ( ( $rate - $control_rate ) / $control_rate ) * 100, 1 );
        }

        if ( ! $is_control && $control_rate > 0 && $impressions >= 30 ) {
            $pooled_rate = $total_impressions > 0 ? $total_conversions / $total_impressions : 0;
            $control_n   = absint( $all_variants[0]->impressions );
            $se          = $pooled_rate > 0
                ? sqrt( $pooled_rate * ( 1 - $pooled_rate ) * ( 1 / max( 1, $impressions ) + 1 / max( 1, $control_n ) ) )
                : 0;
            $z           = $se > 0
                ? abs( ( $rate / 100 ) - ( $control_rate / 100 ) ) / $se
                : 0;

            if ( $z >= 2.576 ) {
                $confidence = 99;
            } elseif ( $z >= 1.96 ) {
                $confidence = 95;
            } elseif ( $z >= 1.645 ) {
                $confidence = 90;
            } elseif ( $z >= 1.28 ) {
                $confidence = 80;
            } else {
                $confidence = round( min( 79, $z * 40 ) );
            }
        }

        if ( ! $is_control && $impressions > 0 ) {
            if ( $confidence >= 95 && $lift > 0 ) {
                $verdict = 'Winner';
            } elseif ( $confidence >= 95 && $lift < 0 ) {
                $verdict = 'Loser';
            } elseif ( $confidence >= 80 ) {
                $verdict = 'Trending';
            } else {
                $verdict = 'Inconclusive';
            }
        }

        return array(
            'variant_id'       => absint( $v->variant_id ),
            'name'             => $v->name,
            'is_control'       => $is_control,
            'impressions'      => $impressions,
            'conversions'      => $conversions,
            'rate'             => $rate,
            'lift'             => $lift,
            'confidence'       => $confidence,
            'verdict'          => $verdict,
            'goal_conversions' => $v->goal_conversions,
        );
    }

    /**
     * Flatten daily event rows into a simple array of per-variant-per-day records.
     *
     * @param array $daily_rows Raw DB rows with event_date, variant_name, event_type, cnt.
     * @return array Flat rows with date, variant_name, impressions, conversions, rate.
     */
    private function flatten_daily_data( $daily_rows ) {
        $grouped = array();

        foreach ( $daily_rows as $row ) {
            $key = $row->event_date . '|' . $row->variant_name;
            if ( ! isset( $grouped[ $key ] ) ) {
                $grouped[ $key ] = array(
                    'date'         => $row->event_date,
                    'variant_name' => $row->variant_name,
                    'impressions'  => 0,
                    'conversions'  => 0,
                );
            }
            if ( 'impression' === $row->event_type ) {
                $grouped[ $key ]['impressions'] = absint( $row->cnt );
            } elseif ( 'conversion' === $row->event_type ) {
                $grouped[ $key ]['conversions'] = absint( $row->cnt );
            }
        }

        $flat = array();
        foreach ( $grouped as $entry ) {
            $entry['rate'] = $entry['impressions'] > 0
                ? round( ( $entry['conversions'] / $entry['impressions'] ) * 100, 2 )
                : 0;
            $flat[] = $entry;
        }

        return $flat;
    }
}
