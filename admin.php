<?php
$start_time = microtime(true);
require_once('auth.php');
require_once('config.php');
require_once('loadclasses.php');

$start_time = microtime(true);

function ellipse($string) {
    if(strlen($string) >= 600) {
        return substr($string, 0, 300).' ... '.substr($string, strlen($string)-300);
    } else {
        return $string;
    }
}

if (session_status() != PHP_SESSION_ACTIVE) {
  header('Location: '.URL::url_path.'index.php');
  die();
}

if (!isset($_SESSION['isAdmin']) || !$_SESSION['isAdmin']) {
  header('Location: '.URL::url_path().'index.php');
  die();
}

if (!isset($_SESSION['ajtoken'])) {
  $_SESSION['ajtoken'] = EVEHELPERS::random_str(32);
}

if (isset($_POST['clearcache'])) {
    $fi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('cache/'), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($fi as $file) {
        if ($file->isFile() and (substr($file->getFilename(), 0, 1) != '.' ) ) {
            unlink($file->getRealPath());
        }
    }
    $di = new DirectoryIterator('cache/api/');
    foreach ($di as $dir) {
        if ($dir->isDir() and (substr($dir->getFilename(), 0, 1) != '.' ) ) {
            rmdir($dir->getRealPath());
        }
    }
}

$qry = DB::getConnection();
$sql="SELECT * FROM esisso";
$users = $qry->query($sql)->num_rows;
$sql="SELECT * FROM esisso WHERE expires > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
$users24h = $qry->query($sql)->num_rows;

$esierrors1h = 0;
$esierrors24h = 0;
$logtext = [];
(is_file('log/esi.log')?$nolog = false:$nolog = true);
if (!$nolog) {
    $handle = fopen('log/esi.log','r');
    while (!feof($handle)) {
        $dd = fgets($handle);
        $temp = [];
        if (strlen($dd) > 20) {
            $arr = explode(" ", $dd);
            $temp = [];
            if (count($arr) >= 4) {
                $temp['date'] = $arr[0];
                $temp['time'] = $arr[1];
                $temp['type'] = $arr[2];
                $temp['message'] = ellipse(htmlentities(preg_replace('/(\&token\=[a-zA-Z0-9\.\-\_]*)/', '&token=<token>', implode(" ", array_slice($arr,3)))));
                $logtext[] = $temp;
            }
            $timestamp = substr($dd, 0, 20);
            $time = strtotime($timestamp);
            if($time > strtotime("-1 hours")) {
                $esierrors1h += 1;
                $esierrors24h += 1;
            } elseif ($time > strtotime("-24 hours")) {
                $esierrors24h += 1;
            }
        }
    }
    fclose($handle);
    $logtext = array_reverse($logtext);
}

$logtext2 = [];
(is_file('log/zkb.log')?$nolog = false:$nolog = true);
if (!$nolog) {
    $handle = fopen('log/zkb.log','r');
    while (!feof($handle)) {
        $dd = fgets($handle);
        $temp = [];
        if (strlen($dd) > 20) {
            $arr = explode(" ", $dd);
            $temp = [];
            if (count($arr) >= 4) {
                $temp['date'] = $arr[0];
                $temp['time'] = $arr[1];
                $temp['type'] = $arr[2];
                $temp['message'] = htmlentities(implode(" ", array_slice($arr,3)));
                $logtext2[] = $temp;
            }
        }
    }
    fclose($handle);
    $logtext2 = array_reverse($logtext2);
}

$fi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('cache/'), RecursiveIteratorIterator::SELF_FIRST);
$cachecount = 0;
$cachesize = 0;
foreach ($fi as $file) {
    if ($file->isFile() and (substr($file->getFilename(), 0, 1) != '.' ) ) {
        $cachecount += 1;
        $cachesize += $file->getSize();
    }
}

