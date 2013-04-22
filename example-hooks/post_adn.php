<?php
require_once("AppDotNetPHP/AppDotNet.php");

class AdnCredentials
{
    public static $access_token = '';
}

function postADNLink($post) {
  $adn = new AppDotNet(AdnCredentials::$access_token);
  if (isset($post['link'])) {
    $intro = "New Link Post \xE2\x86\x92 ";
  } else {
    $intro = "New Post: ";
  }
  $title = $post['post-title'];
  $data=array("entities"=>array("links"=>array(array(
    "text"=>$title,
    "url"=>$post['post-absolute-permalink'],
    "pos"=>mb_strlen($intro,'UTF-8'),
    "len"=>mb_strlen($title,'UTF-8'),
  ))));
  $adn->createPost($intro.$title,$data);
  echo "Created post: ".$intro.$title;
}

class Adn extends Hook
{
    public function doHook(Post $post)
    {
        postADNLink($post->array_for_template());
    }
}
?>
