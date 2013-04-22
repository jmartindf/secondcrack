# A Reference to the Template Tags

## Blog Information

```php
$content['blog-title'] = 'Blog Title';
$content['blog-description'] = 'The blog’s descriptive header';
$content['blog-url'] = "http://yourblog.com/";
```

## Content

There is a ```$content``` array, for either posts or pages.

### The Loop

```php
foreach ($content['posts'] as $post)
```

The ```$content``` array has several useful nodes.

```php
$content['page-title'] = blog title, run through SmartyPants
$content['blog-title'] = blog title, run through SmartyPants
$content['blog-url'] = blog URL
$content['blog-description'] = The blog’s header description, run through SmartyPants
$content['page-type']='post';
$content['posts'] = an array of all posts
$content['post'] = the data for a single post
```

### Post

```php
if( isset($content['post']) ) {
  $content['post']['post-title'] = "Post Title";
  $content['post']['slug'] = "The post’s slug as in this-is-my-slug";
  $content['post']['post-timestamp'] = "The timestamp of the post in Unix timestamp format";
  $content['post']['post-rss-date'] = "date('D, d M Y H:i:s T', $this->timestamp)";
  $content['post']['post-body'] = "The body of the post, already filtered through Markdown and SmartPants";
  $content['post']['post-tags'] = "An array of the post’s tags. Each node is an array of the form array('post-tag' => 'the_tag')";
  $content['post']['post-categories'] = "An array of the post’s categories. Each node is an array of the form array('post-category' => 'the_category')";
  $content['post']['post-type'] = "link" or "article";
  $content['post']['post-permalink'] = "The relative permalink to the post";
  $content['post']['post-permalink-or-link'] = "The relative permalink to the article or the link to which the linkpost points";
  $content['post']['post-absolute-permalink'] = "The full URL of the permalink to the post";
  $content['post']['post-absolute-permalink-or-link'] = "The full URL of the permalink to the article or the link to which the linkpost points";
  $content['post']['post-is-first-on-date'] = "either 'yes' or ''."
  $content['post']['author'] = "The author’s short name (user name)"
  $content['post']['author_name'] = "The author’s full name"
}
```

### Page

```php
$content['page-title'] = "Page Title";
$content['page-body'] = "The body of the page, already filtered through SmartyPants and Markdown";
$content['page-type'] = "page" or "archive" or "tag" or "type";
$content['archives'] = "Page Title";
$content['previous_page_url'] = "Page Title";
$content['next_page_url'] = "Page Title";
```

