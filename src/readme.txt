=== Organic ===
Contributors: jdemaris
Tags: ads affiliate organic platform publishing
Requires at least: 5.0
Tested up to: 6.1
Stable tag: ORGANIC_PLUGIN_VERSION_VALUE
Requires PHP: 7.0
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
These filters allow integrators to use non-standard attributes on Post objects to fulfill the needs
of the Synchronization to Organic Platform.

* `organic_post_id` - accepts default Post ID attribute, response registered as External ID in Organic
* `organic_post_title` - accepts default $post->ID and $post->title, response registered as Title in Organic
* `organic_post_url` - accepts default $post->ID and post Permalink (from get_permalink)
* `organic_post_content` - transform the body of the post
* `organic_post_publish_date` - transform the publish date of the post
* `organic_post_modified_date` - transform the modified date of the post
* `organic_post_authors` - accepts array with one author info based on $post->post_author data and $post->ID; expects an array of dicts with 'externalId' and 'name' keys
* `organic_eligible_for_ads` - enable or disable ads injection, overlapping plugin settings
* `organic_eligible_for_affiliate` - enable or disable affiliate injection, overlapping plugin settings

Example Filter Implementations:
```php
function get_custom_post_id($id) {
    $ext_id = get_post_meta($id, 'custom_post_id', true);
    return $ext_id ?: $id;
}
add_filter( 'organic_post_id', 'get_custom_post_id', 10, 1);
```

```php
function get_custom_post_title($title, $id) {
    $title = 'Our Brand | ' . $title;
    return $title;
}
add_filter( 'organic_post_title', 'get_custom_post_title', 10, 2);
```


== Changelog ==
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