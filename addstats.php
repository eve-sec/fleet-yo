<?php
$start_time = microtime(true);
require_once('auth.php');
require_once('config.php');
require_once('loadclasses.php');
require_once('serverstatus.php');

if (session_status() != PHP_SESSION_ACTIVE) {
  header('Location: '.URL::url_path.'index.php');
  die();
}

if (!isset($_SESSION['ajtoken'])) {
  $_SESSION['ajtoken'] = EVEHELPERS::random_str(32);
}

$access = false;
if(!isset($_SESSION['characterID'])) {
    $page = new Page('Acces denied...');
    $page->setError('You need to be logged in to submit fleets.');
    $page->display();
    die();
}

if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin']) {
  $admin = true;
} else {
  $admin = false;
}

if(isset($_SESSION['characterID']) && in_array($_SESSION['characterID'], FC_PILOTS)) {
    $access = true;
} elseif (in_array(ESIPILOT::getCorpForChar($_SESSION['characterID']), FC_CORPS)) {
    $access = true;
} elseif (in_array(ESIPILOT::getAllyForChar($_SESSION['characterID']), FC_ALLYS)) {
    $access = true;
}

if (!$access && !$admin) {
    $page = new Page('Acces denied...');
    $page->setError('Only certain entities are allowed to submit fleets.');
    $page->display();
    die();
}

$parse = false;
$error = false;

if(isset($_POST) && isset($_POST['submit'])) {
    if ($_POST['fc-id'] != $_SESSION['characterID'] && !$admin) {
        $page = new Page('Acces denied...');
        $page->setError('You are only allowed to submit your own fleets.');
        $page->display();
        die();
    }
    $startdate = DateTime::createFromFormat('j/n/Y H:i', $_POST['startdate'])->format('n/j/Y H:i');
    $enddate = DateTime::createFromFormat('j/n/Y H:i', $_POST['enddate'])->format('n/j/Y H:i');
    $fcname = $_POST['fc-name'];
    $fcid = $_POST['fc-id'];
    $members = $_POST['members'];
    $title = $_POST['title'];
    $stats = $_POST['stats'];
    $parse = true;
} else {
    $startdate = gmdate('n/j/Y H:i', strtotime('-1 hour'));
    $enddate = gmdate('n/j/Y H:i');
    $fcname = $_SESSION['characterName'];
    $fcid = $_SESSION['characterID'];
    $stats = 'fleet';
    $members = '';
}

if ($parse) {
    $created = DateTime::createFromFormat('j/n/Y H:i', $_POST['startdate'])->format('Y-m-d H:i:s');
    $ended = DateTime::createFromFormat('j/n/Y H:i', $_POST['enddate'])->format('Y-m-d H:i:s');
    if (DBH::fleetExists($fcid, $created, $ended)) {
        $page = new Page('Fleet already exists');
        $html = "Error: A fleet matching FC and dates already exists.";
        $page->setError($html);
        $page->display();
        die();
    }
    $members = str_replace("\r\n", "\n", $members);
    $members = str_replace("\r", "\n", $members);
    $names = explode("\n", $members);
    $esiapi = new ESIAPI();
    $universeapi = $esiapi->getApi('Universe');
    try {
        $result = json_decode($universeapi->postUniverseIds(json_encode($names), 'tranquility'), true);
    } catch (Exception $e) {
        $log = new ESILOG('log/esi.log');
        $log->exception($e);
    }
    if (isset($result['characters']) && count($result['characters']) == count($names)) {
        $fleetmembers = [$fcid => $fcname];
        foreach($result['characters'] as $m) {
            $fleetmembers[$m['id']] = $m['name'];
        }
        $fleet = new FLEETSTATS(null, $fcid, $created, $ended, $fleetmembers, $stats, $title);
        if ($fleet->getError()) {
            $page = new Page('Uhoh something went wrong...');
            $page->setError($fleet->getMessage());
            $page->display();
            die();
        } else {
            header('Location: '.URL::path_only().'viewstats.php?fleetID='.$fleet->getFleetID().'&key='.$fleet->getKey());
        }
    } else {
        $error = true;
        $message = 'Some of the names could not be resolved:<br />'.implode('<br />', array_diff($names, array_column($result['characters'], 'name')));
    }
}

