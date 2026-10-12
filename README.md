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

### Visual element selector proxy

The visual selector is an admin-only authoring tool, not part of public test delivery. When an admin clicks **Select Element**, `assets/js/admin.js` opens `assets/js/element-selector.js`, which loads the target page through `wp_ajax_elementtest_proxy_page` in a full-screen iframe so the injected selector script can read the page DOM and return a CSS selector.

Selector proxy request flow:

1. The iframe URL is `admin-ajax.php?action=elementtest_proxy_page&nonce=...&url=...`.
2. `includes/class-ajax-handler.php::proxy_page()` verifies the admin nonce and `manage_options` capability through the shared admin AJAX guard.
3. The proxy fetches only same-site `home_url()` hosts over `http`/`https` on ports 80, 443, or the configured home URL port.
4. Only `wordpress_logged_in_*` cookies are forwarded, redirects are disabled, and 3xx responses are rejected before any body is rendered.
5. The returned HTML is stripped of `<script>`/`<noscript>` blocks, inline event handlers, and `javascript:`/`vbscript:`/`data:text/html` navigation URLs.
6. The proxy injects `assets/js/selector-inject.js`, emits a CSP limited to the plugin's selector script path, and serves the iframe with `X-Frame-Options: SAMEORIGIN`.

The iframe sandbox is intentionally `sandbox="allow-scripts"` without `allow-same-origin`. That gives the proxied page an opaque origin, preventing residual page script from reading `window.parent.elementtestAdmin` or reusing the admin nonce/session for privileged AJAX calls. Because opaque-origin frames cannot be addressed with a stable origin, `selector-inject.js` uses `postMessage(..., '*')`; the parent accepts only ElementTest selector messages while the overlay is open.

Operational constraints:

- Do not add `allow-same-origin` to the selector iframe. Combining it with `allow-scripts` would undo the admin isolation.
- Pages that depend on their original client-side JavaScript may not fully hydrate in the selector because page scripts and inline handlers are stripped. Use a server-rendered URL or paste a manual CSS selector for those pages.
- Off-site URLs, unexpected ports, and redirects fail closed by design. Open the final same-site URL you expect to test.
- Keep `elementtest_proxy_sslverify` enabled in production. Disable it only for controlled local/self-signed development environments.

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

## Conversion Goal Runtime Guide

Conversion listeners are registered in `assets/js/frontend.js` only after the browser receives a server-authoritative variant assignment from `elementtest_get_variant_assignment`. Public conversion writes then pass through `includes/class-ajax-handler.php::track_conversion()`, which rechecks the running test, variant, conversion goal, signed page context, and assignment-proof cookie before inserting a row in `wp_elementtest_events`.

Goal-type behavior and constraints:

- **Click** and **Form Submit** goals use delegated document listeners, so elements added after page load can still match. Use stable CSS selectors that match the final clickable element or form. Tracking uses `navigator.sendBeacon` when available so navigation-heavy goals have a chance to write before the page unloads.
- **Page View** goals compare paths case-insensitively after trimming trailing slashes and the WordPress home-path prefix. Triggers with `?` or `#` require exact browser URL or relative URL matches. Trailing `*` wildcards enforce path boundaries, so `/shop/*` matches `/shop/item` but not `/shopping`.
- **Custom Event** goals can be fired by dispatching the saved DOM event name or by using the public helper:

  ```js
  window.elementtest.convert('purchase_complete', 49.99);
  // Equivalent event form for a goal whose trigger is "purchase_complete":
  document.dispatchEvent(new CustomEvent('purchase_complete', {
    detail: { revenue: 49.99 }
  }));
  ```

  The server does not trust browser-supplied revenue by default. It records the conversion goal's saved `revenue_value` unless the site explicitly opts in:

  ```php
  add_filter( 'elementtest_allow_public_custom_event_revenue', function ( $allow, $test_id, $conversion_id ) {
      return 42 === (int) $test_id;
  }, 10, 3 );
  ```

  When dynamic custom-event revenue is enabled, values are clamped to `elementtest_max_revenue` (default `10000.00`).

- **Video Play** goals watch YouTube iframes only. The frontend adds `enablejsapi=1` when needed, sends the YouTube listening handshake, and records a conversion when the player reports state `1` (playing). Leave the selector blank to watch all YouTube iframes on the tested page, or provide a container selector to scope the listener.
- **Add to Cart (WooCommerce)** goals listen for WooCommerce AJAX add-to-cart events, single-product add-to-cart button clicks, cart form submissions, and `wc_cart_button_updated`. Optional selectors scope the goal to a container/button. The server stores product metadata when present and deduplicates add-to-cart conversions within the 60-second window by product identity, so quick adds of different products can still count separately.

Operational notes:

- Direct POSTs to conversion endpoints are expected to fail unless they include the current server-rendered `page_context` and the visitor already holds the matching HttpOnly assignment-proof cookie.
- A conversion can fire only once per `test_id` + `conversion_id` per page load in the browser; the server also suppresses duplicate conversions for the same visitor/test/variant/goal within 60 seconds.
- Cross-page Page View goals still require a prior source-page visit so the visitor has assignment proof for the test before the destination page records the conversion.

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
- **Nginx / Managed Hosting** — enables reading `X-Real-IP` / `X-Forwarded-For`, but ships **no default trusted CIDRs** (loopback/RFC1918 defaults were unsafe on common php-fpm setups where every request appears as `127.0.0.1`). Add your proxy's egress range with `elementtest_trusted_proxy_cidrs` or forwarded headers stay ignored.
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

`elementtest_trusted_proxy_cidrs` — the IP ranges your proxy connects **from**. Required for the Nginx and Custom presets (and for Cloudflare if you terminate TLS on your own edge). Example for a load balancer that reaches WordPress from a known egress range:

```php
add_filter( 'elementtest_trusted_proxy_cidrs', function ( $cidrs ) {
    $cidrs[] = '203.0.113.0/24'; // Your load balancer's egress range.
    return $cidrs;
} );
```

If PHP-FPM only accepts connections from Nginx on the same host **and** the web server is locked down so clients cannot reach PHP directly, you may include loopback explicitly (this is no longer the plugin default):

```php
add_filter( 'elementtest_trusted_proxy_cidrs', function ( $cidrs ) {
    $cidrs[] = '127.0.0.1/32';
    $cidrs[] = '::1/128';
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