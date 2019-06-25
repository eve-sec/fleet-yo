<?php
require_once('config.php');
require_once('loadclasses.php');

if (session_status() != PHP_SESSION_ACTIVE) {
  session_start();
}

if (!isset($_SESSION['characterID'])) {
  $authtoken = AUTHTOKEN::getFromCookie();
  if ($authtoken) {
    if ($authtoken->verify()) {
      $_SESSION['characterID'] = $authtoken->getCharacterID();
    }
  }
}

if (isset($_SESSION['characterID']) && !isset($_SESSION['characterName'])) {
  $esipilot = new ESIPILOT($_SESSION['characterID']);
  if (!$esipilot->getRefreshToken() && !$esipilot->getRefreshToken()) {
      unset($_SESSION['characterID']);
      unset($_SESSION['characterName']);
      session_destroy();
      $path = URL::path_only();
      $server = URL::server();
      setcookie('fleetyoauth', "", time()-3600, $path, $server, 1);
      unset($_COOKIE['fleetyoauth']);
      header('Location: '.URL::url_path.'index.php');
      exit;
  }
  $_SESSION['characterName'] = $esipilot->getCharacterName();
}

if (isset($_SESSION['characterID']) && isset($_SESSION['characterName'])) {
  if (in_array($_SESSION['characterID'], unserialize(ADMINS))) {
    $_SESSION['isAdmin'] = True;
  }
}

if (!isset($_SESSION['ajtoken'])) {
      $_SESSION['ajtoken'] = EVEHELPERS::random_str(32);
}

?>
