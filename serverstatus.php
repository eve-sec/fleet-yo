<?php
require_once('config.php');
require_once('loadclasses.php');

if (!ESIAPI::checkTQ()) {
  $page = new Page('TQ did not respond...');
  $page->setWarning('Tranquility did not respond in time. Maybe it\'s downtime?');
  $page->display();
  die();
}
?>

