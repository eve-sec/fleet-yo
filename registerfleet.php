<?php
require_once('auth.php');
require_once('config.php');
require_once('loadclasses.php');
require_once('serverstatus.php');

$page = new Page('Register Fleet');

if (!isset($_SESSION['characterID'])) {
  header('Location: '.URL::url_path().'login.php?login=fc&page=registerfleet.php');
  exit;
}

if (count(FC_PILOTS) || count(FC_CORPS) || count(FC_ALLYS)) {
    $allowed=false;
    if (in_array($_SESSION['characterID'], FC_PILOTS)) {
        $allowed=true;
    } elseif (in_array($corpID = ESIPILOT::getCorpForChar($_SESSION['characterID']), FC_CORPS)) {
        $allowed=true;
    } elseif (in_array(ESIPILOT::getAllyForCorp($corpID), FC_ALLYS)) {
        $allowed=true;
    }
    if (!$allowed) {
        $page->setError('Only certain Pilots, Corps or Alliances are allowed to register fleets.');
        $page->display();
        exit;
    }
}

$pilot = new ESIPILOT($_SESSION['characterID']);
$scopes = $pilot->getScopes();
if (!(in_array('esi-fleets.read_fleet.v1', $scopes) && in_array( 'esi-fleets.write_fleet.v1', $scopes))) {
  header('Location: '.URL::url_path().'login.php?login=fc&page=registerfleet.php');
  exit;
}

$fleetID = $pilot->getFleetID();
if (!$fleetID) {
    $page->setError('You don\'t seem to be in fleet. You need to be Fleet Boss to register a fleet.');
    $page->display();
    exit;
}
$fleet = new ESIFLEET($fleetID, $_SESSION['characterID']);
if (!$fleet || $fleet->getError()) {
  $page->setError('Something went wrong. You don\'t seem to be fleet boss.');
  $page->display();
  exit;
}

$_SESSION['fleetID'] = $fleetid;
header('Location: '.URL::url_path().'fleet.php');
exit;
?>
