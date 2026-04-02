=== Server & Site Insight ===
Contributors:      paragdas
Donate link:       https://parag.bd/donate
Tags:              system info, server info, site health, admin dashboard, php info, security, debug
Requires at least: 5.8
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        2.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A premium admin dashboard showing WordPress, server, and environment info with a 0–100 health score, historical tracking, alerts, developer mode, security checks, and export tools.

== Description ==

**Server & Site Insight** gives administrators a beautiful, information-rich dashboard to monitor every aspect of their hosting environment.

= Health Score (0–100) =
A weighted score grades your site from A+ to F. The score ring updates at a glance and the detailed breakdown shows exactly which issues cost points and how to fix them.

= System Information =
* **WordPress** — version, site/home URL, active theme, full plugin list, language, charset, multisite
* **Server** — PHP version, SAPI, server software, MySQL/MariaDB version, memory limit, upload size, execution time
* **Environment** — REST API status, WP_DEBUG, debug log, WP-Cron, HTTPS, environment type, object cache
* **Performance** — real-time memory usage bar, peak memory, query count, OPcache, gzip
* **Security** — XML-RPC status, file editor, HTTPS enforcement, DISALLOW_FILE_MODS, wp-config location, auto-updates

= Health Checks with Explanations =
Every check includes a "Why this matters" explanation and a concrete recommendation. Click **Explain** on any check to expand.

= Historical Tracking =
Daily snapshots of key metrics are stored in wp_options (lightweight circular buffer, max 30 days). A bar chart shows your score history. Change detection highlights metrics that changed since the last snapshot.

= Alerts =
* Optional **email alerts** (HTML) for critical issues — runs via WP-Cron daily
* **Admin notices** (dismissible, per-user) on all admin pages

= Developer Mode =
Toggle in Settings to reveal: PHP extensions list, total database size, DB query count, object cache type, and registered REST route count.

= Export Tools =
* **Copy Report** — full system report to clipboard as plain text
* **Export JSON** — timestamped structured JSON download
* **Print / PDF** — browser print with optimised print stylesheet; no external PDF libraries

= UI Features =
* Sticky summary bar with filter buttons (All / Warnings / Critical)
* Dark mode toggle (persisted per-browser and per-user in settings)
* Custom tooltips on every metric
* History bar chart (last 14 days)
* Double-click any table cell to copy its value
* Responsive design for all screen sizes

= REST API Endpoints =
All require Administrator authentication:
    GET /wp-json/server-site-insight/v1/info[?section=wordpress|server|environment|performance|security|developer]
    GET /wp-json/server-site-insight/v1/health
    GET /wp-json/server-site-insight/v1/score
    GET /wp-json/server-site-insight/v1/history

= Shortcode =
`[ssi_panel]` — displays a compact info table on the front end. Visible to administrators only.

= Security & Coding Standards =
* All outputs escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
* All admin pages protected by `manage_options` capability check
* `ABSPATH` guard in every PHP file
* Nonces on all form submissions and AJAX actions
* No data sent externally
* Follows WordPress Coding Standards

== Installation ==

= Automatic =
1. Go to **Plugins → Add New**.
2. Search for **Server & Site Insight**.
3. Click **Install Now**, then **Activate**.
4. Find **Insight Panel** in the admin menu.

= Manual Upload =
1. Download the plugin `.zip`.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Upload the `.zip` and activate.

= FTP =
1. Unzip and upload the `server-site-insight` folder to `/wp-content/plugins/`.
2. Activate from the **Plugins** screen.

== Frequently Asked Questions ==

= Who can see the Insight Panel? =
Only users with the `manage_options` capability (Administrators).

= Does this plugin slow down my site? =
No. All assets are loaded only on the plugin's own admin page. The daily snapshot is lightweight (one wp_options write per day).

= Does this plugin send data anywhere? =
Never. All data stays on your server.

= How do I use the shortcode? =
Add `[ssi_panel]` to any page. Non-admin visitors see nothing.

= How do I enable developer mode? =
Go to **Insight Panel → Settings** and toggle Developer Mode.

= How do email alerts work? =
Enable them in Settings. WP-Cron runs a daily check and sends an HTML email if any critical issues are found.

= How do I increase the health score? =
Click **Explain** on any failing check — each one includes a concrete recommendation.

== Screenshots ==

1. **Dashboard hero** — health score ring with grade, overall status, and change detection.
2. **Sticky bar** — always-visible summary with filter buttons and action icons.
3. **Health pills strip** — colour-coded status for all 11 checks.
4. **Card grid** — WordPress, Server, Performance, Security, Developer cards.
5. **Health check details** — accordion with explain, why it matters, and recommendation.
6. **Score history chart** — 14-day bar chart showing score trends.
7. **Settings page** — email alerts, admin notices, developer mode, and dark mode toggles.
8. **Dark mode** — full dark theme with CSS custom properties.

== Changelog ==

= 2.0.0 — 2026-03-27 =
* NEW: Health Score (0–100) with grade (A+–F) and SVG ring visualisation.
* NEW: Historical tracking — daily snapshots, change detection, 14-day bar chart.
* NEW: Email alerts (HTML) for critical issues via WP-Cron.
* NEW: Dismissible admin notices for warnings/critical issues.
* NEW: Developer Mode — PHP extensions, DB size, query count, REST routes, object cache.
* NEW: Performance card — real-time memory bar, peak memory, OPcache, query count.
* NEW: Security card — XML-RPC, file editor, wp-config location, DISALLOW_FILE_MODS.
* NEW: Health check details accordion with "Explain" + recommendations.
* NEW: Print/PDF export (browser print with optimised print stylesheet).
* NEW: Dark mode toggle (localStorage + settings persistence).
* NEW: Filter buttons — All / Warnings / Critical.
* NEW: Custom tooltip system for all metrics.
* NEW: Double-click any table cell to copy its value.
* NEW: Settings page with toggle switches.
* NEW: REST API endpoints: /score, /history.
* NEW: Sticky summary bar.
* IMPROVED: Modular class structure with Settings, Tracker, Alerts, Health Score classes.
* IMPROVED: All checks now include explain + recommendation fields.

= 1.0.0 — 2026-03-27 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Major feature release. No data migration needed — new tracking starts fresh on activation.

== Privacy Policy ==

Server & Site Insight does not collect, store, or transmit any personal data. All system information is gathered from your local server environment and displayed exclusively within your WordPress admin to authorised administrators. No data is ever sent to any third-party service.
