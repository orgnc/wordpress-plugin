=== Organic ===
Contributors: jdemaris, ryandial
Tags: ads affiliate organic platform publishing
Requires at least: 5.0
Tested up to: 6.4.2
Stable tag: ORGANIC_PLUGIN_VERSION_VALUE
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Wordpress to your Organic Platform account for ads, affiliate, analytics, marketing campaigns, and more.

== Description ==

In order to fully use this plugin, you will need to contact sales@organic.ly and have your own account set up.

Features:
* Integration with Organic Ads to insert ads onto your pages in a fully controlled way with top tier monetization and reporting
* Integration with Organic Affiliate to insert affiliate links and product card onto your pages
* Integration with Organic Campaigns to match up sponsored content with direct sales campaigns

== Developer Notes ==
## Configuration
If you set the environment variable ORGANIC_ENVIRONMENT to an explicit value, you can control what kind of debug
data gets exposed. Valid values are:

- PRODUCTION = normal operation in production
- TEST = used in unit and integration testing

## Actions
* `organic_ads_txt_changed` - called after the ads.txt content has changed from syncing with Organic Platform. No args.

## Filters
* `organic_eligible_for_ads` - enable or disable ads injection, overlapping plugin settings
* `organic_eligible_for_affiliate` - enable or disable affiliate injection, overlapping plugin settings


== Changelog ==
= 1.17.0 =
* Remove content sync, content ID map sync, category sync, and related admin controls.
* Add a WP-CLI cleanup command for orphaned content sync cron hooks, options, and postmeta.
* Decouple Organic error reporting from the Sentry PHP SDK to avoid conflicts with other Sentry plugins.

= 1.13.1 =
* Fix clash with wp-sentry-integration

= 1.13.0 =
* Additional data requirements for Organic Analytics

= 1.11.1 =
* Add Admin SDK injection for "guides" post type

= 1.11.0 =
* Per-placement prefill adjustments

= 1.9.0 =
* Affiliate widget insertion fixes

= 1.7.0 =
* Fully migrate to the SDKv2

= 1.6.0 =
* Sync WP plugin data to platform

= 1.5.0 =
* Sentry integration

= 1.4.0 =
* Support for js-modules builds for up-to-date browsers
* Site custom CSS support
* A lot of fixes and optimizations

= 1.0.67 =
* Plugin refactoring to meet wordpress.org codestyle and rules

= 1.0.15 =
* Initial submission to wordpress.org with core functionality
