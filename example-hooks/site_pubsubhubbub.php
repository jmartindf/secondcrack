<?php

include("publisher.php");

class Pubsubhubbub extends SiteHook
{
  public function doHook()
  {
    foreach (Post::$hub_urls as $hub_url) {
      $p = new Publisher($hub_url);
      if($p->publish_update(Post::$feed_urls)) {
        error_log('Published via PubSubHubbub ' . $hub_url . '.');
      } else {
        error_log('Could not publish via PubSubHubbub ' . $hub_url . '.');
      }
    }
  }
}
