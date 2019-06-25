<?php
$start_time = microtime(true);
require_once('auth.php');
require_once('config.php');
require_once('loadclasses.php');
require_once('serverstatus.php');

$page = new Page('My fleet');

if (!isset($_SESSION['characterID'])) {
  $page->setError("You are not logged in.");
  $page->display();
  exit;
}

$fleet = ESIFLEET::getFleetForChar($_SESSION['characterID']);
if (!$fleet) {
  if(isset($_SESSION['fleetID'])) {
    $fleet = new ESIFLEET($_SESSION['fleetID'], $_SESSION['characterID']);
    if ($fleet->hasEnded()) {
        $page->setWarning("Looks like you last fleet has ended.");
        unset($_SESSION['fleetID']);
        $page->display();
        exit;
    }
    if ($fleet->getError()) {
      $page->setError($fleet->getMessage());
      $page->display();
      exit; 
    }
  } else {
    $page->setInfo("Could not find a fleet for ".$_SESSION['characterName']);
    $page->display();
    exit;
  }
} else {
  if ($fleet->getTitle() != '') {
    $title = $fleet->getTitle();
  } else {
    $title = 'My Fleet';
  }
 if (!$fleet->update() || $fleet->getError()) {
    if(isset($_SESSION['fleetID']) && $_SESSION['fleetID'] != $fleet->getFleetID()) {
      $fleet = new ESIFLEET($_SESSION['fleetID'], $_SESSION['characterID']);
      if ($fleet->getError()) {
        $page->setError($fleet->getMessage());
        $page->display();
        exit;
      } else {
        $fleet->update();
        if ($fleet->getError()) {
          $page->setError($fleet->getMessage());
          $page->display();
          exit;
        }
      }
    } else {
      $page->setError($fleet->getMessage());
      $page->display();
    }
  } else {
    $_SESSION['fleetID'] = $fleet->getFleetID();
  }
}

$page->addBody("<blockquote class='blockquote small'><p id='motd'>MOTD:<br/>".str_replace('size=', 'dummy=', $fleet->getMotd())."</p></blockquote>");

$backupfc = false;
foreach ($fleet->getMembers() as $member) {
  if ($member['id'] == $_SESSION['characterID']) {
    $backupfc = $member['backupfc'];
  }
}

if ($_SESSION['characterID'] == $fleet->getBoss()) {
   $page->addBody(getFleetHeader(true));
} elseif ($_SESSION['characterID'] == $fleet->getFC()) {
   $page->addBody(getFleetHeader(false));
}

if (isset($_GET['view']) && $_GET['view'] == 'tree') {
    $p = 'tree';
} elseif (isset($_GET['view']) && $_GET['view'] == 'ships') {
    $p = 'ships';
} else {
    $p = 'table';
}

