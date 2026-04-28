# ElementTest Pro

A/B test various elements (CSS, copy, JS, images) of your WordPress pages and track conversion data to measure performance.

Release history lives in `readme.txt` for WordPress.org and is mirrored in `CHANGELOG.md` for GitHub readers.

## Installation

1. Upload the `elementtest-pro` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'ElementTest' in the WordPress admin menu to get started

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

## Database Tables

The plugin creates the following tables (prefixed with the WordPress table prefix):

| Table | Purpose |
|-------|---------|
| `wp_elementtest_tests` | Test configurations |
| `wp_elementtest_variants` | Variant definitions per test |
| `wp_elementtest_events` | User interaction tracking |
| `wp_elementtest_conversions` | Conversion goal definitions |

## Conversion Goal Scoping

All conversion goals -- including **Add to Cart (WooCommerce)** -- are scoped to the page the test is running on. The plugin does **not** inject scripts or track conversions on other pages.

If a test runs on `/products/my-product/`, only add-to-cart events on that page are tracked. Add-to-cart events on `/shop/`, `/cart/`, or other product pages are not captured. This is by design: it avoids injecting the frontend script site-wide, eliminates unnecessary database queries on every WooCommerce page load, and prevents timing conflicts between the plugin's anti-flicker CSS and WooCommerce's variation lifecycle JS.

Cross-page conversion tracking is supported only for **Page View** goals (e.g. tracking a thank-you page visit as a conversion for a test running on a different page).

## Reverse Proxy / CDN Setup

ElementTest uses visitor IP addresses for rate limiting and deduplication. After activation, a banner prompts you to select your hosting setup under **ElementTest > Settings > Reverse Proxy / CDN**.

| Hosting setup | Setting |
|---------------|---------|
| Cloudflare | **Cloudflare** |
| Managed hosting (GoDaddy, SiteGround, Kinsta, WP Engine, etc.) | **Nginx / Managed Hosting** |
| Dedicated / self-managed server (Apache only, no proxy) | **None** |
| Not sure | **Nginx / Managed Hosting** (falls back safely) |

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
