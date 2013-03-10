<?php
include("publisher.php");

$hub_url = "https://pubsubhubbub.appspot.com/publish";
$feed_urls = array("http://minorthoughts.com/rss.xml");
$p = new Publisher($hub_url);
if($p->publish_update($feed_urls)) {
  error_log('Published via PubSubHubbub.');
} else {
  error_log('Could not publish via PubSubHubbub.');
}

?>
