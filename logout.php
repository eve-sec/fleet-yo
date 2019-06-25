<?php

require_once('config.php');
require_once('loadclasses.php');

unset($_SESSION['characterID']);
unset($_SESSION['characterName']);
session_destroy();
$path = URL::path_only();
$server = URL::server();
setcookie(COOKIE_PREFIX, "", time()-3600, $path, $server, 1);
unset($_COOKIE[COOKIE_PREFIX]);

$page = new Page('SSO Login');
$page->setInfo("You were logged out.");
$page->display();
exit;
?>
