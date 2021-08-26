# Empire Platform
Wordpress Plugin

## Actions
* `empire_ads_txt_changed` - called after the ads.txt content has changed from syncing with Empire Platform. No args.
  
## Filters
These filters allow integrators to use non-standard attributes on Post objects to fulfill the needs
of the Synchronization to Empire Platform.

* `empire_post_id` - accepts default Post ID attribute, response registered as External ID in Empire
* `empire_post_title` - accepts default $post->title, response registered as Title in Empire
* `empire_post_url` - accepts default post Permalink (from get_permalink)
* `empire_post_content` - transform the body of the post
* `empire_post_publish_date` - transform the publish date of the post
* `empire_post_modified_date` - transform the modified date of the post
