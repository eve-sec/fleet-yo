<?php
$start_time = microtime(true);
$error = false;
require_once('auth.php');
require_once('config.php');
require_once('loadclasses.php');
require_once('serverstatus.php');

$page = new Page('List statistics');

if (!isset($_SESSION['characterID'])) {
  $page->setError("You need to be logged in.");
  $page->display();
  exit;
}

if (isset($_SESSION['isAdmin']) && $_SESSION['isAdmin']) {
  $admin = true;
} else {
  $admin = false;
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


$stats = FLEETSTATS::getViewableStats($_SESSION['characterID'], $admin);
$allIds = array_merge(array_unique(array_column($stats, 'fleetCorporation')), array_unique(array_column($stats, 'fleetAlliance')), array_unique(array_column($stats, 'fc')));
$names = EVEHELPERS::esiIdsToNames($allIds);

$access = false;
$html = '';

if(isset($_SESSION['characterID']) && in_array($_SESSION['characterID'], FC_PILOTS)) {
    $access = true;
} elseif (in_array(ESIPILOT::getCorpForChar($_SESSION['characterID']), FC_CORPS)) {
    $access = true;
} elseif (in_array(ESIPILOT::getAllyForChar($_SESSION['characterID']), FC_ALLYS)) {
    $access = true;
}

if ($access or $admin) {
    $html .= '<div class="row">
                  <div class="col-xs-12 text-right">
                      <a href="addstats.php" class="btn btn-primary" role="button"><span class="glyphicon glyphicon-plus-sign"></span> Add Fleet</a>
                  </div>
                  <div class="col-xs-12" style="height: 12px;"></div>
               </div>';
}

$html .= '<table class="table table-striped small display" id="statslist">
        <thead>
          <th>Start</th>
          <th>End</th>
          <th>Title</th>
          <th class="no-sort num"></th>
          <th>FC</th>
          <th class="num"># in Fleet</th>
          <th class="num">Kills</th>
          <th class="num">Losses</th>
          <th class="num">ISK dest.</th>
          <th class="num">ISK lost</th>
          <th class="num">Eff. %</th>
          <th class="num fnum0">Dmg. done</th>
          <th class="no-sort num"></th>
          <th>Availability</th>
        </thead>
        <tbody>';
foreach($stats as $s) {
    $key = md5($s['fc'].$s['fleetID'].$s['created'].STATS_SALT);
    $html .= '<tr class="clickable-row" data-href="viewstats.php?fleetID='.$s['fleetID'].'&key='.$key.'"><td>'.date('Y/m/d H:i', strtotime($s['created'])).'</td><td>'.date('Y/m/d H:i', strtotime($s['ended'])).'</td>';
    $html .= '<td>'.$s['title'].'</td>';
    $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Character/'.$s['fc'].'_32.jpg"></td>';
    $html .= '<td>'.$names[$s['fc']].'</td>';
    $html .= '<td>'.$s['memberCount'].'</td>';
    $html .= '<td>'.$s['kills'].'</td>';
    $html .= '<td>'.$s['losses'].'</td>';
    $html .= '<td data-sort="'.$s['iskDestroyed'].'">'.formatIsk($s['iskDestroyed']).'</td>';
    $html .= '<td data-sort="'.$s['iskLost'].'">'.formatIsk($s['iskLost']).'</td>';
    if ($s['iskDestroyed'] == 0 && $s['iskLost'] == 0) {
        $eff = 50;
    } else {
        $eff = round($s['iskDestroyed']*100/($s['iskDestroyed']+$s['iskLost']), 1);
    }
    $html .= '<td data-sort="'.round($eff, 0).'"><div title="'.$eff.' %" style="float:left; margin-top: -12px;"><canvas class="percChart" style="height: 36px; width: 36px;" value="'.round($eff, 0).'"></canvas></div></td>';
    $html .= '<td>'.$s['dmgDone'].'</td>';
    switch ($s['stats']) {
        case 'public':
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Character/1_32.jpg"></td>';
            $html .= '<td>Public</td>';
            break;
        case 'private':
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Character/'.$s['fc'].'_32.jpg"></td>';
            $html .= '<td>'.$names[$s['fc']].'</td>';
            break;
        case 'corporation':
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Corporation/'.$s['fleetCorporation'].'_32.png"></td>';
            $html .= '<td>'.$names[$s['fleetCorporation']].'</td>';
            break;
        case 'alliance':
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Alliance/'.$s['fleetAlliance'].'_32.png"></td>';
            $html .= '<td>'.$names[$s['fleetAlliance']].'</td>';
            break;
        default:
        case 'fleet':
            $html .= '<td><img class="img img-rounded" src="https://imageserver.eveonline.com/Type/42530_32.png"></td>';
            $html .= '<td>Fleetmembers</td>';
            break;
    } 
}
$html .= '</tbody>
    </table>
    <script>var listtable;
        function formatIsk(data, type, row)
        {
            if (type == "sort" || type == "type")
                return data;
                
            pref = "";
            
            if      (data >= 1000000000) { pref = "G"; num = (data / 1000000000).toFixed(2); }
            else if (data >= 1000000)    { pref = "M"; num = (data / 1000000)   .toFixed(2); }
            else if (data >= 1000)       { pref = "K"; num = (data / 1000)      .toFixed(2); }
            else                         { num = data; }
            
            return num + " " + pref + "B";
        }
        window.addEventListener("load",function(event) {
           listtable = $("#statslist").DataTable(
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
                   "aTargets" : [ "isk" ],
                   "render": formatIsk,
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

          $(".clickable-row").click(function() {
              window.location.href = $(this).data("href");
          });

       }, false);
    </script>';



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


$page->addBody($html);
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
