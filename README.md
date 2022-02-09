# Organic Platform
Wordpress Plugin

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