if ($_SESSION['characterID'] == $fleet->getBoss() || $_SESSION['characterID'] == $fleet->getFC()) {
    $page->addBody(tabBar($p));
    if ($p == 'tree') {
        $page->addBody(getTreeView(true));
    } elseif ($p == 'ships') {
        $page->addBody(getShipView(true));
    } else {
        $page->addBody(getFleetTable(true));
    }
    $page->addBody(getEndButton());
    $page->addFooter(getScriptFooter());
} elseif ($fleet->isPublic() || $backupfc || (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'])) {
    $page->addBody(tabBar($p));
    if ($p == 'tree') {
        $page->addBody(getTreeView(false));
    } elseif ($p == 'ships') {
        $page->addBody(getShipView(false));
    } else {
        $page->addBody(getFleetTable(false));
    }
    $page->addFooter(getScriptFooter());
}
$page->setBuildTime(number_format(microtime(true) - $start_time, 3));
$page->display();
exit;

function getFleetHeader($isBoss=false) {
    global $fleet;
    if (!isset($_SESSION['ajtoken'])) {
        $_SESSION['ajtoken'] = EVEHELPERS::random_str(32);
    }
    $motd = $fleet->getMotd();
    $fleetlink = URL::url_path().'fitting.php';
    $fh = '<h5>Fleet options</h5>
           <div class="row">
             <div class="col-xs-12 col-md-4 col-lg-2"><div class="checkbox"><label title="All fleet members can see the compositions/locations"><input type="checkbox" value="" '.($fleet->isPublic() ? 'checked ':'').'onchange="setpublic(this)">Composition public</label></div></div>
             <div class="col-xs-12 col-md-4 col-lg-2"><div class="checkbox"><label><input type="checkbox" value="" '.($fleet->getFreemove() ? 'checked ':'').'onchange="setfreemove(this)">Freemove</label></div></div>
             <div class="col-xs-12 col-md-4 col-lg-2"><div class="checkbox"><label><input type="checkbox" value="" '.(strpos($motd, $fleetlink) ? 'checked ':'').'onchange="setmotd(this)">Fleet-Yo Link in motd</label></div></div>
             <div class="col-xs-12 col-md-6 col-lg-3 tt-pilot form">
               <link href="css/typeaheadjs.css" rel="stylesheet">
               <input type="text" class="typeahead form-control" placeholder="invite to fleet">
               <input id="inv-id" type="hidden" values="">
               <button type="button" id="inv-button" class="tt-btn btn btn-primary disabled" onclick="fleetinvite()"><span class="glyphicon glyphicon-envelope"></span></button>
             </div>
             <div class="col-xs-12 col-md-4 col-lg-3"> 
               <div class="form-group form-inline">
                 <label for="visibility">Stats Visibility:</label>
                 <select class="form-control small" id="visibility" onchange="setVisibility(this)">
                   <option'.($fleet->getVisibility() == 'private'?' selected':'').'>private</option>
                   <option'.($fleet->getVisibility() == 'public'?' selected':'').'>public</option>
                   <option'.($fleet->getVisibility() == 'fleet'?' selected':'').'>fleet</option>
                   <option'.($fleet->getVisibility() == 'corporation'?' selected':'').'>corporation</option>
                   <option'.($fleet->getVisibility() == 'alliance'?' selected':'').'>alliance</option>
                 </select>
               </div>
             </div>
             <div class="col-xs-12 col-md-4 col-lg-2"><div class="checkbox"><label title="Track fleet members ships and enable real-time statistics"><input type="checkbox" value="" '.($fleet->getTracking() ? 'checked ':'').'onchange="settracking(this)">Record statistics</label></div></div>
             <div class="col-xs-12 col-md-4 col-lg-3">
               <div class="form-group form-inline">
                 <label for="fleettitle">Fleet title:</label>
                 <input id="fleettitle" type="text" class="form-control" placeholder="Enter title">
                 <button type="button" class="tt-btn btn btn-primary" onclick="fleettitle();"><span class="glyphicon glyphicon-floppy-disk"></span></button>
               </div>
             </div> 
           </div>';
    $fh .= '<script>
        function setpublic(cb) {
            var state = cb.checked;
            $.ajax({
                type: "POST",
                url: "'.URL::url_path().'ajax/aj_public.php",
                data: {"fid" : '.$fleet->getFleetID().', "ajtok" : "'.$_SESSION['ajtoken'].'", "state" : state},
                success:function(data)
                {
                  if (data !== "true") {
                      BootstrapDialog.show({message: "something went wrong", type: BootstrapDialog.TYPE_WARNING});
                  }
                }
                });
        }
        function settracking(cb) {
            var state = cb.checked;
            $.ajax({
                type: "POST",
                url: "'.URL::url_path().'ajax/aj_tracking.php",
                data: {"fid" : '.$fleet->getFleetID().', "ajtok" : "'.$_SESSION['ajtoken'].'", "state" : state},
                success:function(data)
                {
                  if (data !== "true") {
                      BootstrapDialog.show({message: "something went wrong", type: BootstrapDialog.TYPE_WARNING});
                  }
                }
                });
        }
        function setfreemove(cb) {
            var state = cb.checked;
            $.ajax({
                type: "POST",
                url: "'.URL::url_path().'ajax/aj_freemove.php",
                data: {"fid" : '.$fleet->getFleetID().', "ajtok" : "'.$_SESSION['ajtoken'].'", "state" : state},
                success:function(data)
                {
                  if (data !== "true") {
                      BootstrapDialog.show({message: "something went wrong", type: BootstrapDialog.TYPE_WARNING});
                  }
                }
                });
        }
        function setmotd(cb) {
            var state = cb.checked;
            $.ajax({
                type: "POST",
                url: "'.URL::url_path().'ajax/aj_motd.php",
                data: {"fid" : '.$fleet->getFleetID().', "ajtok" : "'.$_SESSION['ajtoken'].'", "state" : state, "motd" : "blabala"},
                success:function(data)
                {
                  if (data == "false") {
                      BootstrapDialog.show({message: "something went wrong", type: BootstrapDialog.TYPE_WARNING});
                  } else {
                      $( "#motd" ).html("MOTD:<br/>"+data);
                  }
                }
                });
        }
        function fleetinvite() {
             var char_id = $("#inv-id").val();
             $.ajax({
                type: "POST",
                url: "'.URL::url_path().'ajax/aj_fleetinvite.php",
                data: {"fid" : '.$fleet->getFleetID().', "ajtok" : "'.$_SESSION['ajtoken'].'", "cid" : char_id },
                success:function(data)
                {
                  if (data !== "true") {
                      BootstrapDialog.show({message: data, type: BootstrapDialog.TYPE_WARNING});
                  } else {
                      BootstrapDialog.show({message: "Fleet invite sent."});
                  }
                }
             });
        }
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
                      $("#pagetitle").text(title);
                  }
                }
                });
        }
    </script>';
    return $fh;
}

