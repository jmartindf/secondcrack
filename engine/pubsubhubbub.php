<?php
include("publisher.php");

$hub_urls = array("http://minorthoughts.superfeedr.com/","http://pubsubhubbub.appspot.com/publish");
$feed_urls = array("http://minorthoughts.com/rss.xml","http://minorthoughts.com/rss-excerpts.xml");
foreach ($hub_urls as $hub_url) {
  $p = new Publisher($hub_url);
  if($p->publish_update($feed_urls)) {
    error_log('Published via PubSubHubbub.');
  } else {
    error_log('Could not publish via PubSubHubbub.');
  }
}

?>
