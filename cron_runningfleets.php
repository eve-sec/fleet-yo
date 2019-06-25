<?php
chdir(dirname(__FILE__));

require_once('config.php');
require_once('loadclasses.php');

$allow_remote_usage = False;

if (php_sapi_name() !='cli' && !$allow_remote_usage) {
    $page = new Page('Access denied');
    $page->setError('This Cron job may only be run from the command line.');
    $page->display();
    exit;
}

if (!ESIAPI::checkTQ()) {
    exit;
}

$log = new ESILOG('log/cron_fleets.log');

class Cronlock {
    private static $lockhandle = null;
    private static $lockfile = __DIR__.'/runningfleets.lock';

    public static function getLock() {
        self::$lockhandle = fopen(self::$lockfile, 'w');
        if (!flock(self::$lockhandle, LOCK_EX | LOCK_NB)) {
            $log = new ESILOG('log/cron_fleets.log');
            $log->error('Could not get lock. Maybe another instance is still running.');
            fclose(self::$lockhandle);
            exit;
        }
    }

    public static function unLock() {
        if (is_resource(self::$lockhandle)) {
            flock(self::$lockhandle, LOCK_UN);
            fclose(self::$lockhandle);
        }
        if(file_exists(self::$lockfile)) { 
            unlink(self::$lockfile);
        }
        exit;
    }
}

Cronlock::getLock();

function abort_handler() {
    Cronlock::unLock();
}
register_shutdown_function("abort_handler");

//running fleets

//Check if theres a queueID, if not create one.
$quID = DBH::configGet('redisQId');
if (!$quID) {
    $quID = 'fleetYo'.date('Hi');
    DBH::configSet('redisQId', $quID);
}

$todo = array();
$qry = DB::getConnection();
$sql = "SELECT fleetID, created, lastFetch, boss FROM `fleets` WHERE ended IS NULL OR ended > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)";
$result = $qry->query($sql);
while ($row = $result->fetch_assoc()) {
    $todo[] = $row;
}
$running = array();
$do_update = array();
foreach ($todo as $fleet) {
    $f = new ESIFLEET($fleet['fleetID'], $fleet['boss']);
    if (!$f->hasEnded()) {
        $f->update();
        if ($f->getError()) {
            $log->error($f->getMessage());
        }
    }
    $running[$fleet['fleetID']] = array('created' => $fleet['created'], 'members' => array_column($f->getMembers(), 'id'));
    unset($f);
}

if (!count($running)) {
    DBH::configSet('redisQId');
} else {
    $zkbapi = new ZKBAPI();
    $kills = $zkbapi->listenRedisq($quID);
    if (count((array)$kills)) {
        $stmt = $qry->prepare("INSERT IGNORE into kills (fleetID, killID, killTime, shipID, systemID, type, value, killmail) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iisiisds', $fltid, $kllid, $klltime, $shipid, $sysid, $type, $value, $mail);
        foreach ($kills as $k) {
            $kill = $k['killmail'];
            foreach ($running as $id => $fleet) {
                $startdate = strtotime(str_replace(' ', 'T', $fleet['created']).'+00:00');
                if (strtotime($kill['killmail_time']) >= $startdate) {
                    if (isset($kill['victim']['character_id']) && in_array($kill['victim']['character_id'], $fleet['members']) ) {
                        $fltid = $id;
                        $kllid = $kill['killmail_id'];
                        $klltime = $kill['killmail_time'];
                        $shipid = $kill['victim']['ship_type_id'];
                        $sysid = $kill['solar_system_id'];
                        $type = 'loss';
                        $value = $k['zkb']['totalValue'];
                        $mail = json_encode($kill);
                        $stmt->execute();
                        $do_update[$id] = true;
                        continue;
                    }
                    foreach ($kill['attackers'] as $a) {
                        if (isset($a['character_id']) && in_array($a['character_id'], $fleet['members']) )  {
                            $fltid = $id;
                            $kllid = $kill['killmail_id'];
                            $klltime = $kill['killmail_time'];
                            $shipid = $kill['victim']['ship_type_id'];
                            $sysid = $kill['solar_system_id'];
                            $type = 'kill';
                            $value = $k['zkb']['totalValue'];
                            $mail = json_encode($kill);
                            $stmt->execute();
                            $do_update[$id] = true;
                            break;
                        }
                    }
                }
            }
        }
        $stmt->close();
    }
    foreach (array_keys($do_update) as $id) {
        $f = new FLEETSTATS($id);
        $f->updateStats();
        if ($f->getError()) {
            $log->error($f->getMessage());
        }
        unset($f);
    }
}


//Nnw deal with the fleets that just ended

$lastzkb = DBH::configGet('lastZkb');
if (!$lastzkb) {
    DBH::configSet('lastZkb', date("Y-m-d H:i:s"));
} elseif (time() - strtotime($lastzkb) < 300) {
    Cronlock::unLock();
}
$todo = array();
$qry = DB::getConnection();
$sql = "SELECT fleetID, ended, lastFetch, boss FROM `fleets` WHERE ended > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR)";
$result = $qry->query($sql);
while ($row = $result->fetch_assoc()) {
    //Don't mix API and redisQ
    if (!in_array($row['fleetID'], $running)) {
        $todo[] = $row;
    }
}

foreach ($todo as $fleet) {
    $f = new FLEETSTATS($fleet['fleetID']);
    $f->fetchKills();
    $f->updateStats();
    if ($f->getError()) {
        $log->error($f->getMessage());
    }
    unset($f);
}
Cronlock::unLock();
?>
