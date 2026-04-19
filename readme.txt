=== ElementTest Pro ===
Contributors: Doug Wagner
Tags: ab-testing, split-testing, conversion, optimization, analytics
Requires at least: 5.6
Tested up to: 6.7
Stable tag: 2.3.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A/B test various elements of your WordPress pages and track conversion data to measure performance.

== Description ==

ElementTest Pro allows you to A/B test various elements (CSS, copy, JS, images) of your WordPress pages and includes conversion data to measure performance when they are tested against each other.

**Features:**

* Visual element selector
* CSS styling variations
* Text/copy changes
* Image swaps
* JavaScript behavior modifications
* Conversion tracking with multiple goal types (click, pageview, form submit, custom event)
* Cross-page pageview goal tracking
* Performance analytics
* Statistical significance calculator
* JSON import/export for test portability
* Report export (HTML and CSV) with WP-CLI support

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/elementtest-pro` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the ElementTest menu in the admin sidebar to configure the plugin

== Frequently Asked Questions ==

= Does this work with page builders? =

Yes, ElementTest Pro is designed to work with popular page builders like Elementor, Beaver Builder, and Divi.

= Where is the data stored? =

All testing data is stored in your WordPress database. No external services are used.

== Changelog ==

= 2.3.6 =
* Security: Close unauthenticated DB write amplification / DoS on public tracking endpoints (Issue #31). Test/variant/conversion-goal/page-scope validation now runs BEFORE the per-(IP, test_id, event) rate-limit write, so a rotating `test_id` attack can no longer fan out transients or DB lookups. A new per-IP cap on invalid tracking requests fires at the top of every public tracking endpoint, keyed on IP only (one transient per IP regardless of how many attacker-controlled parameters get rotated). Filter `elementtest_invalid_request_cap` (default 30/hour) tunes the threshold. Affects `track_impression`, `track_conversion`, and `get_variant_assignment`.

= 2.3.5 =
* Security: Harden HTML report export against stored XSS. `wp_json_encode()` now emits with `JSON_HEX_TAG` so `<` and `>` are escaped inside the inline `<script>` block, preventing `</script>` breakout via test/variant/goal names.
* Fix: HTML report charts now degrade gracefully when the Chart.js CDN is unreachable. Added a `typeof Chart === 'undefined'` guard that hides `.chart-card` containers and returns early instead of throwing `ReferenceError`.

= 2.3.4 =
* New: HTML report export now includes a visual dashboard powered by Chart.js — 5 charts (daily conversion rate, cumulative conversions, overall conversion rate, goal breakdown, daily traffic split) in addition to the existing data tables
* Chart.js loads from jsDelivr CDN; if the CDN is blocked or unavailable the report falls back cleanly to data tables only
* Charts are hidden in the print stylesheet so printed reports stay clean

= 2.3.3 =
* New: `--format=json` option for `wp elementtest export` and `wp elementtest export_all` CLI commands — enables downstream tooling (e.g. external dashboards) to consume raw report data

= 2.3.1 =
* Fix: Add-to-cart conversion not tracking for CSS variants — switch click handler to capture phase and add form submit backup strategy

= 2.3.0 =
* New: Export A/B test results as standalone HTML reports or CSV files for offline analysis and stakeholder sharing
* New: WP-CLI commands `wp elementtest export` and `wp elementtest export-all` for server-side report generation (SSH/SCP workflow)
* New: "Export HTML" and "Export CSV" buttons on the test results page for single-test download
* New: "Export All Reports" buttons on the tests list page with zip bundling when ZipArchive is available

= 2.2.6 =
* Fix: Enforce path boundary in wildcard conversion URL matching so `/shop/*` no longer incorrectly matches `/shopping` or `/shop-archive`
* Fix: Rate limiting now works correctly with external object caches (Redis, Memcached) by storing counter and expiration together inside the transient value

= 2.2.5 =
* Fix: Resolve add-to-cart button display regression on WooCommerce variable product pages caused by timing conflict between anti-flicker CSS and WooCommerce's variation lifecycle

= 2.2.4 =
* Security: Default `get_visitor_ip()` to `REMOTE_ADDR` only; proxy forwarding headers are no longer trusted unless explicitly enabled via the `elementtest_trusted_proxy_headers` filter (Issue #23)
* New: Admin settings UI for selecting a reverse proxy / CDN preset (Cloudflare, Nginx / Managed Hosting, or custom header)
* New: Activation banner prompting users to configure their hosting setup for accurate visitor tracking
* Fix: Prefer `X-Real-IP` over `X-Forwarded-For` for the Nginx proxy preset
* Fix: Normalize hyphens to underscores in the custom proxy header name to match PHP `$_SERVER` key format

= 2.2.1 =
* Fix: Rate limiter transient TTL was reset on every counter increment, turning the fixed hourly window into a sliding counter that never expired under sustained traffic

= 2.2.0 =
* Security: Stop unauthenticated analytics forgery by computing visitor identity server-side instead of trusting client-supplied hashes
* Security: Add per-IP rate limiting for impression and conversion tracking to reduce event write amplification
* Security: Make stored goal revenue authoritative for standard conversions and clamp dynamic custom-event revenue
* Fix: Keep frontend and AJAX visitor hashing aligned through the shared `ElementTest_Visitor` utility

= 2.1.2 =
* Security: Harden proxy requests by restoring SSL verification and limiting forwarded cookies
* Security: Add `Secure` cookie support on HTTPS sites and restrict admin selector messaging to the current origin
* Fix: Replace deprecated time usage in the tests list and tighten several validation and response paths
* UX: Update compatibility metadata for modern WordPress and PHP versions

= 2.1.0 =
* Fix: Scope WooCommerce add-to-cart goals to the test page to avoid cross-page false positives
* Fix: Preserve distinct add-to-cart conversions inside the deduplication window when product identity changes
* Fix: Prevent false deduplication when product identity is unavailable during WooCommerce tracking

= 2.0.1 =
* Fix: Prevent scoped WooCommerce add-to-cart goals from firing when the triggering button cannot be verified

= 2.0.0 =
* New: Add WooCommerce add-to-cart conversion goals for single-product and AJAX add-to-cart flows
* New: Capture WooCommerce product metadata with conversion events for reporting and debugging
* UX: Add admin controls for configuring add-to-cart goals in the test editor

= 1.1.0 =
* New: JSON import/export for A/B tests — export test configurations (with variants and conversion goals) as JSON files and import them to recreate tests on any site
* New: "Import Tests", "Export Selected", and "Export All" buttons on the tests list page
* New: Per-row "Export" action and checkbox multi-select for bulk operations
* New: Cross-page pageview goal tracking — pageview goals now fire on the target URL even when the test is configured on a different page
* Fix: Conversion tracking end-to-end — resolved 6 issues preventing conversions from recording correctly
* Fix: Server-side 60-second deduplication window for conversions to prevent duplicate records
* Fix: Settings cookie_days value now correctly propagates to the frontend script
* Fix: GMT timestamps used consistently across all AJAX methods (duplicate, toggle status, import) — prevents sort-order drift on non-UTC sites
* Fix: Imported variant traffic percentage clamped to 0-100 range
* Fix: Variant ID type coercion for reliable cross-page comparison
* Fix: Custom event API documentation corrected to show `window.elementtest.convert()` syntax
* Fix: Robust NULL handling for conversion_id in event tracking
* Fix: DB error details now surfaced in AJAX error responses for easier debugging

= 1.0.0 =
* Initial release
* Database schema setup
* Admin interface foundation