function tabBar($p='table') {
    $html = '<ul class="nav nav-tabs">
        <li'.($p=='table'?' class="active"':'').'><a href="fleet.php?view=table">Composition</a></li>
        <li'.($p=='tree'?' class="active"':'').'><a href="fleet.php?view=tree">Wings & squads</a></li>
        <li'.($p=='ships'?' class="active"':'').'><a href="fleet.php?view=ships">By ship</a></li>
    </ul>';
    return $html;
}

function getFleetTable($hasRights=false) {
    global $fleet;
    $members = $fleet->getMembers();
    $modcolumns = FITTING::getModGroups(null, true);
    $locationDict = EVEHELPERS::getSystemNames(array_column($members, 'system'));
    $stationDict = EVEHELPERS::getStationNames(array_column($members, 'station'));
    $structureDict = EVEHELPERS::getStructureNames(array_column($members, 'structure'));
    $shipDict = EVEHELPERS::getInvNames(array_column($members, 'ship'));
    $shipTypeDict = EVEHELPERS::getInvGroupNames(array_column($members, 'ship'));
    $table = '<table id="fleettable" class="small table table-striped table-hover" cellspacing="0" width="100%">
      <thead>
        <tr>
          <th class="no-sort num"></th>
          <th>Pilot</th>
          <th>Location</th>
          <th class="no-sort num"></th>
          <th>Ship</th>
          <th>Type</th>';
          foreach($modcolumns as $mc => $t) {
              $table .='<th title="'.$t.'" class="mod-header no-sort"><img class="mod-column" src="img/col_headers/'.$mc.'.png"></th>';
          }
          if ($hasRights) {
              $table .='<th style="text-align: center" class="no-sort">backupfc</th>';
          }
        $table .='</tr>
      </thead>
      <tfoot>
        <tr>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td align="right"><em>Total:</em></td>';
          foreach($modcolumns as $mc) {
                  $table .='<td align="center" style="font-weight: bold;"></td>';
              }
        $table .='</tr>  
      </tfoot>
      <tbody>';
      foreach ($members as $m) {
          $table .='<tr id='.$m['id'].'>';
          $table .='<td><img style="margin: -6px;" class="img img-rounded" src="https://imageserver.eveonline.com/Character/'.$m['id'].'_32.jpg"></td>';
          $table .='<td>'.$m['name'].($m['sso']?'&nbsp;<i title="Logged in via SSO" class="text-success fa fa-key"></i>':'').'</td><td>'.$locationDict[$m['system']];
          if ($m['station'] && isset($stationDict[$m['station']])) {
              $table .= '<br />'.$stationDict[$m['station']];
          } elseif ($m['structure'] && isset($stationDict[$m['structure']])) {
              $table .= '<br />'.$structureDict[$m['structure']];
          }
          $table .='</td>
                    <td><img style="margin: -6px;" class="img img-rounded" src="https://imageserver.eveonline.com/InventoryType/'.$m['ship'].'_32.png"></td>';
          if ($m['fit'] != null && $m['fit'] != '') {
              $table .= '<td data-sort="'.$shipDict[$m['ship']].'"><a href="#" onclick="showfit(this, \''.base64_encode($m['fit']).'\'); return false;">'.$shipDict[$m['ship']].'</a></td><td>'.$shipTypeDict[$m['ship']].'</td>';
              $fit = json_decode($m['fit'], true);
              foreach(FITTING::getModGroups(array($fit['highs'], $fit['meds'], $fit['lows'])) as $mc) {
                 $table .='<td align="center"'.($mc == 0?' style="opacity: 0.1;"':'').'>'.$mc.'</td>';
              }
          } else {
              $table .= '<td data-sort="'.$shipDict[$m['ship']].'">'.$shipDict[$m['ship']].'</td><td>'.$shipTypeDict[$m['ship']].'</td>';
              foreach($modcolumns as $mc) {
                  $table .='<td></td>';
              } 
          }
          if ($hasRights) {
              $table .='<td align="center"><input type="checkbox" value="" '.(($m['backupfc']) ? 'checked ':'').'onchange="backupfc(this)"></td>';
          }
          $table .='</tr>';
      }
      $table .='</tbody></table>';
    if ($hasRights) {
        $table .='<script>
        function backupfc(cb) {
            var id = $(cb).closest("tr").attr("id");
            var state = cb.checked;
            $.ajax({
                type: "POST",
                url: "'.URL::url_path().'ajax/aj_backupfc.php",
                data: {"fid" : '.$fleet->getFleetID().', "cid" : id, "ajtok" : "'.$_SESSION['ajtoken'].'", "state" : state},
                success:function(data)
                {
                  if (data !== "true") {
                      BootstrapDialog.show({message: "something went wrong", type: BootstrapDialog.TYPE_WARNING});
                  }
                }
                }); 
        }
        </script>';
    }
    return $table;
}

