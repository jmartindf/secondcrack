<?php
ob_start();
require_once(realpath(dirname(__FILE__) . '/..') . '/config.php');
require_once(realpath(dirname(__FILE__) ) . '/password.php');
ob_end_clean();

$auth = false;

if(isset($_COOKIE['username']) &&
   isset($_COOKIE['password']) &&
   $_COOKIE['username'] == Updater::$api_blog_username &&
   password_verify(Updater::$api_blog_password, $_COOKIE['password'])) {
     $auth = true;
}

if (!$auth && ( isset($_POST['username']) && 
    isset($_POST['password']) &&
    $_POST['username'] == Updater::$api_blog_username &&
    $_POST['password'] == Updater::$api_blog_password
  )) {
    $auth = true;
    $expire=time()+60*60*24*60;
    setcookie("username", Updater::$api_blog_username, $expire);
    setcookie("password", password_hash(Updater::$api_blog_password, PASSWORD_DEFAULT), $expire);
}

if (!$auth) {
?>
<html>
<head><meta name="viewport" content="width=device-width"><title>Login to Draft Bookmarklet</title></head>
<h1>Login</h1>
<form method="post" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>">
<label for="fUserName">User:</label><input type="text" id="fUserName" name="username" size="20" /><br />
<label for="fPassword">Password:</label><input type="password" id="fPassword" name="password" size="20" /><br />
<input type="submit" value="Login" />
<input type="hidden" name="u" value="<?php echo htmlentities($_REQUEST['u']); ?>" />
<input type="hidden" name="t" value="<?php echo htmlentities($_REQUEST['t']); ?>" />
<input type="hidden" name="s" value="<?php echo htmlentities($_REQUEST['s']); ?>" />
<input type="hidden" name="is-link" value="<?php echo htmlentities($_REQUEST['is-link']); ?>" />
</form>
<?php
} else {

$bookmarklet_code = <<<EOF
var d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,s=(e?e():(k)?k():(x?x.createRange().text:0)),l=d.location,e=encodeURIComponent;w.location.href='TARGETadd-draft.php?u='+e(l.href)+'&t='+e(d.title)+'&s='+e(s)+'&EXTRA';
EOF;

$bookmarklet_code = str_replace('TARGET', (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/', trim($bookmarklet_code));

if (! isset($_REQUEST['u'])) {
    ?>
    <p>
        <a href="javascript:<?= rawurlencode(str_replace('EXTRA', 'is-link=1', $bookmarklet_code)) ?>">Draft Link</a> &bull;
        <a href="javascript:<?= rawurlencode(str_replace('EXTRA', '', $bookmarklet_code)) ?>">Draft Article</a>
    </p>
    
    <p>
        Draft link code:<br/>
        <textarea>javascript:<?= h(rawurlencode(str_replace('EXTRA', 'is-link=1', $bookmarklet_code))) ?></textarea>
    </p>
    <p>
        Draft article code:<br/>
        <textarea>javascript:<?= h(rawurlencode(str_replace('EXTRA', '', $bookmarklet_code))) ?></textarea>
    </p>
    <?
    exit;
}

include 'pinboard-api.php';
$pb = new PinboardAPI(Updater::$pb_user, Updater::$pb_pass);

$url = substring_before(normalize_space($_REQUEST['u']), ' ');
$title = normalize_space($_REQUEST['t']);
$selection = trim($_REQUEST['s']);

$bookmarks = $pb->search_by_url($url);
if (count($bookmarks)) {
  $bookmark = $bookmarks[0];
} else {
  $bookmark = new PinboardBookmark;
  $bookmark->url = $url;
}

array_push($bookmark->tags, 'toblog', 'blogdraft');
$bookmark->title = $title;
$bookmark->description = $selection;
$bookmark->save();

$is_link = isset($_REQUEST['is-link']) && intval($_REQUEST['is-link']);
$slug = trim(preg_replace('/[^a-z0-9-]+/ms', '-', strtolower(summarize($title, 60))), '-');
if (! $slug) $slug = 'draft';

if ($selection) {
    $body = "> " . str_replace("\n", "\n> ", trim($selection)) . "\n\n";
    if (! $is_link) $body = "[$title]($url):\n\n" . $body;
} else {
    $body = '';
}

$draft_contents = 
    $title . "\n" . 
    str_repeat('=', max(10, min(40, strlen($title)))) . "\n" .
    ($is_link ? "Link: " . $url . "\n" : '') .
    "publish-not-yet\n" .
    "\n" .
    $body
;

$output_path = Updater::$source_path . '/drafts' . ($is_link ? "/links" : '');
if (! file_exists($output_path)) die("Drafts path doesn't exist: [$output_path]");
if (! is_writable($output_path)) die("Drafts path isn't writable: [$output_path]");

$output_filename = $output_path . '/' . $slug . Updater::$post_extension;
if (! file_put_contents($output_filename, $draft_contents)) die('File write failed');
if (! chmod($output_filename, 0666)) die('File permission-set failed');

$dropbox_path = "/home/secondcrack/Dropbox/secondcrack/";
$relative_path = str_replace($dropbox_path, '', $output_filename);
$editorial_path = sprintf("editorial://open/%s?root=dropbox",$relative_path);
$mac_path = sprintf("file:///Users/jmartin/Dropbox/secondcrack/%s",$relative_path);
$windows_path = sprintf("file://C:/Users/jmartin/Dropbox/secondcrack/%s",$relative_path);

// header('Content-Type: text/plain; charset=utf-8');
// echo "Saving to [$output_filename]:\n-----------------------\n$draft_contents\n------------------------\n";

?>
<html>
    <head>
        <meta http-equiv="Refresh" content="30;url=<?= h($url) ?>">
        <meta name="viewport" content="width=320"/>
        <title>Saved draft</title>
    </head>
    <body style="font: Normal 26px 'Lucida Grande', Verdana, sans-serif; text-align:center; color:#888; margin-top:100px;">
        <p>Saved.</p>
        <ul>
            <li><a href="<?php echo $editorial_path; ?>">Open in Editorial...</a></li>
            <li><a href="<?php echo $mac_path; ?>">Open in Sublime Text (Mac)...</a></li>
            <li><a href="<?php echo $windows_path; ?>">Open in Sublime Text (Windows)...</a></li>
        </ul>
        <p><a href="<?= h($url) ?>" style="font-size: 11px; color: #aaa;">redirecting back...</a></p>
    </body>
</html>
<?php
}
?>
