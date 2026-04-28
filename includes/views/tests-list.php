<?php
/**
 * Tests List View Template
 *
 * Displays all A/B tests in a WordPress-native admin table with filtering,
 * sorting, and AJAX-powered row actions (duplicate, delete, start/pause).
 *
 * Expected variables:
 *   $tests   array  Array of test objects, each with aggregated stats:
 *                    - test_id, name, description, status, page_url,
 *                    - element_selector, test_type, start_date, end_date,
 *                    - created_at, updated_at,
 *                    - variant_count, impressions, conversions, conversion_rate,
 *                    - confidence
 *   $counts  array  Associative counts by status:
 *                    - all, running, paused, draft, completed
 *
 * @package ElementTestPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This file is an admin page template included from
// ElementTest_Pro::render_admin_page(); the variables it consumes are
// not globals — they are local to the including method's scope. Plugin
// Check cannot follow PHP `include` semantics so it reports them as
// non-prefixed globals. Read-only `$_GET` lookups are sanitised via
// `sanitize_key()`/`absint()` and only used to drive the status filter
// and pagination, no state changes.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:disable WordPress.Security.NonceVerification.Recommended

// Ensure expected variables exist.
$tests  = isset( $tests ) ? $tests : array();
$counts = isset( $counts ) ? $counts : array(
	'all'       => 0,
	'running'   => 0,
	'paused'    => 0,
	'draft'     => 0,
	'completed' => 0,
);

// Current filter from query string.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, no state change.
$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';
$valid_statuses = array( 'all', 'running', 'paused', 'draft', 'completed' );
if ( ! in_array( $current_status, $valid_statuses, true ) ) {
	$current_status = 'all';
}

$new_test_url = admin_url( 'admin.php?page=elementtest-new' );
$base_url     = admin_url( 'admin.php?page=elementtest-pro' );
?>

<div class="wrap elementtest-tests-list">

	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'ElementTest Pro', 'elementtest-pro' ); ?>
	</h1>
	<a href="<?php echo esc_url( $new_test_url ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'elementtest-pro' ); ?>
	</a>
	<a href="#" class="page-title-action" id="elementtest-import-btn">
		<?php esc_html_e( 'Import Tests', 'elementtest-pro' ); ?>
	</a>
	<input type="file" id="elementtest-import-file" accept=".json" style="display:none;">
	<hr class="wp-header-end">

	<?php if ( ! empty( $tests ) || 'all' !== $current_status ) : ?>

		<!-- Status Filter Tabs -->
		<ul class="subsubsub">
			<?php
			$status_labels = array(
				'all'       => __( 'All', 'elementtest-pro' ),
				'running'   => __( 'Running', 'elementtest-pro' ),
				'paused'    => __( 'Paused', 'elementtest-pro' ),
				'draft'     => __( 'Draft', 'elementtest-pro' ),
				'completed' => __( 'Completed', 'elementtest-pro' ),
			);

			$tab_links = array();
			foreach ( $status_labels as $status_key => $label ) {
				$count   = isset( $counts[ $status_key ] ) ? absint( $counts[ $status_key ] ) : 0;
				$url     = ( 'all' === $status_key )
					? $base_url
					: add_query_arg( 'status', $status_key, $base_url );
				$class   = ( $current_status === $status_key ) ? 'current' : '';
				$tab_links[] = sprintf(
					'<li class="elementtest-filter-%1$s"><a href="%2$s" class="%3$s">%4$s <span class="count">(%5$d)</span></a></li>',
					esc_attr( $status_key ),
					esc_url( $url ),
					esc_attr( $class ),
					esc_html( $label ),
					$count
				);
			}
			echo implode( ' | ', $tab_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each piece already escaped above.
			?>
		</ul>

		<!-- Bulk Actions Toolbar -->
		<div class="tablenav top">
			<div class="alignleft actions">
				<button type="button" class="button" id="elementtest-export-selected" disabled>
					<?php esc_html_e( 'Export Selected', 'elementtest-pro' ); ?>
				</button>
				<button type="button" class="button" id="elementtest-export-all">
					<?php esc_html_e( 'Export All', 'elementtest-pro' ); ?>
				</button>
			</div>
			<div class="alignleft actions" style="margin-left: 8px;">
				<button type="button" class="button" id="elementtest-export-reports-html">
					<span class="dashicons dashicons-media-text" style="vertical-align: text-top; font-size: 16px; width: 16px; height: 16px; margin-right: 2px;"></span>
					<?php esc_html_e( 'Export All Reports (HTML)', 'elementtest-pro' ); ?>
				</button>
				<button type="button" class="button" id="elementtest-export-reports-csv">
					<span class="dashicons dashicons-media-spreadsheet" style="vertical-align: text-top; font-size: 16px; width: 16px; height: 16px; margin-right: 2px;"></span>
					<?php esc_html_e( 'Export All Reports (CSV)', 'elementtest-pro' ); ?>
				</button>
			</div>
			<br class="clear">
		</div>

		<!-- Tests Table -->
		<table class="wp-list-table widefat fixed striped elementtest-table">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column" style="width: 2.2em;">
						<input type="checkbox" id="elementtest-select-all">
					</td>
					<th scope="col" class="manage-column column-name column-primary" style="width: 20%;">
						<?php esc_html_e( 'Test Name', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-status" style="width: 8%;">
						<?php esc_html_e( 'Status', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-page" style="width: 14%;">
						<?php esc_html_e( 'Page', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-type" style="width: 6%;">
						<?php esc_html_e( 'Type', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-variants" style="width: 7%;">
						<?php esc_html_e( 'Variants', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-impressions" style="width: 9%;">
						<?php esc_html_e( 'Impressions', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-conversions" style="width: 9%;">
						<?php esc_html_e( 'Conversions', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-rate" style="width: 9%;">
						<?php esc_html_e( 'Conv. Rate', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-confidence" style="width: 9%;">
						<?php esc_html_e( 'Confidence', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-created" style="width: 9%;">
						<?php esc_html_e( 'Created', 'elementtest-pro' ); ?>
					</th>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php if ( empty( $tests ) ) : ?>
					<tr class="no-items">
						<td class="colspanchange" colspan="11">
							<?php esc_html_e( 'No tests found.', 'elementtest-pro' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $tests as $test ) :
						$test_id    = absint( $test->test_id );
						$test_name  = ! empty( $test->name ) ? $test->name : __( '(no title)', 'elementtest-pro' );
						$status     = ! empty( $test->status ) ? sanitize_key( $test->status ) : 'draft';
						$test_type  = ! empty( $test->test_type ) ? strtoupper( sanitize_text_field( $test->test_type ) ) : '—';
						$page_url   = ! empty( $test->page_url ) ? esc_url( $test->page_url ) : '';

						// Build a human-readable label from the URL path.
						$page_label = '';
						if ( ! empty( $page_url ) ) {
							$parsed = wp_parse_url( $page_url );
							$page_label = isset( $parsed['path'] ) ? untrailingslashit( $parsed['path'] ) : $page_url;
							if ( empty( $page_label ) || '/' === $page_label ) {
								$page_label = '/';
							}
						}

						$variant_count   = isset( $test->variant_count ) ? absint( $test->variant_count ) : 0;
						$impressions     = isset( $test->impressions ) ? absint( $test->impressions ) : 0;
						$conversions     = isset( $test->conversions ) ? absint( $test->conversions ) : 0;
						$conversion_rate = isset( $test->conversion_rate ) ? floatval( $test->conversion_rate ) : 0.0;
						$confidence      = isset( $test->confidence ) ? floatval( $test->confidence ) : 0.0;

						$edit_url    = add_query_arg(
							array(
								'page'    => 'elementtest-new',
								'test_id' => $test_id,
							),
							admin_url( 'admin.php' )
						);
						$results_url = add_query_arg(
							array(
								'page'    => 'elementtest-pro',
								'view'    => 'results',
								'test_id' => $test_id,
							),
							admin_url( 'admin.php' )
						);
					?>
					<tr id="elementtest-test-<?php echo esc_attr( $test_id ); ?>" data-test-id="<?php echo esc_attr( $test_id ); ?>">
						<th scope="row" class="check-column">
							<input type="checkbox" class="elementtest-test-cb" value="<?php echo esc_attr( $test_id ); ?>">
						</th>
						<!-- Test Name -->
						<td class="column-name column-primary" data-colname="<?php esc_attr_e( 'Test Name', 'elementtest-pro' ); ?>">
							<strong>
								<a href="<?php echo esc_url( $edit_url ); ?>" class="row-title">
									<?php echo esc_html( $test_name ); ?>
								</a>
							</strong>
							<div class="row-actions">
								<span class="edit">
									<a href="<?php echo esc_url( $edit_url ); ?>">
										<?php esc_html_e( 'Edit', 'elementtest-pro' ); ?>
									</a> |
								</span>
								<span class="view-results">
									<a href="<?php echo esc_url( $results_url ); ?>">
										<?php esc_html_e( 'View Results', 'elementtest-pro' ); ?>
									</a> |
								</span>
								<span class="duplicate">
									<a href="#" class="elementtest-duplicate-test" data-test-id="<?php echo esc_attr( $test_id ); ?>">
										<?php esc_html_e( 'Duplicate', 'elementtest-pro' ); ?>
									</a> |
								</span>
								<span class="export">
									<a href="#" class="elementtest-export-single" data-test-id="<?php echo esc_attr( $test_id ); ?>">
										<?php esc_html_e( 'Export', 'elementtest-pro' ); ?>
									</a> |
								</span>
								<?php if ( 'running' === $status ) : ?>
									<span class="pause">
										<a href="#" class="elementtest-toggle-status" data-test-id="<?php echo esc_attr( $test_id ); ?>" data-new-status="paused">
											<?php esc_html_e( 'Pause', 'elementtest-pro' ); ?>
										</a> |
									</span>
								<?php elseif ( in_array( $status, array( 'paused', 'draft' ), true ) ) : ?>
									<span class="start">
										<a href="#" class="elementtest-toggle-status" data-test-id="<?php echo esc_attr( $test_id ); ?>" data-new-status="running">
											<?php esc_html_e( 'Start', 'elementtest-pro' ); ?>
										</a> |
									</span>
								<?php endif; ?>
								<span class="trash">
									<a href="#" class="elementtest-delete-test" data-test-id="<?php echo esc_attr( $test_id ); ?>">
										<?php esc_html_e( 'Delete', 'elementtest-pro' ); ?>
									</a>
								</span>
							</div>
							<button type="button" class="toggle-row">
								<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'elementtest-pro' ); ?></span>
							</button>
						</td>

						<!-- Status Badge -->
						<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'elementtest-pro' ); ?>">
							<?php
							$status_classes = array(
								'running'   => 'elementtest-badge-running',
								'paused'    => 'elementtest-badge-paused',
								'draft'     => 'elementtest-badge-draft',
								'completed' => 'elementtest-badge-completed',
							);
							$status_display = array(
								'running'   => __( 'Running', 'elementtest-pro' ),
								'paused'    => __( 'Paused', 'elementtest-pro' ),
								'draft'     => __( 'Draft', 'elementtest-pro' ),
								'completed' => __( 'Completed', 'elementtest-pro' ),
							);
							$badge_class  = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : 'elementtest-badge-draft';
							$status_label = isset( $status_display[ $status ] ) ? $status_display[ $status ] : ucfirst( $status );
							?>
							<span class="elementtest-badge <?php echo esc_attr( $badge_class ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</span>
						</td>

						<!-- Page -->
						<td class="column-page" data-colname="<?php esc_attr_e( 'Page', 'elementtest-pro' ); ?>">
							<?php if ( ! empty( $page_url ) ) : ?>
								<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $page_url ); ?>" class="elementtest-page-link">
									<?php echo esc_html( $page_label ); ?>
								</a>
							<?php else : ?>
								<span class="elementtest-no-value">&mdash;</span>
							<?php endif; ?>
						</td>

						<!-- Type -->
						<td class="column-type" data-colname="<?php esc_attr_e( 'Type', 'elementtest-pro' ); ?>">
							<?php echo esc_html( $test_type ); ?>
						</td>

						<!-- Variants -->
						<td class="column-variants" data-colname="<?php esc_attr_e( 'Variants', 'elementtest-pro' ); ?>">
							<?php echo esc_html( $variant_count ); ?>
						</td>

						<!-- Impressions -->
						<td class="column-impressions" data-colname="<?php esc_attr_e( 'Impressions', 'elementtest-pro' ); ?>">
							<?php echo esc_html( number_format_i18n( $impressions ) ); ?>
						</td>

						<!-- Conversions -->
						<td class="column-conversions" data-colname="<?php esc_attr_e( 'Conversions', 'elementtest-pro' ); ?>">
							<?php echo esc_html( number_format_i18n( $conversions ) ); ?>
						</td>

						<!-- Conversion Rate -->
						<td class="column-rate" data-colname="<?php esc_attr_e( 'Conv. Rate', 'elementtest-pro' ); ?>">
							<?php
							$rate_class = '';
							if ( $conversion_rate >= 5.0 ) {
								$rate_class = 'elementtest-rate-high';
							} elseif ( $conversion_rate >= 2.0 ) {
								$rate_class = 'elementtest-rate-medium';
							} elseif ( $conversion_rate > 0 ) {
								$rate_class = 'elementtest-rate-low';
							}
							?>
							<span class="<?php echo esc_attr( $rate_class ); ?>">
								<?php echo esc_html( number_format_i18n( $conversion_rate, 2 ) . '%' ); ?>
							</span>
						</td>

						<!-- Confidence -->
						<td class="column-confidence" data-colname="<?php esc_attr_e( 'Confidence', 'elementtest-pro' ); ?>">
							<?php
							$confidence_class = 'elementtest-confidence-low';
							if ( $confidence >= 95.0 ) {
								$confidence_class = 'elementtest-confidence-high';
							} elseif ( $confidence >= 90.0 ) {
								$confidence_class = 'elementtest-confidence-medium';
							}
							?>
							<span class="<?php echo esc_attr( $confidence_class ); ?>">
								<?php echo esc_html( number_format_i18n( $confidence, 1 ) . '%' ); ?>
							</span>
						</td>

						<!-- Created -->
						<td class="column-created" data-colname="<?php esc_attr_e( 'Created', 'elementtest-pro' ); ?>">
							<?php
							if ( ! empty( $test->created_at ) ) {
								$timestamp = strtotime( $test->created_at );
								printf(
									'<span title="%1$s">%2$s</span>',
									esc_attr( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ),
									esc_html( human_time_diff( $timestamp, time() ) . ' ' . __( 'ago', 'elementtest-pro' ) )
								);
							} else {
								echo '<span class="elementtest-no-value">&mdash;</span>';
							}
							?>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<td class="manage-column column-cb check-column">
						<input type="checkbox" id="elementtest-select-all-footer">
					</td>
					<th scope="col" class="manage-column column-name column-primary">
						<?php esc_html_e( 'Test Name', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-status">
						<?php esc_html_e( 'Status', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-page">
						<?php esc_html_e( 'Page', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-type">
						<?php esc_html_e( 'Type', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-variants">
						<?php esc_html_e( 'Variants', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-impressions">
						<?php esc_html_e( 'Impressions', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-conversions">
						<?php esc_html_e( 'Conversions', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-rate">
						<?php esc_html_e( 'Conv. Rate', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-confidence">
						<?php esc_html_e( 'Confidence', 'elementtest-pro' ); ?>
					</th>
					<th scope="col" class="manage-column column-created">
						<?php esc_html_e( 'Created', 'elementtest-pro' ); ?>
					</th>
				</tr>
			</tfoot>
		</table>

	<?php else : ?>

		<!-- Empty State -->
		<div class="elementtest-empty-state">
			<div class="elementtest-empty-state-icon">
				<span class="dashicons dashicons-chart-bar"></span>
			</div>
			<h2><?php esc_html_e( 'No tests yet', 'elementtest-pro' ); ?></h2>
			<p><?php esc_html_e( 'Create your first A/B test to start optimizing your website.', 'elementtest-pro' ); ?></p>
			<a href="<?php echo esc_url( $new_test_url ); ?>" class="button button-primary button-hero">
				<?php esc_html_e( 'Create Your First Test', 'elementtest-pro' ); ?>
			</a>
		</div>

	<?php endif; ?>

</div><!-- .wrap -->

<!-- Delete Confirmation Dialog -->
<div id="elementtest-delete-dialog" style="display:none;">
	<p><?php esc_html_e( 'Are you sure you want to delete this test? This action cannot be undone. All variants and collected data will be permanently removed.', 'elementtest-pro' ); ?></p>
</div>

<style>
	/**
	 * Status Badges
	 */
	.elementtest-badge {
		display: inline-block;
		padding: 3px 8px;
		border-radius: 3px;
		font-size: 12px;
		font-weight: 600;
		line-height: 1.4;
		white-space: nowrap;
	}

	.elementtest-badge-running {
		background: #d4edda;
		color: #155724;
	}

	.elementtest-badge-paused {
		background: #fff3cd;
		color: #856404;
	}

	.elementtest-badge-draft {
		background: #e2e3e5;
		color: #495057;
	}

	.elementtest-badge-completed {
		background: #cce5ff;
		color: #004085;
	}

	/**
	 * Conversion Rate Colors
	 */
	.elementtest-rate-high {
		color: #155724;
		font-weight: 600;
	}

	.elementtest-rate-medium {
		color: #856404;
		font-weight: 600;
	}

	.elementtest-rate-low {
		color: #721c24;
	}

	/**
	 * Confidence Colors
	 */
	.elementtest-confidence-high {
		color: #155724;
		font-weight: 600;
	}

	.elementtest-confidence-medium {
		color: #856404;
		font-weight: 600;
	}

	.elementtest-confidence-low {
		color: #721c24;
	}

	/**
	 * Page Link (truncated with ellipsis)
	 */
	.elementtest-page-link {
		display: block;
		max-width: 100%;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	/**
	 * No-value placeholder
	 */
	.elementtest-no-value {
		color: #999;
	}

	/**
	 * Empty State
	 */
	.elementtest-empty-state {
		text-align: center;
		padding: 60px 20px;
		max-width: 500px;
		margin: 40px auto;
		background: #fff;
		border: 1px solid #c3c4c7;
		border-radius: 4px;
	}

	.elementtest-empty-state-icon .dashicons {
		font-size: 64px;
		width: 64px;
		height: 64px;
		color: #c3c4c7;
		margin-bottom: 16px;
	}

	.elementtest-empty-state h2 {
		font-size: 1.4em;
		margin: 0 0 8px;
		color: #1d2327;
	}

	.elementtest-empty-state p {
		font-size: 14px;
		color: #646970;
		margin: 0 0 24px;
	}

	/**
	 * Table layout adjustments
	 */
	.elementtest-table .column-status,
	.elementtest-table .column-type,
	.elementtest-table .column-variants,
	.elementtest-table .column-impressions,
	.elementtest-table .column-conversions,
	.elementtest-table .column-rate,
	.elementtest-table .column-confidence,
	.elementtest-table .column-created {
		text-align: left;
	}
</style>

<script>
(function( $ ) {
	'use strict';

	/**
	 * Duplicate a test via AJAX.
	 */
	$( document ).on( 'click', '.elementtest-duplicate-test', function( e ) {
		e.preventDefault();

		var $link  = $( this );
		var testId = $link.data( 'test-id' );
		var $row   = $link.closest( 'tr' );

		$link.text( '<?php echo esc_js( __( 'Duplicating...', 'elementtest-pro' ) ); ?>' );

		$.post( elementtestAdmin.ajaxUrl, {
			action:  'elementtest_duplicate_test',
			test_id: testId,
			nonce:  elementtestAdmin.nonce
		}, function( response ) {
			if ( response.success ) {
				location.reload();
			} else {
				alert( response.data || '<?php echo esc_js( __( 'Failed to duplicate test.', 'elementtest-pro' ) ); ?>' );
				$link.text( '<?php echo esc_js( __( 'Duplicate', 'elementtest-pro' ) ); ?>' );
			}
		}).fail( function() {
			alert( '<?php echo esc_js( __( 'An error occurred. Please try again.', 'elementtest-pro' ) ); ?>' );
			$link.text( '<?php echo esc_js( __( 'Duplicate', 'elementtest-pro' ) ); ?>' );
		});
	});

	/**
	 * Toggle test status (start/pause) via AJAX.
	 */
	$( document ).on( 'click', '.elementtest-toggle-status', function( e ) {
		e.preventDefault();

		var $link     = $( this );
		var testId    = $link.data( 'test-id' );
		var newStatus = $link.data( 'new-status' );

		$link.text( '<?php echo esc_js( __( 'Updating...', 'elementtest-pro' ) ); ?>' );

		$.post( elementtestAdmin.ajaxUrl, {
			action:     'elementtest_toggle_status',
			test_id:    testId,
			new_status: newStatus,
			nonce:     elementtestAdmin.nonce
		}, function( response ) {
			if ( response.success ) {
				location.reload();
			} else {
				alert( response.data || '<?php echo esc_js( __( 'Failed to update test status.', 'elementtest-pro' ) ); ?>' );
				location.reload();
			}
		}).fail( function() {
			alert( '<?php echo esc_js( __( 'An error occurred. Please try again.', 'elementtest-pro' ) ); ?>' );
			location.reload();
		});
	});

	/**
	 * Delete a test via AJAX with confirmation.
	 */
	$( document ).on( 'click', '.elementtest-delete-test', function( e ) {
		e.preventDefault();

		var $link  = $( this );
		var testId = $link.data( 'test-id' );
		var $row   = $link.closest( 'tr' );

		var confirmed = confirm( '<?php echo esc_js( __( 'Are you sure you want to delete this test? This action cannot be undone.', 'elementtest-pro' ) ); ?>' );
		if ( ! confirmed ) {
			return;
		}

		$link.text( '<?php echo esc_js( __( 'Deleting...', 'elementtest-pro' ) ); ?>' );
		$row.css( 'opacity', '0.5' );

		$.post( elementtestAdmin.ajaxUrl, {
			action:  'elementtest_delete_test',
			test_id: testId,
			nonce:  elementtestAdmin.nonce
		}, function( response ) {
			if ( response.success ) {
				$row.fadeOut( 300, function() {
					$row.remove();

					// If no rows remain, reload to show empty state.
					if ( $( '#the-list tr' ).length === 0 ) {
						location.reload();
					}
				});
			} else {
				alert( response.data || '<?php echo esc_js( __( 'Failed to delete test.', 'elementtest-pro' ) ); ?>' );
				$row.css( 'opacity', '1' );
				$link.text( '<?php echo esc_js( __( 'Delete', 'elementtest-pro' ) ); ?>' );
			}
		}).fail( function() {
			alert( '<?php echo esc_js( __( 'An error occurred. Please try again.', 'elementtest-pro' ) ); ?>' );
			$row.css( 'opacity', '1' );
			$link.text( '<?php echo esc_js( __( 'Delete', 'elementtest-pro' ) ); ?>' );
		});
	});

	// =================================================================
	// Checkbox select-all
	// =================================================================

	$( '#elementtest-select-all, #elementtest-select-all-footer' ).on( 'change', function() {
		var checked = $( this ).prop( 'checked' );
		$( '.elementtest-test-cb' ).prop( 'checked', checked );
		$( '#elementtest-select-all, #elementtest-select-all-footer' ).prop( 'checked', checked );
		toggleExportSelectedBtn();
	});

	$( document ).on( 'change', '.elementtest-test-cb', function() {
		toggleExportSelectedBtn();
		var allChecked = $( '.elementtest-test-cb' ).length === $( '.elementtest-test-cb:checked' ).length;
		$( '#elementtest-select-all, #elementtest-select-all-footer' ).prop( 'checked', allChecked );
	});

	function toggleExportSelectedBtn() {
		var anyChecked = $( '.elementtest-test-cb:checked' ).length > 0;
		$( '#elementtest-export-selected' ).prop( 'disabled', ! anyChecked );
	}

	// =================================================================
	// Export helpers
	// =================================================================

	function triggerExportDownload( testIds ) {
		var data = {
			action: 'elementtest_export_tests',
			nonce:  elementtestAdmin.nonce
		};
		if ( testIds && testIds.length ) {
			data.test_ids = testIds;
		}

		$.post( elementtestAdmin.ajaxUrl, data, function( response ) {
			if ( response.success ) {
				var json = JSON.stringify( response.data, null, 2 );
				var blob = new Blob( [ json ], { type: 'application/json' } );
				var url  = URL.createObjectURL( blob );
				var a    = document.createElement( 'a' );
				var ts   = new Date().toISOString().slice( 0, 10 );
				a.href     = url;
				a.download = 'elementtest-export-' + ts + '.json';
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
		});
	}

	$( '#elementtest-export-selected' ).on( 'click', function() {
		var ids = [];
		$( '.elementtest-test-cb:checked' ).each( function() {
			ids.push( parseInt( $( this ).val(), 10 ) );
		});
		if ( ids.length ) {
			triggerExportDownload( ids );
		}
	});

	$( '#elementtest-export-all' ).on( 'click', function() {
		triggerExportDownload( [] );
	});

	$( document ).on( 'click', '.elementtest-export-single', function( e ) {
		e.preventDefault();
		var testId = $( this ).data( 'test-id' );
		triggerExportDownload( [ testId ] );
	});

	// =================================================================
	// Report export (HTML / CSV)
	// =================================================================

	function triggerReportExportAll( format ) {
		var $btn = $( '#elementtest-export-reports-' + format );
		var label = $btn.text();
		$btn.prop( 'disabled', true ).text( '<?php echo esc_js( __( 'Exporting...', 'elementtest-pro' ) ); ?>' );

		$.post( elementtestAdmin.ajaxUrl, {
			action: 'elementtest_export_all_reports',
			nonce:  elementtestAdmin.nonce,
			format: format
		}, function( response ) {
			if ( ! response.success ) {
				var msg = ( response.data && response.data.message ) ? response.data.message : '<?php echo esc_js( __( 'Export failed.', 'elementtest-pro' ) ); ?>';
				alert( msg );
				return;
			}

			var d = response.data;

			if ( d.zip ) {
				var raw  = atob( d.content );
				var arr  = new Uint8Array( raw.length );
				for ( var i = 0; i < raw.length; i++ ) {
					arr[i] = raw.charCodeAt( i );
				}
				var blob = new Blob( [ arr ], { type: d.mimetype } );
				var url  = URL.createObjectURL( blob );
				var a    = document.createElement( 'a' );
				a.href     = url;
				a.download = d.filename;
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			} else if ( d.files && d.files.length ) {
				d.files.forEach( function( f ) {
					var blob = new Blob( [ f.content ], { type: f.mimetype } );
					var url  = URL.createObjectURL( blob );
					var a    = document.createElement( 'a' );
					a.href     = url;
					a.download = f.filename;
					document.body.appendChild( a );
					a.click();
					document.body.removeChild( a );
					URL.revokeObjectURL( url );
				});
			}
		}).fail( function() {
			alert( '<?php echo esc_js( __( 'An error occurred during export.', 'elementtest-pro' ) ); ?>' );
		}).always( function() {
			$btn.prop( 'disabled', false ).text( label );
		});
	}

	$( '#elementtest-export-reports-html' ).on( 'click', function() {
		triggerReportExportAll( 'html' );
	});

	$( '#elementtest-export-reports-csv' ).on( 'click', function() {
		triggerReportExportAll( 'csv' );
	});

	// =================================================================
	// Import
	// =================================================================

	$( '#elementtest-import-btn' ).on( 'click', function( e ) {
		e.preventDefault();
		$( '#elementtest-import-file' ).trigger( 'click' );
	});

	$( '#elementtest-import-file' ).on( 'change', function() {
		var file = this.files[0];
		if ( ! file ) {
			return;
		}

		if ( file.type && file.type !== 'application/json' && ! file.name.endsWith( '.json' ) ) {
			alert( '<?php echo esc_js( __( 'Please select a .json file.', 'elementtest-pro' ) ); ?>' );
			$( this ).val( '' );
			return;
		}

		var reader = new FileReader();
		reader.onload = function( e ) {
			var jsonData = e.target.result;

			// Quick client-side sanity check.
			try {
				var parsed = JSON.parse( jsonData );
				if ( ! parsed.tests || ! Array.isArray( parsed.tests ) ) {
					alert( '<?php echo esc_js( __( 'Invalid file: JSON must contain a "tests" array.', 'elementtest-pro' ) ); ?>' );
					return;
				}
			} catch ( err ) {
				alert( '<?php echo esc_js( __( 'Invalid JSON file.', 'elementtest-pro' ) ); ?>' );
				return;
			}

			var count = parsed.tests.length;
			if ( ! confirm(
				'<?php echo esc_js( __( 'Import', 'elementtest-pro' ) ); ?> ' + count + ' <?php echo esc_js( __( 'test(s) as drafts?', 'elementtest-pro' ) ); ?>'
			) ) {
				return;
			}

			$.post( elementtestAdmin.ajaxUrl, {
				action:    'elementtest_import_tests',
				nonce:     elementtestAdmin.nonce,
				json_data: jsonData
			}, function( response ) {
				if ( response.success ) {
					alert( response.data.message );
					location.reload();
				} else {
					var msg = ( response.data && response.data.message ) ? response.data.message : '<?php echo esc_js( __( 'Import failed.', 'elementtest-pro' ) ); ?>';
					if ( response.data && response.data.errors && response.data.errors.length ) {
						msg += '\n\n' + response.data.errors.join( '\n' );
					}
					alert( msg );
				}
			}).fail( function() {
				alert( '<?php echo esc_js( __( 'An error occurred during import.', 'elementtest-pro' ) ); ?>' );
			});
		};
		reader.readAsText( file );

		// Reset input so the same file can be re-selected.
		$( this ).val( '' );
	});

})( jQuery );
</script>
