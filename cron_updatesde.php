<?php
chdir(dirname(__FILE__));

require_once('config.php');
require_once('loadclasses.php');

include('sql/required.php');

$sde_base = 'https://www.fuzzwork.co.uk/dump/';
$sde_ext = '.sql.bz2';

$xcmd = '/bin/bunzip2';
$sql_bin = '/usr/bin/mysql';

$allow_remote_usage = False;

$log = new ESILOG('log/cron_sde.log');

function download($url, $target){
    $path = __DIR__.'/'.$target;
    $file_path = fopen($path,'w');
    $client = new \GuzzleHttp\Client();
    $response = $client->get($url, ['save_to' => $file_path]);
    return ['response_code'=>$response->getStatusCode(), 'name' => $target];
}

if (php_sapi_name() !='cli' && !$allow_remote_usage) {
    $page = new Page('Access denied');
    $page->setError('This Cron job may only be run from the command line.');
    $page->display();
    exit;
}

$starttime = time();

$count = 0;

if (download($sde_base.'mysql-latest.tar.bz2.md5', 'sql/current.md5')['response_code'] != 200) {
    $log->put('Failed to fetch the MD5 file.');
    exit;
}

$hash = file_get_contents("sql/current.md5");
try {
    @$oldhash = file_get_contents("sql/latest.md5");
} catch (Exception $e) {
    $oldhash = '';
}

if ($hash == $oldhash) {
    $log->put('SDE up-to-date, nothing to see here.');
    unlink(__DIR__."/sql/current.md5");
    exit;
}

$fi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('sql/'), RecursiveIteratorIterator::SELF_FIRST);
foreach ($fi as $file) {
    if ($file->isFile() 
        && (substr($file->getFilename(), 0, 1) != '.' )
        && !in_array($file->getFilename(), array('schema.sql', 'latest.md5', 'required.txt', 'required.php', 'current.md5'))
    ){
            unlink($file->getRealPath());
    }
}

$todo = SDE_TABLES;
foreach ($todo as $t) {
    if (download($sde_base.'latest/'.$t.$sde_ext, 'sql/'.$t.$sde_ext)['response_code'] != 200) {
        $log->put('Download of '.$t.$sde_ext.' failed.');
    }
}

foreach ($todo as $t) {
    exec($xcmd.' '.__DIR__.'/sql/'.$t.$sde_ext);
    @exec($sql_bin.' -u'.DB_USER.' -p'.DB_PASS.' '.DB_NAME.' < '.__DIR__.'/sql/'.$t.'.sql');
    @exec($sql_bin.' -u'.DB_USER.' -p'.DB_PASS.' '.DB_NAME.' -e "ALTER TABLE '.$t.' ENGINE = MyISAM;"');
    $count += 1;
}

try {
    unlink(__DIR__."/sql/latest.md5");
} catch (Exception $e) {
}
rename(__DIR__."/sql/current.md5", __DIR__."/sql/latest.md5");

if (php_sapi_name() !='cli') {
    $page = new Page('Cache Cleared');
    $page->addBody('Sucessfully imported '.$count. ' files.');
    $page->display();
    exit;
} else {
    $log->put('Sucessfully imported '.$count. ' files.');
    exit;
}
?>
