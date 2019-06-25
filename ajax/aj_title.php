<?php
if (session_status() != PHP_SESSION_ACTIVE) {
  session_start();
}

chdir(str_replace('/ajax','', getcwd()));
require_once('config.php');
require_once('loadclasses.php');

if($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
  if(@isset($_SERVER['HTTP_REFERER']) && ( preg_replace('/\?.*/', '', $_SERVER['HTTP_REFERER']) == str_replace('/ajax','',URL::url_path().'fleet.php') || strpos($_SERVER['HTTP_REFERER'], str_replace('/ajax','',URL::url_path().'viewstats.php') ) === 0 ) )
  {
    if(($_POST['ajtok'] == $_SESSION['ajtoken']) && isset($_POST['fid']) && isset($_POST['title']) && isset($_SESSION['characterID'])) {
        $fleet = new FLEETSTATS($_POST['fid']);
        if ($_SESSION['characterID'] == $fleet->getFC() || $_SESSION['isAdmin']) {
            $fleet->setTitle($_POST['title']);
            echo('true');
            exit;
        } else {
            echo('false');
            exit;
        }
    }
    else {
      echo('false');
      exit;
    }
  }
  else {
    echo('false1');
    exit;
  }
}
else {
  echo('false');
  exit;
}
?>
