<?php
spl_autoload_register(function($class){
	require preg_replace('{\\\\|_(?!.*\\\\)}', DIRECTORY_SEPARATOR, ltrim($class, '\\')).'.php';
});

$fdir = dirname(__FILE__);
use \Michelf\MarkdownExtra;
require_once($fdir . '/Michelf/MarkdownExtra.inc.php');
require_once($fdir . '/PHPSmartyPants/smartypants.php');
require_once($fdir . '/Updater.php');
require_once($fdir . '/Template.php');
require_once($fdir . '/Utils.php');

class Post
{
    public static $blog_title = 'Untitled Blog';
    public static $blog_url   = 'http://no-idea.com/';
    public static $blog_description = 'About my blog.';
    public static $pages_in_folders = false;
    public static $category_permalinks = false;
    public static $default_category = "uncategorized";
    public static $authors = array('unk' => 'Unknown Author');
    public static $default_author = 'unk';
    public static $child_categories = array();
    public static $track_google = false;
    public static $track_mint = false;
    public static $draft_publish_now = false;
    public static $hub_urls = array();
    public static $feed_urls = array();
    public static $permalink = "/%year%/%month%/%day%/%slug";
    
    public $source_filename = '';
    public $title = '';
    public $is_draft = false;
    public $publish_now = false;
    public $timestamp = 0;
    public $slug = '';
    public $type = '';
    public $headers = array();
    public $tags = array();
    public $categories = array();
    public $body = '';
    public $author = '';

    public $year;
    public $month;
    public $day;
    public $offset_in_day;
    
    public $is_first_post_on_this_date = false; // Used for index-page rendering
    
    public static function from_files(array $filenames)
    {
        $posts = array();
        $last_date = false;
        foreach ($filenames as $f) {
            if (! file_exists($f)) continue;
            $post = new Post($f);
            $date = $post->year . $post->month . $post->day;
            if ($date != $last_date) {
                $post->is_first_post_on_this_date = true;
                $last_date = $date;
            }
            $posts[] = $post;
        }
        return $posts;
    }
    
    public static function next_sequence_number_for_day($filename_prefix)
    {
        $max = 0;
        foreach (glob($filename_prefix . '*' . Updater::$post_extension) as $filename) {
            $n = intval(substr(substring_after($filename, $filename_prefix), 0, 2));
            if ($n > $max) $max = $n; 
        }
        return str_pad($max + 1, 2, '0', STR_PAD_LEFT);
    }
    
