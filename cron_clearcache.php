<?php
chdir(dirname(__FILE__));

require_once('config.php');
require_once('loadclasses.php');

//expiry time for cache files in seconds
$expiry = 3600;
$allow_remote_usage = False;

if (php_sapi_name() !='cli' && !$allow_remote_usage) {
    $page = new Page('Access denied');
    $page->setError('This Cron job may only be run from the command line.');
    $page->display();
    exit;
}

$starttime = time();

$count = 0;
$size = 0;
$fi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('cache/'), RecursiveIteratorIterator::SELF_FIRST);
foreach ($fi as $file) {
    if ($file->isFile() and (substr($file->getFilename(), 0, 1) != '.' ) ) {
        if ($starttime > $file->getMTime()+$expiry) {
            $size += $file->getSize();
            $count += 1;
            unlink($file->getRealPath());
        }
    }
}
$di = new DirectoryIterator('cache/api/');
foreach ($di as $dir) {
    if ($dir->isDir() and (substr($dir->getFilename(), 0, 1) != '.' ) ) {
        rmdir($dir->getRealPath());
    }
}
if (php_sapi_name() !='cli') {
    $page = new Page('Cache Cleared');
    $page->addBody('Sucessfully cleared cache. Deleted '.$count. ' files with a total size of '.round($size/(1024*1024), 2).' MB.');
    $page->display();
    exit;
} else {
    echo(date("Y-m-d H:i:s").' - Sucessfully cleared cache. Deleted '.$count. ' files with a total size of '.round($size/(1024*1024), 2).' MB.');
    exit;
}
?>
