<?php
require_once("facebook-php-sdk/src/facebook.php");
//-- App information --//
$app_id     = "165207016975430";
$app_secret = "8d9163b9977fd91070f340e14064c015";
$my_url     = "https://admin.minorthoughts.com/fb_tokens.php";

//-- Start a session --//
session_start();

//-- Check if short-term code exists --// 
$code = $_REQUEST["code"];

//-- Redirect to Facebook if the user has not yet granted permission --//
if(empty($code))
{
  //-- CSRF protection --//
  $_SESSION['state'] = md5(uniqid(rand(), TRUE));

  //-- URL to request permission --//
  $dialog_url = "https://www.facebook.com/dialog/oauth?client_id="
  . $app_id . "&redirect_uri=" . urlencode($my_url) . "&state="
  . $_SESSION['state'] . "&scope=manage_pages,publish_stream";
  echo("<script> top.location.href='" . $dialog_url . "'</script>");
}

//-- This grabs redirect info if the user has granted permission --//
if($_SESSION['state'] && ($_SESSION['state'] === $_REQUEST['state']))
{
  //-- URL to request long-term (60 days) access token --//
  $token_url = "https://graph.facebook.com/oauth/access_token?"
  . "client_id=" . $app_id . "&redirect_uri=" . urlencode($my_url)
  . "&client_secret=" . $app_secret . "&code=" . $code;
  //-- This grabs the long-term token and expiration time (in seconds) --//
  $response = file_get_contents($token_url);
  $params = null;
  parse_str($response, $params);
  $_SESSION['access_token'] = $params['access_token'];
  $_SESSION['expires']      = $params['expires'];

  //-- Print access token and expiration date --//
  echo 'Token: '. $_SESSION['access_token'] .'<br>';
  $expireDate = time() + $_SESSION['expires']; 
  echo 'Expiration: '. date('Y-m-d', $expireDate) .'<br>';

  $graph_url = "https://graph.facebook.com/me/accounts?access_token=" . $_SESSION['access_token'];
  $accounts = json_decode(file_get_contents($graph_url));
  echo "<pre>" . print_r($accounts, true) . "</pre>";
  foreach($accounts['data'] as $account){
    $id = $account['id'];
    $name = $account['name'];
    $ACCESS_TOKEN = $account['access_token'];
    $expires = date('Y-m-d', time() + $account['expires']);
    echo "<p>Account ID: $id<br />Name: $name<br />Page Access Token: $ACCESS_TOKEN<br />Expires: $expires</p>";
  }


  //-- Store access token and expiration date in database --//
  // $mysql_host     = "Your Host Name";
  // $mysql_user     = "Your User Name";
  // $mysql_password = "Your Password";
  // $mysql_db       = "Your DB name";
  // $mysql_table    = "Your Table";
  // if (!$mysql_link = mysql_connect($mysql_host, $mysql_user, $mysql_password))
  // {
  //   echo 'Could not connect to mysql';
  //   exit;
  // }
  // if (!mysql_select_db($mysql_db, $mysql_link))
  // {
  //   echo 'Could not select database';
  //   exit;
  // }
  // $query = "INSERT INTO $mysql_table (token, expire) VALUES (\\"".$_SESSION['access_token']."\\",\\"$expireDate\\")";
  // if (!mysql_query($query,$mysql_link))
  // {
  //   echo 'Error: ' .mysql_error();
  //   exit;
  // }
  // echo "Stored token and expiration date in database"
}
else
{
  echo("The state does not match. You may be a victim of CSRF.");
}
?>
