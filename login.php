<?php

require_once('config.php');
require_once('loadclasses.php');

if (session_status() != PHP_SESSION_ACTIVE) {
  session_start();
}

function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
{
    $str = '';
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
        $str .= $keyspace[random_int(0, $max)];
    }
    return $str;
}

if (isset($_GET['code'])) {
  $code = $_GET['code'];
  $state = $_GET['state'];
  if ($state != $_SESSION['authstate']) {
    $page = new Page('SSO Login');
    $html = "Error: Invalid state, aborting.";
    session_destroy();
    $page->setError($html);
    $page->display();
    exit;
  }
  $esisso = new ESISSO();
  $esisso->setCode($code);
  if (!$esisso->getError()) {
    $dbsso = new ESISSO(null, $esisso->getCharacterID());
    if (count(array_intersect($esisso->getScopes(), $dbsso->getScopes())) == count($esisso->getScopes())) {
      if (count($esisso->getScopes()) == count($dbsso->getScopes())) {
          $result = $esisso->addToDb();
      } else {
        $esisso = $dbsso;
        $result = true;
        $esisso->setMessage("You were succesfully logged in.");
      }
    } else {
      $result = $esisso->addToDb();
    }
    if ($result) {
        $_SESSION['characterID'] = $esisso->getCharacterID();
        $_SESSION['characterName'] = $esisso->getCharacterName();
        $authtoken = new AUTHTOKEN(null, $_SESSION['characterID']);
        $authtoken->addToDb();
        $authtoken->storeCookie();
        $page = new Page('SSO Login');
        if (isset($_SESSION['fleetID']) && (count(FC_PILOTS) || count(FC_CORPS) || count(FC_ALLYS))) {
            $allowed=false;
            if (in_array($_SESSION['characterID'], FC_PILOTS)) {
                $allowed=true;
            } elseif (in_array($corpID = ESIPILOT::getCorpForChar($_SESSION['characterID']), FC_CORPS)) {
                $allowed=true;
            } elseif (in_array(ESIPILOT::getAllyForCorp($corpID), FC_ALLYS)) {
                $allowed=true;
            }
            if (!$allowed) {
                unset($_SESSION['fleetID']);
                $page->setError('Only certain Pilots, Corps or Alliances are allowed to register fleets.');
                $page->display();
            }
        }

        if (isset($_GET['page'])) {
            $page->addHeader('<meta http-equiv="refresh" content="2;url='.URL::url_path().$_GET['page'].'">');
        }
        $page->setInfo($esisso->getMessage());
        $page->display();
        exit;
    }
  } else {
    $page = new Page('SSO Login');
    $page->setError($esisso->getMessage());
    $page->display();
    exit;
  }
}

if (isset($_GET['login'])) {
  if ($_GET['login'] == 'fc') {
    $scopes = array('esi-location.read_location.v1',
                    'esi-location.read_ship_type.v1',
                    'esi-universe.read_structures.v1',
                    'esi-ui.write_waypoint.v1',
                    'esi-fleets.read_fleet.v1',
                    'esi-fleets.write_fleet.v1');
  } elseif ($_GET['login'] == 'member') {
    $scopes = array('esi-location.read_location.v1', 
                    'esi-location.read_ship_type.v1', 
                    'esi-universe.read_structures.v1',
                    'esi-ui.write_waypoint.v1');
  }
  $authurl = "https://login.eveonline.com/v2/oauth/authorize/";
  $state = random_str(32);
  $_SESSION['authstate'] = $state;
  $url = $authurl."?response_type=code&redirect_uri=".rawurlencode(URL::full_url())."&client_id=".ESI_ID."&scope=".urlencode(implode(' ',$scopes))."&state=".urlencode($state);
  header('Location: '.$url);
  exit;
}
?>