function getTreeView($hasRights=false) {
    global $fleet;
    $members = $fleet->getMembers();
    $locationDict = EVEHELPERS::getSystemNames(array_column($members, 'system'));
    $shipDict = EVEHELPERS::getInvNames(array_column($members, 'ship'));
    $_fleet = $fleet->getWings();
    $data = array('id' => $fleet->getFleetID(), 'children' => array(), 'state' => array('opened' => true), 'icon' => 'fas fa-fighter-jet');
    $data['text'] = '<span class="ft-name">Fleet ('.$_fleet['count'].'):</span>';
    if (!$_fleet['commander']) {
        $data['text'] .= ' <em> no fleet commander</em>';
    } else {
        $data['text'] .= ' <img src="https://imageserver.eveonline.com/Character/'.$_fleet['commander']['characterID'].'_32.jpg">&nbsp'.$_fleet['commander']['characterName'].' <small>('.$shipDict[$_fleet['commander']['shipTypeID']].', '.$locationDict[$_fleet['commander']['locationID']].') '.($_fleet['commander']['fleetWarp']?'':'&nbsp;<i title="Doesn\'t fleet warp" class="text-danger fa fa-angle-double-up"></i>').($_fleet['commander']['enabled']?'&nbsp;<i title="Logged in via SSO" class="text-success fa fa-key"></i>':'').'</small>';
    }
    $wings = array();
    foreach($_fleet['wings'] as $wid => $w) {
        if ($w['commander']) {
            $wing = array('id' => $wid, 'text' => '<span class="ft-name">'.$w['name'].' ('.$w['count'].'):</span> <img src="https://imageserver.eveonline.com/Character/'.$w['commander']['characterID'].'_32.jpg">&nbsp'.$w['commander']['characterName'].' <small>('.$shipDict[$w['commander']['shipTypeID']].', '.$locationDict[$w['commander']['locationID']].') '.($w['commander']['fleetWarp']?'':'&nbsp;<i title="Doesn\'t fleet warp" class="text-danger fa fa-angle-double-up"></i>').($w['commander']['enabled']?'&nbsp;<i title="Logged in via SSO" class="text-success fa fa-key"></i>':'').'</small>', 'children' => array(), 'state' => array('opened' => true), 'icon' => 'fas fa-fighter-jet');
        } else {
            $wing = array('id' => $wid, 'text' => '<span class="ft-name">'.$w['name'].' ('.$w['count'].'):</span> <em>no wing commander</em>', 'children' => array(), 'state' => array('opened' => true), 'icon' => 'fas fa-fighter-jet');
        }
        foreach ($w['squads'] as $sid =>$s) {
            if ($s['commander']) {
                $squad = array('id' => $sid, 'text' => '<span class="ft-name">'.$s['name'].' ('.$s['count'].'):</span> <img src="https://imageserver.eveonline.com/Character/'.$s['commander']['characterID'].'_32.jpg">&nbsp'.$s['commander']['characterName'].' <small>('.$shipDict[$s['commander']['shipTypeID']].', '.$locationDict[$s['commander']['locationID']].') '.($s['commander']['fleetWarp']?'':'&nbsp;<i title="Doesn\'t fleet warp" class="text-danger fa fa-angle-double-up"></i>').($s['commander']['enabled']?'&nbsp;<i title="Logged in via SSO" class="text-success fa fa-key"></i>':'').'</small>', 'children' => array(), 'state' => array('opened' => true), 'icon' => 'fas fa-fighter-jet');
            } else {
                $squad = array('id' => $sid, 'text' => '<span class="ft-name">'.$s['name'].' ('.$s['count'].'):</span> <em>no squad commander</em>', 'children' => array(), 'state' => array('opened' => true), 'icon' => 'fas fa-fighter-jet');
            }
            foreach ($s['members'] as $m) {
                $squad['children'][] = array('id' => $m['characterID'], 'text' => $m['characterName'].' <small>('.$shipDict[$m['shipTypeID']].', '.$locationDict[$m['locationID']].') '.($m['fleetWarp']?'':'&nbsp;<i title="Doesn\'t fleet warp" class="text-danger fa fa-angle-double-up"></i>').($m['enabled']?'&nbsp;<i title="Logged in via SSO" class="text-success fa fa-key"></i>':'').'</small>', 'icon' => "https://imageserver.eveonline.com/Character/".$m['characterID']."_32.jpg");
            }
            $wing['children'][] = $squad;
        }
        $data['children'][] = $wing;
    }
    $tree = '<div class="well well-sm"><div id="fleettree"></div></div>';
    $tree .= "<script>var treedata = ".json_encode(array($data)).";
              window.addEventListener('load',function(event) { 
                  $('#fleettree').jstree({
                      'conditionalselect' : function (node) {
                          return false;
                      },
                      'plugins' : ['conditionalselect'],
                      'core' : {
                          'themes': {
                            'name': 'default-dark',
                            'dots': true,
                            'icons': true,
                          },
                          'data' : treedata,
                      },
                  }); 
              });
              </script>";
    return $tree;
}

