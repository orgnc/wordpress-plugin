# Organic Wordpress Plugin
https://wordpress.org/plugins/organic/

# Development
## Initial setup
1. [Install poetry](https://python-poetry.org/docs/#installation)
2. Make sure poetry will use your currently active Python
``` bash
$ poetry config virtualenvs.prefer-active-python true
```

3. Install poetry plugins
```bash
$ poetry self add poetry-dotenv-plugin
$ poetry self add poetry-pre-commit-plugin
```

4. (Optional) Before setting up project you may want [to install pyenv](https://github.com/pyenv/pyenv#installation) and use it [to configure the latest Python](https://python-poetry.org/docs/managing-environments/)
```bash
$ pyenv install 3.11
$ pyenv local 3.11
```

5. Install deps
```bash
$ peotry install
```

## Work on plugin
1. Build the dev environment
```bash
$ poetry run ./dev.py up
```

2. Lint files
```bash
$ poetry run ./dev.py lint
```

3. Shutdown dev enviroment
```bash
$ poetry run ./dev.py down
```

For more command and helpful flags check the help
```bash
$ poetry run ./dev.py --help
$ poetry run ./dev.py up --help
$ poetry run ./dev.py down --help
```

## Organic Affiliate Features Development
The Wordpress Plugin includes the following Affiliate App features:
* Insert Product Card: implemented as a [Gutenberg Block](https://developer.wordpress.org/block-editor/getting-started/create-block/).
* Insert Product Carousel: implemented as a [Gutenberg Block](https://developer.wordpress.org/block-editor/getting-started/create-block/).
* Insert Affiliate Link: implemented as a [Custom Format](https://developer.wordpress.org/block-editor/how-to-guides/format-api/).

These blocks are atomatically build on every file change after `./dev.py up` - see `docker compose logs -f --tail 20 nodejs` for logs

## Building the zip file
build-zip.sh builds a zipped and unzipped version of the plugin in wordpress-plugin/build/.
(In other words, the wordpress-plugin/build/organic directory should be a copy of (most of) the wordpress-plugin directory, with the same file structure.)
With this in mind, to test the build locally--for instance, if you need to confirm that changes made in build-zip.sh are correct--you can:
```bash
$ poetry run ./dev.py build-zip
```

## Organic Dev and SWP
If you are working on internal Organic projects and want to work with your local version add
these to your `docker-compose.override.yml` inside of your `organic-dev` repo:
```yaml
services:
  solutions-wordpress:
    volumes:
      - ./wordpress-plugin/src:/var/www/html/web/app/mu-plugins/wordpress-plugin:ro
```
Or for tesing with result of `./dev.py build-zip`
```yaml
services:
  solutions-wordpress:
    volumes:
      - ./wordpress-plugin/build/organic:/var/www/html/web/app/mu-plugins/wordpress-plugin:ro
```


# Configuration
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
