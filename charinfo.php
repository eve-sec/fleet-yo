<?php
$start_time = microtime(true);
require_once('auth.php');
require_once('config.php');
require_once('loadclasses.php');
require_once('serverstatus.php');

if (session_status() != PHP_SESSION_ACTIVE) {
  session_start();
}

if (!isset($_SESSION['characterID'])) {
  header('Location: '.URL::url_path().'login.php?login=member&page=charinfo.php');
  $page = new Page('Character Info');
  $page->setError("You are not logged in.");
  $page->display();
  exit;
}

$html = '';

$esipilot = new ESIPILOT($_SESSION['characterID']);
$page = new Page($esipilot->getCharacterName());
if ($esipilot->getError()) {
  $page->setError($esipilot->getMessage());
}
$page->addBody(' <div class="row">
  <div class="col-sm-2 col-xs-3"><img class="img-rounded" src="https://imageserver.eveonline.com/Character/'.$esipilot->getCharacterID().'_64.jpg"></div>
  <div class="col-md-5 col-xs-6">
  <div class="row"><div class="col-sm-5">Name:</div><div class="col-sm-7">'.$esipilot->getCharacterName().'</div></div>
  <div class="row"><div class="col-sm-5">Current System:</div><div class="col-sm-7">'.$esipilot->getLocationName().'</div></div>
  <div class="row"><div class="col-sm-5">Current Station:</div><div class="col-sm-7">'.$esipilot->getStationName().'</div></div>
  <div class="row"><div class="col-sm-5">Current ship:</div><div class="col-sm-7">'.$esipilot->getShipTypeName().'</div></div>
  </div>
  <div class="col-sm-2 col-xs-3"><img class="img-rounded" src="https://imageserver.eveonline.com/Type/'.$esipilot->getShipTypeID().'_64.png"></div>
</div>');
$page->setBuildTime(number_format(microtime(true) - $start_time, 3));
$page->display();
exit;
?>
