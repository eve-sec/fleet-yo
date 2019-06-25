<?php
if (session_status() != PHP_SESSION_ACTIVE) {
  session_start();
}

use Swagger\Client\ApiException;
use Swagger\Client\Api\FleetsApi;

chdir(str_replace('/ajax','', getcwd()));
require_once('config.php');
require_once('loadclasses.php');

if($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
  if(@isset($_SERVER['HTTP_REFERER']) && preg_replace('/\?.*/', '', $_SERVER['HTTP_REFERER']) == str_replace('/ajax','',URL::url_path().'fleet.php'))
  {
    if(($_POST['ajtok'] == $_SESSION['ajtoken']) && ($_POST['fid'] == $_SESSION['fleetID'])) {
      $qry = DB::getConnection();
      $sql = "SELECT boss,fc FROM fleets WHERE fleetID=".$_SESSION['fleetID'];
      $result = $qry->query($sql);
      if ($result->num_rows) {
        $row = $result->fetch_assoc();
        if ($_SESSION['characterID'] == $row['fc'] || $_SESSION['characterID'] == $row['boss']) {
          $fleet = new ESIFLEET($_SESSION['fleetID'], $row['boss']);
          if ($fleet->getError()) {
            echo('false');
            exit;
          }
          if ($fleet) {
            if ($fleet->getError()) {
              echo('false');
              exit;
            }
            $esiapi = new ESIAPI();
            $esiapi->setAccessToken($fleet->getAccessToken());
            $fleetapi = $esiapi->getApi('Fleets');
            try {
              $fleetinfo = $fleetapi->getFleetsFleetId($_SESSION['fleetID'], 'tranquility');
              $oldmotd = $fleetinfo->getMotd();
              $fleetlink = str_replace('/ajax','',URL::url_path()).'fitting.php';
              if($_POST['state'] == "true") {
                $newmotd = $oldmotd."<br/>Please submit your fitting here: ".$fleetlink;
              } else {
                $newmotd = str_replace("<br/>Please submit your fitting here: ".$fleetlink, "", $oldmotd);
              }
              $motd = new \Swagger\Client\Model\PutFleetsFleetIdNewSettings(array("motd" => $newmotd));
              $response = $fleetapi->putFleetsFleetId($_SESSION['fleetID'], $motd,'tranquility');
            } catch (ApiException $e) {
              echo('false');
              exit;
            }
            echo($newmotd);
            exit;
          } else {
            echo('false');
            exit;
          }
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
