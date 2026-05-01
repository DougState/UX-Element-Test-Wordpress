=== ElementTest Pro ===
Contributors: desigstate
Tags: ab-testing, split-testing, conversion, optimization, analytics
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 2.4.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A/B test various elements of your WordPress pages and track conversion data to measure performance.

== Description ==

ElementTest Pro allows you to A/B test various elements (CSS, copy, JS, images) of your WordPress pages and includes conversion data to measure performance when they are tested against each other.

Source code: https://github.com/DougState/UX-Element-Test-Wordpress

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

== Third-Party Libraries ==

This plugin bundles the following third-party library; its source is
included verbatim in `assets/js/` and is loaded only by the standalone
HTML report export so that exported reports render their charts when
opened offline (no remote requests are made by this plugin):

* **Chart.js** v4 — https://www.chartjs.org/ — MIT License — https://github.com/chartjs/Chart.js/blob/master/LICENSE.md

== Frequently Asked Questions ==

= Does this work with page builders? =

Yes, ElementTest Pro is designed to work with popular page builders like Elementor, Beaver Builder, and Divi.

= Where is the data stored? =

All testing data is stored in your WordPress database. No external services are used.

= What happens to my data if I uninstall the plugin? =

Deleting the plugin from the Plugins screen runs the bundled `uninstall.php`, which removes the plugin's options and drops its custom database tables (`{prefix}elementtest_tests`, `{prefix}elementtest_variants`, `{prefix}elementtest_events`, `{prefix}elementtest_conversions`). Deactivating the plugin alone leaves all data in place.

== Screenshots ==

1. Tests list — manage all A/B tests from one screen.
2. Test editor — configure variants, traffic split, and conversion goals.
3. Results dashboard — view conversion rates, statistical significance, and per-variant performance.
4. HTML report export — share standalone reports with stakeholders.

== Upgrade Notice ==

= 2.4.0 =
Security release: hardens the AJAX handler with prepared SQL for dynamic ID lists, stricter proxy URL validation, and non-negative imported goal revenue. Recommended for all users.

= 2.3.9 =
Bug fixes for wildcard pageview goal matching on the frontend and a corrected Plugin URI header.

= 2.3.6 =
Security release: closes an unauthenticated DB write amplification / DoS vector on public tracking endpoints. Recommended for all users.

== Changelog ==

= 2.4.4 =
* Tooling: Admin-only `?et_force=` query-parameter override for variant assignment. Lets logged-in admins (`manage_options`) deterministically preview any variant for QA — `?et_force=control` selects the Control variant of every test on the page, `?et_force=<variant_id>` selects a specific variant by ID. The forced assignment is written to the existing `elementtest_variant_<test_id>` cookie so it sticks across navigation; remove the cookie (or visit the page without the parameter and let it re-roll) to resume normal random assignment. Useful for QA on tests where the existing 50/50 cookie roll keeps producing the same variant on a single tester's browser. Gated server-side via a new `isAdmin` flag in the localized `elementtestFrontend` payload — non-admin visitors cannot bias real test data via shared URLs (anonymous traffic falls through to the normal weighted random path). Logs the forced assignment to `console.info` (or `console.warn` if the parameter does not match any variant) so DevTools makes the override unambiguous.

= 2.4.3 =
* Tooling: New WP-CLI subcommand `wp elementtest fix-variant-changes` for repairing pre-2.4.2 `wp_kses_post()`-mangled `js` and `css` variant source already in the database. The 2.4.2 fix only stopped *new* saves from being mangled; rows already in `wp_elementtest_variants` stayed corrupted (`>=` stored as `&gt;=`, `&&` as `&amp;&amp;`, `.parent > .child` selectors as `.parent &gt; .child`). The command JOINs `wp_elementtest_variants` to `wp_elementtest_tests`, filters to `test_type` in (`css`, `js`), and decodes only the five HTML entities `wp_kses_post()` produces from JS/CSS tokens (`&amp;`, `&lt;`, `&gt;`, `&quot;`, `&#039;`) — leaving named entities like `&middot;` or `&nbsp;` intact. Defaults to dry-run; `--apply` writes; `--backup=path.json` snapshots affected rows before any UPDATE; `--show-diff` prints up to 10 changed line pairs per variant; `--type=js|css` and `--test-id=N` narrow the scan.

= 2.4.2 =
* Fix: JavaScript variant `changes` source is no longer mangled on save. The plugin previously applied `wp_kses_post()` uniformly to the `changes` column for every test type, but `changes` is polymorphic — it holds CSS rules, HTML, JavaScript source, or an image URL depending on `test_type`. Running JS source through `wp_kses_post()` parses it as HTML, rebalances/strips `<`, `>`, and `&` (e.g. operators like `>=`, string literals containing `<div>...</div>`, or `&middot;` entities), and produces source that throws `SyntaxError` at parse time when the variant's `<script>` is appended. Sanitization is now branched on `test_type` via a new `sanitize_variant_changes()` helper: `copy` continues to use `wp_kses_post()`, `image` uses `esc_url_raw()`, and `css`/`js` are stored as raw source. Both call sites (`save_test()`, `import_tests()`) are gated by `manage_options`, the same capability WordPress already requires for arbitrary code via Plugins / Theme Editor, so no trust-surface change.
* Note: existing `js` variants saved on 2.4.1 or earlier are still mangled in the database. Re-save each affected variant after upgrading, or use the new `wp elementtest fix-variant-changes` command (added in 2.4.3) to repair them in bulk.

