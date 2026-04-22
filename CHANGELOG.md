# Changelog

`readme.txt` remains the canonical WordPress.org release history for this plugin. This file mirrors the shipped release notes in a GitHub-friendly format.

## 2.3.7

- Fix: Availability regression in the 2.3.6 invalid-request cap. The cap keyed its transient on the raw resolved visitor IP, so on proxy setups where `REMOTE_ADDR` collapses to a private/reserved address (e.g. `10.x.x.x`, `172.16.x.x`, `192.168.x.x`, loopback) many visitors shared a single bucket. Enough invalid requests (e.g. stale cached pages sending retired `test_id` values) would trip the cap and lock legitimate users out of `get_variant_assignment`, `track_impression`, and `track_conversion` for up to an hour.
- The invalid-request cap now gates its transient key on `FILTER_VALIDATE_IP` with `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`. When the resolved IP is not publicly routable the cap is bypassed entirely (read and write), so shared-IP proxy setups no longer cross-lockout. Per-test rate limiting for validated traffic is unaffected.

## 2.3.6

- Security: Close unauthenticated DB write amplification / DoS on public tracking endpoints (Issue #31). Previously, the per-test IP rate limiter ran *before* validating that `test_id` belonged to a real, running test. Because the rate-limit transient key mixes in `$test_id`, an attacker could rotate `test_id` values to get a fresh transient on every request — both evading the cap and creating unbounded rows in `wp_options`.
- Reorder: `track_impression()`, `track_conversion()`, and `get_variant_assignment()` now validate test / variant / conversion-goal / page-scope BEFORE touching the per-test rate limit. Invalid `test_id` requests no longer write transients and no longer reach the per-(IP, test_id, event) bucket at all.
- New per-IP cap on invalid tracking requests: a read-only, IP-only gate runs first on every public tracking endpoint — a single transient per IP no matter how many attacker-controlled parameters get rotated. The counter is only incremented after validation failure. Default cap: 30 bad requests per hour per IP, tunable via the `elementtest_invalid_request_cap` filter.

## 2.3.5

- Security: Harden HTML report export against stored XSS. The inline `<script>` block that carries the report payload now encodes its JSON with `JSON_HEX_TAG`, so `<` and `>` are escaped — a test, variant, or goal name containing a literal `</script>` can no longer break out of the script context.
- Fix: HTML report charts now degrade gracefully when the Chart.js CDN is unreachable. The inline chart bootstrap checks `typeof Chart === 'undefined'` before calling `new Chart(...)`, hides the chart cards, and returns early. Previously the script threw `ReferenceError` and left empty chart cards visible.

## 2.3.4

- New: HTML report export now includes a visual dashboard powered by Chart.js. Five charts render above the existing data tables: daily conversion rate per variant (line), cumulative conversions per variant (line), overall conversion rate per variant (bar), goal breakdown (stacked bar), and daily traffic split per variant (line).
- Chart.js is loaded from the jsDelivr CDN. If the CDN is blocked or unavailable, the report falls back cleanly to the data tables — no broken charts, no errors.
- The print stylesheet hides the charts so printed / PDF-exported reports stay clean.

## 2.3.3

- New: `--format=json` option for the `wp elementtest export` and `wp elementtest export_all` CLI commands. JSON output includes the full report payload — test metadata, per-variant impressions / conversions / rate / lift / confidence / verdict, and per-goal breakdowns — enabling downstream tooling like external dashboards to consume the raw data directly. HTML and CSV remain the default formats.

## 2.3.1

- Fix: Add-to-cart conversions now track reliably on CSS variants. The click handler runs in the capture phase and a form-submit backup path is in place, so WooCommerce theme / swatch JS can no longer block tracking via `stopPropagation()`.

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
