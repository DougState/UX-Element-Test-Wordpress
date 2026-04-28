<?php
/**
 * Template: Test Results Dashboard
 *
 * Displays A/B test performance analytics with variant comparison,
 * conversion data, and statistical significance.
 *
 * Expected variables (set by render_admin_page):
 *   $test       object  Test row from database.
 *   $variants   array   Variant objects with aggregated event stats.
 *   $goals      array   Conversion goal objects for this test.
 *   $daily_data array   Daily impression/conversion counts per variant.
 *
 * @package ElementTestPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This file is an admin page template included from
// ElementTest_Pro::render_results_page(); variables it consumes are
// local to the including method, not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:disable WordPress.Security.NonceVerification.Recommended

// Ensure variables exist.
$test       = isset( $test )       ? $test       : null;
$variants   = isset( $variants )   ? $variants   : array();
$goals      = isset( $goals )      ? $goals      : array();
$daily_data = isset( $daily_data ) ? $daily_data : array();

if ( ! $test ) {
	echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Test not found.', 'elementtest-pro' ) . '</p></div></div>';
	return;
}

$test_id   = absint( $test->test_id );
$test_name = ! empty( $test->name ) ? $test->name : __( '(Untitled Test)', 'elementtest-pro' );
$status    = ! empty( $test->status ) ? sanitize_key( $test->status ) : 'draft';
$test_type = ! empty( $test->test_type ) ? strtoupper( $test->test_type ) : '—';

// Compute totals.
$total_impressions = 0;
$total_conversions = 0;
$control_rate      = 0;

foreach ( $variants as $v ) {
	$total_impressions += absint( $v->impressions );
	$total_conversions += absint( $v->conversions );
	if ( ! empty( $v->is_control ) && absint( $v->impressions ) > 0 ) {
		$control_rate = round( ( absint( $v->conversions ) / absint( $v->impressions ) ) * 100, 2 );
	}
}

$overall_rate = $total_impressions > 0
	? round( ( $total_conversions / $total_impressions ) * 100, 2 )
	: 0;

// Status labels.
$status_labels = array(
	'running'   => __( 'Running', 'elementtest-pro' ),
	'paused'    => __( 'Paused', 'elementtest-pro' ),
	'draft'     => __( 'Draft', 'elementtest-pro' ),
	'completed' => __( 'Completed', 'elementtest-pro' ),
);

$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );

// Duration.
$start = ! empty( $test->start_date ) ? strtotime( $test->start_date ) : strtotime( $test->created_at );
$now   = time();
$days  = max( 1, ceil( ( $now - $start ) / DAY_IN_SECONDS ) );
?>

<div class="wrap elementtest-results-wrap">

	<!-- ================================================================
	     Header
	     ================================================================ -->
	<div class="etr-header">
		<div class="etr-header-top">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=elementtest-pro' ) ); ?>" class="etr-back">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'All Tests', 'elementtest-pro' ); ?>
			</a>
			<div class="etr-header-actions">
			<?php if ( $status === 'running' ) : ?>
				<button type="button" class="button etr-action-btn" data-action="pause" data-test-id="<?php echo esc_attr( $test_id ); ?>">
					<span class="dashicons dashicons-controls-pause"></span>
					<?php esc_html_e( 'Pause Test', 'elementtest-pro' ); ?>
				</button>
			<?php elseif ( $status === 'paused' || $status === 'draft' ) : ?>
				<button type="button" class="button button-primary etr-action-btn" data-action="start" data-test-id="<?php echo esc_attr( $test_id ); ?>">
					<span class="dashicons dashicons-controls-play"></span>
					<?php esc_html_e( 'Start Test', 'elementtest-pro' ); ?>
				</button>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=elementtest-new&test_id=' . $test_id ) ); ?>" class="button">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Edit', 'elementtest-pro' ); ?>
			</a>
			<button type="button" class="button etr-export-btn" data-test-id="<?php echo esc_attr( $test_id ); ?>" data-format="html">
				<span class="dashicons dashicons-media-text"></span>
				<?php esc_html_e( 'Export HTML', 'elementtest-pro' ); ?>
			</button>
			<button type="button" class="button etr-export-btn" data-test-id="<?php echo esc_attr( $test_id ); ?>" data-format="csv">
				<span class="dashicons dashicons-media-spreadsheet"></span>
				<?php esc_html_e( 'Export CSV', 'elementtest-pro' ); ?>
			</button>
		</div>
		</div>
		<div class="etr-header-title">
			<h1><?php echo esc_html( $test_name ); ?></h1>
			<span class="etr-status etr-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
			<span class="etr-meta-tag"><?php echo esc_html( $test_type ); ?></span>
		</div>
		<?php if ( ! empty( $test->description ) ) : ?>
			<p class="etr-description"><?php echo esc_html( $test->description ); ?></p>
		<?php endif; ?>
	</div>

	<!-- ================================================================
	     Summary Cards (KPI row)
	     ================================================================ -->
	<div class="etr-kpi-row">
		<div class="etr-kpi-card">
			<span class="etr-kpi-label"><?php esc_html_e( 'Total Impressions', 'elementtest-pro' ); ?></span>
			<span class="etr-kpi-value"><?php echo esc_html( number_format_i18n( $total_impressions ) ); ?></span>
			<span class="etr-kpi-sub"><?php
				/* translators: %s: number of days */
				printf( esc_html__( 'over %s days', 'elementtest-pro' ), esc_html( number_format_i18n( $days ) ) );
			?></span>
		</div>
		<div class="etr-kpi-card">
			<span class="etr-kpi-label"><?php esc_html_e( 'Total Conversions', 'elementtest-pro' ); ?></span>
			<span class="etr-kpi-value"><?php echo esc_html( number_format_i18n( $total_conversions ) ); ?></span>
			<span class="etr-kpi-sub"><?php
				/* translators: %s: overall conversion rate as a percentage value */
				printf( esc_html__( '%s%% overall rate', 'elementtest-pro' ), esc_html( $overall_rate ) );
			?></span>
		</div>
		<div class="etr-kpi-card">
			<span class="etr-kpi-label"><?php esc_html_e( 'Control Rate', 'elementtest-pro' ); ?></span>
			<span class="etr-kpi-value"><?php echo esc_html( $control_rate ); ?>%</span>
			<span class="etr-kpi-sub"><?php esc_html_e( 'baseline conversion', 'elementtest-pro' ); ?></span>
		</div>
		<div class="etr-kpi-card">
			<span class="etr-kpi-label"><?php esc_html_e( 'Duration', 'elementtest-pro' ); ?></span>
			<span class="etr-kpi-value"><?php echo esc_html( number_format_i18n( $days ) ); ?></span>
			<span class="etr-kpi-sub"><?php esc_html_e( 'days running', 'elementtest-pro' ); ?></span>
		</div>
	</div>

	<!-- ================================================================
	     Variant Comparison
	     ================================================================ -->
	<div class="etr-section">
		<div class="etr-section-header">
			<h2><?php esc_html_e( 'Variant Performance', 'elementtest-pro' ); ?></h2>
		</div>
		<div class="etr-variants-grid">
			<?php
			$variant_colors = array( '#00a32a', '#2271b1', '#9b59b6', '#e67e22', '#1abc9c', '#e74c3c' );
			$idx = 0;

			foreach ( $variants as $v ) :
				$v_impressions = absint( $v->impressions );
				$v_conversions = absint( $v->conversions );
				$v_rate        = $v_impressions > 0 ? round( ( $v_conversions / $v_impressions ) * 100, 2 ) : 0;
				$is_control    = ! empty( $v->is_control );
				$color         = isset( $variant_colors[ $idx ] ) ? $variant_colors[ $idx ] : '#7f8c8d';

				// Compute lift vs control.
				$lift     = 0;
				$lift_dir = '';
				if ( ! $is_control && $control_rate > 0 ) {
					$lift     = round( ( ( $v_rate - $control_rate ) / $control_rate ) * 100, 1 );
					$lift_dir = $lift > 0 ? 'up' : ( $lift < 0 ? 'down' : 'flat' );
				}

				// Bar width for visual comparison.
				$max_rate  = 0.01;
				foreach ( $variants as $vv ) {
					$vr = absint( $vv->impressions ) > 0 ? ( absint( $vv->conversions ) / absint( $vv->impressions ) ) * 100 : 0;
					if ( $vr > $max_rate ) {
						$max_rate = $vr;
					}
				}
				$bar_pct = $max_rate > 0 ? round( ( $v_rate / $max_rate ) * 100 ) : 0;

				// Confidence (simplified - would use a proper z-test in production).
				$confidence = 0;
				if ( ! $is_control && $control_rate > 0 && $v_impressions >= 30 ) {
					// Simple approximation based on sample size and effect size.
					$pooled_rate  = $total_impressions > 0 ? $total_conversions / $total_impressions : 0;
					$se           = $pooled_rate > 0 ? sqrt( $pooled_rate * ( 1 - $pooled_rate ) * ( 1 / max( 1, $v_impressions ) + 1 / max( 1, absint( $variants[0]->impressions ) ) ) ) : 0;
					$z            = $se > 0 ? abs( ( $v_rate / 100 ) - ( $control_rate / 100 ) ) / $se : 0;
					// Convert z-score to confidence percentage (approximation).
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

				$conf_class = 'low';
				if ( $confidence >= 95 ) {
					$conf_class = 'high';
				} elseif ( $confidence >= 90 ) {
					$conf_class = 'medium';
				}
			?>
			<div class="etr-variant-card <?php echo $is_control ? 'etr-variant-control' : ''; ?>">
				<div class="etr-variant-card-header">
					<div class="etr-variant-badge" style="background: <?php echo esc_attr( $color ); ?>;">
						<?php echo esc_html( chr( 65 + $idx ) ); ?>
					</div>
					<div class="etr-variant-name-wrap">
						<span class="etr-variant-name"><?php echo esc_html( $v->name ); ?></span>
						<?php if ( $is_control ) : ?>
							<span class="etr-tag etr-tag-control"><?php esc_html_e( 'Control', 'elementtest-pro' ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! $is_control && $lift_dir ) : ?>
						<div class="etr-lift etr-lift-<?php echo esc_attr( $lift_dir ); ?>">
							<?php if ( $lift_dir === 'up' ) : ?>
								<span class="dashicons dashicons-arrow-up-alt"></span>
							<?php elseif ( $lift_dir === 'down' ) : ?>
								<span class="dashicons dashicons-arrow-down-alt"></span>
							<?php endif; ?>
							<span><?php echo esc_html( ( $lift > 0 ? '+' : '' ) . $lift ); ?>%</span>
						</div>
					<?php endif; ?>
				</div>

				<div class="etr-variant-card-body">
					<!-- Rate bar -->
					<div class="etr-rate-bar-row">
						<div class="etr-rate-bar-track">
							<div class="etr-rate-bar-fill" style="width: <?php echo esc_attr( $bar_pct ); ?>%; background: <?php echo esc_attr( $color ); ?>;"></div>
						</div>
						<span class="etr-rate-value"><?php echo esc_html( $v_rate ); ?>%</span>
					</div>

					<!-- Stats grid -->
					<div class="etr-stats-row">
						<div class="etr-stat">
							<span class="etr-stat-num"><?php echo esc_html( number_format_i18n( $v_impressions ) ); ?></span>
							<span class="etr-stat-label"><?php esc_html_e( 'Impressions', 'elementtest-pro' ); ?></span>
						</div>
						<div class="etr-stat">
							<span class="etr-stat-num"><?php echo esc_html( number_format_i18n( $v_conversions ) ); ?></span>
							<span class="etr-stat-label"><?php esc_html_e( 'Conversions', 'elementtest-pro' ); ?></span>
						</div>
						<div class="etr-stat">
							<span class="etr-stat-num"><?php echo esc_html( $v_rate ); ?>%</span>
							<span class="etr-stat-label"><?php esc_html_e( 'Conv. Rate', 'elementtest-pro' ); ?></span>
						</div>
						<?php if ( ! $is_control ) : ?>
						<div class="etr-stat">
							<span class="etr-stat-num etr-confidence-<?php echo esc_attr( $conf_class ); ?>"><?php echo esc_html( $confidence ); ?>%</span>
							<span class="etr-stat-label"><?php esc_html_e( 'Confidence', 'elementtest-pro' ); ?></span>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( ! $is_control && $v_impressions > 0 ) : ?>
				<div class="etr-variant-card-footer etr-verdict-<?php echo esc_attr( $conf_class ); ?>">
					<?php if ( $confidence >= 95 && $lift > 0 ) : ?>
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Winner — statistically significant improvement', 'elementtest-pro' ); ?>
					<?php elseif ( $confidence >= 95 && $lift < 0 ) : ?>
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'Loser — statistically significant decline', 'elementtest-pro' ); ?>
					<?php elseif ( $confidence >= 80 ) : ?>
						<span class="dashicons dashicons-clock"></span>
						<?php esc_html_e( 'Trending — needs more data for significance', 'elementtest-pro' ); ?>
					<?php else : ?>
						<span class="dashicons dashicons-minus"></span>
						<?php esc_html_e( 'Inconclusive — not enough data yet', 'elementtest-pro' ); ?>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<?php
				$idx++;
			endforeach;
			?>
		</div>
	</div>

	<!-- ================================================================
	     Timeline Chart
	     ================================================================ -->
	<div class="etr-section">
		<div class="etr-section-header">
			<h2><?php esc_html_e( 'Performance Over Time', 'elementtest-pro' ); ?></h2>
		</div>
		<div class="etr-chart-container">
			<canvas id="etr-timeline-chart" height="280"></canvas>
			<?php if ( empty( $daily_data ) ) : ?>
			<div class="etr-chart-empty">
				<span class="dashicons dashicons-chart-area"></span>
				<p><?php esc_html_e( 'Chart data will appear once the test starts collecting impressions.', 'elementtest-pro' ); ?></p>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- ================================================================
	     Conversion Goals Breakdown
	     ================================================================ -->
	<?php if ( ! empty( $goals ) ) : ?>
	<div class="etr-section">
		<div class="etr-section-header">
			<h2><?php esc_html_e( 'Conversion Goals', 'elementtest-pro' ); ?></h2>
		</div>
		<table class="etr-goals-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Goal', 'elementtest-pro' ); ?></th>
					<th><?php esc_html_e( 'Type', 'elementtest-pro' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'elementtest-pro' ); ?></th>
					<?php foreach ( $variants as $v ) : ?>
						<th><?php echo esc_html( $v->name ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $goals as $goal ) : ?>
				<tr>
					<td class="etr-goal-name"><?php echo esc_html( $goal->name ); ?></td>
					<td><span class="etr-tag"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $goal->trigger_type ) ) ); ?></span></td>
					<td>
						<?php if ( ! empty( $goal->trigger_selector ) ) : ?>
							<code><?php echo esc_html( $goal->trigger_selector ); ?></code>
						<?php elseif ( ! empty( $goal->trigger_event ) ) : ?>
							<code><?php echo esc_html( $goal->trigger_event ); ?></code>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<?php foreach ( $variants as $v ) : ?>
						<td>
							<?php
							$goal_convs = absint( isset( $v->goal_conversions[ $goal->conversion_id ] )
								? $v->goal_conversions[ $goal->conversion_id ]
								: 0 );
							echo esc_html( number_format_i18n( $goal_convs ) );
							?>
						</td>
					<?php endforeach; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- ================================================================
	     Test Details
	     ================================================================ -->
	<div class="etr-section etr-details-section">
		<div class="etr-section-header">
			<h2><?php esc_html_e( 'Test Details', 'elementtest-pro' ); ?></h2>
		</div>
		<div class="etr-details-grid">
			<div class="etr-detail">
				<span class="etr-detail-label"><?php esc_html_e( 'Test ID', 'elementtest-pro' ); ?></span>
				<span class="etr-detail-value">#<?php echo esc_html( $test_id ); ?></span>
			</div>
			<div class="etr-detail">
				<span class="etr-detail-label"><?php esc_html_e( 'Page URL', 'elementtest-pro' ); ?></span>
				<span class="etr-detail-value">
					<?php if ( ! empty( $test->page_url ) ) : ?>
						<a href="<?php echo esc_url( $test->page_url ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( wp_parse_url( $test->page_url, PHP_URL_PATH ) ?: $test->page_url ); ?>
							<span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;"></span>
						</a>
					<?php else : ?>
						—
					<?php endif; ?>
				</span>
			</div>
			<div class="etr-detail">
				<span class="etr-detail-label"><?php esc_html_e( 'Element', 'elementtest-pro' ); ?></span>
				<span class="etr-detail-value"><code><?php echo esc_html( $test->element_selector ?: '—' ); ?></code></span>
			</div>
			<div class="etr-detail">
				<span class="etr-detail-label"><?php esc_html_e( 'Created', 'elementtest-pro' ); ?></span>
				<span class="etr-detail-value"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $test->created_at ) ) ); ?></span>
			</div>
			<?php if ( ! empty( $test->start_date ) ) : ?>
			<div class="etr-detail">
				<span class="etr-detail-label"><?php esc_html_e( 'Started', 'elementtest-pro' ); ?></span>
				<span class="etr-detail-value"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $test->start_date ) ) ); ?></span>
			</div>
			<?php endif; ?>
			<?php if ( ! empty( $test->end_date ) ) : ?>
			<div class="etr-detail">
				<span class="etr-detail-label"><?php esc_html_e( 'End Date', 'elementtest-pro' ); ?></span>
				<span class="etr-detail-value"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $test->end_date ) ) ); ?></span>
			</div>
			<?php endif; ?>
		</div>
	</div>