    public function __construct($source_filename, $is_draft = -1 /* auto */)
    {
        $this->source_filename = $source_filename;
        $this->is_draft = ($is_draft === -1 ? (false !== strpos($source_filename, 'drafts/') || false !== strpos($source_filename, 'pages/')) : $is_draft);
        $ts = filemtime($source_filename);
        $now = time();
        $diff_in_minutes = ($now-$ts)/60;
        if(($diff_in_minutes>=30) && Post::$draft_publish_now) {
          $this->timestamp = time();
        } else {
          $this->timestamp = $ts;
        }

        $segments = preg_split( '/\R\R/',  trim(file_get_contents($source_filename)), 2);
        if (! isset($segments[1])) $segments[1] = '';

        if (count($segments) > 1) {
            // Read headers for Tag, Type values
            $headers = explode("\n", $segments[0]);
            $has_title_yet = false;
            foreach ($headers as $header) {
                if (isset($header[0]) && $header[0] == '=') {
                    $has_title_yet = true;
                    continue;
                }
                
                if (! $has_title_yet) {
                    $has_title_yet = true;
                    $this->title = $header;
                    continue;
                }

                if ($this->is_draft && strtolower($header) == 'publish-now') {
                    $this->publish_now = true;
                    continue;
                }
                
                $fields = explode(':', $header, 2);
                if (count($fields) < 2) continue;
                $fname = strtolower($fields[0]);
                $fields[1] = trim($fields[1]);
                if ($fname == 'tags') {
                    $this->tags = self::parse_tag_str($fields[1]);
                } else if ($fname == 'categories') {
                    $this->categories = self::parse_category_str($fields[1]);
                } else if ($fname == 'type') {
                    $this->type = str_replace('|', ' ', $fields[1]);
                } else if ($fname == 'published') {
                    $this->timestamp = strtotime($fields[1]);
                } else if ($fname == 'author') {
                    $this->author = strtolower($fields[1]);
                } else if ($fname == 'slug') {
                    $this->slug = strtolower($fields[1]);
                } else {
                    $this->headers[$fname] = $fields[1];
                }
                
                if (isset($this->headers['link'])) $this->type = 'link';
                if ($this->author==""||!isset(Post::$authors[$this->author])) $this->author = Post::$default_author;
                if(!isset($this->categories)) $this->categories = array(Post::$default_category);
                if(!isset($this->tags)) $this->tags = array();
            }
            array_shift($segments);
        }
        
        $this->body = isset($segments[0]) ? $segments[0] : '';
        
        $filename = basename($source_filename);
        $filename_datestr = substr($filename, 0, 8);
        if (is_numeric($filename_datestr)) {
            $this->year = intval(substr($filename_datestr, 0, 4));
            $this->month = intval(substr($filename_datestr, 4, 2));
            $this->day = intval(substr($filename_datestr, 6, 2));
            $this->offset_in_day = intval(substr($filename, 9, 2));
            $this->slug = ($this->slug != "") ? $this->slug : ltrim(substr($filename, 11, -(strlen(Updater::$post_extension))), '-');
        } else {
            $this->year = intval(idate('Y', $this->timestamp));
            $this->month = intval(idate('m', $this->timestamp));
            $this->day = intval(idate('d', $this->timestamp));
            $posts_in_ym = Updater::posts_in_year_month($this->year, $this->month);
            $this->offset_in_day = isset($posts_in_ym[$this->day]) ? count($posts_in_ym[$this->day]) + 1 : 1;
            $this->slug = substring_before($filename, '.');
        }
        
        if (! $this->is_draft && $source_filename != $this->expected_source_filename()) {
            error_log("Expected filename mismatch:\nfilename: $source_filename\nexpected: " . $this->expected_source_filename());
        }
    }
    
    public function expected_source_filename($for_new_file = false)
    {
        $padded_month = str_pad($this->month, 2, '0', STR_PAD_LEFT);
        $padded_day = str_pad($this->day, 2, '0', STR_PAD_LEFT);

        $prefix = 
            Updater::$source_path . '/posts/' . 
            $this->year . '/' . $padded_month . '/' .
            $this->year . $padded_month . $padded_day . '-'
        ;
        
        if ($for_new_file) $padded_offset = self::next_sequence_number_for_day($prefix);
        else $padded_offset = str_pad($this->offset_in_day, 2, '0', STR_PAD_LEFT);
        
        if ($for_new_file) {
            $slug = trim(preg_replace('/[^a-z0-9-]+/ms', '-', strtolower($this->slug)), '-');
            if (! $slug) $slug = 'post';
        } else {
            $slug = $this->slug;
        }
        
        return $prefix . $padded_offset . '-' . strtolower($slug) . Updater::$post_extension;
    }
    
    public function normalized_source()
    {
        $out_headers = $this->headers;
        
        if ($this->is_draft) unset($out_headers['published']);
        else $out_headers['published'] = date('Y-m-d h:i:sa', $this->timestamp);
        $source = $this->title . "\n" . str_repeat('=', max(10, min(40, strlen($this->title)))) . "\n";
        if ($this->author) $out_headers['author'] = $this->author;
        if ($this->type) $out_headers['type'] = $this->type;
        if ($this->tags) $out_headers['tags'] = implode(', ', $this->tags);
        if ($this->categories) $out_headers['categories'] = implode(', ', $this->categories);
        if (!array_key_exists("excerpt",$out_headers)) {
          $out_headers["excerpt"] = create_excerpt($this->body);
        }
        foreach ($out_headers as $k => $v) $source .= ucfirst($k) . ': ' . $v . "\n";
        $source .= "\n" . $this->body;
        return $source;
    }
    
