# Changelog

`readme.txt` remains the canonical WordPress.org release history for this plugin. This file mirrors the shipped release notes in a GitHub-friendly format.

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
