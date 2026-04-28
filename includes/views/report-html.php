<?php
/**
 * Template: Standalone HTML Report
 *
 * Renders a self-contained HTML document for a single A/B test report.
 * All styles are inlined. Charts rendered by Chart.js (CDN).
 *
 * Expected variable:
 *   $report  array  Structured report data from ElementTest_Report_Generator::get_report_data().
 *
 * @package ElementTestPro
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Standalone HTML report template — included from
// ElementTest_Report_Generator::generate_html() with $report set in the
// calling scope. Local view variables, not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$test     = $report['test'];
$variants = $report['variants'];
$goals    = $report['goals'];
$daily    = $report['daily'];
$summary  = $report['summary'];
$version  = defined( 'ELEMENTTEST_VERSION' ) ? ELEMENTTEST_VERSION : '2.3.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $test['name'] ); ?> — A/B Test Report</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        font-size: 14px;
        line-height: 1.5;
        color: #1d2327;
        background: #fff;
        max-width: 960px;
        margin: 0 auto;
        padding: 40px 24px;
    }
    h1 { font-size: 22px; font-weight: 600; margin-bottom: 4px; }
    h2 { font-size: 16px; font-weight: 600; margin: 32px 0 12px; padding-bottom: 8px; border-bottom: 2px solid #e0e0e0; }
    .report-meta { color: #646970; font-size: 13px; margin-bottom: 24px; }
    .report-meta span { margin-right: 16px; }

    /* KPI row */
    .kpi-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .kpi-card {
        flex: 1;
        min-width: 140px;
        background: #f6f7f7;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 16px;
        text-align: center;
    }
    .kpi-label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #646970; margin-bottom: 4px; }
    .kpi-value { display: block; font-size: 24px; font-weight: 700; color: #1d2327; }
    .kpi-sub { display: block; font-size: 12px; color: #787c82; margin-top: 2px; }

    /* Charts */
    .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px; }
    .chart-card {
        background: #f9fafb;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 16px;
    }
    .chart-card.full-width { grid-column: 1 / -1; }
    .chart-card h3 { font-size: 14px; font-weight: 600; margin-bottom: 4px; color: #1d2327; }
    .chart-card p { font-size: 12px; color: #787c82; margin-bottom: 12px; }
    .chart-card canvas { width: 100% !important; }

    /* Tables */
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 13px; }
    th { background: #f6f7f7; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px; color: #646970; }
    td { color: #1d2327; }
    tr:last-child td { border-bottom: none; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .control-tag { display: inline-block; background: #e7f5e8; color: #00a32a; font-size: 11px; padding: 1px 6px; border-radius: 3px; font-weight: 600; }

    /* Verdict badges */
    .verdict-winner { color: #00a32a; font-weight: 600; }
    .verdict-loser { color: #d63638; font-weight: 600; }
    .verdict-trending { color: #dba617; font-weight: 600; }
    .verdict-inconclusive { color: #787c82; }

    /* Lift indicator */
    .lift-up { color: #00a32a; }
    .lift-down { color: #d63638; }

    /* Footer */
    .report-footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #787c82; text-align: center; }

    /* Print styles */
    @media print {
        body { padding: 20px 0; font-size: 12px; max-width: 100%; }
        .kpi-card { break-inside: avoid; }
        .charts-grid { display: none; }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; }
        h2 { page-break-after: avoid; }
        .report-footer { position: fixed; bottom: 0; width: 100%; }
    }
</style>
</head>
<body>

<h1><?php echo esc_html( $test['name'] ); ?></h1>
<div class="report-meta">
    <span><strong>Status:</strong> <?php echo esc_html( ucfirst( $test['status'] ) ); ?></span>
    <span><strong>Type:</strong> <?php echo esc_html( $test['test_type'] ); ?></span>
    <?php if ( ! empty( $test['page_url'] ) ) : ?>
        <span><strong>Page:</strong> <?php echo esc_html( $test['page_url'] ); ?></span>
    <?php endif; ?>
    <br>
    <span><strong>Generated:</strong> <?php echo esc_html( $report['generated'] ); ?></span>
    <?php if ( ! empty( $test['start_date'] ) ) : ?>
        <span><strong>Started:</strong> <?php echo esc_html( $test['start_date'] ); ?></span>
    <?php endif; ?>
    <?php if ( ! empty( $test['end_date'] ) ) : ?>
        <span><strong>Ended:</strong> <?php echo esc_html( $test['end_date'] ); ?></span>
    <?php endif; ?>
</div>
<?php if ( ! empty( $test['description'] ) ) : ?>
    <p style="color: #646970; margin-bottom: 24px;"><?php echo esc_html( $test['description'] ); ?></p>
<?php endif; ?>

<!-- KPI Summary -->
<h2>Summary</h2>
<div class="kpi-row">
    <div class="kpi-card">
        <span class="kpi-label">Total Impressions</span>
        <span class="kpi-value"><?php echo esc_html( number_format( $summary['total_impressions'] ) ); ?></span>
        <span class="kpi-sub">over <?php echo esc_html( $summary['duration_days'] ); ?> days</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Total Conversions</span>
        <span class="kpi-value"><?php echo esc_html( number_format( $summary['total_conversions'] ) ); ?></span>
        <span class="kpi-sub"><?php echo esc_html( $summary['overall_rate'] ); ?>% overall rate</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Control Rate</span>
        <span class="kpi-value"><?php echo esc_html( $summary['control_rate'] ); ?>%</span>
        <span class="kpi-sub">baseline conversion</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Duration</span>
        <span class="kpi-value"><?php echo esc_html( $summary['duration_days'] ); ?></span>
        <span class="kpi-sub">days running</span>
    </div>
</div>

<!-- Charts (rendered by Chart.js) -->
<?php if ( ! empty( $daily ) ) : ?>
<h2>Visual Dashboard</h2>
<div class="charts-grid">
    <div class="chart-card">
        <h3>Daily Conversion Rate</h3>
        <p>Control vs variant — daily conversion percentage</p>
        <canvas id="chartConvRate" height="220"></canvas>
    </div>
    <div class="chart-card">
        <h3>Cumulative Conversions</h3>
        <p>Running total of conversions over time</p>
        <canvas id="chartCumulative" height="220"></canvas>
    </div>
    <div class="chart-card">
        <h3>Overall Conversion Rate</h3>
        <p>Aggregate rate per variant</p>
        <canvas id="chartOverall" height="220"></canvas>
    </div>
    <?php if ( ! empty( $goals ) && count( $goals ) > 1 ) : ?>
    <div class="chart-card">
        <h3>Goal Breakdown</h3>
        <p>Conversions by goal per variant</p>
        <canvas id="chartGoals" height="220"></canvas>
    </div>
    <?php endif; ?>
    <div class="chart-card full-width">
        <h3>Daily Impressions — Traffic Split</h3>
        <p>How traffic was distributed between variants each day</p>
        <canvas id="chartTraffic" height="180"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Variant Performance -->
<h2>Variant Performance</h2>
<table>
    <thead>
        <tr>
            <th>Variant</th>
            <th></th>
            <th class="num">Impressions</th>
            <th class="num">Conversions</th>
            <th class="num">Rate</th>
            <th class="num">Lift</th>
            <th class="num">Confidence</th>
            <th>Verdict</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $variants as $v ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $v['name'] ); ?></strong></td>
            <td><?php echo $v['is_control'] ? '<span class="control-tag">Control</span>' : ''; ?></td>
            <td class="num"><?php echo esc_html( number_format( $v['impressions'] ) ); ?></td>
            <td class="num"><?php echo esc_html( number_format( $v['conversions'] ) ); ?></td>
            <td class="num"><?php echo esc_html( $v['rate'] ); ?>%</td>
            <td class="num">
                <?php if ( ! $v['is_control'] ) : ?>
                    <span class="<?php echo $v['lift'] > 0 ? 'lift-up' : ( $v['lift'] < 0 ? 'lift-down' : '' ); ?>">
                        <?php echo esc_html( ( $v['lift'] > 0 ? '+' : '' ) . $v['lift'] ); ?>%
                    </span>
                <?php else : ?>
                    —
                <?php endif; ?>
            </td>
            <td class="num">
                <?php echo ! $v['is_control'] ? esc_html( $v['confidence'] ) . '%' : '—'; ?>
            </td>
            <td>
                <?php
                $verdict_class = '';
                if ( $v['verdict'] ) {
                    $verdict_class = 'verdict-' . strtolower( $v['verdict'] );
                }
                ?>
                <span class="<?php echo esc_attr( $verdict_class ); ?>"><?php echo esc_html( $v['verdict'] ); ?></span>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ( ! empty( $goals ) ) : ?>
<!-- Conversion Goals -->
<h2>Conversion Goals</h2>
<table>
    <thead>
        <tr>
            <th>Goal</th>
            <th>Type</th>
            <th>Trigger</th>
            <?php foreach ( $variants as $v ) : ?>
                <th class="num"><?php echo esc_html( $v['name'] ); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $goals as $g ) : ?>
        <tr>
            <td><?php echo esc_html( $g['name'] ); ?></td>
            <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $g['trigger_type'] ) ) ); ?></td>
            <td><?php echo $g['trigger_detail'] ? '<code>' . esc_html( $g['trigger_detail'] ) . '</code>' : '—'; ?></td>
            <?php foreach ( $variants as $v ) : ?>
                <td class="num">
                    <?php echo esc_html( number_format( isset( $v['goal_conversions'][ $g['conversion_id'] ] ) ? $v['goal_conversions'][ $g['conversion_id'] ] : 0 ) ); ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ( ! empty( $daily ) ) : ?>
<!-- Daily Breakdown -->
<h2>Daily Breakdown</h2>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Variant</th>
            <th class="num">Impressions</th>
            <th class="num">Conversions</th>
            <th class="num">Rate</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $daily as $row ) : ?>
        <tr>
            <td><?php echo esc_html( $row['date'] ); ?></td>
            <td><?php echo esc_html( $row['variant_name'] ); ?></td>
            <td class="num"><?php echo esc_html( number_format( $row['impressions'] ) ); ?></td>
            <td class="num"><?php echo esc_html( number_format( $row['conversions'] ) ); ?></td>
            <td class="num"><?php echo esc_html( $row['rate'] ); ?>%</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="report-footer">
    Generated by ElementTest Pro v<?php echo esc_html( $version ); ?>
</div>

<?php
/*
 * Chart.js (MIT licensed) is bundled with the plugin in
 * assets/js/chart.umd.min.js and inlined here so the exported HTML
 * report renders charts correctly when opened offline (e.g. emailed
 * or saved to disk) without contacting any external server.
 *
 * The file is read with the WP_Filesystem API to comply with the
 * WordPress.org plugin guidelines that prohibit direct PHP filesystem
 * calls (fopen/fread/file_get_contents) inside plugin code.
 */
$chartjs_inline = '';
if ( ! empty( $daily ) && defined( 'ELEMENTTEST_PLUGIN_DIR' ) ) {
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();
	global $wp_filesystem;
	$chartjs_path = ELEMENTTEST_PLUGIN_DIR . 'assets/js/chart.umd.min.js';
	if ( $wp_filesystem && $wp_filesystem->exists( $chartjs_path ) ) {
		$chartjs_inline = $wp_filesystem->get_contents( $chartjs_path );
	}
}
?>
<?php if ( ! empty( $daily ) && '' !== $chartjs_inline ) : ?>
<script>
<?php
// Chart.js library source — emitted verbatim. This is library code, not
// dynamic data, so it is intentionally not run through esc_js().
echo $chartjs_inline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?>
</script>
<script>
(function() {
    if (typeof Chart === 'undefined') {
        var cards = document.querySelectorAll('.chart-card');
        for (var i = 0; i < cards.length; i++) cards[i].style.display = 'none';
        return;
    }
    var reportData = <?php echo wp_json_encode( $report, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ); ?>;
    var COLORS = ['#6366f1', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899'];

    var variantNames = reportData.variants.map(function(v) { return v.name; });

    // Build per-date, per-variant lookup from flat daily rows.
    var dateMap = {};
    reportData.daily.forEach(function(row) {
        if (!dateMap[row.date]) dateMap[row.date] = {};
        dateMap[row.date][row.variant_name] = row;
    });
    var dates = Object.keys(dateMap).sort();
    var shortDates = dates.map(function(d) {
        var parts = d.split('-');
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[parseInt(parts[1], 10) - 1] + ' ' + parseInt(parts[2], 10);
    });

    var sharedTooltip = {
        backgroundColor: 'rgba(30, 41, 59, 0.95)',
        titleColor: '#f8fafc',
        bodyColor: '#e2e8f0',
        borderColor: '#475569',
        borderWidth: 1,
        cornerRadius: 6,
        padding: 10
    };

    var sharedScales = {
        x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#787c82', maxRotation: 45 } },
        y: { grid: { color: '#f0f0f0' }, ticks: { font: { size: 11 }, color: '#787c82' } }
    };

    // 1) Daily Conversion Rate (line chart)
    var convRateDatasets = variantNames.map(function(name, i) {
        return {
            label: name,
            data: dates.map(function(d) {
                var row = dateMap[d] && dateMap[d][name];
                return row ? row.rate : null;
            }),
            borderColor: COLORS[i % COLORS.length],
            backgroundColor: COLORS[i % COLORS.length] + '20',
            borderWidth: 2.5,
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.3,
            spanGaps: true
        };
    });

    new Chart(document.getElementById('chartConvRate'), {
        type: 'line',
        data: { labels: shortDates, datasets: convRateDatasets },
        options: {
            responsive: true,
            plugins: { tooltip: sharedTooltip, legend: { labels: { font: { size: 12 } } } },
            scales: Object.assign({}, sharedScales, {
                y: Object.assign({}, sharedScales.y, {
                    ticks: Object.assign({}, sharedScales.y.ticks, { callback: function(v) { return v + '%'; } })
                })
            })
        }
    });

    // 2) Cumulative Conversions (line/area chart)
    var cumulativeDatasets = variantNames.map(function(name, i) {
        var running = 0;
        return {
            label: name,
            data: dates.map(function(d) {
                var row = dateMap[d] && dateMap[d][name];
                if (row) running += row.conversions;
                return running;
            }),
            borderColor: COLORS[i % COLORS.length],
            backgroundColor: COLORS[i % COLORS.length] + '18',
            borderWidth: 2.5,
            fill: true,
            tension: 0.3
        };
    });

    new Chart(document.getElementById('chartCumulative'), {
        type: 'line',
        data: { labels: shortDates, datasets: cumulativeDatasets },
        options: {
            responsive: true,
            plugins: { tooltip: sharedTooltip, legend: { labels: { font: { size: 12 } } } },
            scales: sharedScales
        }
    });

    // 3) Overall Conversion Rate (bar chart)
    new Chart(document.getElementById('chartOverall'), {
        type: 'bar',
        data: {
            labels: variantNames,
            datasets: [{
                label: 'Conversion Rate',
                data: reportData.variants.map(function(v) { return v.rate; }),
                backgroundColor: variantNames.map(function(_, i) { return COLORS[i % COLORS.length] + 'cc'; }),
                borderColor: variantNames.map(function(_, i) { return COLORS[i % COLORS.length]; }),
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: sharedTooltip,
                legend: { display: false }
            },
            scales: Object.assign({}, sharedScales, {
                y: Object.assign({}, sharedScales.y, {
                    beginAtZero: true,
                    ticks: Object.assign({}, sharedScales.y.ticks, { callback: function(v) { return v + '%'; } })
                })
            })
        }
    });

    // 4) Goal Breakdown (grouped bar chart, only if >1 goal)
    var goalEl = document.getElementById('chartGoals');
    if (goalEl && reportData.goals.length > 1) {
        var goalLabels = reportData.goals.map(function(g) { return g.name; });
        var goalDatasets = reportData.variants.map(function(v, i) {
            return {
                label: v.name,
                data: reportData.goals.map(function(g) {
                    return v.goal_conversions[g.conversion_id] || 0;
                }),
                backgroundColor: COLORS[i % COLORS.length] + 'cc',
                borderColor: COLORS[i % COLORS.length],
                borderWidth: 1,
                borderRadius: 6
            };
        });

        new Chart(goalEl, {
            type: 'bar',
            data: { labels: goalLabels, datasets: goalDatasets },
            options: {
                responsive: true,
                plugins: { tooltip: sharedTooltip, legend: { labels: { font: { size: 12 } } } },
                scales: Object.assign({}, sharedScales, {
                    y: Object.assign({}, sharedScales.y, { beginAtZero: true })
                })
            }
        });
    }

    // 5) Daily Impressions — Stacked Bar
    var trafficDatasets = variantNames.map(function(name, i) {
        return {
            label: name,
            data: dates.map(function(d) {
                var row = dateMap[d] && dateMap[d][name];
                return row ? row.impressions : 0;
            }),
            backgroundColor: COLORS[i % COLORS.length] + 'cc',
            borderColor: COLORS[i % COLORS.length],
            borderWidth: 1,
            borderRadius: { topLeft: 4, topRight: 4 }
        };
    });

    new Chart(document.getElementById('chartTraffic'), {
        type: 'bar',
        data: { labels: shortDates, datasets: trafficDatasets },
        options: {
            responsive: true,
            plugins: { tooltip: sharedTooltip, legend: { labels: { font: { size: 12 } } } },
            scales: Object.assign({}, sharedScales, {
                x: Object.assign({}, sharedScales.x, { stacked: true }),
                y: Object.assign({}, sharedScales.y, { stacked: true, beginAtZero: true })
            })
        }
    });
})();
</script>
<?php endif; ?>

</body>
</html>
