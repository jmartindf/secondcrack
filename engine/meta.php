<?php

require_once(dirname(__FILE__) . '/Post.php');
require_once(dirname(__FILE__) . '/Hook.php');

$fdir = dirname(__FILE__);
require_once($fdir . '/Post.php');

$config_file = realpath(dirname(__FILE__) . '/..') . '/config.php';
if (! file_exists($config_file)) {
    fwrite(STDERR, "Missing config file [$config_file]\nsee [$config_file.default] for an example\n");
    exit(1);
}
require_once($config_file);

class Meta
{

  static function build($dir, &$all_tags, &$all_cats)
  {
    $out = array();
    if (is_dir($dir)) {
      if ($dh = opendir($dir)) {
        while ( ($file = readdir($dh) ) !== false) {
          if ($file[0] == '.') continue;
          $fullpath = $dir . '/' . $file;
          if (is_dir($fullpath)) {
            $out = array_merge($out, self::build($fullpath, $all_tags, $all_cats));
          } else {
            if (substr($fullpath, -(strlen(Updater::$post_extension))) == Updater::$post_extension) {
              $post = new Post($fullpath);
              $all_tags = array_merge($all_tags, $post->tags);
              $all_cats = array_merge($all_cats, $post->categories);
            }
          }
        }
        closedir($dh);
      }
    }
    $all_tags = array_unique($all_tags);
    $all_cats = array_unique($all_cats);
    return $out;
  }
}

$tags = array();
$cats = array();

Meta::build(Updater::$source_path, $tags, $cats);
asort($tags);
asort($cats);

file_put_contents(Updater::$dest_path . '/meta.json', json_encode(array('tags' => $tags, 'cats' => $cats)));
?>
