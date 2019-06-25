<?php
$start_time = microtime(true);
$error = false;
require_once('auth.php');
require_once('config.php');
require_once('loadclasses.php');
require_once('serverstatus.php');

if (session_status() != PHP_SESSION_ACTIVE) {
  header('Location: '.URL::url_path.'index.php');
  die();
}

if (!isset($_GET['fleetID']) || (!isset($_GET['key']) && !(isset($_SESSION['isAdmin']) && $_SESSION['isAdmin']) )) {
  $page = new Page('Something went wrong...');
  $page->setError('Missing Information');
  $page->display();
  die();  
}

$fleet = new FLEETSTATS($_GET['fleetID']);

$canChange = false;

if(!(isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'])) {
    if ($_GET['key'] != $fleet->getKey()) {
      $page = new Page('Acces denied...');
      $page->setError('You don\'t have access to view this fleet.');
      $page->display();
      die();
    }
    
    if ($fleet->getVisibility() == 'private') {
      if(!isset($_SESSION['characterID']) || $_SESSION['characterID'] != $fleet->getFC()) {
        $page = new Page('Acces denied...');
        $page->setError('These are private statistics, if you are the FC, are you logged in?.');
        $page->display();
        die();
      }
    } elseif ($fleet->getVisibility() == 'corporation') {
      if(!isset($_SESSION['characterID'])) {
        $page = new Page('Acces denied...');
        $page->setError('These statistics are available to certain entities only, are you logged in?');
        $page->display();
        die();
      }
      if(ESIPILOT::getCorpForChar($_SESSION['characterID']) != $fleet->getFCCorporation()) {
        $page = new Page('Acces denied...');
        $page->setError('You don\'t have access to view this fleet.');
        $page->display();
        die();
      }
    } elseif ($fleet->getVisibility() == 'alliance') {
      if(!isset($_SESSION['characterID'])) {
        $page = new Page('Acces denied...');
        $page->setError('These statistics are available to certain entities only, are you logged in?');
        $page->display();
        die();
      }
      $alliance = ESIPILOT::getAllyForChar($_SESSION['characterID']);
      if($alliance == 0 || $alliance != $fleet->getFCAlliance()) {
        $page = new Page('Acces denied...');
        $page->setError('You don\'t have access to view this fleet.');
        $page->display();
        die();
      }
    } elseif ($fleet->getVisibility() == 'fleet') {
      if(!isset($_SESSION['characterID'])) {
        $page = new Page('Acces denied...');
        $page->setError('These statistics are available to fleet members, are you logged in?');
        $page->display();
        die();
      }
      if(!in_array($_SESSION['characterID'], array_keys($fleet->getMembers()))) {
        $page = new Page('Acces denied...');
        $page->setError('You don\'t have access to view this fleet.');
        $page->display();
        die();
      }
    }
} else {
    $canChange = true;
}

if(isset($_SESSION['characterID']) && $_SESSION['characterID'] == $fleet->getFC()) {
    $canChange = true;
}

if ($canChange && isset($_POST['update']) && $_POST['update'] == 'update') {
    $fleet->clearKills();
    $fleet->fetchKills();
    $fleet->updateStats();
    header('Location: '.URL::full_url());
}


function formatIsk($value) {
    if ($value > 1000000000) {
        return round($value/1000000000, 2).'b';
    } elseif ($value > 1000000) {
        return round($value/1000000, 1).'m';
    } elseif ($value > 1000) {
        return round($value/1000, 0).'k';
    } else {
         return round($value, 0);
    }
}

function fleetHeader() {
    global $fleet;
    global $canChange;
    $html = '
    <div class="well well-sm h6">
      <div class="row" style="padding-top: 7px;">
        <div class="col-xs-3 col-sm-2 col-md-1">
            FC: 
        </div>
        <div class="col-xs-9 col-sm-4 col-md-3 col-lg-2">
            <img class="img img-rounded" style="float: left; margin: -7px 4px 0 0;" src="https://imageserver.eveonline.com/Character/'.$fleet->getFC().'_32.jpg">&nbsp;'.$fleet->getFCName().'
        </div>
        <div class="col-xs-3 col-sm-2 col-md-1">
            Start:
        </div>
        <div class="col-xs-9 col-sm-4 col-md-3 col-lg-2">
            '.$fleet->getCreated().'
        </div>
        <div class="col-xs-3 col-sm-2 col-md-1">
            End:
        </div>
        <div class="col-xs-9 col-sm-4 col-md-3 col-lg-2">
            '.$fleet->getEnded().'
        </div>
        <div class="col-xs-3 col-sm-2 col-md-1">
            Pilots:
        </div>
        <div class="col-xs-9 col-sm-4 col-md-3 col-lg-2">
            '.count($fleet->getMembers()).'
        </div>
      </div>
    </div>
    <div>
      <div class="row">
        '.($canChange?'<div class="col-xs-12 col-md-4 col-lg-4 text-right"></div>
        <div class="col-md-6 col-lg-4 form-group form-inline text-right">
            <label for="fleettitle">Fleet title:</label>
            <input id="fleettitle" type="text" class="form-control" placeholder="Enter title">
            <button type="button" class="tt-btn btn btn-primary" onclick="fleettitle()"><span class="glyphicon glyphicon-floppy-disk"></span></button>
        </div>':'').'
        <div class="col-xs-6 col-md-2 col-lg-1 text-right">
          <button type="button" class="btn btn-primary" onclick="linkToClipboard(\''.URL::full_url_noq().'?fleetID='.$fleet->getFleetID().'&key='.$fleet->getKey().'\');">Copy Link</button>
        </div>';
if ($canChange) {
$html .= '<div class="col-xs-6 col-md-2 col-lg-1 text-right">
            <form id="updatefleet" method="post" action="" data-toggle="validator" role="form" name="updateflet" autocomplete="off">
              <button type="submit" name="update" value="update" class="btn btn-primary">Re-fetch</button>
            </form>
          </div>
          <div class="col-xs-6 col-md-4 col-lg-2">
              <div class="form-group form-inline">
                <label for="visibility">Visibility:</label>
                <select class="form-control" id="visibility" onchange="setVisibility(this)">
                    <option'.($fleet->getVisibility() == 'private'?' selected':'').'>private</option>
                    <option'.($fleet->getVisibility() == 'public'?' selected':'').'>public</option>
                    <option'.($fleet->getVisibility() == 'fleet'?' selected':'').'>fleet</option>
                    <option'.($fleet->getVisibility() == 'corporation'?' selected':'').'>corporation</option>
                    <option'.($fleet->getVisibility() == 'alliance'?' selected':'').'>alliance</option>
                </select>
              </div>
          </div>
        <script>
        function setVisibility(select) {
            var stats = $(select).val();
            $.ajax({
                type: "POST",
                url: "'.URL::url_path().'ajax/aj_statsvisibility.php",
                data: {"fid" : '.$fleet->getFleetID().', "ajtok" : "'.$_SESSION['ajtoken'].'", "stats" : stats},
                success:function(data)
                {
                  if (data !== "true") {
                      BootstrapDialog.show({message: "something went wrong", type: BootstrapDialog.TYPE_WARNING});
                  }
                }
                });
        }
        function fleettitle() {
            var title = $("#fleettitle").val();
            $.ajax({
                type: "POST",
                url: "'.URL::url_path().'ajax/aj_title.php",
                data: {"fid" : '.$fleet->getFleetID().', "ajtok" : "'.$_SESSION['ajtoken'].'", "title" : title},
                success:function(data)
                {
                  if (data !== "true") {
                      BootstrapDialog.show({message: "something went wrong", type: BootstrapDialog.TYPE_WARNING});
                  } else {
                      $("#pagetitle").text(title+" on '.$fleet->getCreated().'");
                  }
                }
                });
        }
        </script>';
}
$html .= '</div>
    </div>';
    return $html;
}

function tabBar($p='corps') {
    $html = '<ul class="nav nav-tabs">
        <li'.($p=='overview'?' class="active"':'').'><a href="viewstats.php?fleetID='.URL::getQ('fleetID').'&key='.URL::getQ('key').'&p=overview">Overview</a></li>
        <li'.($p=='pilots'?' class="active"':'').'><a href="viewstats.php?fleetID='.URL::getQ('fleetID').'&key='.URL::getQ('key').'&p=pilots">Pilots</a></li>
        <li'.($p=='kills'?' class="active"':'').'><a href="viewstats.php?fleetID='.URL::getQ('fleetID').'&key='.URL::getQ('key').'&p=kills">Kills & Losses</a></li>
        <li'.($p=='ships'?' class="active"':'').'><a href="viewstats.php?fleetID='.URL::getQ('fleetID').'&key='.URL::getQ('key').'&p=ships">Ships</a></li>
        <li'.($p=='enemies'?' class="active"':'').'><a href="viewstats.php?fleetID='.URL::getQ('fleetID').'&key='.URL::getQ('key').'&p=enemies">Enemies</a></li>
        <li'.($p=='timeline'?' class="active"':'').'><a href="viewstats.php?fleetID='.URL::getQ('fleetID').'&key='.URL::getQ('key').'&p=timeline">Timeline</a></li>
    </ul>';
    return $html;
}

function overview() {
    global $fleet;
    if ($fleet->getStats()['iskDestroyed'] == 0 && $fleet->getStats()['iskLost'] == 0) {
        $eff = 50;
    } else {
        $eff = $fleet->getStats()['iskDestroyed']*100/($fleet->getStats()['iskDestroyed']+$fleet->getStats()['iskLost']);
    }
    $html = '<table class="table table-striped small" id="nottable">
        <tbody>
            <tr><td>Kills</td><td>'.$fleet->getStats()['kills'].'</td></tr>
            <tr><td>Losses</td><td>'.$fleet->getStats()['losses'].'</td></tr>
            <tr><td>ISK destroyed</td><td>'.formatIsk($fleet->getStats()['iskDestroyed']).'</td></tr>
            <tr><td>ISK lost</td><td>'.formatIsk($fleet->getStats()['iskLost']).'</td></tr>
            <tr><td>Damage dealt</td><td>'.number_format($fleet->getStats()['dmgDone']).'</td></tr>
            <tr><td style="vertical-align: middle;">ISK efficiency</td><td><div style="float:left; margin-top: -15px;"><canvas class="percChart" style="height: 40px; width: 40px;" value="'.round($eff, 0).'"></canvas></div><div>'.round($eff, 1).' %</div></td></tr>
        </tbody>
    </table>
    <h5>Top Kills & losses</h5>
    <div class="row">';
    $killmails = $fleet->getKillmails(5);
    if (!count($killmails)) {
        $html .= '<div class="col-xs-12"><span>no kills.</span></div>';
    }
    foreach ($killmails as $id => $k) {
        $html .= '<div class="col-xs-6 col-sm-4 col-lg-2 text-center">
                      <a style="text-decoration: none;" href="https://zkillboard.com/kill/'.$id.'/" target="_blank"><div class="well well-sm">';
        $html .= '<span class="table">'.$k['shipName'].'<br /><img class="img img-rounded" style="margin: 6px;" src="https://imageserver.eveonline.com/Render/'.$k['shipID'].'_128.png"><br />';
        $html .= $k['name'].'<br />';
        if (isset($k['allianceID']) && $k['allianceID']) {
            $html .= $k['allianceName'].'<br />';
        } else {
            $html .= $k['corporationName'].'<br /></span>';
        }
        $html .= '<span class="h6 text-'.($k['type'] == 'kill'?'success':'danger').'">'.formatIsk($k['value']).'<span>';
        $html .= '</div></a></div>';
    }
    $ships = $fleet->getShipsUsed();
    usort($ships, function($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    $totalShips = array_sum(array_column($ships, 'count'));
    $shipdata = array();
    $max = false;
    foreach ($ships as $i => $ship) {
        if ($i <= 5 && $ship['count'] >= $totalShips/20) {
            $shipdata[$ship['shipName']] = $ship['count'];
        } else {
            if (isset($shipdata['others'])) {
                $shipdata['others'] += $ship['count'];
            } else {
                $shipdata['others'] = $ship['count'];
            }
        }
    }
    $shipclasses = array(); 
    $temp = DBH::addShipClasses($ships);
    foreach ($temp as $s) {
        if (isset($shipclasses[$s['shipClass']])) {
            $shipclasses[$s['shipClass']] += $s['count'];
        } else {
            $shipclasses[$s['shipClass']] = $s['count'];
        }
    }
    arsort($shipclasses);
    $classdata = array();
    $i = 1;
    foreach ($shipclasses as $name => $count) {
        if ($i < 5) {
            $classdata[$name] = $count;
        } else {
            if (isset($classdata['others'])) {
                $classdata['others'] += $count;
            } else {
                $classdata['others'] = $count;
            }
        }
        $i += 1;
    }
    $html .= '</div>
              <h5>Composition</h5>
              <div class=row>
                  <div class="col-xs-12 col-md-6 text-center">
                      <div class="well well-sm" style="width: 100%;">
                          <span class="h6">By ship:<br /><br /></span>
                          <div>
                              <canvas height="300" id="shipsused"></canvas>
                          </div>
                      </div>
                  </div>
                  <div class="col-xs-12 col-md-6 text-center">
                      <div class="well well-sm" style="width: 100%;">
                          <span class="h6">By ship class:<br /><br /></span>
                          <div>
                              <canvas height="300" id="classesused"></canvas>
                          </div>
                      </div>
                  </div>
              </div>
              <script>
        var shipOptions = {
            responsive: true,
            showAllTooltips: true,
            maintainAspectRatio: false,
            elements: {
                arc: {
                    borderWidth: 0
                }
            },
            tooltips: {
                enabled: false,
            },
            legend: {
                //display: false,
                position: "bottom",
            },
        }
        var shipCounts = ['.implode(', ', $shipdata).']
        var shipLabels = ["'.implode('", "', array_keys($shipdata)).'"]
        var classCounts = ['.implode(', ', $classdata).']
        var classLabels = ["'.implode('", "', array_keys($classdata)).'"]


                   function ttbefore(chart) {
                     if (chart.config.options.showAllTooltips) {
                         // create an array of tooltips
                         // we cant use the chart tooltip because there is only one tooltip per chart
                         chart.pluginTooltips = [];
                         chart.config.data.datasets.forEach(function (dataset, i) {
                             chart.getDatasetMeta(i).data.forEach(function (sector, j) {
                                 var tt = new Chart.Tooltip({
                                     _chart: chart.chart,
                                     _chartInstance: chart,
                                     _data: chart.data,
                                     _options: chart.options.tooltips,
                                     _active: [sector]
                                 }, chart);
                                 chart.pluginTooltips.push(tt);
                             });
                         });
                   
                         // turn off normal tooltips
                         chart.options.tooltips.enabled = false;
                     }
                   }
                   function ttafter(chart, easing) {
                     if (chart.config.options.showAllTooltips) {
                         // we dont want the permanent tooltips to animate, so dont do anything till the animation runs atleast once
                         if (!chart.allTooltipsOnce) {
                             if (easing !== 1)
                                 return;
                             chart.allTooltipsOnce = true;
                         }
                   
                         // turn on tooltips
                         chart.options.tooltips.enabled = true;
                         Chart.helpers.each(chart.pluginTooltips, function (tooltip) {
                             tooltip.initialize();
                             tooltip.update();
                             if (tooltip._model.xAlign == "left") {
                                 tooltip._model.xAlign = "right";
                                 tooltip._model.x -= tooltip._model.width*1.1;
                             } else {
                                 tooltip._model.xAlign = "left";
                                 tooltip._model.x += tooltip._model.width*1.1;
                             }
                             // we dont actually need this since we are not animating tooltips
                             tooltip.pivot();
                             tooltip.transition(easing).draw();
                         });
                         chart.options.tooltips.enabled = false;
                     }
                   }

            window.addEventListener("load",function() {
                var ctx = $("#shipsused")[0].getContext("2d");
                window.myPie = new Chart(ctx, {
                    type: "doughnut",
                    plugins: [{
                        beforeRender: ttbefore,
                        afterDraw: ttafter,
                    }],
                    data: {
                        datasets: [{
                            data: shipCounts,
                            backgroundColor: [
                                window.chartColors.c1,
                                window.chartColors.c2,
                                window.chartColors.c3,
                                window.chartColors.c4,
                                window.chartColors.c5,
                                window.chartColors.c6,
                            ],
                        }],
                        labels: shipLabels,
                    },
                    options: shipOptions,
                });
                var ctx = $("#classesused")[0].getContext("2d");
                window.myPie = new Chart(ctx, {
                    type: "doughnut",
                    plugins: [{
                        beforeRender: ttbefore,
                        afterDraw: ttafter,
                    }],
                    data: {
                        datasets: [{
                            data: classCounts,
                            backgroundColor: [
                                window.chartColors.c1,
                                window.chartColors.c2,
                                window.chartColors.c3,
                                window.chartColors.c4,
                                window.chartColors.c5,
                                window.chartColors.c6,
                            ],
                        }],
                        labels: classLabels,
                    },
                    options: shipOptions,
                });
            }, false);
        </script>';
    return $html;
}

function pilots() {
    global $fleet;
    global $canChange;
    $totalKills = $fleet->getStats()['kills'];
    $totalLosses = $fleet->getStats()['losses'];
    $totalDmg = $fleet->getStats()['dmgDone'];
    $html = '';
    if ($canChange) {
        $html .= '<div class="well well-sm tt-pilot form">
           <link href="css/typeaheadjs.css" rel="stylesheet">
           <input type="text" class="typeahead form-control" placeholder="Add pilot to fleet">
           <input id="inv-id" type="hidden" values="">
           <button type="button" id="inv-button" class="tt-btn btn btn-primary disabled" onclick="addpilot()"><i class="fa fa-1.5x fa-user-plus"></i></button>
         </div>';
    }
    $html .= '<table class="table table-striped small" id="pilottable">
        <thead>
          <th class="no-sort num"></th>
          <th>Pilot</th>
          <th class="no-sort num"></th>
          <th>Corp/Alliance</th>
          <th>Ships</th>
          <th class="num">Kills</th>
          <th class="num">Losses</th>
          <th class="num">ISK dest.</th>
          <th class="num">ISK lost</th>
          <th class="num">Eff. %</th>
          <th class="num">Dmg. done</th>
          <th class="num">Dmg. %</th>';
    if ($canChange) {
        $html .= '<th class="no-sort num"></th>'; 
    }
    $html .= '</thead>
        <tbody>';
    foreach ($fleet->getMembers() as $id => $member) {
        if ($member['iskDestroyed'] > 0 || $member['iskLost'] > 0) {
            $eff = round($member['iskDestroyed']*100/($member['iskLost']+$member['iskDestroyed']), 1);
        } else {
            $eff = 50;
        }
        if ($totalDmg > 0) {
            $dmgPercent = round($member['dmgDone']*100/$totalDmg, 1);
        } else {
            $dmgPercent = 0;
        }
        $html .= '<tr><td><img class="img img-rounded" src="https://imageserver.eveonline.com/Character/'.$id.'_32.jpg"></td>';
        $html .= '<td>'.$member['name'].'</td>';
        if ($member['allianceID'] != 0) {
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Alliance/'.$member['allianceID'].'_32.png"></td>
                      <td>'.$member['allianceName'].'<br />'.$member['corporationName'].'</td>';
        } else {
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Corporation/'.$member['corporationID'].'_32.png"></td>
                      <td>'.$member['corporationName'].'</td>';
        }
        $shipnames = DBH::getInvNames($member['ships']);
        $html .= '<td>'.implode("<br />", $shipnames).'</td>';
        $html .= '<td>'.$member['kills'].'</td>';
        $html .= '<td>'.$member['losses'].'</td>';
        $html .= '<td data-sort="'.$member['iskDestroyed'].'">'.formatIsk($member['iskDestroyed']).'</td>';
        $html .= '<td data-sort="'.$member['iskLost'].'">'.formatIsk($member['iskLost']).'</td>';
        $html .= '<td data-sort="'.round($eff, 0).'"><div title="'.$eff.' %" style="float:left; margin-top: -12px;"><canvas class="percChart" style="height: 36px; width: 36px;" value="'.round($eff, 0).'"></canvas></div></td>';
        $html .= '<td>'.$member['dmgDone'].'</td>';
        $html .= '<td data-sort="'.round($dmgPercent, 0).'"><div title="'.$dmgPercent.' %" style="float:left; margin-top: -12px;"><canvas class="percChart" style="height: 36px; width: 36px;" noeff="1" value="'.round($dmgPercent, 0).'"></canvas></div></td>';
        if ($canChange) {
            $html .= '<td><span style="cursor: pointer;" class="glyphicon glyphicon-trash" onclick="removepilot(this, '.$id.', \''.$member['name'].'\');"></span></td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>
    </table>
    <script>var ptable;
           window.addEventListener("load",function(event) {
               ptable = $("#pilottable").DataTable(
               {
                   "bPaginate": true,
                   "pageLength": 25,
                   "aoColumnDefs" : [ {
                       "bSortable" : false,
                       "aTargets" : [ "no-sort" ]
                   },{
                       "sClass" : "num-col",
                       "aTargets" : [ "num" ]
                   }],
                   fixedHeader: {
                       header: true,
                       footer: false
                   },
                   responsive: false,
                   "order": [[ 1, "asc" ]],
               });
           }, false);

           function addpilot() {
                var char_id = $("#inv-id").val();
                $.ajax({
                   type: "POST",
                   url: "'.URL::url_path().'ajax/aj_addpilot.php",
                   data: {"fid" : '.$fleet->getFleetID().', "ajtok" : "'.$_SESSION['ajtoken'].'", "cid" : char_id },
                   success:function(data)
                   {
                     if (data !== "true") {
                         BootstrapDialog.show({message: "Something went wrong...", type: BootstrapDialog.TYPE_WARNING});
                     } else {
                         BootstrapDialog.show({message: "Pilot added, you might wanna re-fetch kills."});
                     }
                   }
                });
           }
   
   
           function removepilot(ele, id, name) {
                BootstrapDialog.show({
                     message: "Are you sure you want to remove "+name+" from the fleet statistics?",
                     type: BootstrapDialog.TYPE_WARNING,
                     buttons: [{
                         label: "Remove pilot",
                         action: function(dialogItself){
                             dialogItself.close();
                             $.ajax({
                                 type: "POST",
                                 url: "'.URL::url_path().'ajax/aj_removepilot.php",
                                 data: {"ajtok" : "'.$_SESSION['ajtoken'].'", "cid" : id, "fid" : '.$fleet->getFLeetID().'},
                                 success:function(data) {
                                     if (data !== "true") {
                                         BootstrapDialog.show({message: "Something went wrong..."+data, type: BootstrapDialog.TYPE_WARNING});
                                     } else {
                                         var trow = $( ele ).closest("tr");
                                         ptable.row(trow).remove().draw(false);
                                         BootstrapDialog.closeAll();
                                     }
                                 }
                             });
                         }
                     },{
                         label: "Cancel",
                         action: function(dialogItself){
                             dialogItself.close();
                         }
                     }],
                });
           }
    </script>';

    return $html;
}

function kills() {
    global $fleet;
    $killmails = $fleet->getKillmails();
    $html = '<table class="table table-striped small" id="killtable">
        <thead>
          <th>Time</th>
          <th class="no-sort num"></th>
          <th>Victim</th>
          <th class="no-sort num"></th>
          <th>Corp/Alliance</th>
          <th class="no-sort num"></th>
          <th>Ship</th>
          <th class="num">Value</th>
          <th class="num"># inv.</th>
          <th>Location</th>
        </thead>
        <tbody>';
    foreach ($killmails as $killID => $k) {
        $html .= '<tr class="clickable-row'.($k['type']=='loss'?' loss':'').'" data-href="https://zkillboard.com/kill/'.$killID.'/"><td>'.date('m/d H:i', $k['killTime']).'</td>';
        if (isset($k['victimID']) && $k['victimID']) {
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Character/'.$k['victimID'].'_32.jpg"></td>';
            $html .= '<td>'.$k['name'].'</td>';
        } else {
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Type/'.$k['shipID'].'_32.png"></td>';
            $html .= '<td>'.$k['corporationName'].'</td>';
        }
        if (isset($k['allianceID']) && $k['allianceID']) {
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Alliance/'.$k['allianceID'].'_32.png"></td>';
            $html .= '<td>'.$k['allianceName'].'<br/>'.$k['corporationName'].'</td>';
        } else {
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Corporation/'.$k['corporationID'].'_32.png"></td>';
            $html .= '<td>'.$k['corporationName'].'</td>';
        }
        $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Type/'.$k['shipID'].'_32.png"></td>';
        $html .= '<td>'.$k['shipName'].'<br/>'.$k['shipClass'].'</td>';
        $html .= '<td data-sort="'.$k['value'].'"><span class="text-'.($k['type'] == 'kill'?'success':'danger').'">'.formatIsk($k['value']).'<span></td>';
        $html .= '<td>'.$k['involved'].'</td>';
        $sec = round($k['security'], 1);
        if ($sec < 0) {
            $osec = 00;
        } else {
            $osec = str_replace(".", "", $sec);
        }
        $html .= '<td>'.$k['systemName'].' <small>(<span class="s'.$osec.'">'.$sec.'</span>)</small><br/>'.$k['regionName'].'</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody>
    </table>
    <script>window.addEventListener("load",function(event) {
            var table = $("#killtable").dataTable(
               {
                   "bPaginate": true,
                   "pageLength": 25,
                   "aoColumnDefs" : [ {
                       "bSortable" : false,
                       "aTargets" : [ "no-sort" ]
                   },{
                       "sClass" : "num-col",
                       "aTargets" : [ "num" ]
                   }],
                   fixedHeader: {
                       header: true,
                       footer: false
                   },
                   responsive: false,
                   "order": [[ 0, "asc" ]],
               });
           }, false);
        window.addEventListener("load",function(event) {
            $(".clickable-row").click(function() {
                window.open($(this).data("href"), "_blank");
            });
        }, false);
    </script>';
    return $html;
}

function ships() {
    global $fleet;
    $ships = $fleet->getShipStats();
    $names = DBH::getInvNames(array_merge(array_keys($ships['friendly']), array_keys($ships['victims'])));
    $classes = DBH::getInvGroups(array_merge(array_keys($ships['friendly']), array_keys($ships['victims'])));
    $totalDmg = array_sum(array_column($ships['friendly'], 'dmgDone'));
    $totalDmgV = array_sum(array_column($ships['victims'], 'dmgDone'));
    $html = '<h5>Friendly ships</h5>
      <table class="table table-striped small shiptable" id="friendlyships">
        <thead>
          <th>#</th>
          <th class="no-sort num"></th>
          <th>Ship</th>
          <th>Class</th>
          <th class="num">Kills</th>
          <th class="num">Losses</th>
          <th class="num">Final Blows</th>
          <th class="num">ISK dest.</th>
          <th class="num">ISK lost</th>
          <th>Eff.</th>
          <th class="num fnum0">Dmg. done</th>
          <th>% of total</th>
          <th class="num fnum0">Dmg. taken</th>
        </thead>
        <tbody>';
    foreach ($ships['friendly'] as $id => $s) {
        $html .= '<tr><td>'.$s['count'].'</td>';
        $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Type/'.$id.'_32.png"></td>';
        $html .= '<td>'.$names[$id].'</td>';
        $html .= '<td>'.$classes[$id].'</td>';
        $html .= '<td>'.$s['kills'].'</td>';
        $html .= '<td>'.$s['losses'].'</td>';
        $html .= '<td>'.$s['finalBlows'].'</td>';
        $html .= '<td data-sort="'.$s['valueDestroyed'].'">'.formatIsk($s['valueDestroyed']).'</td>';
        $html .= '<td data-sort="'.$s['valueLost'].'">'.formatIsk($s['valueLost']).'</td>';
        if ($s['valueDestroyed'] > 0 || $s['valueLost'] > 0) {
            $eff = round($s['valueDestroyed']*100/($s['valueDestroyed']+$s['valueLost']), 1);
        } else {
            $eff = 50;
        }
        $html .= '<td data-sort="'.round($eff, 0).'"><div title="'.$eff.' %" style="float:left; margin-top: -12px;"><canvas class="percChart" style="height: 36px; width: 36px;" value="'.round($eff, 0).'"></canvas></div></td>';
        $html .= '<td>'.$s['dmgDone'].'</td>';
        if ($totalDmg > 0) {
            $perc = round($s['dmgDone']*100/$totalDmg, 1);
        } else {
            $perc = 0;
        }
        $html .= '<td data-sort="'.round($perc, 0).'"><div title="'.$perc.' %" style="float:left; margin-top: -12px;"><canvas class="percChart" noeff="1" style="height: 36px; width: 36px;" value="'.round($perc, 0).'"></canvas></div></td>';
        $html .= '<td>'.$s['dmgTaken'].'</td>';
    }
    $html .= '</tbody>
        </table>
      <h5>Enemy ships</h5>
      <table class="table table-striped small shiptable" id="friendlyships">
        <thead>
          <th>#</th>
          <th class="no-sort num"></th>
          <th>Ship</th>
          <th>Class</th>
          <th class="num">Kills</th>
          <th class="num">Losses</th>
          <th class="num">Final Blows</th>
          <th class="num">ISK dest.</th>
          <th class="num">ISK lost</th>
          <th>Eff.</th>
          <th class="num fnum0">Dmg. done</th>
          <th>% of total</th>
          <th class="num fnum0">Dmg. taken</th>
        </thead>
        <tbody>';
    foreach ($ships['victims'] as $id => $s) {
        $html .= '<tr><td>'.$s['count'].'</td>';
        $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Type/'.$id.'_32.png"></td>';
        $html .= '<td>'.$names[$id].'</td>';
        $html .= '<td>'.$classes[$id].'</td>';
        $html .= '<td>'.$s['kills'].'</td>';
        $html .= '<td>'.$s['losses'].'</td>';
        $html .= '<td>'.$s['finalBlows'].'</td>';
        $html .= '<td data-sort="'.$s['valueDestroyed'].'">'.formatIsk($s['valueDestroyed']).'</td>';
        $html .= '<td data-sort="'.$s['valueLost'].'">'.formatIsk($s['valueLost']).'</td>';
        if ($s['valueDestroyed'] > 0 || $s['valueLost'] > 0) {
            $eff = round($s['valueDestroyed']*100/($s['valueDestroyed']+$s['valueLost']), 1);
        } else {
            $eff = 50;
        }
        $html .= '<td data-sort="'.round($eff, 0).'"><div title="'.$eff.' %" style="float:left; margin-top: -12px;"><canvas class="percChart" style="height: 36px; width: 36px;" value="'.round($eff, 0).'"></canvas></div></td>';
        $html .= '<td>'.$s['dmgDone'].'</td>';
        if ($totalDmgV > 0) {
            $perc = round($s['dmgDone']*100/$totalDmgV, 1);
        } else {
            $perc = 0;
        }
        $html .= '<td data-sort="'.round($perc, 0).'"><div title="'.$perc.' %" style="float:left; margin-top: -12px;"><canvas class="percChart" noeff="1" style="height: 36px; width: 36px;" value="'.round($perc, 0).'"></canvas></div></td>';
        $html .= '<td>'.$s['dmgTaken'].'</td>';
    }
    $html .= '</tbody>
        </table>
        <script>window.addEventListener("load",function(event) {
            var table = $(".shiptable").dataTable(
               {
                   "bPaginate": true,
                   "pageLength": 25,
                   "aoColumnDefs" : [ {
                       "bSortable" : false,
                       "aTargets" : [ "no-sort" ]
                   },{
                       "sClass" : "num-col",
                       "aTargets" : [ "num" ],
                   },{
                       "aTargets" : [ "fnum2" ],
                       render: $.fn.dataTable.render.number( ",", ".", 2)
                   },{
                       "aTargets" : [ "fnum0" ],
                       render: $.fn.dataTable.render.number( ",", ".", 0)
                   }],
                   fixedHeader: {
                       header: true,
                       footer: false
                   },
                   responsive: false,
                   "order": [[ 0, "desc" ]],
               });
           }, false);
        </script>';
        return $html;
}

function enemies() {
    global $fleet;
    $enemies = $fleet->getEnemies();
    $enemyCorps = $enemies['corporations'];
    $enemyAllys = $enemies['alliances'];
    $totalCorps = array_sum(array_column($enemies['corporations'], 'count'));
    $corpdata = array();
    usort($enemies['corporations'], function($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    foreach ($enemies['corporations'] as $i => $e) {
        if ($i < 5 && $e['count'] >= $totalCorps/50) {
            $corpdata[$e['name']] = $e['count'];
        } else {
            if (isset($corpdata['others'])) {
                $corpdata['others'] += $e['count'];
            } else {
                $corpdata['others'] = $e['count'];
            }
        }
    }
    $totalAllys = array_sum(array_column($enemies['alliances'], 'count'));
    $allydata = array();
    usort($enemies['alliances'], function($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    foreach ($enemies['alliances'] as $i => $e) {
        if ($i < 5 && $e['count'] >= $totalAllys/50) {
            $allydata[$e['name']] = $e['count'];
        } else {
            if (isset($allydata['others'])) {
                $allydata['others'] += $e['count'];
            } else {
                $allydata['others'] = $e['count'];
            }
        }
    }

    $html = '<h5>Enemy entities</h5>
             <div class=row>
                 <div class="col-xs-13 col-md-6 text-center">
                     <div class="well well-sm" style="width: 100%;">
                         <span class="h6">By corporation:<br /><br /></span>
                         <div>
                             <canvas height="300" id="enemycorps"></canvas>
                         </div>
                     </div>
                 </div>
                 <div class="col-xs-12 col-md-6 text-center">
                     <div class="well well-sm" style="width: 100%;">
                         <span class="h6">By alliance:<br /><br /></span>
                         <div>
                             <canvas height="300" id="enemyallys"></canvas>
                         </div>
                     </div>
                 </div>
             </div>
    <h5>Enemy pilots</h5>
    <table class="table table-striped small" id="enemytable">
        <thead>
          <th class="no-sort num"></th>
          <th>Pilot</th>
          <th class="no-sort num"></th>
          <th>Corporation</th>
          <th class="no-sort num"></th>
          <th>Alliance</th>
        </thead>
        <tbody>';
    foreach ($enemies['pilots'] as $id => $plt) {
        $html .= '<tr data-href="">';
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Character/'.$id.'_32.jpg"></td>';
            $html .= '<td>'.$plt['name'].'</td>';
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Corporation/'.$plt['corporationID'].'_32.png"></td>';
            $html .= '<td>'.$enemyCorps[$plt['corporationID']]['name'].'</td>';
        if (isset($plt['allianceID']) && $plt['allianceID']) {
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Alliance/'.$plt['allianceID'].'_32.png"></td>';
            $html .= '<td>'.$enemyAllys[$plt['allianceID']]['name'].'</td>';
        } else {
            $html .= '<td></td>';
            $html .= '<td>No Alliance</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>
           </table>
        <script>
        var enemyOptions = {
            responsive: true,
            maintainAspectRatio: false,
            elements: {
                arc: {
                    borderWidth: 0
                }
            },
            tooltips: {
                enabled: true,
            },
            legend: {
                //display: false,
                position: "bottom",
            },
            circumference: Math.PI,
            rotation: -Math.PI,
        }
        var corpCounts = ['.implode(', ', $corpdata).']
        var corpLabels = ["'.implode('", "', array_keys($corpdata)).'"]
        var allyCounts = ['.implode(', ', $allydata).']
        var allyLabels = ["'.implode('", "', array_keys($allydata)).'"]
            window.addEventListener("load",function() {
                var ctx = $("#enemycorps")[0].getContext("2d");
                window.myPie = new Chart(ctx, {
                    type: "doughnut",
                    data: {
                        datasets: [{
                            data: corpCounts,
                            backgroundColor: [
                                window.chartColors.c1,
                                window.chartColors.c2,
                                window.chartColors.c3,
                                window.chartColors.c4,
                                window.chartColors.c5,
                                window.chartColors.c6,
                            ],
                        }],
                        labels: corpLabels,
                    },
                    options: enemyOptions,
                });
                var ctx = $("#enemyallys")[0].getContext("2d");
                window.myPie = new Chart(ctx, {
                    type: "doughnut",
                    data: {
                        datasets: [{
                            data: allyCounts,
                            backgroundColor: [
                                window.chartColors.c1,
                                window.chartColors.c2,
                                window.chartColors.c3,
                                window.chartColors.c4,
                                window.chartColors.c5,
                                window.chartColors.c6,
                            ],
                        }],
                        labels: allyLabels,
                    },
                    options: enemyOptions,
                });
            var table = $("#enemytable").DataTable(
               {
                   "bPaginate": true,
                   "pageLength": 25,
                   "aoColumnDefs" : [ {
                       "bSortable" : false,
                       "aTargets" : [ "no-sort" ]
                   },{
                       "sClass" : "num-col",
                       "aTargets" : [ "num" ]
                   }],
                   fixedHeader: {
                       header: true,
                       footer: false
                   },
                   responsive: false,
                   "order": [[ 1, "asc" ]],
               });
            }, false);
        </script>';
    return $html;
}

function timeline() {
    global $fleet;
    $killmails = $fleet->getKillmails();
    usort($killmails, function ($a, $b) { return ($a["killTime"] >  $b["killTime"]); });
    $start = strtotime($fleet->getCreated());
    $end = strtotime($fleet->getEnded());
    $duration = ($end - $start) / 60;
    $tick = 2;
    while (($duration/$tick) > 25 && $tick < 60) {
        if ($tick < 5) {
            $tick = 5;
        } elseif ($tick < 15) {
            $tick += 5;
        } else {
            $tick = $tick*2;
        }
    }
    $tickstart = (string)(floor((int)date('i', $start)/$tick)*$tick);
    if (strlen($tickstart) == 1) {
        $tickstart = '0'.$tickstart;
    }
    $_time = strtotime(date('Y-m-d H:', $start).$tickstart.':00');
    $slots = array();
    while ($_time < $end + $tick*60) {
        $idx = date('H:i', $_time);
        $slots[$idx] = array('kill' => 0, 'loss' => 0, 'killtt' => '<table id="killtt-'.$idx.'" class="small">', 'losstt' => '<table id="losstt-'.$idx.'" class="small">');
        $_time += $tick*60;
    }
    foreach ($killmails as $k) {
        $kt = $k['killTime'];
        $ktr = date('H:i', (strtotime(date('Y-m-d H:00:00', $kt))+round(date('i', $kt)/$tick ,0)*$tick*60));
        $ttstr = '<tr><td><img class="img img-rounded" src="https://imageserver.eveonline.com/Type/'.$k['shipID'].'_32.png"></td>';
        if (isset($k['victimID'])) {
            $ttstr.= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Character/'.$k['victimID'].'_32.jpg"></td>
                      <td>'.$k['name'].' ('.$k['shipName'].')<br />'.$k['corporationName'].(isset($k['allianceName'])?' / '.$k['allianceName']:'').'</td></tr>';
        } else {
            $ttstr.= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Corporation/'.$k['victimID'].'_32.png"></td>
                      <td>'.$k['corporationName'].' ('.$k['shipName'].')'.(isset($k['allianceName'])?'<br />'.$k['allianceName']:'').'</td></tr>';
        }
        $slots[$ktr][$k['type']] += 1;
        $slots[$ktr][$k['type'].'tt'] .= $ttstr;
    }
    $html = '<div style="display: none">';
    foreach ($slots as $t => $s) {
        $html .= $s['killtt'].'</table>'.$s['losstt'].'</table>';
    }
    $html .= '</div>
              <h5>Timeline</h5>
              <div class=row>
                  <div class="col-xs-12 col-sm-11 col-md-9 col-lg-8 text-center">
                      <div class="well well-sm" style="width: 100%;">
                          <div>
                              <canvas style="height: 200px" id="timeline"></canvas>
                          </div>
                      </div>
                  </div>
              </div>';

    $html .= "<style>
        canvas{
            -moz-user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
        }

        .chartjs-tooltip-key {
            display: inline-block;
            width: 10px;
            height: 10px;
            margin-right: 10px;
        }
    </style>
    <script src='js/chartTT.js'></script>
    <script>
        var labels = ['".implode("', '", array_keys($slots))."'];
        var kills = ['".implode("', '", array_column($slots, 'kill'))."'];
        var losses = ['".implode("', '", array_column($slots, 'loss'))."'];


    window.onload = function() {

        var timelineConfig = {
            type: 'bar',
            responsive: true,
            maintainAspectRatio: false,
            data: {
                labels: labels,
                datasets: [{
                    type: 'bar',
                    label: 'Kills',
                    backgroundColor: window.chartColors.c2,
                    data: kills,
                }, {
                    type: 'bar',
                    label: 'Losses',
                    backgroundColor: window.chartColors.bad,
                    data: losses,
                }]
            },
            options: {
                    tooltips: {
                        enabled: false,
                        //mode: 'index',
                        //position: 'nearest',
                        //position: 'cursor',
                        custom: tlTooltip,
                    },
                scales: {
                    xAxes: [{
                        color: 'white',
                        type: 'time',
                        display: true,
                        ticks: {
                            fontColor: 'white',
                            autoSkipPadding: 5, 
                            padding: 10, 
                            maxRotation: 45, 
                        },
                        gridLines: {
                            color: 'white',
                            drawOnChartArea: false,
                            drawTicks: true,
                            tickMarkLength: 5,
                        },
                        time: {
                            unit: 'minute',
                            unitStepSize: '".$tick."',
                            format: 'hh:mm',
                            displayFormats: {
                                'millisecond': 'HH:mm',
                                'second': 'HH:mm',
                                'minute': 'HH:mm',
                                'hour': 'HH:mm',
                                'day': 'HH:mm',
                                'week': 'HH:mm',
                                'month': 'HH:mm',
                                'quarter': 'HH:mm',
                                'year': 'HH:mm',
                            }
                        }
                    }],
                    yAxes: [{
                        type: 'linear',
                        color: 'white',
                        position: 'left',
                        gridLines: {zeroLineColor: 'white',},
                        ticks: {
                            stepSize: 1,
                            beginAtZero: true,
                            fontColor: 'white',
                            color: 'white',
                            fontColor: 'white',
                            autoSkipPadding: 5,
                            padding: 10,
                            maxRotation: 0,
                        },
                        scaleLabel: {
                            display: true,
                            labelString: '# of Kills/Losses'
                        }
                    }],

                },
                legend: {
                    position: 'bottom',
                },

            }
        };

        var ctx = document.getElementById('timeline').getContext('2d');
        window.myLine = new Chart(ctx, timelineConfig);

    };
    </script>";
    return $html;
}



$footer = '<script>
        window.chartColors = {
            good: "rgb(42, 159, 214)",
            bad: "rgb(204, 0, 0)",
            bg: "rgba(0, 0, 0, 0)",
            c1: "rgb(42, 159, 214)",
            c2: "rgb(119, 179, 0)",
            c3: "rgb(204, 0, 0)",
            c4: "rgb(153, 51, 204)",
            c5: "rgb(136, 136, 136)",
            c6: "rgb(255, 136, 0)",
        };
        var percOptions = {
            responsive: false,
            elements: {
                arc: {
                    borderWidth: 0
                }
            },
            tooltips: {
                enabled: false
            },
        }
        window.addEventListener("load",function() {
            $( ".percChart" ).each(function() {
                var ctx = $( this )[0].getContext("2d");
                var perc = $( this ).attr("value");
                var noeff = $( this ).attr("noeff");
                if (perc >= 50 || noeff ) {
                    color1 = window.chartColors.good;
                } else {
                    color1 = window.chartColors.bad;
                }
                window.myPie = new Chart(ctx, {
                    type: "pie",
                    data: {
                        datasets: [{
                            data: [
                                perc,
                                100 - perc,
                            ],
                            backgroundColor: [
                                color1,
                                window.chartColors.bg,
                            ],
                        }],
                    },
                    options: percOptions,  
                });
            });
        }, false);
        function linkToClipboard(link) {
            var targetId = "_hiddenCopyText_";
            var target = document.createElement("textarea");
            target.style.position = "absolute";
            target.style.left = "-9999px";
            target.style.top = "0";
            target.id = targetId;
            document.body.appendChild(target);
            target.textContent = link;
            var currentFocus = document.activeElement;
            target.focus();
            target.setSelectionRange(0, target.value.length);
            document.execCommand("copy");
            if (currentFocus && typeof currentFocus.focus === "function") {
                currentFocus.focus();
            }
            document.body.removeChild(target);
        }
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.3/Chart.min.js" integrity="sha256-oSgtFCCmHWRPQ/JmR4OoZ3Xke1Pw4v50uh6pLcu+fIc=" crossorigin="anonymous"></script>
    <script src="js/bootstrap-dialog.min.js"></script>
    <link href="css/bootstrap-dialog.min.css" rel="stylesheet">
';

if ($fleet->getTitle() != '') {
    $title = $fleet->getTitle()." on ".$fleet->getCreated();
    $desc = $title.' FC:&nbsp;'.$fleet->getFCName().' '.$fleet->getStats()['kills'].'&nbsp;kills, '.$fleet->getStats()['losses'].'&nbsp;losses.';
} else {
    $title = $fleet->getFCName()."'s feet on ".$fleet->getCreated();
    $desc = $title.' '.$fleet->getStats()['kills']. 'kills, '.$fleet->getStats()['losses'].'.';
}

$page = new Page($title);
$page->setDescription($desc);
//$page->getCached();

if (isset($_GET['p'])) {
    $p = $_GET['p'];
} else {
    $p = 'overview';
}

$page->addBody(fleetHeader());
$page->addBody(tabBar($p));

switch ($p) {
    case 'pilots':
        $page->addBody(pilots());
        break;
    case 'kills':
        $page->addBody(kills());
        break;
    case 'ships':
        $page->addBody(ships());
        break;
    case 'enemies':
        $page->addBody(enemies());
        break;
    case 'timeline':
        $page->addBody(timeline());
        break;
    default:
    case 'overview':
        $page->addBody(overview());
        break;        
}

$page->addFooter($footer);
$page->setBuildTime(number_format(microtime(true) - $start_time, 3));
if ($error) {
  $page->setError($message);
  $page->display();
} else {
  $page->display(true);
}
exit;
?>