    public function array_for_template()
    {
        $tags = array();
        $categories = array();

        foreach ($this->categories as $category) { $categories[$category] = array('post-category' => $category); }
        foreach ($this->tags as $tag) { $tags[$tag] = array('post-tag' => $tag); }

        $year = $this->year;
        $month = str_pad($this->month, 2, '0', STR_PAD_LEFT);
        $day = str_pad($this->day, 2, '0', STR_PAD_LEFT);

        reset($categories);
        $catstr = key($categories);
        if(array_key_exists($catstr, Post::$child_categories)) {
            $catstr = Post::$child_categories[$catstr] . "/" . $catstr;
        }
        $catstr = strtolower($catstr);

        $search = array("%year%", "%month%", "%day%", "%category%", "%slug%");
        $replace = array($year, $month, $day, $catstr, $this->slug);
        $base_uri = str_replace($search,$replace,Post::$permalink);

        return array_merge(
            $this->headers,
            array(
                'post-title' => html_entity_decode(SmartyPants($this->title), ENT_QUOTES, 'UTF-8'),
                'post-slug' => $this->slug,
                'post-timestamp' => $this->timestamp,
                'post-rss-date' => date('D, d M Y H:i:s T', $this->timestamp),
                'post-body' => SmartyPants(MarkdownExtra::defaultTransform($this->body)),
                'post-tags' => $tags,
                'post-categories' => $categories,
                'post-type' => $this->type,
                'post-permalink' => $base_uri,
                'post-permalink-or-link' => isset($this->headers['link']) && $this->headers['link'] ? $this->headers['link'] : $base_uri,
                'post-absolute-permalink' => rtrim(self::$blog_url, '/') . $base_uri,
                'post-absolute-permalink-or-link' => rtrim(self::$blog_url, '/') . (isset($this->headers['link']) && $this->headers['link'] ? $this->headers['link'] : $base_uri),

                'post-is-first-on-date' => $this->is_first_post_on_this_date ? 'yes' : '',
                'author' => $this->author,
                'author_name' => Post::$authors[$this->author]
            )
        );
    }

    public function write_page()
    {
        $t = new Template(Updater::$page_template);
        $t->content = array(
            'page-title' => html_entity_decode(SmartyPants($this->title), ENT_QUOTES, 'UTF-8'),
            'page-body' => SmartyPants(MarkdownExtra::defaultTransform($this->body)),
            'blog-title' => html_entity_decode(SmartyPants(self::$blog_title), ENT_QUOTES, 'UTF-8'),
            'blog-url' => self::$blog_url,
            'blog-description' => html_entity_decode(SmartyPants(self::$blog_description), ENT_QUOTES, 'UTF-8'),
            'page-type' => 'page',
            'archives' => array(),
            'previous_page_url' => false,
            'next_page_url' => false,
        );
        $output_html = $t->outputHTML();

        $dest_path = Updater::$dest_path;

        if(Post::$pages_in_folders) {
          $dest_path .= '/' . substr(dirname($this->source_filename),strlen(Updater::$source_path)+7); // strip out initial "/pages" + 1 for 0 based counting
          // error_log("Destination path is: ".$dest_path);
        }
        if (! file_exists($dest_path)) mkdir_as_parent_owner($dest_path, 0755, true);
        file_put_contents_as_dir_owner($dest_path . '/' . $this->slug, $output_html);
    }
    
    public function write_permalink_page($draft = false)
    {
        $post_data = $this->array_for_template();
        $post_data['post-is-first-on-date'] = true;

        $t = new Template(Updater::$permalink_template);
        $t->content = array_merge(
            $post_data, 
            array(
                'page-title' => html_entity_decode(SmartyPants(self::$blog_title), ENT_QUOTES, 'UTF-8'),
                'blog-title' => html_entity_decode(SmartyPants(self::$blog_title), ENT_QUOTES, 'UTF-8'),
                'blog-url' => self::$blog_url,
                'blog-description' => html_entity_decode(SmartyPants(self::$blog_description), ENT_QUOTES, 'UTF-8'),
                'page-type' => 'post',
                'posts' => array($post_data),
                'post' => $post_data,
                'archives' => array(),
                'previous_page_url' => false,
                'next_page_url' => false,
            )
        );
        $output_html = $t->outputHTML();

        if (! $draft || Updater::$write_public_drafts) {
            $output_www_path = $draft ? '/drafts' : dirname($post_data['post-permalink']);
            $output_path = Updater::$dest_path . $output_www_path;
            if (! file_exists($output_path)) mkdir_as_parent_owner($output_path, 0755, true);
            file_put_contents_as_dir_owner(Updater::$dest_path . ($draft ? '/drafts/' . $this->slug : $post_data['post-permalink']), $output_html);
        }
        
        if ($draft) file_put_contents_as_dir_owner(Updater::$source_path . '/drafts/_previews/' . $this->slug . '.html', $output_html);
    }
    