</div>

<!-- Chart rendering (vanilla Canvas) -->
<script>
(function() {
	'use strict';

	var dailyData    = <?php echo wp_json_encode( $daily_data ); ?>;
	var variantNames = <?php
		$names = array();
		foreach ( $variants as $v ) {
			$names[] = $v->name;
		}
		echo wp_json_encode( $names );
	?>;
	var variantColors = <?php echo wp_json_encode( $variant_colors ); ?>;

	if ( ! dailyData || ! dailyData.length ) {
		return;
	}

	var canvas = document.getElementById( 'etr-timeline-chart' );
	if ( ! canvas || ! canvas.getContext ) {
		return;
	}

	// Set canvas size for retina.
	var dpr    = window.devicePixelRatio || 1;
	var rect   = canvas.getBoundingClientRect();
	canvas.width  = rect.width * dpr;
	canvas.height = rect.height * dpr;
	var ctx = canvas.getContext( '2d' );
	ctx.scale( dpr, dpr );

	var W = rect.width;
	var H = rect.height;
	var pad = { top: 30, right: 24, bottom: 40, left: 56 };

	var chartW = W - pad.left - pad.right;
	var chartH = H - pad.top - pad.bottom;

	// Parse data: dailyData = [ { date: '2024-01-15', variants: { 'A': { impressions: 100, conversions: 5 }, ... } }, ... ]
	var dates = dailyData.map( function( d ) { return d.date; });

	// Find max conversion rate across all variants and days.
	var maxRate = 0;
	dailyData.forEach( function( day ) {
		if ( ! day.variants ) return;
		for ( var v in day.variants ) {
			if ( day.variants.hasOwnProperty( v ) ) {
				var imp  = day.variants[ v ].impressions || 0;
				var conv = day.variants[ v ].conversions || 0;
				var rate = imp > 0 ? ( conv / imp ) * 100 : 0;
				if ( rate > maxRate ) maxRate = rate;
			}
		}
	});

	maxRate = Math.max( maxRate * 1.2, 1 ); // Add 20% headroom.

	// Draw grid lines.
	ctx.strokeStyle = 'rgba(0,0,0,0.06)';
	ctx.lineWidth   = 1;
	var gridLines   = 5;

	for ( var g = 0; g <= gridLines; g++ ) {
		var gy = pad.top + ( chartH * g / gridLines );
		ctx.beginPath();
		ctx.moveTo( pad.left, gy );
		ctx.lineTo( W - pad.right, gy );
		ctx.stroke();

		// Y-axis labels.
		var yVal = maxRate - ( maxRate * g / gridLines );
		ctx.fillStyle = '#8b95a5';
		ctx.font      = '11px -apple-system, BlinkMacSystemFont, sans-serif';
		ctx.textAlign = 'right';
		ctx.fillText( yVal.toFixed(1) + '%', pad.left - 8, gy + 4 );
	}

	// X-axis labels (show every Nth date).
	var step = Math.max( 1, Math.floor( dates.length / 8 ) );
	ctx.textAlign = 'center';
	ctx.fillStyle = '#8b95a5';
	for ( var d = 0; d < dates.length; d += step ) {
		var dx = pad.left + ( chartW * d / Math.max(1, dates.length - 1) );
		var dateParts = dates[ d ].split('-');
		var dateLabel = dateParts[1] + '/' + dateParts[2];
		ctx.fillText( dateLabel, dx, H - pad.bottom + 20 );
	}

	// Draw lines for each variant.
	variantNames.forEach( function( vName, vi ) {
		var color = variantColors[ vi ] || '#7f8c8d';
		var points = [];

		dailyData.forEach( function( day, di ) {
			if ( ! day.variants || ! day.variants[ vName ] ) {
				points.push( null );
				return;
			}
			var imp  = day.variants[ vName ].impressions || 0;
			var conv = day.variants[ vName ].conversions || 0;
			var rate = imp > 0 ? ( conv / imp ) * 100 : 0;
			var x    = pad.left + ( chartW * di / Math.max(1, dates.length - 1) );
			var y    = pad.top + chartH - ( chartH * rate / maxRate );
			points.push({ x: x, y: y, rate: rate });
		});

		// Draw line.
		ctx.strokeStyle = color;
		ctx.lineWidth   = 2.5;
		ctx.lineJoin    = 'round';
		ctx.lineCap     = 'round';
		ctx.beginPath();

		var started = false;
		points.forEach( function( pt ) {
			if ( ! pt ) return;
			if ( ! started ) {
				ctx.moveTo( pt.x, pt.y );
				started = true;
			} else {
				ctx.lineTo( pt.x, pt.y );
			}
		});
		ctx.stroke();

		// Draw dots.
		points.forEach( function( pt ) {
			if ( ! pt ) return;
			ctx.beginPath();
			ctx.arc( pt.x, pt.y, 3, 0, Math.PI * 2 );
			ctx.fillStyle = color;
			ctx.fill();
			ctx.strokeStyle = '#fff';
			ctx.lineWidth   = 1.5;
			ctx.stroke();
		});
	});

	// Legend.
	var legendX = pad.left;
	var legendY = 14;
	ctx.font = '12px -apple-system, BlinkMacSystemFont, sans-serif';

	variantNames.forEach( function( vName, vi ) {
		var color = variantColors[ vi ] || '#7f8c8d';
		ctx.fillStyle   = color;
		ctx.fillRect( legendX, legendY - 8, 12, 12 );
		ctx.fillStyle   = '#3c434a';
		ctx.textAlign   = 'left';
		ctx.fillText( vName, legendX + 18, legendY + 2 );
		legendX += ctx.measureText( vName ).width + 38;
	});

})();
</script>