function getShipView($hasRights=false) {
    global $fleet;
    $members = $fleet->getMembers();
    $ships = array();
    foreach ($members as $m) {
        if (!isset($ships[$m['ship']])) {
            $ships[$m['ship']] = 1;
        } else {
            $ships[$m['ship']] += 1;
        }
    }
    $shipGroupsDict = EVEHELPERS::getInvGroupNames(array_keys($ships));
    $shipDict = EVEHELPERS::getInvNames(array_column($members, 'ship'));
    $shipGroups = array();
    foreach ($ships as $s => $cnt) {
        if (!isset($shipGroups[$shipGroupsDict[$s]])) {
            $shipGroups[$shipGroupsDict[$s]] = $cnt;
        } else {
            $shipGroups[$shipGroupsDict[$s]] += $cnt;
        }
    }
    $html = '<div class="well well-sm"><div class="row">';
    $html .= ' <div class="col-sm-12 col-md-7 col-lg-5">
                 <h6>By Ship</h6>
                   <table class="table table-striped small shiptable">
                      <thead>
                          <th class="num no-sort"></th>
                          <th>Name</th>
                          <th>Class</th>
                          <th class="num">Count</th>
                      </thead>';
    foreach ($ships as $s => $cnt) {
        $html .='<tr><td><img style="margin: -6px;" class="img img-rounded" src="https://imageserver.eveonline.com/InventoryType/'.$s.'_32.png"></td>
                     <td>'.$shipDict[$s].'</td>
                     <td>'.$shipGroupsDict[$s].'</td>
                     <td>'.$cnt.'</td></tr>';
    }
    $html .= '</table></div>
              <div class="col-sm-12 col-md-5 col-lg-4">
                 <h6>By Class</h6>
                   <table class="table table-striped small shiptable">
                      <thead>
                          <th>Class</th>
                          <th class="num">Count</th>
                      </thead>';
    foreach ($shipGroups as $g => $cnt) {
        $html .='<tr><td>'.$g.'</td><td>'.$cnt.'</td></tr>';
    }
    $html .= '</table></div>';
    $html .= '</div></div>';
    return $html;
}

