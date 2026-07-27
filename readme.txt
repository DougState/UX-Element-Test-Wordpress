=== ElementTest Pro ===
Contributors: Doug Wagner
Tags: ab-testing, split-testing, conversion, optimization, analytics
Requires at least: 5.6
Tested up to: 7.0
Stable tag: 2.5.14
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
* Optional GA4 custom event forwarding for variant conversions (see the **GA4 Integration** section below for the full operator's guide)

== GA4 Integration ==

ElementTest Pro can forward variant conversions to Google Analytics 4 as custom events alongside the plugin's own database tracking. Enable it under **ElementTest > Settings > Google Analytics 4 Integration**. The full operator's guide also ships inside the plugin at **ElementTest > GA4**.

**What it sends.** When the **Enable GA4 Events** setting is on AND a `gtag.js` tag is already loaded on the page, every variant conversion fires a `gtag('event', 'elementtest_converted', { test_id, test_name, variant_id, variant_name, revenue_value, transport_type: 'beacon' })` call in parallel with the existing plugin-DB record. The plugin does NOT load `gtag.js` itself - it piggybacks on whatever GA4 tag is already on your site (WooCommerce Google Analytics Pro, Site Kit by Google, Google Tag Manager, etc.). Conversion-only in 2.5.x; variant impression forwarding (`elementtest_variant_viewed`) is planned for a future release. The `transport_type: 'beacon'` flag is load-bearing - it parallels the plugin's existing sendBeacon AJAX, so click and form-submit conversion goals survive page navigation without dropping the GA4 event.

**Three things people often conflate.** GA4 has three separate concepts that all need to line up before variant data is useful in your reports.

1. **Confirming the event arrives at GA4.** DebugView (immediate, debug sessions only - install the GA Debugger browser extension or add ?gtm_debug=1 to the URL). Realtime (immediate, any session - GA4 > Reports > Realtime, look for `elementtest_converted` in the Event count card within ~30 seconds). Standard Events report (24-48 hour delay - GA4 > Reports > Engagement > Events, appears automatically once processed).

2. **Marking it as a Key Event (formerly Conversion).** Wait until `elementtest_converted` appears under GA4 > Admin > Events, then toggle the **Mark as key event** star on its row. Or preemptively create it under GA4 > Admin > Key events > Create.

3. **Seeing the parameters as report columns (custom dimensions).** The event parameters (`test_id`, `test_name`, `variant_id`, `variant_name`, `revenue_value`) show up in DebugView and Realtime immediately, but will NOT appear as columns / breakdowns / dimensions in standard GA4 reports until you register them as custom dimensions. GA4 > Admin > Custom definitions > Create custom dimensions, then add: Variant Test ID (Event scope, `test_id` parameter), Variant Test Name (Event, `test_name`), Variant ID (Event, `variant_id`), Variant Name (Event, `variant_name`), Variant Revenue (Event, use the **Metric** option not Dimension, `revenue_value` parameter). The metric option on revenue_value is important - that's what makes GA4 sum it across events. After you register them, expect another 24-48 hour delay before existing event history retroactively populates the new columns. Going forward, every new event populates them in near-realtime.

**Known limitations.** Client-side only - visitors who block gtag.js (ad-blockers, strict privacy browsers, denied consent) won't generate GA4 events; the plugin dashboard remains the source of truth for conversion counts and GA4 numbers will be lower. The plugin does NOT load gtag.js - if your site has no GA4 tag, this feature is a no-op. `revenue_value` is a custom metric, not GA4's standard ecommerce revenue (which uses `value` + `currency` on `purchase` events) - register it as a custom metric per step 3 above for revenue totals in explorations. PII rule - GA4 disallows personally-identifiable info (emails, names) in event parameter values; test names and variant names are sent verbatim, so don't include PII. The plugin surfaces a warning next to the Test Name and Variant Name input fields when GA4 forwarding is on.

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

= 2.5.14 =
* Change: Plugin author set to Doug Wagner.
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.14.

= 2.5.13 =
* Fix: Admin test saves now fail closed when an existing variant or conversion goal row is invalid or cannot be written. Previously, malformed rows could be silently skipped and the editor could still show "Test saved successfully."
* Fix: Assignment proof cookie path is now `/` so admin-ajax.php can receive the signed cookie on split home/siteurl installs.
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.13.

= 2.5.12 =
* Add: update-source status indicator on the Plugins screen row (last check result and latest available version) and a warning notice when the release check fails — so update problems are never silent. On private mirrors, the notice also flags a missing or expired access token; the token value itself is never displayed.

= 2.5.11 =
* Add: GitHub Releases updater. The dashboard shows update notices and one-click updates when a new release is published on this repository (Update URI + `update_plugins_github.com`, WP 5.8+). No configuration needed for installs from this public repository. Private mirrors are also supported: define `ELEMENTTEST_GITHUB_TOKEN` in wp-config.php with a fine-grained GitHub token (Contents: read-only) to authenticate release checks and downloads (via the GitHub API asset endpoint; the token is never sent to the download CDN).
* Add: release workflow builds `elementtest-pro-{version}.zip` and attaches it to the GitHub Release when a version tag is pushed.

= 2.5.10 =
* Compliance: Resolved all 259 warnings from the WordPress.org Plugin Check (PCP) scan — the plugin now passes Plugin Check with zero errors and zero warnings (PR #1).
* Compliance: Removed the discouraged `load_plugin_textdomain()` call; WordPress 4.6+ loads translations automatically for wp.org-hosted plugins (PR #1).
* Fix: Reordered `$_POST['page_url']` sanitization in the conversion-tracking and variant-assignment AJAX handlers so `esc_url_raw()` directly wraps the unslashed input (PR #1).
* Compliance: Prefixed the file-scope globals in `uninstall.php` (`$elementtest_site_ids` / `$elementtest_site_id`) (PR #1).
* Compliance: Added documented, justified phpcs annotations for false positives — cross-function nonce verification in the AJAX handler, per-field unslash/sanitize loops, `WP_DEBUG`-gated `error_log()` calls, and function-scoped variables in the `includes/views/` templates (PR #1).
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.10 to match the plugin version (per the 2.3.9 sync convention).

= 2.5.9 =
* Compliance: The plugin now passes the WordPress.org Plugin Check with zero errors (PR #64).
* Compliance: Chart.js is now bundled with the plugin (`assets/vendor/chart.umd.min.js`) and inlined into exported HTML reports instead of being loaded from the jsdelivr CDN, per WordPress.org plugin directory policy (PR #64).
* Compliance: Dev-only CLI test scripts and shell scripts are no longer shipped in the distributed plugin (PR #64).
* Compliance: Database table names are now written inline as `{$wpdb->prefix}elementtest_*` in all SQL, and the remaining direct-query and placeholder-list warnings carry documented phpcs justifications (custom plugin tables have no WP API equivalent) (PR #64).
* Compliance: Added the missing translators comment for the "%s%% overall rate" string on the results dashboard, created the `languages/` folder declared by the `Domain Path` header, and corrected the readme `Stable tag` (PR #64).
* Compatibility: Tested up to WordPress 7.0 (PR #64).
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.9 to match the plugin version (per the 2.3.9 sync convention).

= 2.5.8 =
* Security: `proxy_page()` now uses `preg_replace_callback` instead of `preg_replace` when injecting the `<base>` tag, preventing a same-origin URL containing `$1` from being misinterpreted as a regex backreference and corrupting proxied HTML output (PR #62, release PR #63).
* Security: Database error logging on insert failures (test, goal, conversion) is now gated behind `WP_DEBUG` so production sites do not write full SQL error text to the PHP error log by default (PR #62).
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.8 to match the plugin version (per the 2.3.9 sync convention).

= 2.5.7 =
* Fix: Assignment proof cookie lifetime now matches the configured sticky assignment window (`cookie_days`, default 30 days) instead of a fixed 24-hour TTL. Cross-page pageview conversions on longer funnels were silently dropped after the first day even though the visitor still held a valid assignment cookie (PR #58).
* Fix: Public variant assignment is now stable before the first impression — repeated assignment requests no longer resample variants and skew traffic splits. Public conversion tracking with `conversion_id=0` is rejected to prevent no-goal aggregate conversion rows (PR #59).
* Fix: User-Agent rotation can no longer resample variant assignments. The assignment seed is now IP-only while proof cookies remain UA-bound for dedup (PR #60).
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.7 to match the plugin version (per the 2.3.9 sync convention).

= 2.5.6 =
* Security: Public impression and conversion writes now require a server-minted signed assignment proof cookie (PRs #56 and #57, closes #54). The public AJAX nonce is a CSRF control, not proof that a visitor was assigned a variant — an unauthenticated client could harvest valid test/variant IDs from the page and POST directly to the tracking endpoints, forging impressions, conversions, and (for custom-event goals) arbitrary revenue. Fix: `elementtest_get_variant_assignment` is now the server-authoritative gate — it validates page scope, chooses the variant server-side, and sets an HttpOnly `elementtest_assignment_<test_id>` cookie bound to visitor hash, variant, and expiry. Impression and conversion handlers reject writes without a matching proof cookie. Public custom-event conversions default to the DB-stored goal revenue; sites that knowingly accept client-supplied dynamic revenue must opt in via the `elementtest_allow_public_custom_event_revenue` filter. The frontend requests assignment before applying variants, recording impressions, or registering conversion listeners. Cross-page pageview goals depend on the proof cookie created when the visitor saw the source test page; sessions without proof may need one fresh source-page visit before cross-page conversions count.
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.6 to match the plugin version (per the 2.3.9 sync convention).

= 2.5.5 =
* Fix: Cross-page pageview goals on subdirectory WordPress installs (PR #55). When WordPress lives in a subdirectory (e.g. `example.com/blog/`), the server correctly detected pageview conversion goals on thank-you or order-received pages, but the client-side URL re-check added in 2.5.2 did not strip the install's home path before comparing paths. Visitors reached the goal page and the plugin loaded, yet conversions were silently dropped because `/blog/thank-you` did not match a trigger stored as `/thank-you`. Fix: pass the WordPress home path to the frontend and strip it inside the pageview path normalizer, mirroring the PHP path logic already used for test delivery and goal detection. Path-boundary rules and exact query-string matching from 2.5.2 are unchanged.
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.5 to match the plugin version (per the 2.3.9 sync convention).

= 2.5.4 =
* Fix: Visitor IP spoofing via forwarded headers (PR #53). When a reverse-proxy preset was enabled, the plugin trusted `X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP`, or a custom header without verifying the request actually arrived through the proxy. An attacker POSTing directly to the tracking AJAX endpoint could set the header to any IP, bypassing rate limits and deduplication and forging analytics data. Forwarded headers are now honored only when the direct connection IP falls inside a trusted proxy range; otherwise the plugin uses the direct IP. Cloudflare and nginx presets include default ranges; custom setups must declare their proxy's egress CIDR via the `elementtest_trusted_proxy_cidrs` filter or forwarded headers are ignored (secure default). Default `proxy_type=none` is unchanged.
* Fix: IPv4-mapped IPv6 proxy addresses (e.g. `::ffff:10.1.2.3`) now match internal proxy CIDRs correctly, so legitimate nginx/private-network proxies are recognized instead of falling back to the mapped address and ignoring forwarded headers.
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.4 to match the plugin version (per the 2.3.9 sync convention).

= 2.5.3 =
* Improvement: The **Confidence** column on the tests list now shows each test's real statistical confidence instead of always reading `0.0%`. It is computed with the same significance test the test detail view and exports already use, so the number in the list matches what you see when you open the test (e.g. a test the detail view calls a 95% winner now reads 95% in the list too). Tests without enough data yet — any non-control variant needs at least 30 impressions — still show `0.0%`.
* Change: Removed the **Conversion Rate** column from the tests list. A conversion rate shown without its control baseline is not actionable at a glance; the Confidence column is the signal that tells you whether a test has a result worth opening. Per-variant conversion rates are still shown on the test detail view and in the HTML/CSV exports.
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.3 to match the plugin version (per the 2.3.9 sync convention).

= 2.5.2 =
* Fix: Conversion tracking on bare vs `www.` host variants (PR #49). Non-pageview conversions (click, form submit, custom event, add-to-cart) were silently dropped when a test's configured page URL used one host form (e.g. `example.com`) but the visitor was served the same page on the other (`www.example.com`), or vice versa. The frontend activates tests by path only, so those visitors saw variants and recorded impressions, but the conversion AJAX failed the server-side page-scope check and lost the conversion with no error. Root cause: `ElementTest_Frontend::check_active_tests()` strips protocol and host for delivery matching, while `ElementTest_Ajax_Handler::normalize_conversion_url()` compared the full host + port + path. Fix: canonicalize a leading `www.` in `normalize_conversion_url()` before the host/port/path comparison so the conversion-write check matches the host-agnostic, path-based frontend delivery. Different paths and unrelated domains still fail the check.
* Fix: Cached cross-page pageview conversion over-counting (PR #50). A full-page cache keyed only by path could serve HTML generated for a query-string-specific cross-page pageview goal (e.g. `/checkout/order-received/?key=wc_order_*`) to a later visitor on the same path with a different query string. The browser trusted the server-baked goal payload and recorded the conversion directly, producing a false conversion (and, when GA4 forwarding was enabled, a false `elementtest_converted` event) and corrupting A/B data on cached checkout/thank-you flows. Fix: cross-page conversion-only pageview goals now re-validate the live browser URL with the same matcher normal pageview goals use before recording. Triggers containing `?` or `#` still require an exact URL match.
* Fix: Client-side pageview path matching now mirrors the server (PR #50). A new path-normalization step lowercases and trims trailing slashes from both the current path and the trigger before comparison, so the cached-safe client re-check never rejects a conversion the server already approved over a trailing-slash or letter-case difference.
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.2 to match the plugin version (per the 2.3.9 sync convention).

= 2.5.1 =
* (2.5.0 was prepared but never tagged or shipped; its entries are captured into 2.5.1 below. The 2.5.0 → 2.5.1 bump rolls in a WooCommerce-specific Add-to-Cart conversion fix discovered during manual validation on a production site — the original 2.5.0 added gtag forwarding only inside `trackConversion()` and missed the parallel `trackAddToCartConversion()` codepath, so WC Add-to-Cart goals fired correctly to the plugin DB but never reached GA4. The 2.5.1 fix extracts a shared `fireGa4ConversionEvent()` helper called from both conversion-firing functions, so every goal type now emits the `elementtest_converted` event identically.)
* Feature: GA4 custom event forwarding for variant conversions. When the new "Enable GA4 Events" setting is on in **ElementTest → Settings → Google Analytics 4 Integration**, every variant conversion fires a `gtag('event', 'elementtest_converted', { test_id, test_name, variant_id, variant_name, revenue_value, transport_type: 'beacon' })` call in parallel with the existing plugin-DB conversion record. Events arrive in GA4 DebugView within ~30 seconds and become sliceable in GA4 reports once you register the event parameters as custom dimensions in the GA4 admin (the plugin does NOT register them for you — see the description text under the GA4 settings checkbox). The `transport_type: 'beacon'` flag is the load-bearing detail: the plugin's existing conversion AJAX uses `navigator.sendBeacon` so click and form-submit conversion goals survive the immediate navigation that follows; without matching beacon transport on the gtag call, those goal types would drop a large fraction of events to GA4. Gated on `window.gtag` being defined AND the saved `ga4_enabled` flag — when either is false the call is a no-op and the conversion still records to the plugin DB as before, so admin-context pages or consent-blocked frontends never break conversion tracking. The plugin does NOT load `gtag.js` itself; it only piggybacks on an existing GA4 tag (e.g., one loaded by WooCommerce Google Analytics Pro, Site Kit, or a tag manager). Conversion-only in this release — variant impression events (`elementtest_variant_viewed`) are planned for a follow-up release.
* Feature: "Run diagnostic" button on the GA4 Integration settings panel. Admin-only (settings page is `manage_options`-gated). Reports `typeof window.gtag` in the admin-page context and the configured Measurement ID, then attempts to fire a hardcoded `elementtest_diagnostic_test` event so admins can confirm GA4 receives events before relying on the live conversion path. Output is color-coded and explains the front-end console verification step when admin-context gtag is undefined — which is common, since most sites only load `gtag.js` on the front-end.
* UX: PII warning displayed next to the Test Name and Variant Name input fields when GA4 forwarding is enabled. GA4 explicitly disallows PII (emails, names, etc.) in event parameter values; placing the warning at the point of authorship rather than in settings copy reduces the risk that a test or variant authored months after GA4 was enabled silently leaks PII to Google.
* Note: GA4 custom event parameters (`test_id`, `test_name`, `variant_id`, `variant_name`, `revenue_value`) appear in GA4 DebugView and Realtime immediately, but they will NOT show up as columns in standard GA4 reports until you register them as **custom dimensions** in the GA4 admin (**Admin → Custom definitions → Create custom dimensions**). The plugin does not automate this step. Once registered, expect a few hours of report-processing latency before the columns are populated.
* Fix: Query-string wildcard pageview matching (bundled from PR #45 merged into main during this release cycle). Trigger URLs ending in `*` that include a `?` or `#` prefix now match correctly against the current request URL in `frontend.js` and `class-frontend.php`. Fixes false negatives where the conversion would not register even though the visitor reached the configured page.
* Internal: JS `VERSION` constant in `assets/js/frontend.js` synced to 2.5.0 to match the plugin version (per the 2.3.9 sync convention).

= 2.4.4 =
* Tooling: Admin-only `?et_force=` query-parameter override for variant assignment. Lets logged-in admins (`manage_options`) deterministically preview any variant for QA — `?et_force=control` selects the Control variant of every test on the page, `?et_force=<variant_id>` selects a specific variant by ID. The forced assignment is written to the existing `elementtest_variant_<test_id>` cookie so it sticks across navigation; remove the cookie (or visit the page without the parameter and let it re-roll) to resume normal random assignment. Required for QA on tests where the existing 50/50 cookie roll keeps producing the same variant on a single tester's browser. Gated server-side via a new `isAdmin` flag in the localized `elementtestFrontend` payload (`current_user_can( 'manage_options' )`) so non-admin visitors cannot bias real test data via shared URLs — anonymous traffic falls through to the normal weighted random path. Logs the forced assignment to `console.info` (or a `console.warn` if the parameter does not match any variant) so DevTools makes the override unambiguous. Requested while QA-testing test #6 on woo.dougstate.com where three consecutive 50/50 rolls all picked Variant B and the test owner needed a deterministic way to load Control without manually editing cookies in DevTools.

= 2.4.3 =
* Tooling: New WP-CLI subcommand `wp elementtest fix-variant-changes` for repairing pre-2.4.2 `wp_kses_post()`-mangled `js` and `css` variant source already in the database. The 2.4.2 fix only stopped *new* saves from being mangled; rows already in `wp_elementtest_variants` stayed corrupted (`>=` stored as `&gt;=`, `&&` as `&amp;&amp;`, `.parent > .child` selectors as `.parent &gt; .child`). The new command JOINs `wp_elementtest_variants` to `wp_elementtest_tests`, filters to `test_type` in (`css`, `js`), and decodes only the five HTML entities `wp_kses_post()` produces from JS/CSS tokens (`&amp;`, `&lt;`, `&gt;`, `&quot;`, `&#039;`) — leaving named entities like `&middot;` or `&nbsp;` intact because admins commonly embed those intentionally inside JS-built HTML strings. Defaults to dry-run; `--apply` writes; `--backup=path.json` snapshots affected rows before any UPDATE; `--show-diff` prints up to 10 changed line pairs per variant; `--type=js|css` and `--test-id=N` narrow the scan.
* Note: with this command available, the 2.4.2 release-note recommendation to "re-save each affected variant manually" is no longer the only option — `wp elementtest fix-variant-changes --apply --backup=variants-backup.json` migrates them in bulk.

= 2.4.2 =
* Fix: JavaScript variant `changes` source is no longer mangled on save. The `wp_kses_post()` sanitizer was applied uniformly to the `changes` column for every test type, but `changes` is polymorphic — it holds CSS rules, HTML, JavaScript source, or an image URL depending on `test_type`. Running JS source through `wp_kses_post()` parses it as HTML, rebalances/strips `<`, `>`, and `&`, and produces source that throws `SyntaxError` at parse time when the variant's `<script>` is appended. Sanitization is now branched on `test_type`: `copy` continues to use `wp_kses_post()`, `image` uses `esc_url_raw()`, and `css`/`js` are stored as raw source. Both call sites (`save_test()` and `import_tests()`) are gated by `manage_options`, the same capability WordPress already requires for arbitrary code via Plugins / Theme Editor, so no trust-surface change. Issue documented as a Low correctness finding in the 2026-04-06 security review.
* Note: existing `js` variants saved on 2.4.1 or earlier are still mangled in the database. Re-save each affected variant after upgrading to repopulate it from the original source. (Re-saving an `image` variant under 2.4.2 will also normalize the URL via `esc_url_raw` instead of `wp_kses_post`.)

= 2.4.1 =
* Fix: Full-URL wildcard pageview triggers (PR #43). Prefixes ending in `*` with a scheme/host no longer fall back to a loose full-URL prefix match that could incorrectly match sibling paths such as `/shopping`; path-boundary matching uses the pathname. Full-URL prefix fallback retained only when the prefix explicitly includes query or fragment. Updated `setupPageviewGoal` (`frontend.js`) and `detect_pageview_goal_tests()` (`class-frontend.php`).
* UX: Cap the test results "Performance Over Time" chart at `max-height: 500px` so the chart fits on screen on wide displays.

= 2.4.0 =
* Security: Harden AJAX handler (PR #42) — proper `$wpdb->prepare()` for dynamic ID lists when deleting orphaned variants/goals and when exporting multiple tests; stricter `proxy_page()` URL validation (HTTP/HTTPS only, case-insensitive host match, port allowlist); clamp imported goal `revenue_value` to non-negative.

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
