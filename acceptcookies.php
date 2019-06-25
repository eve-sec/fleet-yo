<?php
require_once('loadclasses.php');
$data = json_encode(array('cookiesaccept' => 'true'));
$path = URL::path_only();
$server = URL::server();
setcookie('fleetyocookies', $data, strtotime("now")+3600*24*365, $path, $server, 0);
echo('done.');
?>
