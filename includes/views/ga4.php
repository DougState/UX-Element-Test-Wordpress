<?php
/**
 * Template: GA4 Setup Guide page.
 *
 * Operator's guide for the GA4 custom event forwarding feature added in 2.5.1.
 * Explains what the integration sends, how to register custom dimensions in
 * the GA4 admin so the event parameters become report columns, how to mark
 * `elementtest_converted` as a key event, where each piece of data shows up
 * (DebugView, Realtime, standard reports with their 24-48hr delay), and how
 * to verify the wire-up via the browser console.
 *
 * Pure static docs page. The actual on/off toggle for the feature lives in
 * `settings.php`; this file is read-only guidance.
 *
 * Expected variables:
 *   $settings  array  Current settings from get_option('elementtest_settings').
 *
 * @package ElementTestPro
 * @since   2.5.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template is included from within a class method; variables are function-scoped, not global.

// Defense-in-depth: the menu is registered with manage_options, but a future
// include path (network admin, shortcode preview, REST handler) should still
// refuse to render the guide to non-admins.
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$settings           = isset( $settings ) ? $settings : array();
$ga4_enabled        = ! empty( $settings['ga4_enabled'] );
$ga4_measurement_id = isset( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '';
$settings_url       = admin_url( 'admin.php?page=elementtest-settings' );
?>

<div class="wrap elementtest-wrap">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'GA4 Setup Guide', 'elementtest-pro' ); ?></h1>
	<hr class="wp-header-end">

	<p class="description" style="margin:8px 0 20px;font-size:14px;">
		<?php esc_html_e( 'Operator\'s guide for the GA4 custom event forwarding feature. This page is read-only documentation. The actual on/off toggle and Measurement ID field live on the', 'elementtest-pro' ); ?>
		<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Settings page', 'elementtest-pro' ); ?></a><?php esc_html_e( '.', 'elementtest-pro' ); ?>
	</p>

	<div class="elementtest-admin-wrapper">

		<!-- ============================================================
		     Current status
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Current status', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'GA4 forwarding', 'elementtest-pro' ); ?></th>
							<td>
								<?php if ( $ga4_enabled ) : ?>
									<span style="color:#46b450;font-weight:600;">&#10003; <?php esc_html_e( 'Enabled', 'elementtest-pro' ); ?></span>
								<?php else : ?>
									<span style="color:#8a8a8a;">&#9675; <?php esc_html_e( 'Disabled', 'elementtest-pro' ); ?></span>
									&mdash;
									<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'enable it on the Settings page', 'elementtest-pro' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Measurement ID', 'elementtest-pro' ); ?></th>
							<td>
								<?php if ( ! empty( $ga4_measurement_id ) ) : ?>
									<code><?php echo esc_html( $ga4_measurement_id ); ?></code>
								<?php else : ?>
									<span style="color:#8a8a8a;"><?php esc_html_e( '(none saved)', 'elementtest-pro' ); ?></span>
								<?php endif; ?>
								<p class="description">
									<?php esc_html_e( 'The Measurement ID is only used to label this configuration and to verify your gtag.js setup. The plugin does NOT load gtag.js itself; it piggybacks on the existing GA4 tag on your site (e.g. one loaded by WooCommerce Google Analytics Pro, Site Kit by Google, Google Tag Manager, or a theme snippet).', 'elementtest-pro' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- ============================================================
		     What this feature sends
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'What this feature sends to GA4', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<p>
					<?php esc_html_e( 'When the GA4 setting is on AND a gtag.js tag is loaded on the page, every variant conversion fires a custom GA4 event in parallel with the existing plugin-DB record:', 'elementtest-pro' ); ?>
				</p>
				<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;font-family:Menlo,Consolas,monospace;font-size:13px;line-height:1.55;overflow-x:auto;">gtag('event', 'elementtest_converted', {
  test_id:        42,
  test_name:      "Blue button headline",
  variant_id:     7,
  variant_name:   "Variant B",
  revenue_value:  9.99,
  transport_type: 'beacon'
});</pre>
				<p>
					<strong><?php esc_html_e( 'Conversion-only in 2.5.x.', 'elementtest-pro' ); ?></strong>
					<?php esc_html_e( 'Variant impression forwarding (elementtest_variant_viewed) is planned for a future release.', 'elementtest-pro' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'transport_type: \'beacon\' is the load-bearing detail.', 'elementtest-pro' ); ?></strong>
					<?php esc_html_e( 'It parallels the navigator.sendBeacon used by the plugin\'s conversion AJAX, so the GA4 event survives the immediate page navigation that happens on click and form-submit conversion goals (gtag\'s default transport would otherwise drop a large fraction of those events).', 'elementtest-pro' ); ?>
				</p>
			</div>
		</div>

		<!-- ============================================================
		     Three things people often conflate
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Three things people often conflate', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<p>
					<?php esc_html_e( 'GA4 has three separate concepts that all need to line up before variant data is useful in your reports. Treat them in this order.', 'elementtest-pro' ); ?>
				</p>

				<h3 style="margin-top:20px;"><?php esc_html_e( '1. Confirming the event arrives at GA4', 'elementtest-pro' ); ?></h3>
				<ul style="list-style:disc;margin-left:20px;">
					<li>
						<strong>DebugView (immediate, but only for debug sessions):</strong>
						<?php esc_html_e( 'Install the GA Debugger browser extension (Chrome / Firefox) or add ?gtm_debug=1 to the URL, reload, then trigger the conversion. The event appears in', 'elementtest-pro' ); ?>
						<strong><?php esc_html_e( 'GA4 → Admin → DebugView', 'elementtest-pro' ); ?></strong>
						<?php esc_html_e( 'within seconds.', 'elementtest-pro' ); ?>
					</li>
					<li>
						<strong>Realtime (immediate, any session):</strong>
						<?php esc_html_e( 'GA4 → Reports → Realtime. Trigger the conversion in any browser tab, then look for elementtest_converted in the "Event count by Event name" card within ~30 seconds. This is the easiest end-to-end test.', 'elementtest-pro' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Standard Events report (24-48 hour delay):', 'elementtest-pro' ); ?></strong>
						<?php esc_html_e( 'GA4 → Reports → Engagement → Events. The event name appears automatically once GA4 has processed enough data. New event names take a day or two to first show up here.', 'elementtest-pro' ); ?>
					</li>
				</ul>

				<h3 style="margin-top:24px;"><?php esc_html_e( '2. Marking it as a Key Event (formerly "Conversion")', 'elementtest-pro' ); ?></h3>
				<p>
					<?php esc_html_e( 'This is what makes GA4 count elementtest_converted as a goal in conversion reports, the same way purchase or sign_up are typically counted.', 'elementtest-pro' ); ?>
				</p>
				<ol style="list-style:decimal;margin-left:20px;">
					<li>
						<?php esc_html_e( 'Wait until elementtest_converted shows up under', 'elementtest-pro' ); ?>
						<strong><?php esc_html_e( 'GA4 → Admin → Events', 'elementtest-pro' ); ?></strong>
						<?php esc_html_e( '(1-2 days after the first event fires).', 'elementtest-pro' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Toggle the "Mark as key event" star on its row.', 'elementtest-pro' ); ?>
					</li>
				</ol>
				<p>
					<?php esc_html_e( 'You can also do this preemptively from', 'elementtest-pro' ); ?>
					<strong><?php esc_html_e( 'GA4 → Admin → Key events → Create', 'elementtest-pro' ); ?></strong>
					<?php esc_html_e( 'if you want it counted from the moment the first event lands.', 'elementtest-pro' ); ?>
				</p>

				<h3 style="margin-top:24px;"><?php esc_html_e( '3. Seeing the parameters as report columns (custom dimensions)', 'elementtest-pro' ); ?></h3>
				<p>
					<?php esc_html_e( 'The event parameters (test_id, test_name, variant_id, variant_name, revenue_value) show up in DebugView and Realtime', 'elementtest-pro' ); ?>
					<em><?php esc_html_e( 'immediately', 'elementtest-pro' ); ?></em><?php esc_html_e( ', but they will NOT appear as breakdowns / columns / dimensions in standard GA4 reports until you register them as custom dimensions.', 'elementtest-pro' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'GA4 → Admin → Custom definitions → Create custom dimensions', 'elementtest-pro' ); ?></strong>
					<?php esc_html_e( 'for each parameter you care about:', 'elementtest-pro' ); ?>
				</p>
				<table class="wp-list-table widefat striped" style="margin:12px 0 16px;max-width:800px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Dimension name', 'elementtest-pro' ); ?></th>
							<th><?php esc_html_e( 'Scope', 'elementtest-pro' ); ?></th>
							<th><?php esc_html_e( 'Event parameter', 'elementtest-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Variant Test ID', 'elementtest-pro' ); ?></td><td><?php esc_html_e( 'Event', 'elementtest-pro' ); ?></td><td><code>test_id</code></td></tr>
						<tr><td><?php esc_html_e( 'Variant Test Name', 'elementtest-pro' ); ?></td><td><?php esc_html_e( 'Event', 'elementtest-pro' ); ?></td><td><code>test_name</code></td></tr>
						<tr><td><?php esc_html_e( 'Variant ID', 'elementtest-pro' ); ?></td><td><?php esc_html_e( 'Event', 'elementtest-pro' ); ?></td><td><code>variant_id</code></td></tr>
						<tr><td><?php esc_html_e( 'Variant Name', 'elementtest-pro' ); ?></td><td><?php esc_html_e( 'Event', 'elementtest-pro' ); ?></td><td><code>variant_name</code></td></tr>
						<tr><td><?php esc_html_e( 'Variant Revenue', 'elementtest-pro' ); ?></td><td><?php esc_html_e( 'Event (use the Metric option)', 'elementtest-pro' ); ?></td><td><code>revenue_value</code></td></tr>
					</tbody>
				</table>
				<p style="background:#fbf2dc;border-left:4px solid #8a5a00;padding:10px 14px;margin:8px 0;">
					<strong><?php esc_html_e( 'Note:', 'elementtest-pro' ); ?></strong>
					<?php esc_html_e( 'revenue_value should be registered as a', 'elementtest-pro' ); ?>
					<strong><?php esc_html_e( 'custom metric', 'elementtest-pro' ); ?></strong><?php esc_html_e( ', not a dimension. That way GA4 sums it across events for revenue totals. The other four are dimensions (slice / filter).', 'elementtest-pro' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'After you register them, expect', 'elementtest-pro' ); ?>
					<strong><?php esc_html_e( 'another 24-48 hour delay', 'elementtest-pro' ); ?></strong>
					<?php esc_html_e( 'before existing event history retroactively populates the new columns. Going forward, every new event will populate them in near-realtime.', 'elementtest-pro' ); ?>
				</p>
			</div>
		</div>

		<!-- ============================================================
		     Verifying the wire-up in the browser console
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Verifying the wire-up in the browser console', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<p>
					<?php esc_html_e( 'The Settings page has a "Run diagnostic" button, but that test only checks gtag in the wp-admin context — and most sites only load gtag.js on the front-end, so admin-context will typically report "not available" even when GA4 is correctly configured. To verify the front-end is wired up:', 'elementtest-pro' ); ?>
				</p>
				<ol style="list-style:decimal;margin-left:20px;">
					<li><?php esc_html_e( 'Open any front-end page where a variant test is running.', 'elementtest-pro' ); ?></li>
					<li><?php esc_html_e( 'Open the browser DevTools Console.', 'elementtest-pro' ); ?></li>
					<li><?php esc_html_e( 'Paste the snippet below and press Enter (Firefox may require you to type "allow pasting" first).', 'elementtest-pro' ); ?></li>
					<li><?php esc_html_e( 'Trigger the conversion goal (click whatever element the test\'s goal is configured to watch).', 'elementtest-pro' ); ?></li>
					<li><?php esc_html_e( 'You should see a green [ETP gtag] log line in the console, AND elementtest_converted should appear in GA4 → Reports → Realtime within ~30 seconds.', 'elementtest-pro' ); ?></li>
				</ol>
				<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;font-family:Menlo,Consolas,monospace;font-size:12px;line-height:1.55;overflow-x:auto;margin-top:12px;">(function () {
  const og = window.gtag;
  window.gtag = function () {
    if (String(arguments[1] ?? '').startsWith('elementtest')) {
      console.log('%c[ETP gtag]', 'background:#46b450;color:white;padding:2px 4px', ...arguments);
    }
    // Guard against pages where gtag.js wasn't loaded: if og is undefined,
    // the proxy is still installed (window.gtag is now this function), but
    // calling og.apply() would throw TypeError. Returning undefined matches
    // gtag's normal return shape.
    return og ? og.apply(this, arguments) : undefined;
  };
  const ob = navigator.sendBeacon.bind(navigator);
  navigator.sendBeacon = function (url, data) {
    if (url?.includes('admin-ajax')) {
      const p = data instanceof FormData ? Object.fromEntries(data.entries()) : data;
      if (p?.action === 'elementtest_track_conversion') {
        console.log('%c[ETP conv AJAX]', 'background:#2271b1;color:white;padding:2px 4px', p);
      }
    }
    return ob(url, data);
  };
  console.log('[ETP state]', {
    ga4Enabled: elementtestFrontend.ga4Enabled,
    tests: (elementtestFrontend.tests || []).map(t =&gt; ({ id: t.test_id, name: t.test_name })),
    // Check the ORIGINAL gtag (captured pre-override above), not window.gtag —
    // we already replaced window.gtag with our proxy at this point, so
    // typeof window.gtag would always be 'function' regardless of whether
    // gtag.js was actually loaded.
    has_gtag: typeof og,
  });
  console.log('Proxies installed. Trigger the conversion goal now.');
})();</pre>
			</div>
		</div>

		<!-- ============================================================
		     Known limitations
		     ============================================================ -->
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Known limitations and gotchas', 'elementtest-pro' ); ?></h2>
			</div>
			<div class="inside">
				<ul style="list-style:disc;margin-left:20px;">
					<li>
						<strong><?php esc_html_e( 'Client-side only.', 'elementtest-pro' ); ?></strong>
						<?php esc_html_e( 'Visitors who block gtag.js (ad-blockers, strict privacy browsers, denied consent) will not generate GA4 events. The plugin\'s own dashboard remains the source of truth for conversion counts. GA4 numbers will typically be lower than plugin DB numbers.', 'elementtest-pro' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'The plugin does NOT load gtag.js.', 'elementtest-pro' ); ?></strong>
						<?php esc_html_e( 'It piggybacks on an existing GA4 tag (WooCommerce Google Analytics Pro, Site Kit by Google, Google Tag Manager, etc.). If your site has no GA4 tag, this feature is a no-op.', 'elementtest-pro' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Consent plugins.', 'elementtest-pro' ); ?></strong>
						<?php esc_html_e( 'If a consent plugin blocks gtag.js until consent is granted, the plugin\'s GA4 forwarding is also blocked until that point — by design, since we use window.gtag which routes through whatever consent layer is active.', 'elementtest-pro' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'revenue_value is a custom metric, not GA4\'s standard ecommerce revenue.', 'elementtest-pro' ); ?></strong>
						<?php esc_html_e( 'GA4\'s standard ecommerce revenue parameter is value (paired with currency) on specific event names like purchase. We use revenue_value to match the plugin\'s own database field, which means it won\'t auto-aggregate in GA4\'s ecommerce reports — you need to register it as a custom metric (see step 3 above) and build explorations for revenue analysis.', 'elementtest-pro' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'PII rule.', 'elementtest-pro' ); ?></strong>
						<?php esc_html_e( 'GA4 explicitly disallows personally-identifiable info (emails, names) in event parameter values. Test names and variant names are sent verbatim, so do not include PII in those fields. The plugin shows a warning next to the Test Name and Variant Name input fields when GA4 forwarding is on.', 'elementtest-pro' ); ?>
					</li>
				</ul>
			</div>
		</div>

	</div>
</div>
