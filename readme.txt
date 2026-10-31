=== Free Analytics ===
Contributors: katsar0v
Tags: analytics, statistics, privacy, cookieless, gdpr
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookieless, self-hosted visitor statistics for WordPress. No third parties, no IP addresses stored.

== Description ==

Free Analytics counts visitors, page views, traffic sources, clicks on contact links and form submissions, and shows them in a dashboard in wp-admin. Everything is stored in your own WordPress database. The plugin does not connect to any external service, sets no cookies and uses no browser storage.

It is designed so that no personal data is stored: the visitor is an anonymous code made from the IP address and user agent with a random salt that changes every day, and neither the IP address nor the user agent is stored. Whether you can count visitors without a consent banner depends on your jurisdiction and your setup, so check with your own legal advice.

= Features =

* Visitors, visits, page views, pages per visit and bounce rate, with the change against the previous period.
* Chart by hour, day or month; period presets and a custom date range.
* Most viewed content for every public post type (with thumbnails), most viewed pages, and a 404 report.
* Traffic sources with labels for search engines, social networks and AI assistants; Google Ads and Facebook click IDs; UTM campaigns.
* Contact clicks: phone (tel:), e-mail (mailto:), Google Maps, Viber and WhatsApp links.
* Form submissions for Bricks, Contact Form 7, Elementor Pro, WPForms, Gravity Forms and Fluent Forms.
* Devices, browsers and operating systems; online now.
* A widget on the wp-admin home screen.
* WP-CLI commands: `wp free-analytics summary` and `wp free-analytics prune`.
* Works with full-page caches (WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, Cloudflare, nginx). WP Rocket's "Delay JavaScript execution" exclusion is added automatically.
* Translations: Bulgarian is included, and a POT file is provided.
* Filters for tracking, capability, post types, item subtitles and data retention.

= How it works =

A small deferred script (about 4 KB) sends one request per page view to the REST route `free-analytics/v1/hit` on your own site. The visitor code is the first 16 hex characters of an HMAC-SHA256 of IP address, user agent and site host, with a random salt that is deleted when the next day starts. Nobody can tell whether two days' visitors were the same person, so for a period of several days "Visitors" is the sum of each day's unique visitors.

Not counted: logged-in users, bots (recognised by user agent), previews and page-builder editors. At most 30 hits per visitor per minute are accepted. Data is kept for 13 months by default.

= Requirements =

WordPress 6.5 or newer, PHP 8.1 or newer, and MySQL or MariaDB (the plugin uses MySQL-specific SQL). Logged-out visitors must be able to reach the REST route, and the web server must pass the real client IP address to PHP.

= Source code =

Development, tests, filters and the JavaScript API are documented at https://github.com/katsar0v/free-analytics

== Installation ==

1. Download `free-analytics.zip` from the Releases page on GitHub, or upload the `free-analytics` folder to `/wp-content/plugins/`. The folder must be named `free-analytics`.
2. In wp-admin go to Plugins -> Add New Plugin -> Upload Plugin, choose the zip and activate it.
3. Open Statistics in the wp-admin menu. There is nothing to set up; the database table is created automatically.

The plugin header has "Update URI: false", so WordPress does not offer updates. To update, upload the new zip over the old one ("Replace current with uploaded"). Do not use Delete for an update: it removes the data.

If your site uses a reverse proxy or a CDN, make sure the web server passes the real client IP address to PHP (mod_remoteip on Apache, the real_ip module on nginx). If you use LiteSpeed Cache, Perfmatters or FlyingPress with "delay JavaScript", add `free-analytics` and `freeAnalyticsConfig` to its exclusions.

== Frequently Asked Questions ==

= Do I need a cookie banner? =

The plugin sets no cookies, uses no browser storage and is designed so that no personal data is stored: no IP address and no user agent string are kept, and the visitor code cannot be linked across days. Whether the counting needs consent or a mention in your privacy policy depends on the law that applies to you and on the rest of your site. Check with your own legal advice. This is not legal advice.

= Why is "Visitors" over several days a sum? =

The salt behind the visitor code changes every day and the old one is deleted, so the plugin cannot know that Monday's visitor is the same person as Tuesday's. A period's "Visitors" is therefore the sum of each day's unique visitors.

= Does it slow my site down? =

The script is about 4 KB, loaded deferred in the footer, and sends one small request per page view with sendBeacon, so it does not block rendering. On the server each hit runs a handful of small indexed queries and answers with an empty 204 response.

= Does it work with caching plugins and CDNs? =

Yes. The script's settings are the same for every visitor of a URL and contain no nonce, and the beacon is a POST request that is never cached. Optimizers that delay JavaScript need `free-analytics` and `freeAnalyticsConfig` in their exclusions (WP Rocket gets them automatically). Security plugins must not block the REST route `free-analytics/v1/hit` for logged-out visitors.

= All visitors are counted as one. What is wrong? =

PHP sees the address of your proxy instead of the visitor's. Configure the web server to pass the real client IP address (mod_remoteip on Apache, real_ip on nginx). The dashboard shows a warning when it detects this.

= Why are all my pages shown as "/"? =

With plain permalinks (`/?p=123`) the stored path has no query string. Use pretty permalinks. The "Most viewed" tables per post type are not affected.

= Does it work on multisite? =

Not tested.

= How can I change who sees the statistics or how long the data is kept? =

Use the filters `free_analytics_capability` (default `manage_options`) and `free_analytics_retention_months` (default 13). All filters are documented on GitHub.

= What happens to the data when I deactivate or delete the plugin? =

Deactivating keeps the data. Deleting the plugin in wp-admin drops the table and removes the plugin's options.

== Screenshots ==

1. Dashboard (desktop)
2. Dashboard on a phone (390 px)
3. wp-admin home widget
4. Bulgarian translation

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
