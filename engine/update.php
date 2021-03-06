<?php
define('LOCK_FILE', isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '/tmp/secondcrack-updater.pid');
define('REBUILD', isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : '');

function rmrf($dirPath) {
  if( is_dir($dirPath) ) {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
      $path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
      echo "Cleaned up ".$path->getPathname()."\n";
    }
    rmdir($dirPath);
  }
}

// Ensure that no other instances are running
if (file_exists(LOCK_FILE) &&
    ($pid = intval(trim(file_get_contents(LOCK_FILE)))) &&
    posix_kill($pid, 0)
) {
    fwrite(STDERR, "Already running [pid $pid]\n");
    exit(1);
}

if (file_put_contents(LOCK_FILE, posix_getpid())) {
    register_shutdown_function(
        function() {
            try { unlink(LOCK_FILE); } catch (Exception $e) {
                fwrite(STDERR, "Cannot remove lock file [" . LOCK_FILE . "]: " . $e->getMessage() . "\n");
            }
        }
    );
} else {
    fwrite(STDERR, "Cannot write lock file: " . LOCK_FILE . "\n");
    exit(1);
}

$fdir = dirname(__FILE__);
require_once($fdir . '/Post.php');

$config_file = realpath(dirname(__FILE__) . '/..') . '/config.php';
if (! file_exists($config_file)) {
    fwrite(STDERR, "Missing config file [$config_file]\nsee [$config_file.default] for an example\n");
    exit(1);
}
require_once($config_file);
if ((REBUILD != '')&&isset(Updater::$rebuild_path)&&isset(Updater::$rebuild_cache)) {
  $dest = Updater::$dest_path;
  $cache = Updater::$cache_path;
  echo "Trying to rebuild the site...\n";
  Updater::$dest_path = Updater::$rebuild_path;
  Updater::$cache_path = Updater::$rebuild_cache;
  // Remove the new cache and output directories
  rmrf(Updater::$rebuild_path);
  rmrf(Updater::$rebuild_cache);
  Updater::update();
  rmrf($cache);
  rmrf($dest);
  rename(Updater::$rebuild_path, $dest);
  rename(Updater::$rebuild_cache, $cache);
  exit(Updater::$changes_were_written ? 2 : 0);
} else {
  Updater::update();
  exit(Updater::$changes_were_written ? 2 : 0);
}
