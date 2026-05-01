# Changelog

`readme.txt` remains the canonical WordPress.org release history for this plugin. This file mirrors the shipped release notes in a GitHub-friendly format.

## 2.4.1

- Fix: Full-URL wildcard pageview triggers (PR #43). A prefix like `https://example.com/shop/*` could previously match sibling paths like `/shopping` because the listener fell back to a loose full-URL `indexOf` check. The trigger is now resolved to its `URL.pathname` and matched with the same path-boundary rules as path-only wildcards (`/shop/*`); the full-URL fallback only applies when the prefix explicitly includes `?` or `#`. Mirrors the same change in `detect_pageview_goal_tests()`.
- UX: Cap the test results "Performance Over Time" chart at `max-height: 500px` so the chart fits on screen on wide displays.

## 2.4.0

- Security: Harden AJAX handler (PR #42). Replace `absint`-interpolated `NOT IN (...)` / `IN (...)` fragments with proper `$wpdb->prepare()` dynamic `%d` placeholders when deleting orphaned variants/goals and when exporting multiple tests by ID.
- Security: Harden `proxy_page()` SSRF defenses: allow only `http`/`https`, compare hosts case-insensitively, and reject non-standard ports unless they match the site’s configured home URL port (reduces internal service probing via odd ports).
- Fix: Clamp imported conversion-goal `revenue_value` to non-negative in `import_tests()`.
- Chore: Remove redundant double `absint` pass on export `test_ids` after the prepared-statement refactor.

## 2.3.9

- Fix: Enforce path boundary in wildcard pageview goal matching in the frontend JavaScript (`setupPageviewGoal` in `frontend.js`). A wildcard trigger like `/shop/*` previously matched `/shopping` or `/shop-archive` on the client side because the JS used a bare `indexOf` prefix check. The fix strips trailing slashes from the prefix and requires either an exact match or a `/` path boundary, mirroring the same fix applied to `conversion_page_matches()` in `class-ajax-handler.php` (2.2.6, PR #29) and `detect_pageview_goal_tests()` in `class-frontend.php` (2.3.8) — but which was never ported to the client-side pageview goal listener.
- Fix: Update Plugin URI header from placeholder `example.com` URL to the actual GitHub repository.
- Fix: Sync `frontend.js` VERSION constant with plugin version (was stuck at 2.3.6).

## 2.3.8

- Fix: Duplicate Test now copies conversion goals. Previously, `duplicate_test()` only cloned variants from `wp_elementtest_variants`; all rows in `wp_elementtest_conversions` (click, pageview, form submit, custom event, video play, add-to-cart goals including trigger selectors and revenue values) were silently dropped, forcing manual re-creation after every duplication. Especially impactful for add-to-cart tests where goal configuration is non-trivial.
- Fix: Enforce path boundary in wildcard pageview goal detection (`detect_pageview_goal_tests()` in `class-frontend.php`) so a trigger like `/shop/*` no longer incorrectly matches `/shopping` or `/shop-archive` when determining cross-page conversion-only tests. The same boundary fix was applied to `conversion_page_matches()` in `class-ajax-handler.php` in 2.2.6 (PR #29) but was missing from the frontend test-detection path.

## 2.3.7

- Fix: Availability regression in the 2.3.6 invalid-request cap. The cap keyed its transient on the raw resolved visitor IP, so on proxy setups where `REMOTE_ADDR` collapses to a private/reserved address (e.g. `10.x.x.x`, `172.16.x.x`, `192.168.x.x`, loopback) many visitors shared a single bucket. Enough invalid requests (e.g. stale cached pages sending retired `test_id` values) would trip the cap and lock legitimate users out of `get_variant_assignment`, `track_impression`, and `track_conversion` for up to an hour.
- The invalid-request cap now gates its transient key on `FILTER_VALIDATE_IP` with `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`. When the resolved IP is not publicly routable the cap is bypassed entirely (read and write), so shared-IP proxy setups no longer cross-lockout. Per-test rate limiting for validated traffic is unaffected. (PR #36)

## 2.3.6

- Security: Close unauthenticated DB write amplification / DoS on public tracking endpoints (Issue #31). Previously, `check_ip_rate_limit( $test_id, ... )` ran *before* validating that the `test_id` belonged to a real, running test. Because the rate-limit transient key mixes in `$test_id`, an attacker could rotate `test_id` values to get a fresh transient on every request — both evading the cap AND creating unbounded rows in `wp_options`.
- Reorder: `track_impression()`, `track_conversion()`, and `get_variant_assignment()` now validate test/variant/conversion-goal/page-scope BEFORE touching the per-test rate limit. Invalid `test_id` requests no longer write transients and no longer reach the existing per-(IP, test_id, event) bucket at all.
- New per-IP cap on invalid tracking requests: `invalid_request_cap_exceeded()` is the first gate on every public tracking endpoint. Read-only, cheap, keyed on IP only — a single transient per IP no matter how many attacker-controlled parameters get rotated. Incremented via `record_invalid_request()` only after validation failure. Default cap: 30 bad requests per hour per IP, tunable via `elementtest_invalid_request_cap` filter.

## 2.3.5

- Security: Harden HTML report export against stored XSS. `wp_json_encode()` emitting the report payload inside the inline `<script>` block now uses `JSON_HEX_TAG` so `<` and `>` are escaped — a test, variant, or goal name containing the literal `</script>` can no longer break out of the script context. Reported by Cursor Bugbot on PR #33 (High severity).
- Fix: HTML report charts now degrade gracefully when the Chart.js CDN is unreachable. The inline IIFE checks `typeof Chart === 'undefined'` before calling `new Chart(...)`, hides the `.chart-card` containers, and returns early. Previously the script threw `ReferenceError` and left empty chart cards visible, contradicting the graceful-degradation promise documented in DECISIONS.md for 2.3.4. Reported by Cursor Bugbot on PR #33 (Medium severity).

## 2.3.4

- New: HTML report export now includes a visual dashboard powered by Chart.js. Five charts render above the existing data tables: daily conversion rate (per variant, line), cumulative conversions (per variant, line), overall conversion rate (per variant, bar), goal breakdown (stacked bar), and daily traffic split (per variant, line).
- Chart.js is loaded from the jsDelivr CDN. If the CDN is blocked or unavailable, the report falls back cleanly to the data tables — no broken charts, no errors.
- The print stylesheet hides the charts so printed/PDF-exported reports stay clean.
- Result of the Alt A vs Alt B evaluation (see DECISIONS.md). Alt B's external dashboard approach (JSON-only) is preserved on the `feature/alt-b-json-dashboard` branch for reference; the JSON CLI output added in 2.3.3 remains the supported path for external tooling.

## 2.3.3

- New: `--format=json` option for `wp elementtest export` and `wp elementtest export_all` CLI commands. JSON output includes the full report payload (test metadata, per-variant impressions/conversions/rate/lift/confidence/verdict, per-goal breakdowns) and enables downstream tooling like external dashboards to consume the raw data directly. HTML and CSV remain the default formats.
- Note: 2.3.2 is intentionally skipped. That version number was briefly used for cross-page add-to-cart tracking which was reverted back to single-page scoping in 2.3.1. See DECISIONS.md.

## 2.3.1

- Fix: Add-to-cart conversion not tracking for CSS variants — switch click handler to capture phase and add form submit backup strategy so WooCommerce theme/swatch JS cannot block tracking via `stopPropagation()`.
- Revert: Removed cross-page add-to-cart conversion tracking (shipped briefly as 2.3.2). ElementTest is a single-page element testing tool; cross-page attribution adds complexity without matching the product's use case. See DECISIONS.md.

## 2.3.0

- New: Export A/B test results as standalone HTML reports or CSV files for offline analysis and stakeholder sharing.
- New: WP-CLI commands `wp elementtest export` and `wp elementtest export-all` for server-side report generation (SSH/SCP workflow).
- New: "Export HTML" and "Export CSV" buttons on the test results page for single-test download.
- New: "Export All Reports" buttons on the tests list page with zip bundling when ZipArchive is available.

## 2.2.6

- Security: Enforce page-scoped conversions at the AJAX write boundary so `track_conversion` rejects events that did not originate on the test's configured page URL (SEC-001 defense-in-depth).
- Fix: Enforce path boundary in wildcard conversion URL matching so `/shop/*` no longer incorrectly matches `/shopping` or `/shop-archive`.
- Fix: IP rate limiting now works correctly with external object caches (Redis, Memcached). The counter and window expiration are stored together inside the transient value so `set_transient()` is used for all updates instead of directly writing to the options table, which is invisible to object cache backends.

## 2.2.5

- Fix: Resolve add-to-cart button display regression on WooCommerce variable product pages caused by a timing conflict between the anti-flicker CSS and WooCommerce's variation lifecycle (300 ms slideDown delay). A new `setupWooCommerceVariationHandler` in the frontend JS hooks into `show_variation` and `found_variation` events to re-ensure visibility of tested elements after WooCommerce completes its show/hide cycle.

## 2.2.4

- Security: Default `get_visitor_ip()` to `REMOTE_ADDR` only; proxy forwarding headers (`X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP`) are no longer trusted unless explicitly enabled via the `elementtest_trusted_proxy_headers` filter. Closes the IP spoofing bypass of identity dedup and rate limiting (Issue #23).
- Fix: Prefer `X-Real-IP` over `X-Forwarded-For` for the Nginx proxy preset so the nginx-controlled header is checked before the client-spoofable one.
- Fix: Normalize hyphens to underscores in the custom proxy header name so it always matches PHP's `$_SERVER` key format.
- New: Admin settings UI for selecting a reverse proxy / CDN (Cloudflare, Nginx / Managed Hosting, or custom header).
- New: Activation banner prompting users to configure their hosting setup for accurate visitor tracking.
- UX: Rename "Nginx / Load Balancer" to "Nginx / Managed Hosting" with guidance that it is the safe default for most hosts.

**Which proxy setting do I need?** If you use Cloudflare, select Cloudflare. For managed hosting (GoDaddy, SiteGround, Kinsta, WP Engine), select Nginx / Managed Hosting — it falls back safely if proxy headers aren't present. For a dedicated or self-managed server, you can verify your setup via SSH:

```
# Check if Nginx is running as a reverse proxy:
systemctl status nginx

# See what is listening on ports 80/443:
ss -tlnp | grep -E ':80|:443'
```

If only Apache is listening on 80/443, select None. If Nginx is on 80/443 with Apache on a backend port, select Nginx / Managed Hosting.

## 2.2.1

- Fix: Rate limiter transient TTL was reset on every counter increment, turning the fixed hourly window into a sliding counter that never expired under sustained traffic.

## 2.2.0

- Security: Stop unauthenticated analytics forgery by computing visitor identity server-side instead of trusting client-supplied hashes.
- Security: Add per-IP rate limiting for impression and conversion tracking to reduce event write amplification.
- Security: Make stored goal revenue authoritative for standard conversions and clamp dynamic custom-event revenue.
- Fix: Keep frontend and AJAX visitor hashing aligned through the shared `ElementTest_Visitor` utility.

## 2.1.2

- Security: Harden proxy requests by restoring SSL verification and limiting forwarded cookies.
- Security: Add `Secure` cookie support on HTTPS sites and restrict admin selector messaging to the current origin.
- Fix: Replace deprecated time usage in the tests list and tighten several validation and response paths.
- UX: Update compatibility metadata for modern WordPress and PHP versions.

## 2.1.0

- Fix: Scope WooCommerce add-to-cart goals to the test page to avoid cross-page false positives.
- Fix: Preserve distinct add-to-cart conversions inside the deduplication window when product identity changes.
- Fix: Prevent false deduplication when product identity is unavailable during WooCommerce tracking.

## 2.0.1

- Fix: Prevent scoped WooCommerce add-to-cart goals from firing when the triggering button cannot be verified.

## 2.0.0

- New: Add WooCommerce add-to-cart conversion goals for single-product and AJAX add-to-cart flows.
- New: Capture WooCommerce product metadata with conversion events for reporting and debugging.
- UX: Add admin controls for configuring add-to-cart goals in the test editor.

## 1.1.0

- New: JSON import/export for A/B tests, including variants and conversion goals.
- New: Import, export selected, export all, and per-row export actions in the tests list.
- New: Cross-page pageview goal tracking for thank-you and other destination URLs.
- Fix: Resolve multiple conversion-tracking issues, including server-side deduplication and frontend settings propagation.

## 1.0.0

- Initial release.
- Database schema setup.
- Admin interface foundation.
