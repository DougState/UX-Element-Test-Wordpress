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
- Admin-only forced-variant preview URLs for QA
- WP-CLI repair command for legacy JS/CSS variant source mangling
- Optional GA4 custom event forwarding for variant conversions (fires `elementtest_converted` to your existing `gtag` tag in parallel with the plugin's DB tracking)

## Developer and QA Workflows

### Previewing a specific variant as an admin

Logged-in admins can force the frontend assignment endpoint to return a specific variant for visual QA without waiting on random traffic allocation:

```text
https://example.com/product/my-page/?et_force=control
https://example.com/product/my-page/?et_force=123
```

- `control` selects the control variant for every running test on the page.
- A numeric value selects the matching `wp_elementtest_variants.variant_id` for that test.
- The override is gated by `current_user_can( 'manage_options' )`; anonymous visitors and non-admin users ignore the query parameter and stay on the normal weighted assignment path.
- The request still goes through `elementtest_get_variant_assignment`, page-scope validation, the normal `elementtest_variant_<test_id>` browser cookie, and the HttpOnly assignment-proof cookie. Use a clean browser/profile when you need to test organic random assignment after forcing variants.
- Production admin preview traffic can still create impressions through the normal tracking flow. Prefer staging for repeated QA passes, or account for admin visits when reviewing report data.

### Repairing pre-2.4.2 JS/CSS variant source

Before 2.4.2, variant `changes` were sanitized with `wp_kses_post()` regardless of test type. For `js` and `css` tests this could encode operators and selectors (`>=` to `&gt;=`, `&&` to `&amp;&amp;`, `.a > .b` to `.a &gt; .b`) so variants either threw JavaScript syntax errors or CSS silently failed to match.

Use the WP-CLI repair command to inspect and optionally decode affected rows:

```bash
# Dry-run scan of all JS/CSS variants. No database writes.
wp elementtest fix-variant-changes

# Show compact diffs for JavaScript variants only.
wp elementtest fix-variant-changes --type=js --show-diff

# Repair with a JSON backup of original rows.
wp elementtest fix-variant-changes --apply \
  --backup=variants-backup-$(date +%Y%m%d).json

# Limit the repair to one test.
wp elementtest fix-variant-changes --test-id=6 --apply --backup=t6.json
```

The command decodes only the five entity fingerprints produced by the old sanitizer (`&amp;`, `&lt;`, `&gt;`, `&quot;`, `&#039;`/`&#39;`). Other named entities such as `&nbsp;` and `&copy;` are left intact because they may be intentional inside HTML strings.

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
- **`revenue_value` is a custom metric, not GA4's standard ecommerce revenue.** GA4's ecommerce reports look at the `value` + `currency` parameters on event names like `purchase`. The plugin uses `revenue_value` to match the plugin's own database field. Register it as a custom metric (above) and build explorations for revenue analysis.
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

### How forwarded IP headers are trusted

Forwarded headers (`X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP`, or your custom header) are spoofable by any client that connects to WordPress directly, so ElementTest only honors them when the **direct connection** (`REMOTE_ADDR`) comes from a trusted proxy:

- **Cloudflare** — trusts requests arriving from Cloudflare's published edge IP ranges.
- **Nginx / Managed Hosting** — trusts requests arriving from loopback and private (RFC1918) ranges, which covers same-host and internal-network reverse proxies.
- **Custom** — ships no trusted ranges by default; you must declare the IP(s) your proxy connects from (see below).

When the direct connection is **not** a trusted proxy, forwarded headers are ignored and the direct connection IP is used. A request that bypasses the proxy and hits `admin-ajax.php` directly therefore cannot forge its IP to evade rate limiting or deduplication.

### Advanced: customizing trusted proxies

Two filters let you override the defaults for unusual setups.

`elementtest_trusted_proxy_headers` — the `$_SERVER` keys to read the real client IP from:

```php
add_filter( 'elementtest_trusted_proxy_headers', function () {
    return array( 'HTTP_CF_CONNECTING_IP' );
} );
```

`elementtest_trusted_proxy_cidrs` — the IP ranges your proxy connects **from**. Add this if your proxy (or load balancer) reaches WordPress from a public IP not covered by the presets above; otherwise its forwarded headers will be ignored:

```php
add_filter( 'elementtest_trusted_proxy_cidrs', function ( $cidrs ) {
    $cidrs[] = '203.0.113.0/24'; // Your load balancer's egress range.
    return $cidrs;
} );
```

Both IPv4 and IPv6 CIDRs are supported. A bare IP (no `/`) is treated as a single host.

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