    public static function write_index($dest_path, $title, $type, array $posts, $template = 'main.html', $archive_array = false, $seq_count = 0, $excerpts=false)
    {
        $posts_data = array();
        foreach ($posts as $p) $posts_data[] = $p->array_for_template();

        $t = new Template($template);
        $t->content = array(
            'page-title' => html_entity_decode(SmartyPants($title), ENT_QUOTES, 'UTF-8'),
            'blog-title' => html_entity_decode(SmartyPants(self::$blog_title), ENT_QUOTES, 'UTF-8'),
            'blog-url' => self::$blog_url,
            'blog-description' => html_entity_decode(SmartyPants(self::$blog_description), ENT_QUOTES, 'UTF-8'),
            'page-type' => $type,
            'posts' => $posts_data,
            'previous_page_url' => false,
            'next_page_url' => $seq_count > 1 ? substring_after(substring_before($dest_path, '.', true), Updater::$dest_path) . '-2' : false,
            'archives' => $archive_array ? $archive_array : array(),
            'excerpts' => $excerpts,
        );
        $output_html = $t->outputHTML();
        
        $output_path = dirname($dest_path);
        if (! file_exists($output_path)) mkdir_as_parent_owner($output_path, 0755, true);
        file_put_contents_as_dir_owner($dest_path, $output_html);
    }

    public function write_index_sequence($dest_path, $title, $type, array $posts, $template = 'main.html', $archive_array = false, $posts_per_page = 20)
    {
        $sequence = 0;
        $new_dest_path = $dest_path;
        // $dest_uri = substring_after(substring_before($dest_path, '.', true), Updater::$dest_path);
        $dest_uri = substring_after(substring_after($dest_path, '/', true), Updater::$dest_path);
        $total_sequences = ceil(count($posts) / $posts_per_page);
        while ($posts) {
            $sequence++;
            $seq_array = array_splice($posts, 0, $posts_per_page);
            if ($sequence == 1) continue; // skip writing the redundant "-1" page
            
            $new_dest_path = $dest_path . '-' . $sequence . '.html';

            $posts_data = array();
            foreach ($seq_array as $p) $posts_data[] = $p->array_for_template();

            $t = new Template($template);
            $t->content = array(
                'page-title' => html_entity_decode(SmartyPants($title), ENT_QUOTES, 'UTF-8'),
                'blog-title' => html_entity_decode(SmartyPants(self::$blog_title), ENT_QUOTES, 'UTF-8'),
                'blog-url' => self::$blog_url,
                'blog-description' => html_entity_decode(SmartyPants(self::$blog_description), ENT_QUOTES, 'UTF-8'),
                'page-type' => $type,
                'posts' => $posts_data,
                'previous_page_url' => $sequence != 1 ? ($sequence == 2 ? $dest_uri : $dest_uri . '-' . ($sequence - 1)) : false,
                'next_page_url' => $sequence < $total_sequences ? $dest_uri . '-' . ($sequence + 1) : false,
                'archives' => $archive_array ? $archive_array : array(),
            );
            $output_html = $t->outputHTML();
            
            $output_path = dirname($new_dest_path);
            if (! file_exists($output_path)) mkdir_as_parent_owner($output_path, 0755, true);
            file_put_contents_as_dir_owner($new_dest_path, $output_html);
        }

        return $total_sequences;
    }

    public static function parse_tag_str($tag_str)
    {
        // Tags are comma-separated, and any spaces between multiple words in a tag will be converted to underscores for URLs
        if (! strlen($tag_str)) return array();
        $tags = array_map('trim', explode(',', strtolower($tag_str)));
        $tags = str_replace(' ', '_', $tags);
        $tags = array_unique(array_filter($tags, 'strlen'));
        sort($tags);
        return $tags;
    }

    public static function parse_category_str($category_str)
    {
        // Categories are comma-separated, and any spaces between multiple words in a category will be converted to dashes for URLs
        if (! strlen($category_str)) return array();
        $categories = array_map('trim', explode(',', strtolower($category_str)));
        $categories = str_replace(' ', '-', $categories);
        $categories = array_unique(array_filter($categories, 'strlen'));
        sort($categories);
        return $categories;
    }
}
