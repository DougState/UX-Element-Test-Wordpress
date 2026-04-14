<?php
/**
 * Template: Settings page.
 *
 * Renders the plugin settings form using WordPress Settings API.
 *
 * Expected variables:
 *   $settings  array  Current settings from get_option('elementtest_settings').
 *
 * @package ElementTestPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = isset( $settings ) ? $settings : array();
?>

<div class="wrap elementtest-wrap">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'ElementTest Pro Settings', 'elementtest-pro' ); ?></h1>
	<hr class="wp-header-end">

	<form method="post" action="options.php" class="elementtest-admin-wrapper">

		<?php settings_fields( 'elementtest_settings_group' ); ?>

		<!-- ============================================================
		     General Settings
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'General', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="elementtest-cookie-days">
									<?php esc_html_e( 'Cookie Duration', 'elementtest-pro' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="elementtest-cookie-days"
									name="elementtest_settings[cookie_days]"
									value="<?php echo esc_attr( isset( $settings['cookie_days'] ) ? $settings['cookie_days'] : 30 ); ?>"
									class="small-text"
									min="1"
									max="365"
								>
								<span class="description"><?php esc_html_e( 'days', 'elementtest-pro' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'How long a visitor\'s variant assignment persists. Default: 30 days.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Exclude Admins', 'elementtest-pro' ); ?>
							</th>
							<td>
								<label for="elementtest-exclude-admins">
									<input
										type="checkbox"
										id="elementtest-exclude-admins"
										name="elementtest_settings[exclude_admins]"
										value="1"
										<?php checked( ! empty( $settings['exclude_admins'] ) ); ?>
									>
									<?php esc_html_e( 'Do not show test variants or track events for logged-in administrators', 'elementtest-pro' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Prevents admin visits from skewing test results.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- ============================================================
		     Test Automation
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Automation', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="elementtest-auto-pause">
									<?php esc_html_e( 'Auto-Pause Tests', 'elementtest-pro' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="elementtest-auto-pause"
									name="elementtest_settings[auto_pause_days]"
									value="<?php echo esc_attr( isset( $settings['auto_pause_days'] ) ? $settings['auto_pause_days'] : 0 ); ?>"
									class="small-text"
									min="0"
									max="365"
								>
								<span class="description"><?php esc_html_e( 'days (0 = never)', 'elementtest-pro' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Automatically pause running tests after this many days. Set to 0 to disable.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="elementtest-data-retention">
									<?php esc_html_e( 'Data Retention', 'elementtest-pro' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="elementtest-data-retention"
									name="elementtest_settings[data_retention_days]"
									value="<?php echo esc_attr( isset( $settings['data_retention_days'] ) ? $settings['data_retention_days'] : 0 ); ?>"
									class="small-text"
									min="0"
									max="3650"
								>
								<span class="description"><?php esc_html_e( 'days (0 = keep forever)', 'elementtest-pro' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Automatically delete event tracking data older than this many days. Set to 0 to keep all data.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- ============================================================
		     Proxy / IP Detection
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Reverse Proxy / CDN', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description" style="margin-bottom: 12px;">
					<?php esc_html_e( 'ElementTest needs your visitors\' real IP addresses for accurate rate limiting and deduplication. Most managed hosting providers (GoDaddy, SiteGround, Kinsta, WP Engine, etc.) run Nginx or a load balancer in front of WordPress. If unsure, select "Nginx / Managed Hosting" — it safely falls back to the direct connection IP when proxy headers are not present.', 'elementtest-pro' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Proxy Setup', 'elementtest-pro' ); ?>
							</th>
							<td>
								<fieldset>
									<label>
										<input
											type="radio"
											name="elementtest_settings[proxy_type]"
											value="none"
											<?php checked( isset( $settings['proxy_type'] ) ? $settings['proxy_type'] : 'none', 'none' ); ?>
										>
										<?php esc_html_e( 'None (direct connection)', 'elementtest-pro' ); ?>
									</label>
									<br>
									<label>
										<input
											type="radio"
											name="elementtest_settings[proxy_type]"
											value="cloudflare"
											<?php checked( isset( $settings['proxy_type'] ) ? $settings['proxy_type'] : 'none', 'cloudflare' ); ?>
										>
										<?php esc_html_e( 'Cloudflare', 'elementtest-pro' ); ?>
										<span class="description">&mdash; <?php esc_html_e( 'trusts CF-Connecting-IP header', 'elementtest-pro' ); ?></span>
									</label>
									<br>
									<label>
										<input
											type="radio"
											name="elementtest_settings[proxy_type]"
											value="nginx"
											<?php checked( isset( $settings['proxy_type'] ) ? $settings['proxy_type'] : 'none', 'nginx' ); ?>
										>
										<?php esc_html_e( 'Nginx / Managed Hosting', 'elementtest-pro' ); ?>
										<span class="description">&mdash; <?php esc_html_e( 'recommended for most managed hosts (GoDaddy, SiteGround, Kinsta, WP Engine, etc.)', 'elementtest-pro' ); ?></span>
									</label>
									<br>
									<label>
										<input
											type="radio"
											name="elementtest_settings[proxy_type]"
											value="custom"
											<?php checked( isset( $settings['proxy_type'] ) ? $settings['proxy_type'] : 'none', 'custom' ); ?>
										>
										<?php esc_html_e( 'Custom header', 'elementtest-pro' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr id="elementtest-custom-header-row" style="<?php echo ( isset( $settings['proxy_type'] ) && 'custom' === $settings['proxy_type'] ) ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="elementtest-proxy-custom-header">
									<?php esc_html_e( 'Custom Header Name', 'elementtest-pro' ); ?>
								</label>
							</th>
							<td>
								<input
									type="text"
									id="elementtest-proxy-custom-header"
									name="elementtest_settings[proxy_custom_header]"
									value="<?php echo esc_attr( isset( $settings['proxy_custom_header'] ) ? $settings['proxy_custom_header'] : '' ); ?>"
									class="regular-text"
									placeholder="HTTP_X_CUSTOM_IP"
								>
								<p class="description">
									<?php esc_html_e( 'The $_SERVER key for the header your proxy sets (e.g., HTTP_X_CUSTOM_IP).', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<script>
		(function() {
			var radios = document.querySelectorAll( 'input[name="elementtest_settings[proxy_type]"]' );
			var customRow = document.getElementById( 'elementtest-custom-header-row' );
			if ( ! radios.length || ! customRow ) return;
			radios.forEach( function( r ) {
				r.addEventListener( 'change', function() {
					customRow.style.display = this.value === 'custom' ? '' : 'none';
				} );
			} );
		})();
		</script>

		<!-- ============================================================
		     GA4 Integration (Future)
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Google Analytics 4 Integration', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Enable GA4 Events', 'elementtest-pro' ); ?>
							</th>
							<td>
								<label for="elementtest-ga4-enabled">
									<input
										type="checkbox"
										id="elementtest-ga4-enabled"
										name="elementtest_settings[ga4_enabled]"
										value="1"
										<?php checked( ! empty( $settings['ga4_enabled'] ) ); ?>
									>
									<?php esc_html_e( 'Send variant views and conversions as custom GA4 events', 'elementtest-pro' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Requires an active GA4 tag (gtag.js) on your site. Events will be sent as elementtest_variant_viewed and elementtest_converted.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="elementtest-ga4-measurement-id">
									<?php esc_html_e( 'Measurement ID', 'elementtest-pro' ); ?>
								</label>
							</th>
							<td>
								<input
									type="text"
									id="elementtest-ga4-measurement-id"
									name="elementtest_settings[ga4_measurement_id]"
									value="<?php echo esc_attr( isset( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '' ); ?>"
									class="regular-text"
									placeholder="G-XXXXXXXXXX"
								>
								<p class="description">
									<?php esc_html_e( 'Your GA4 Measurement ID. Only needed if you want to verify the tag is present.', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Save Settings', 'elementtest-pro' ) ); ?>

	</form>

</div>
