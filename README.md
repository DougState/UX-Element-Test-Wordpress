# ElementTest Pro

A/B test various elements (CSS, copy, JS, images) of your WordPress pages and track conversion data to measure performance.

Release history lives in `readme.txt` for WordPress.org and is mirrored in `CHANGELOG.md` for GitHub readers.

## Installation

1. Upload the `elementtest-pro` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'ElementTest' in the WordPress admin menu to get started

## Releases

Ready-to-install zip builds are published on [GitHub Releases](https://github.com/DougState/UX-Element-Test-Wordpress/releases). To install from source, upload the repository folder as a plugin (see Installation above).

## Features

- A/B testing for page elements (CSS, copy, JavaScript, image variants)
- Visual element selector
- Traffic allocation with auto-balance
- Conversion tracking (click, page view, form submit, custom event, YouTube video play, WooCommerce add-to-cart)
- Wildcard URL matching for purchase/order-received page goals
- JSON import/export of test configurations
- Performance analytics with statistical significance
- Schedule-based test start/stop
- Export reports as standalone HTML (with Chart.js visual dashboard), CSV, or JSON via admin UI or WP-CLI
- Optional GA4 custom event forwarding for variant conversions (fires `elementtest_converted` to your existing `gtag` tag in parallel with the plugin's DB tracking)

## Database Tables

The plugin creates the following tables (prefixed with the WordPress table prefix):


| Table                        | Purpose                      |
| ---------------------------- | ---------------------------- |
| `wp_elementtest_tests`       | Test configurations          |
| `wp_elementtest_variants`    | Variant definitions per test |
| `wp_elementtest_events`      | User interaction tracking    |
| `wp_elementtest_conversions` | Conversion goal definitions  |


## Conversion Goal Scoping

All conversion goals -- including **Add to Cart (WooCommerce)** -- are scoped to the page the test is running on. The plugin does **not** inject scripts or track conversions on other pages.

If a test runs on `/products/my-product/`, only add-to-cart events on that page are tracked. Add-to-cart events on `/shop/`, `/cart/`, or other product pages are not captured. This is by design: it avoids injecting the frontend script site-wide, eliminates unnecessary database queries on every WooCommerce page load, and prevents timing conflicts between the plugin's anti-flicker CSS and WooCommerce's variation lifecycle JS.

Cross-page conversion tracking is supported only for **Page View** goals (e.g. tracking a thank-you page visit as a conversion for a test running on a different page).

## GA4 Integration

ElementTest Pro can forward variant conversions to Google Analytics 4 as custom events alongside the plugin's own database tracking. Enable it under **ElementTest → Settings → Google Analytics 4 Integration**. Full operator's guide also ships inside the plugin at **ElementTest → GA4**.

### What it sends

When the **Enable GA4 Events** setting is on AND a `gtag.js` tag is already loaded on the page (the plugin does NOT load `gtag.js` itself — it piggybacks on WooCommerce GA Pro, Site Kit by Google, Google Tag Manager, or whatever else loaded gtag on your front-end), every variant conversion fires:

```js
gtag('event', 'elementtest_converted', {
  test_id:        42,
  test_name:      "Blue button headline",
  variant_id:     7,
  variant_name:   "Variant B",
  revenue_value:  9.99,
  transport_type: 'beacon'   // survives the immediate page nav on click / form-submit goals
});
```

Conversions only in 2.5.x. Variant impression forwarding (`elementtest_variant_viewed`) is tracked for a future release.

### Three things people often conflate

GA4 has three separate concepts that all need to line up before variant data is useful in your reports. Treat them in this order.

#### 1. Confirming the event arrives at GA4

- **DebugView (immediate, debug sessions only):** install the GA Debugger browser extension or add `?gtm_debug=1` to the URL, then trigger a conversion. **GA4 → Admin → DebugView** shows the event within seconds.
- **Realtime (immediate, any session):** **GA4 → Reports → Realtime**. Trigger the conversion, look for `elementtest_converted` in the "Event count by Event name" card within ~30 seconds. Easiest end-to-end test.
- **Standard Events report (24-48 hour delay):** **GA4 → Reports → Engagement → Events**. The event name appears automatically once GA4 has processed enough data.

#### 2. Marking it as a Key Event (formerly "Conversion")

Makes GA4 count `elementtest_converted` as a goal in conversion reports.

1. Wait until `elementtest_converted` appears under **GA4 → Admin → Events** (1-2 days after the first event fires).
2. Toggle the **Mark as key event** star on its row.

Or preemptively: **GA4 → Admin → Key events → Create** and add `elementtest_converted` by hand.

#### 3. Seeing the parameters as report columns (custom dimensions)

The event parameters (`test_id`, `test_name`, `variant_id`, `variant_name`, `revenue_value`) show up in DebugView and Realtime **immediately**, but they will NOT appear as columns / breakdowns / dimensions in standard GA4 reports until you register them as custom dimensions.

**GA4 → Admin → Custom definitions → Create custom dimensions** for each parameter you care about:


| Dimension name    | Scope                                            | Event parameter |
| ----------------- | ------------------------------------------------ | --------------- |
| Variant Test ID   | Event                                            | `test_id`       |
| Variant Test Name | Event                                            | `test_name`     |
| Variant ID        | Event                                            | `variant_id`    |
| Variant Name      | Event                                            | `variant_name`  |
| Variant Revenue   | Event (use the **Metric** option, not Dimension) | `revenue_value` |


Register `revenue_value` as a **custom metric**, not a dimension — that way GA4 sums it across events for revenue totals. The other four are dimensions (slice / filter).

After you register them, expect **another 24-48 hour delay** before existing event history retroactively populates the new columns. Going forward, every new event populates them in near-realtime.

### Known limitations

- **Client-side only.** Visitors who block `gtag.js` (ad-blockers, strict privacy browsers, denied consent) won't generate GA4 events. The plugin's own dashboard remains the source of truth for conversion counts. GA4 numbers will be lower than plugin DB numbers.
- **The plugin does NOT load `gtag.js`.** It piggybacks on an existing GA4 tag. If your site has no GA4 tag, this feature is a no-op.
- `**revenue_value` is a custom metric, not GA4's standard ecommerce revenue.** GA4's ecommerce reports look at the `value` + `currency` parameters on event names like `purchase`. The plugin uses `revenue_value` to match the plugin's own database field. Register it as a custom metric (above) and build explorations for revenue analysis.
- **PII rule.** GA4 explicitly disallows personally-identifiable info (emails, names) in event parameter values. Test names and variant names are sent verbatim — don't include PII. The plugin surfaces a warning next to the Test Name and Variant Name input fields when GA4 forwarding is on.

### Verifying the wire-up

The Settings page has a "Run diagnostic" button, but it only tests gtag in the `wp-admin` context — most sites only load gtag.js on the front-end, so admin-context typically reports "not available" even when GA4 is correctly configured. Use the front-end console snippet documented at **ElementTest → GA4** to verify front-end wiring.

## Reverse Proxy / CDN Setup

ElementTest uses visitor IP addresses for rate limiting and deduplication. After activation, a banner prompts you to select your hosting setup under **ElementTest > Settings > Reverse Proxy / CDN**.


| Hosting setup                                                  | Setting                                         |
| -------------------------------------------------------------- | ----------------------------------------------- |
| Cloudflare                                                     | **Cloudflare**                                  |
| Managed hosting (GoDaddy, SiteGround, Kinsta, WP Engine, etc.) | **Nginx / Managed Hosting**                     |
| Dedicated / self-managed server (Apache only, no proxy)        | **None**                                        |
| Not sure                                                       | **Nginx / Managed Hosting** (falls back safely) |


If you manage your own server and want to verify, check via SSH:

```bash
# Check if Nginx is running as a reverse proxy:
systemctl status nginx

# See what is listening on ports 80/443:
ss -tlnp | grep -E ':80|:443'
```

If only Apache is listening on 80/443, select **None**. If Nginx is on 80/443 with Apache on a backend port, select **Nginx / Managed Hosting**.

## Report Export (WP-CLI)

Export test reports from the command line for offline analysis or stakeholder sharing:

```bash
# Single test — HTML with charts, CSV, or JSON
wp elementtest export 42 --format=html --output=/tmp/report.html
wp elementtest export 42 --format=csv
wp elementtest export 42 --format=json

# All non-draft tests
wp elementtest export-all --format=html --output=/tmp/reports/
wp elementtest export-all --format=csv
wp elementtest export-all --format=json
```

The HTML export includes a visual dashboard with five Chart.js charts (daily conversion rate, cumulative conversions, overall conversion rate, goal breakdown, daily traffic split) plus full data tables. Charts load from the jsDelivr CDN; data tables print cleanly when Chart.js is unavailable.

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## License

GPL v2 or later