<!-- Report export handler -->
<script>
(function( $ ) {
	'use strict';

	$( '.etr-export-btn' ).on( 'click', function() {
		var $btn   = $( this );
		var testId = $btn.data( 'test-id' );
		var format = $btn.data( 'format' );
		var label  = $btn.text();

		$btn.prop( 'disabled', true ).text( '<?php echo esc_js( __( 'Exporting...', 'elementtest-pro' ) ); ?>' );

		$.post( ajaxurl, {
			action:  'elementtest_export_report',
			nonce:   elementtestAdmin.nonce,
			test_id: testId,
			format:  format
		}, function( response ) {
			if ( response.success && response.data ) {
				var blob = new Blob( [ response.data.content ], { type: response.data.mimetype } );
				var url  = URL.createObjectURL( blob );
				var a    = document.createElement( 'a' );
				a.href     = url;
				a.download = response.data.filename;
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			} else {
				var msg = ( response.data && response.data.message ) ? response.data.message : '<?php echo esc_js( __( 'Export failed.', 'elementtest-pro' ) ); ?>';
				alert( msg );
			}
		}).fail( function() {
			alert( '<?php echo esc_js( __( 'An error occurred during export.', 'elementtest-pro' ) ); ?>' );
		}).always( function() {
			$btn.prop( 'disabled', false ).text( label );
		});
	});
})( jQuery );
</script>