$html = '<div class="row">
           <form id="addstats" method="post" action="" data-toggle="validator" role="form" name="addstats" autocomplete="off">
             <div class="col-sm-12 col-md-6 col-lg-3 form-group form-inline">
               <label for="title">Fleet title:</label>
               <input type="test" id="title" name="title" class="form-control" value="'.$fcname.'\'s fleet">
             </div>            
             <div class="col-sm-12 form-inline"><h5>Fleet options</h5>
               <div class="form-group">
                 <label for="date1">Start:</label>
                 <div class="input-group date" id="date1">
                    <input type="text" class="form-control" id="startdate" name="startdate" required/>
                    <span class="input-group-addon">
                        <span class="glyphicon glyphicon-calendar"></span>
                    </span>
                 </div>
                 <label for="date2">End:</label>
                 <div class="input-group date" id="date2">
                    <input type="text" class="form-control" id="enddate" name="enddate" required/>
                    <span class="input-group-addon">
                        <span class="glyphicon glyphicon-calendar"></span>
                    </span>
                 </div>
                 <label for="fc" class="control-label">FC:</label>
                 <div class="tt-pilot form-group" id="fc">
                   <input'.(!$admin?' disabled':'').' id="inv-name" type="text" class="typeahead pilot form-control" name="fc-name" value="'.$fcname.'" required autocomplete="new-password">
                   <input id="inv-id" type="hidden" value="'.$fcid.'" name="fc-id">
                 </div>
                       <label for="stats">Visibility:</label>
                       <select class="form-control" id="stats" name="stats">
                           <option'.($stats == 'private'?' selected':'').'>private</option>
                           <option'.($stats == 'public'?' selected':'').'>public</option>
                           <option'.($stats == 'fleet'?' selected':'').'>fleet</option>
                           <option'.($stats == 'corporation'?' selected':'').'>corporation</option>
                           <option'.($stats == 'alliance'?' selected':'').'>alliance</option>
                       </select>

               </div>
             </div>
             <div class="col-sm-12"><h5>Fleet members</h5>
               <div class="form-group col-sm-12 col-md-10 col-lg-7">
                 <label for="members" class="control-label">Paste members (one per line):</label>
                 <textarea id="members" class="textarea form-control" rows="15" name="members" required>'.$members.'</textarea>
               </div>
               <div class="col-xs-12">
                 <button type="submit" id="inv-button" class="tt-btn btn btn-primary" name="submit" value="submit">Submit</button>
               </div>
              </div>
           </form>
         </div>';
$footer = '<script type="text/javascript">
                $(function () {
                    $("#date1").datetimepicker({
                        locale: "en-gb",
                        defaultDate: "'.$startdate.'"
                    });
                    $("#date2").datetimepicker({
                        locale: "en-gb",
                        defaultDate: "'.$enddate.'"
                    });
                });
    </script>
    <script src="js/typeahead.bundle.min.js"></script>
    <script src="js/esi_autocomplete.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.13/css/dataTables.bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.datatables.net/responsive/2.1.1/css/responsive.bootstrap.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.13/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.13/js/dataTables.bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.1.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.1.1/js/responsive.bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome-animation/0.0.10/font-awesome-animation.min.css" integrity="sha256-C4J6NW3obn7eEgdECI2D1pMBTve41JFWQs0UTboJSTg=" crossorigin="anonymous" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.22.2/moment-with-locales.min.js" integrity="sha256-VrmtNHAdGzjNsUNtWYG55xxE9xDTz4gF63x/prKXKH0=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js" integrity="sha256-5YmaxAwMjIpMrVlK84Y/+NjCpKnFYa8bWWBbUHSBGfU=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.min.css" integrity="sha256-yMjaV542P+q1RnH6XByCPDfUFhmOafWbeLPmqKh11zo=" crossorigin="anonymous" />';

$page = new Page('Add Fleet stats');
$page->addHeader('<link href="css/typeaheadjs.css" rel="stylesheet">');
$page->addBody($html);
$page->addFooter($footer);
$page->setBuildTime(number_format(microtime(true) - $start_time, 3));
if ($error) {
  $page->setError($message);
}
$page->display();
exit;
?>
