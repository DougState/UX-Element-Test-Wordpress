# Changelog

`readme.txt` remains the canonical WordPress.org release history for this plugin. This file mirrors the shipped release notes in a GitHub-friendly format.

## 2.5.6

> Security fix for unauthenticated public tracking forgery (PRs #56 and #57, closes #54), merged to `main` after 2.5.5. See `DECISIONS.md` (2026-06-29 "Public tracking writes require signed assignment proof") for rationale. JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.6 (per the 2.3.9 sync convention).

- Fix: **Public tracking writes require signed assignment proof.** The public tracking endpoints accept guest traffic, and the public nonce plus test/variant metadata are visible on any page with running tests. An unauthenticated client could harvest those IDs and POST directly to `elementtest_track_impression` or `elementtest_track_conversion`, forging analytics and (for custom-event goals) supplying arbitrary dynamic revenue within the clamp. Fix: `elementtest_get_variant_assignment` is now the server-authoritative assignment gate — it validates page scope, chooses the variant server-side, and sets a signed HttpOnly `elementtest_assignment_<test_id>` cookie bound to `test_id`, `variant_id`, server-derived visitor hash, and expiry. Impression and conversion writes must present that proof cookie for the same visitor/test/variant tuple before inserting events. Public custom-event conversions default to the DB-stored goal revenue; sites that knowingly accept dynamic client revenue must opt in with `elementtest_allow_public_custom_event_revenue`. Frontend test processing requests assignment before applying variants, recording impressions, or registering conversion listeners. Conversion-only pageview goals depend on the proof cookie created when the visitor saw the source test page; old sessions without proof may need one fresh source-page visit before cross-page conversions count. Files: `includes/class-ajax-handler.php`, `assets/js/frontend.js`. (PRs #56, #57)

## 2.5.5

> Correctness fix for cross-page pageview goals on subdirectory WordPress installs (PR #55), merged to `main` after 2.5.4. Reinforces the PR #50 client/server matcher alignment — PHP already stripped the install home path; JS did not after the cached-safe re-check landed. JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.5 (per the 2.3.9 sync convention).

- Fix: **Subdirectory pageview goal matching on the client.** When WordPress is installed in a subdirectory (e.g. `https://example.com/blog/`), `ElementTest_Frontend::get_current_path()` and `normalise_path()` already strip the home path before comparing test URLs and pageview goal triggers. After 2.5.2 (PR #50), cross-page conversion-only pageview goals are re-validated in the browser via `setupPageviewGoal()` → `normalizePageviewPath()` before `trackConversion()` runs — but that JS helper lowercased and trimmed trailing slashes only; it did **not** strip `homePath`. Symptom: the PHP pre-filter matched (`detect_pageview_goal_tests()` baked the goal into `conversionOnlyTests` and enqueued the frontend script on `/blog/thank-you`), then the client re-check failed (`/blog/thank-you` ≠ trigger `/thank-you`) and the conversion was silently dropped. Fix: localize `homePath` from `wp_parse_url( home_url(), PHP_URL_PATH )` in `enqueue_frontend_assets()` and strip it inside `normalizePageviewPath()` with the same path-boundary guard PHP uses (`/blog` must not match `/blogging/...`). Query/hash triggers still require an exact live URL match; wildcard path-boundary rules from 2.4.1 / 2.5.2 unchanged. Files: `includes/class-frontend.php`, `assets/js/frontend.js`. Standalone regression checks in `tests/test-pageview-path-normalization.js` (6 cases). (PR #55)

## 2.5.4

> Security fix for visitor IP spoofing (PR #53, closes #52), merged to `main` after 2.5.3. See `DECISIONS.md` (2026-06-03 "Forwarded headers require trusted-proxy source") for rationale. JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.4 (per the 2.3.9 sync convention).

- Fix: **Forwarded IP headers no longer trusted without verifying the request came through the proxy.** When a reverse-proxy preset was enabled in Settings → Reverse Proxy / CDN, `ElementTest_Visitor::get_visitor_ip()` honored `X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP`, or a custom header whenever the preset was set — without checking that `REMOTE_ADDR` was actually the proxy. An unauthenticated client POSTing directly to `admin-ajax.php?action=elementtest_track_impression` (or `_conversion`) could set the header to any value. That spoofed IP flowed into `user_hash`, dedupe, the invalid-request cap, and per-test rate-limit keys, letting an attacker forge analytics and amplify DB writes past the intended per-IP caps (validated on #52: rotating the spoofed `X-Forwarded-For` inserted distinct impression rows). Fix: forwarded headers are now honored **only when the direct connection (`REMOTE_ADDR`) falls inside a trusted-proxy CIDR**; direct hits fall back to `REMOTE_ADDR`. New `elementtest_trusted_proxy_cidrs` filter, wired to the `proxy_type` setting by `ElementTest_Pro::resolve_proxy_cidrs()`: Cloudflare → published edge IPv4+IPv6 ranges; nginx → loopback + RFC1918 private ranges; custom → none until the admin adds CIDRs via the filter (secure default). New IPv4/IPv6-aware `ip_in_cidr()` matcher using `inet_pton` + prefix mask; cross-family comparisons and malformed input never match. Files: `includes/class-visitor.php`, `elementtest-pro.php`, `README.md`. Standalone regression checks in `tests/test-visitor-ip.php` (10 assertions). (PR #53)

- Fix: **IPv4-mapped IPv6 addresses failing CIDR match.** When `REMOTE_ADDR` was an IPv4-mapped IPv6 address (e.g. `::ffff:10.1.2.3`), `inet_pton` returned 16 bytes while IPv4 CIDRs like `10.0.0.0/8` parsed to 4 bytes; the length check rejected the match, so `is_trusted_proxy()` always returned false for legitimate internal proxies reporting mapped addresses and forwarded headers were ignored. Normalize IPv4-mapped IPv6 binary representations to their 4-byte IPv4 equivalent before comparison, adjusting prefix length when the subnet itself is in mapped form. Files: `includes/class-visitor.php`. (PR #53)

## 2.5.3

> Admin tests-list readability pass (PR #51), merged to `main` after 2.5.2. See `DECISIONS.md` (2026-06-01 "Tests list shows real confidence; drops conversion rate") for rationale. JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.3 (per the 2.3.9 sync convention).

- Feature: The **Confidence** column on the ElementTest tests list now shows real statistical confidence instead of a hardcoded `0.0%`. Previously the list query in `elementtest-pro.php` selected `0 AS confidence`, so every row read 0% even when the detail view reported a significant winner (e.g. 95%). The list now reflects the best (max) non-control variant confidence per test, computed with the same two-proportion z-test as the detail view and exports, so the three surfaces agree. Tests below the significance gate (any non-control variant needs ≥ 30 impressions, same as the detail view) or with no control data show `0.0%`. Implemented as a new `ElementTest_Report_Generator::get_list_confidences( array $test_ids )` that runs a single aggregate query for all visible tests (no per-test query storm), plus a shared `ElementTest_Report_Generator::z_to_confidence( $z )` helper now used by both the list and `compute_variant_stats()`. Files: `elementtest-pro.php`, `includes/class-report-generator.php`.

- UX: Removed the **Conv. Rate** column from the tests list. At the list level the conversion rate without its control baseline is noise; the meaningful signal is the Confidence column (does this test have a result worth clicking into?). Per-variant conversion rates remain on the detail view and in HTML/CSV exports — only the list column is gone. The list SQL no longer computes `conversion_rate`, and the now-unused `.elementtest-rate-*` styles were removed. Files: `includes/views/tests-list.php`.

## 2.5.2

> Two correctness fixes for conversion page-scope matching (PRs #49 and #50), merged to `main` after 2.5.1. Both are silent data-integrity bugs — one drops real conversions, the other invents false ones — with no user-facing error in either case. See `DECISIONS.md` (2026-06-01 entries) for the architectural rationale. JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.2 (per the 2.3.9 sync convention).

- Fix: Conversion tracking on bare vs `www.` host variants. Non-pageview conversions (click, form submit, custom event, add-to-cart) were silently dropped when a test's configured page URL used one host form (e.g. `example.com`) but the visitor was served the same page on the other (`www.example.com`), or vice versa. The frontend activates tests by path only, so those visitors still saw variants and recorded impressions — but the conversion AJAX failed the server-side page-scope check, losing the conversion data with no error. Root cause: `ElementTest_Frontend::check_active_tests()` strips protocol and host for delivery matching, while `ElementTest_Ajax_Handler::normalize_conversion_url()` compared the full host + port + path. Fix: canonicalize a leading `www.` in `normalize_conversion_url()` before the existing host/port/path comparison, aligning the conversion-write scope check with the host-agnostic, path-based frontend delivery. Different paths and unrelated domains still fail the check. Files: `includes/class-ajax-handler.php`. Authored by @Cursor Agent / Doug Wagner. (PR #49)

- Fix: Cached cross-page pageview conversion over-counting (PR #50). A full-page cache keyed only by path could serve HTML generated for a query-string-specific cross-page pageview goal (e.g. `/checkout/order-received/?key=wc_order_*`) to a later visitor on the same path with a different query string. `processConversionOnlyTests()` in `assets/js/frontend.js` trusted the server-baked goal payload and called `trackConversion()` directly, so that later visitor recorded a false conversion — and, when 2.5.x GA4 forwarding was enabled, a false `elementtest_converted` event — corrupting A/B data on cached checkout/thank-you flows. Fix: route conversion-only pageview goals through `setupPageviewGoal()` with the goal's original `trigger_event` so the live browser URL is re-validated client-side before recording, the same matcher normal on-page pageview goals already use. Triggers containing `?` or `#` still require an exact URL match.

- Fix: Normalize client-side pageview path matching to mirror PHP (PR #50). New `normalizePageviewPath()` helper in `assets/js/frontend.js` lowercases and strips trailing slashes from both the current path and the trigger before comparison, so the new cached-safe client re-check is never *stricter* than the server-side pre-filter (a trailing-slash or case difference would otherwise drop conversions the server already approved). Path-only exact triggers now use normalized-path comparison; query/hash triggers keep exact-URL matching. Files: `assets/js/frontend.js`.

## 2.5.1

> 2.5.0 was prepared but never tagged or shipped on `main`; its entries are folded into 2.5.1. The 2.5.0 → 2.5.1 bump rolls in one hotfix discovered during manual validation on production: WooCommerce Add-to-Cart conversion goals were silently bypassing GA4 forwarding (plugin DB recorded the conversion correctly, but no `elementtest_converted` event reached GA4). Root cause: `frontend.js` has two conversion-firing functions (`trackConversion()` for general goals, `trackAddToCartConversion()` for WC Add-to-Cart) and the gtag block was only added to the first. The 2.5.1 fix extracts a shared `fireGa4ConversionEvent(testId, variantId, revenue)` helper and calls it from both functions, so every goal type now emits the same `elementtest_converted` event. Any future conversion-firing path added to `frontend.js` should also call the helper — see the new TODOs for "conversion-firing path enumeration" and "test plan: enumerate every conversion-firing function" added to `TODOS.md` to prevent the same omission from recurring.

- Feature: GA4 custom event forwarding for variant conversions. When the new "Enable GA4 Events" setting in **ElementTest → Settings → Google Analytics 4 Integration** is on, every variant conversion fires a parallel client-side gtag event:
  ```js
  gtag('event', 'elementtest_converted', {
    test_id, test_name,
    variant_id, variant_name,
    revenue_value,
    transport_type: 'beacon'
  });
  ```
  The gtag call is added inside `trackConversion()` in `assets/js/frontend.js`, immediately after the existing `sendTrackingRequest()` so the plugin DB write happens first regardless of gtag state. Test name and variant name are looked up by ID from the localized `elementtestFrontend.tests` payload at fire time so the `trackConversion(testId, variantId, conversionId, revenue)` signature stays unchanged at all six call sites in the file.

- Why this exists: ElementTest Pro already records every conversion in the plugin's own database with its own dashboard and stat-sig calculator, but marketing teams typically live in GA4. Pulling variant conversions into the same GA4 property where session source, geography, device, and ecommerce data already aggregate lets the marketing team slice variant performance by traffic source, campaign, and audience without ever opening the WordPress admin.

- `transport_type: 'beacon'` is the load-bearing detail. `sendTrackingRequest()` at `assets/js/frontend.js:550` uses `navigator.sendBeacon` for the conversion AJAX because click and form-submit conversion goals immediately navigate away from the page. gtag's default batch tick would not flush in time and would drop a large fraction of those events. Passing `transport_type: 'beacon'` to gtag instructs GA4's `gtag.js` to use the same survival mechanism.

- The gate is `window.gtag && elementtestFrontend.ga4Enabled`. Both must be true for the call to fire. When either fails, the call is a no-op and the plugin-DB conversion still records normally. This means:
  - Sites without `gtag.js` loaded (most WordPress admin pages, sites with no GA tag) silently skip the event — no console errors, no broken conversion tracking.
  - Sites where a consent plugin blocks `gtag.js` until consent is granted (older CookieYes / Complianz behavior) also silently skip — when consent is later granted and the page reloads with gtag available, future conversions forward as expected.
  - Admins who flip the GA4 setting off see zero `elementtest_converted` events in GA4 DebugView within a page reload.

- The plugin does NOT load `gtag.js` itself. It piggybacks on an existing GA4 tag (e.g., one loaded by WooCommerce Google Analytics Pro, Site Kit by Google, Tag Manager, or a theme-level snippet). Loading `gtag.js` from the plugin would conflict with site-level GA configuration and break consent-banner integrations.

- Feature: New "Run diagnostic" button in the GA4 Integration settings panel (`includes/views/settings.php`). Admin-only by virtue of the settings page being `manage_options`-gated. Reports `typeof window.gtag` for the admin-page context, the configured Measurement ID from the existing `ga4_measurement_id` settings field (previously unused — this release wires it into the diagnostic), and attempts to fire a hardcoded `elementtest_diagnostic_test` event with `source`, `plugin_version`, and `ts` parameters. Output panel is color-coded (`#46b450` success, `#dba617` gtag unavailable, `#dc3232` error) and includes explicit next-step guidance for the most common failure mode — front-end-only `gtag.js` loaders where the admin context reports `typeof window.gtag === 'undefined'` even when GA4 works correctly on the front-end. Use this button BEFORE relying on the live conversion path to verify GA4 is reachable from your site.

- UX: PII warning string shown next to the **Test Name** input and **Variant Name** inputs (the static Variant B field plus the Underscore.js template for dynamically added variants) in `includes/views/new-test.php` when `ga4_enabled` is true. Copy: "GA4 forwarding is on — do not include PII (emails, names, etc.) in this field. The value is sent to Google Analytics as an event parameter." GA4 explicitly disallows PII in event parameter values and may suspend properties that violate the policy. Warning placement at the point of authorship (test creation / edit) rather than in settings copy reduces the risk that a test authored months after GA4 was enabled silently leaks PII.

- Localize payload: `enqueue_frontend_assets()` in `includes/class-frontend.php` now passes `ga4Enabled` (boolean, mirrors the saved `ga4_enabled` setting) at the top level of the `elementtestFrontend` JS object, and `test_name` (sanitized via `sanitize_text_field()`) on each test object inside the `tests` array. Variant name was already in the payload at line 450.

- Note on GA4 reports: custom event parameters appear in GA4 DebugView and Realtime immediately, but they will NOT show up as columns in standard GA4 reports until you register them as **custom dimensions** in the GA4 admin (**Admin → Custom definitions → Create custom dimensions**). Register `test_id`, `test_name`, `variant_id`, `variant_name`, and `revenue_value` as event-scoped custom dimensions. The plugin does not automate this step. Once registered, expect a few hours of GA4 report-processing latency before the columns are populated.

- Note on client-side accuracy: GA4 numbers in this release will be lower than the plugin's own dashboard numbers for visitors who block gtag.js (ad-blockers, strict privacy browsers, denied consent). The visibility goal of this release is "marketing sees variant data in GA4," not "GA4 matches the plugin DB exactly." If accurate counts vs. ad-block resilience become a requirement, a future release will add server-side Measurement Protocol mode (tracked in TODOS.md). The plugin dashboard remains the source of truth for conversion counts and statistical significance.

- Files touched: `includes/views/settings.php` (diagnostic button + amber output area + JS handler), `includes/class-frontend.php` (localize payload `ga4Enabled` + per-test `test_name`), `assets/js/frontend.js` (gated gtag call inside `trackConversion()` with lookup-by-ID + beacon transport, JS `VERSION` constant synced to 2.5.0), `includes/views/new-test.php` (conditional PII warning at three name-input sites). Five-commit branch (`feature/ga4-custom-events`) consolidated into this 2.5.0 release.

- Bundled fix (from PR #45 merged into `main` during this release cycle): query-string wildcard pageview matching in `assets/js/frontend.js` and `includes/class-frontend.php`. Trigger URLs ending in `*` that include a `?` or `#` prefix now match correctly against the current request URL. Authored by @Cursor Agent / Doug Wagner.

- Review-driven fixes landed during the /ship gate before merge:
  - Cross-page pageview conversions now carry `test_name` and `variant_name` in the GA4 event payload, not just numeric IDs. `detect_pageview_goal_tests()` fetches variant names alongside IDs and threads them through `build_conversion_only_data()`; the `trackConversion()` gate in `frontend.js` falls back to `elementtestFrontend.conversionOnlyTests` when a test isn't in the on-page `tests` array.
  - `revenue_value` coerced via `parseFloat()` + `isNaN()` guard before forwarding to gtag. A revenue value arriving as the string `"0.00"` was forwarded as a string, breaking GA4-side numeric aggregation; now it lands as a clean JS number.
  - Defensive `current_user_can( 'manage_options' )` check at the top of `includes/views/settings.php`. The settings page is already gated at the menu-registration layer; this is belt-and-suspenders for any future include path (network admin, shortcode preview, REST handler) that might leak the diagnostic button JS to non-admins.

## 2.4.4

- Tooling: Admin-only `?et_force=` query-parameter override for variant assignment in `assets/js/frontend.js` `assignVariant()`. Lets logged-in admins (`manage_options`) deterministically preview any variant for QA testing without waiting on random rolls or repeatedly editing cookies. Two forms:
  - `?et_force=control` — select the Control variant (the variant with `is_control=1`) of every test on the page.
  - `?et_force=<variant_id>` — select a specific variant by its `wp_elementtest_variants.variant_id` value.

  The forced assignment is written to the existing `elementtest_variant_<test_id>` cookie so it sticks across navigation; remove the cookie (or visit the page without the parameter and let it re-roll) to resume normal weighted random assignment. The override is gated server-side via a new `isAdmin` field in the localized `elementtestFrontend` payload (`includes/class-frontend.php` `enqueue_frontend_assets()` → `current_user_can( 'manage_options' )`), so non-admin visitors cannot bias real test data via shared URLs — for them the parameter is simply ignored and the normal weighted random path runs. The forced assignment logs to `console.info` (or `console.warn` when the parameter does not match any variant for that test) so DevTools makes the override unambiguous.

- Why this exists: filed during QA on test #6 (Heel Grounder JS Buy More Test) on woo.dougstate.com where the test owner cleared cookies and switched VPN locations multiple times but the 50/50 roll kept landing on Variant B (three consecutive misses). Database verification confirmed the test config was correct (Control `traffic_percentage=50`, `is_control=1`, real `variant_id=11`) and that Control impressions WERE being recorded for other visitors — the issue was purely that the tester's own browser kept rolling Variant B by chance. `?et_force=control` removes that friction.

- Usage example for QA:
  ```
  https://woo.dougstate.com/products/velcro-closure-heel-grounders-lime-green/?et_force=control
  https://woo.dougstate.com/products/velcro-closure-heel-grounders-lime-green/?et_force=11
  ```

## 2.4.3

- Tooling: New WP-CLI subcommand `wp elementtest fix-variant-changes` (in `includes/class-cli-commands.php`) for repairing pre-2.4.2 `wp_kses_post()`-mangled `js` and `css` variant source already stored in the database. The 2.4.2 helper `sanitize_variant_changes()` only changes behavior for *new* saves; rows already in `wp_elementtest_variants` stayed corrupted (`>=` stored as `&gt;=`, `&&` as `&amp;&amp;`, `.parent > .child` CSS selectors as `.parent &gt; .child`). Without repair, JS variants throw `SyntaxError` at parse time inside `applyJsChanges()` and CSS rules silently fail to match. The new command JOINs `wp_elementtest_variants` to `wp_elementtest_tests`, filters to `test_type` in (`css`, `js`), and decodes only the five HTML entities `wp_kses_post()` produces from JS/CSS tokens (`&amp;`, `&lt;`, `&gt;`, `&quot;`, `&#039;`) — leaving named entities such as `&middot;`, `&nbsp;`, `&copy;` intact because admins commonly embed those intentionally inside HTML string literals built up in JS variants for `.innerHTML` / `$.html()` insertion.
- Defaults to dry-run; `--apply` writes; `--backup=path.json` snapshots affected rows (variant_id, test_id, test_name, variant_name, test_type, original `changes`) before any UPDATE; `--show-diff` prints up to 10 changed line pairs per variant; `--type=js|css` and `--test-id=N` narrow the scan. Uses `strtr()` (not `html_entity_decode()`) so a doubly-encoded `&amp;gt;` decodes once to `&gt;` rather than collapsing to `>` and silently breaking legitimate user-authored entities.
- Note: with this command available, the 2.4.2 release-note recommendation to "re-save each affected variant manually" is no longer the only option. The recommended invocation is:

```sh
sudo -u www-data wp elementtest fix-variant-changes --show-diff --path=/var/www/html
sudo -u www-data wp elementtest fix-variant-changes --apply \
    --backup=elementtest-variants-backup-$(date +%Y%m%d).json --path=/var/www/html
```

## 2.4.2

- Fix: JavaScript variant `changes` source is no longer mangled on save. The plugin previously applied `wp_kses_post()` uniformly to the `changes` column inside `save_test()` and `import_tests()` (`includes/class-ajax-handler.php`), but `changes` is polymorphic — it holds CSS rules, HTML, JavaScript source, or an image URL depending on `test_type`. Running JS source through `wp_kses_post()` parses it as HTML, rebalances/strips `<`, `>`, and `&` (e.g. operators like `>=`, string literals containing `<div>...</div>`, or `&middot;` entities), and produces source that throws `SyntaxError` at parse time when the variant's `<script>` is appended in `applyJsChanges()`. Sanitization is now branched on `test_type` via a new `sanitize_variant_changes()` helper: `copy` continues to use `wp_kses_post()`, `image` uses `esc_url_raw()`, and `css`/`js` are stored as raw source. Both call sites are gated by `manage_options`, the same capability WordPress already requires for arbitrary code via Plugins / Theme Editor, so the trust surface is unchanged. Issue was documented as a Low correctness finding in `security-reviews/2026-04-06-class-ajax-handler-v2.2.6.md`.
- Note: existing `js` variants saved on 2.4.1 or earlier are still mangled in the database — the fix only changes behavior for *new* saves. Re-save each affected variant after upgrading (paste the original JS source back into the test editor) to repopulate it correctly. Re-saving an `image` variant will also normalize the URL via `esc_url_raw` instead of `wp_kses_post`.

## 2.4.1

- Fix: Full-URL wildcard pageview triggers (PR #43). A prefix ending in `*` such as `https://example.com/shop/*` previously used a loose full-URL `indexOf(prefix)` fallback that could incorrectly match sibling paths such as `/shopping`. `setupPageviewGoal` in `frontend.js` now resolves the pathname from the prefix (`URL.pathname`) and applies the same path-boundary rules as path-only wildcards (`/shop/*`). Full-URL prefix matching is retained only when the prefix explicitly includes `?` or `#`. `detect_pageview_goal_tests()` in `class-frontend.php` mirrors the same behavior (`/*` remains whole-site; path boundary first; conditional full-URL fallback).
- UX: Cap the test results "Performance Over Time" chart at `max-height: 500px` in `assets/css/results.css` so the chart fits on screen on wide displays. The custom canvas renderer reads `getBoundingClientRect()` for sizing, so capping the rendered height also caps the drawing buffer and the chart's internal `chartH` math reflows correctly.

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