$html = '<div class="row">
             <div class="col-sm-12 col-md-6 col-lg-4"><h3>Statistics</h3>
               <div class="well well-sm">
                 <div class="row">
                     <div class="col-sm-7 col-lg-9">
                         Total users:
                     </div>
                     <div class="col-sm-4 col-lg-2 text-right">
                         '.$users.'
                     </div>
                 </div>
                 <div class="row">
                     <div class="col-sm-7 col-lg-9">
                         Users in the last 24h:
                     </div>
                     <div class="col-sm-4 col-lg-2 text-right">
                         '.$users24h.'
                     </div>
                 </div>
                 <div class="row">
                     <div class="col-sm-7 col-lg-9">
                         ESI errors (last hour):
                     </div>
                     <div class="col-sm-4 col-lg-2 text-right">
                         '.$esierrors1h.'
                     </div>
                 </div>
                 <div class="row">
                     <div class="col-sm-7 col-lg-9">
                         ESI errors (last 24 hours):
                     </div>
                     <div class="col-sm-4 col-lg-2 text-right">
                         '.$esierrors24h.'
                     </div>
                 </div>
               </div>
             </div>
             <div class="col-sm-12 col-md-6 col-lg-4"><h3>Cache</h3>
               <div class="well well-sm">
                 <div class="row">
                     <div class="col-sm-7 col-lg-8">
                         Number of Files:
                     </div>
                     <div class="col-sm-5 col-lg-4 text-right">
                         '.$cachecount.'
                     </div>
                 </div>
                 <div class="row">
                     <div class="col-sm-7 col-lg-8">
                         Cache Size:
                     </div>
                     <div class="col-sm-5 col-lg-4 text-right">
                         '.round($cachesize/(1024*1024), 2).' MB
                     </div>
                 </div>
               </div>
               <div class="col-sm-12 text-right">
                   <form id="cache" role="form" action="" method="post">
                       <button id="clearcache" name="clearcache" type="submit" value="clearcache" class="btn btn-primary">Clear Cache</button>
                   </form>
               </div>
             </div>
             <div class="col-lg-12"><h3>ESI error log</h3>
                 <div class="">
                     <table class="logtable table table-striped small" id="logtable">
                       <thead>
                         <th>Date</th>
                         <th>Time</th>
                         <th class="wordbreak">Message</th>
                       </thead>
                       <tbody>';
foreach ($logtext as $l) {
    $html .= '<tr><td>'.$l['date'].'</td><td>'.$l['time'].'</td><td class="wrap">'.$l['message'].'</td></tr>';
}
                       
$html.=             ' </tbody>
                    </table>
                 </div>
             </div>
             <div class="col-lg-12"><h3>Zkill error log</h3>
                 <div class="">
                     <table class="logtable table table-striped small" id="logtable2">
                       <thead>
                         <th>Date</th>
                         <th>Time</th>
                         <th class="wordbreak">Message</th>
                       </thead>
                       <tbody>';
foreach ($logtext2 as $l) {
    $html .= '<tr><td>'.$l['date'].'</td><td>'.$l['time'].'</td><td class="wrap">'.$l['message'].'</td></tr>';
}

$html.=             ' </tbody>
                    </table>
                 </div>
             </div>

         </div>';

$footer = '<script>$(document).ready(function() {
            var table = $(".logtable").dataTable(
               {
                   "bPaginate": true,
                   "pageLength": 25,
                   "aoColumnDefs" : [ {
                       "bSortable" : false,
                       "aTargets" : [ "no-sort" ]
                   }, {
                       className: "wordbreak",
                       "aTargets" : [ "wordbreak" ]
                   } ],
                   fixedHeader: {
                       header: true,
                       footer: false
                   },
                   "order": [[ 0, "desc" ], [ 1, "desc" ]],
               });
             });
         </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.13/css/dataTables.bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.datatables.net/responsive/2.1.1/css/responsive.bootstrap.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.13/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.13/js/dataTables.bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.1.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.1.1/js/responsive.bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome-animation/0.0.10/font-awesome-animation.min.css" integrity="sha256-C4J6NW3obn7eEgdECI2D1pMBTve41JFWQs0UTboJSTg=" crossorigin="anonymous" />';

$page = new Page('Admin Panel');
$page->addBody($html);
$page->addFooter($footer);
$page->setBuildTime(number_format(microtime(true) - $start_time, 3));
$page->display();
exit;
?>