= 2.4.1 =
* Fix: Full-URL wildcard pageview triggers (PR #43). A prefix like `https://example.com/shop/*` could previously match sibling paths like `/shopping` because the listener fell back to a loose full-URL `indexOf` check. The trigger is now resolved to its `URL.pathname` and matched with the same path-boundary rules as path-only wildcards (`/shop/*`); the full-URL fallback only applies when the prefix explicitly includes `?` or `#`. Mirrors the same change in `detect_pageview_goal_tests()`.
* UX: Cap the test results "Performance Over Time" chart at `max-height: 500px` so the chart fits on screen on wide displays.

= 2.4.0 =
* Security: Harden AJAX handler (PR #42) — proper `$wpdb->prepare()` for dynamic ID lists when deleting orphaned variants/goals and when exporting multiple tests; stricter `proxy_page()` URL validation (HTTP/HTTPS only, case-insensitive host match, port allowlist); clamp imported goal `revenue_value` to non-negative.
* Compliance: Add `uninstall.php` so options and custom tables are cleaned up when the plugin is deleted; sanitize cookie names/values when forwarding WordPress auth cookies through the admin proxy fetch.

= 2.3.9 =
* Fix: Enforce path boundary in wildcard pageview goal matching in the frontend JavaScript (`setupPageviewGoal` in `frontend.js`). Previously, a wildcard trigger like `/shop/*` would incorrectly match `/shopping` or `/shop-archive` on the client side. The same boundary fix was applied to the PHP backend in `conversion_page_matches()` (2.2.6, PR #29) and `detect_pageview_goal_tests()` (2.3.8), but was missing from the client-side pageview goal listener.
* Fix: Update Plugin URI header from placeholder `example.com` to actual GitHub repository URL.
* Fix: Sync frontend.js VERSION constant with plugin version.

= 2.3.8 =
* Fix: Duplicate Test now copies conversion goals. Previously, duplicating a test only cloned variants; all conversion goals (click, pageview, form submit, custom event, video play, add-to-cart) were silently dropped, forcing manual re-creation.
* Fix: Enforce path boundary in wildcard pageview goal detection so a trigger like `/shop/*` no longer incorrectly matches `/shopping` or `/shop-archive` when determining cross-page conversion-only tests. The same boundary fix was applied to `conversion_page_matches()` in 2.2.6 but was missing from the frontend test-detection path.

= 2.3.7 =
* Fix: Availability regression in the 2.3.6 invalid-request cap. The cap keyed its transient on the raw resolved visitor IP, so on proxy setups where `REMOTE_ADDR` collapses to a private/reserved address (e.g. `10.x.x.x`, `172.16.x.x`, `192.168.x.x`, loopback) many visitors shared a single bucket and enough invalid requests would lock legitimate users out of `get_variant_assignment`, `track_impression`, and `track_conversion` for up to an hour.
* The invalid-request cap now gates its transient key on `FILTER_VALIDATE_IP` with `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`. When the resolved IP is not publicly routable the cap is bypassed entirely (read and write). Per-test rate limiting for validated traffic is unaffected (PR #36).

= 2.3.6 =
* Security: Close unauthenticated DB write amplification / DoS on public tracking endpoints (Issue #31). Test/variant/conversion-goal/page-scope validation now runs BEFORE the per-(IP, test_id, event) rate-limit write, so a rotating `test_id` attack can no longer fan out transients or DB lookups. A new per-IP cap on invalid tracking requests fires at the top of every public tracking endpoint, keyed on IP only (one transient per IP regardless of how many attacker-controlled parameters get rotated). Filter `elementtest_invalid_request_cap` (default 30/hour) tunes the threshold. Affects `track_impression`, `track_conversion`, and `get_variant_assignment`.

= 2.3.5 =
* Security: Harden HTML report export against stored XSS. `wp_json_encode()` now emits with `JSON_HEX_TAG` so `<` and `>` are escaped inside the inline `<script>` block, preventing `</script>` breakout via test/variant/goal names (Bugbot PR #33, High severity).
* Fix: HTML report charts now degrade gracefully when the Chart.js CDN is unreachable. Added a `typeof Chart === 'undefined'` guard that hides `.chart-card` containers and returns early instead of throwing `ReferenceError` (Bugbot PR #33, Medium severity).

= 2.3.4 =
* New: HTML report export now includes a visual dashboard powered by Chart.js — 5 charts (daily conversion rate, cumulative conversions, overall conversion rate, goal breakdown, daily traffic split) in addition to the existing data tables
* Chart.js loads from jsDelivr CDN; if the CDN is blocked or unavailable the report falls back cleanly to data tables only
* Charts are hidden in the print stylesheet so printed reports stay clean

= 2.3.3 =
* New: `--format=json` option for `wp elementtest export` and `wp elementtest export_all` CLI commands — enables downstream tooling (e.g. external dashboards) to consume raw report data
* Note: 2.3.2 is intentionally skipped — that version number was briefly used for the cross-page add-to-cart tracking that was reverted in 2.3.1

= 2.3.1 =
* Fix: Add-to-cart conversion not tracking for CSS variants — switch click handler to capture phase and add form submit backup strategy
* Revert: Removed cross-page add-to-cart conversion tracking (briefly shipped as 2.3.2) — single-page scoping is the correct model for this plugin

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
