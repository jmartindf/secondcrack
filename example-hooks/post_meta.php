<?php
class Meta extends Hook
{
  private function build($dir, &$all_tags, &$all_cats)
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

  public function doHook(Post $post)
  {
    $tags = array();
    $cats = array();

    $this->build(Updater::$source_path, $tags, $cats);
    sort($tags);
    sort($cats);

    file_put_contents(Updater::$dest_path . '/meta.json', json_encode(array('tags' => $tags, 'cats' => $cats)));
    error_log('Regenerated meta data as a result of updating ' . $post->title);
  }
}
