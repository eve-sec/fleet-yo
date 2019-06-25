<?php
if (session_status() != PHP_SESSION_ACTIVE) {
  session_start();
}

chdir(str_replace('/ajax','', getcwd()));
require_once('config.php');
require_once('loadclasses.php');

if($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
  if(@isset($_SERVER['HTTP_REFERER']) && preg_replace('/\?.*/', '', $_SERVER['HTTP_REFERER']) == str_replace('/ajax','',URL::url_path().'fleet.php'))
  {
    if(($_POST['ajtok'] == $_SESSION['ajtoken']) && ($_POST['fid'] == $_SESSION['fleetID'])) {
      $qry = DB::getConnection();
      $sql = "SELECT boss,fc FROM fleets WHERE fleetID=".$_POST['fid'];
      $result = $qry->query($sql);
      if ($result->num_rows) {
        $row = $result->fetch_assoc();
        if ($_SESSION['characterID'] == $row['fc'] || $_SESSION['characterID'] == $row['boss']) {
          $sql = "UPDATE fleets SET tracking=".$_POST['state']." WHERE fleetID=".$_POST['fid'];
          $result = $qry->query($sql);
          echo('true');
        } else {
          echo('false');
          exit;
        }
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
    echo('false');
    exit;
  }
}
else {
  echo('false');
  exit;
}
?>
