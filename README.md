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
* `organic_eligible_for_ads` - enable or disable ads injection, overlapping plugin settings

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
## Organic Affiliate Features Development
The Wordpress Plugin includes the following Affiliate App features:
* Insert Product Card: implemented as a [Gutenberg Block](https://developer.wordpress.org/block-editor/getting-started/create-block/).
* Insert Product Carousel: implemented as a [Gutenberg Block](https://developer.wordpress.org/block-editor/getting-started/create-block/).
* Insert Affiliate Link: implemented as a [Custom Format](https://developer.wordpress.org/block-editor/how-to-guides/format-api/).

### Building
The source code is in the [affiliate/](https://github.com/orgnc/wordpress-plugin/tree/master/affiliate/src) directory.
To build the code you'll need npm. SWP container doesn't include it by default. To install it run:
```sh
apt update && apt install -y npm
```
To build the code, run (in the affiliate/ directory):
```sh
npm install
npm run build
```
### NOTE
Wordpress doesn't support symlinks. If you develop the WP plugin by creating a symlink in the
mu-plugins directory, the Gutenberg Block won't work. Instead, you'll need to copy the code inside the
mu-plugins/wordpress-plugin directory.
## Building the zip file
build-zip.sh builds a zipped and unzipped version of the plugin in wordpress-plugin/build/.
(In other words, the wordpress-plugin/build/organic directory should be a copy of (most of) the wordpress-plugin directory, with the same file structure.)
With this in mind, to test the build locally--for instance, if you need to confirm that changes made in build-zip.sh are correct--you can:
* Delete the wordpress-plugin/build directory (if it exists).
* Run build-zip.sh in the container.
* Copy wordpress-plugin/organic (newly built) into the parent mu-plugins directory.
* Temporarily remove the wordpress-plugin directory from this mu-plugins directory. You can restore this after testing.
* Rename the organic directory (now in mu-plugins) to wordpress-plugin. Now you can test in your local SWP editor.