function getEndButton() {
    global $fleet;
    $html = '<div class="row"><div class="col-xs-12 text-right">
                 <button type="button" class="btn btn-primary" onclick="endfleet();"><span class="glyphicon glyphicon-log-out"></span> End Fleet</button>
             </div></div>
             <script>
             function endfleet() {
             BootstrapDialog.show({
                  message: "Are you sure you want to end the fleet?<br />This won\'t affect the fleet in-game but the statistics will end NOW.",
                  type: BootstrapDialog.TYPE_WARNING,
                  buttons: [{
                      label: "Do it!",
                      action: function(dialogItself){
                          dialogItself.close();
                          $.ajax({
                              type: "POST",
                              url: "'.URL::url_path().'ajax/aj_endfleet.php",
                              data: {"ajtok" : "'.$_SESSION['ajtoken'].'", "fid" : '.$fleet->getFLeetID().'},
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

function getScriptFooter() {
    $html = '<script>
         function showfit(btn, fit) {
              name = $(btn).text();
              var dialog = new BootstrapDialog(
                  {message: "Parsing Fit...</br><center><i class=\"fa fa-spinner fa-pulse fa-3x fa-fw\"></i></center>",
                  title: name,
                  draggable: true,
                  buttons: [{
                      label: "Close",
                      action: function(dialogRef) {
                          dialogRef.close();
                      }
                  }],});
              dialog.open();
              $.get("viewfit.php?fit="+fit, function(data, status){
                  dialog.setMessage(data);
              });
         }
        $(document).ready(function() {
            $("#fleettable").dataTable(
               {
                   "bPaginate": false,
                   "aoColumnDefs" : [ {
                       "bSortable" : false,
                       "aTargets" : [ "no-sort" ]
                   },{
                       "sClass" : "num-col",
                       "aTargets" : [ "num" ]
                   } ],
                   fixedHeader: {
                       header: true,
                       footer: true
                   },
                   "order": [[ 1, "asc" ]],
                   "footerCallback": function (row, data, start, end, display) {
                       console.log("footer");
                       var api = this.api(), data;
                       var colNumber = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22];
                       var intVal = function (i) {
                             return typeof i === "string" ?
                                  i.replace(/[, Rs]|(\.\d{2})/g,"")* 1 :
                                  typeof i === "number" ?
                                  i : 0;
                       };
                       for (i = 0; i < colNumber.length; i++) {
                           var colNo = colNumber[i];
                           total2 = api
                               .column(colNo)
                               .data()
                               .reduce(function (a, b) {
                                   return intVal(a) + intVal(b);
                               }, 0);
                           
                           $(api.column(colNo).footer()).html(
                                $(api.column(colNo).header()).html()+"<br />"+total2
                           );
                       }
                    },
               });
               $(".shiptable").DataTable({
                   "bPaginate": false,
                   "bFilter": false,
                   "aoColumnDefs" : [ {
                       "bSortable" : false,
                       "aTargets" : [ "no-sort" ]
                   },{
                       "sClass" : "num-col",
                       "aTargets" : [ "num" ]
                   } ],
               }).columns(-1).order("desc").draw();
        });
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.13/css/dataTables.bootstrap.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.13/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.13/js/dataTables.bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.7/themes/default/style.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.7/themes/default-dark/style.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.7/jstree.min.js"></script>
    <script src="js/typeahead.bundle.min.js"></script>
    <script src="js/esi_autocomplete.js"></script>
    <script src="js/bootstrap-dialog.min.js"></script>
    <link href="css/bootstrap-dialog.min.css" rel="stylesheet">';
    return $html;
}

?>
