# Changelog

All notable changes to this plugin are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## 1.0.0 - 2026-10-31

Initial release.

- Cookieless page view counting with a small deferred JavaScript beacon; the visitor is a daily-salted hash of IP and user agent, and neither is stored.
- Dashboard under wp-admin -> Statistics: visitors, visits, page views, pages per visit and bounce rate, with the change against the previous period.
- Chart by hour, day or month; period presets and a custom date range.
- "Most viewed" tables for every public post type (with thumbnails), most viewed pages, and a 404 report.
- Traffic sources with search and social labels, Google Ads and Facebook click IDs, and UTM campaigns.
- Contact tracking: clicks on tel, mailto, Google Maps, Viber and WhatsApp links.
- Form submissions for Bricks, Contact Form 7, Elementor Pro, WPForms, Gravity Forms and Fluent Forms.
- Devices, browsers and operating systems; online now.
- wp-admin home widget.
- Works with full-page caches; WP Rocket "Delay JavaScript execution" exclusion is added automatically.
- WP-CLI commands `wp free-analytics summary` and `wp free-analytics prune`.
- Filters for tracking, capability, featured post types, item subtitles and retention.
- Bulgarian (bg_BG) translation and a POT file.
- Data older than 13 months is deleted daily (configurable).
