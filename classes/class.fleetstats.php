<?php
require_once('config.php');

use Swagger\Client\ApiException;
use Swagger\Client\Api\CharacterApi;
use Swagger\Client\Api\UniverseApi;
use Swagger\Client\Api\FleetsApi;

require_once('classes/esi/autoload.php');
require_once('classes/class.esisso.php');
require_once('classes/class.url.php');

if (session_status() != PHP_SESSION_ACTIVE) {
  session_start();
}

// Credit to FuzzySteve https://github.com/fuzzysteve/eve-sso-auth/
class FLEETSTATS
{
    protected $fleetID = null;
    protected $fc = null;
    protected $title = '';
    protected $members = array();
    protected $created = null;
    protected $ended = null;
    protected $stats = 'corporation';
    protected $error = false;
    protected $message = null;
    protected $kills = 0;
    protected $losses = 0;
    protected $iskDestroyed = 0;
    protected $iskLost = 0;
    protected $dmgDone = 0;

    
    public function __construct($fleetID = null, $fc = null, $created = null, $ended = null, $members = array(), $stats = 'corporation', $title = '') {
        $this->log = new ESILOG('log/esi.log');
        if (!$fleetID) {
            if (!$fc || !$created || !$ended) {
                $this->error = true;
                $this->message = 'Missing information.';
                return false;
            }
            $this->created = $created;
            $this->ended = $ended;
            $this->stats = $stats;
            $this->title = $title;

            $qry = DB::getConnection();
            $stmt = $qry->prepare("INSERT into statsFleets (fc, title, created, ended, stats) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('issss', $fcid, $ftit, $date1, $date2, $vis);
            $fcid = $fc;
            $ftit = $title;
            $date1 = $created;
            $date2 = $ended;
            $vis = $stats;
            $stmt->execute();
            $this->fleetID = $stmt->insert_id;
            $stmt->close();

            foreach ($members as $id => $name) {
                $this->members[$id] = array('name' => $name);
            }
            $this->addAffiliations();
            $stmt = $qry->prepare("INSERT into participation (fleetID, characterID, corporationID, allianceID) VALUES (".$this->fleetID.", ?, ?, ?)");
            $stmt->bind_param('iii', $char, $corp, $ally);
            foreach($this->members as $id => $m) {
                $char = $id;
                $corp = $m['corporationID'];
                $ally = $m['allianceID'];
                $stmt->execute();
            }
            $stmt->close();
            $this->addAffiliationNames();
            $this->fetchKills();
            $this->updateStats();
        } else {
            $dbfleet = DBH::getStatsFleet($fleetID);
            if (!$dbfleet) {
                $this->error = true;
                $this->message = 'Fleet information does not exist.';
                return false;
            }
            $this->fleetID = $fleetID;
            $this->fc = $dbfleet['fc'];
            $this->created = $dbfleet['created'];
            $this->ended = $dbfleet['ended'];
            $this->stats = $dbfleet['stats'];
            $this->title = $dbfleet['title'];
            $this->members = DBH::getParticipants($fleetID);
            $this->addAllNames();
            $this->updateStats();
        }
    }

    public function addAffiliations() {
        if (count($this->members)) {
            $esiapi = new ESIAPI();
            $charapi = $esiapi->getApi('Character');
            try{
                $result = $charapi->postCharactersAffiliation(json_encode(array_keys($this->members)));
            } catch (Exception $e) {
                $this->error = true;
                $this->message = 'Could not fetch character affiliations: '.$e->getMessage();
                $this->log->error($this->message);
                return false;
            }
            $i = 0;
            foreach ($result as $r) {
                $id = $r->getCharacterID();
                $this->members[$id]['corporationID'] = $r->getCorporationID();
                if ($r->getAllianceID()) {
                    $this->members[$id]['allianceID'] = $r->getAllianceID();
                } else {
                    $this->members[$id]['allianceID'] = 0;
                }
                $i += 1;
            }
            $this->addAffiliationNames();
        }
    }

    public function updateDbAffiliations() {
        $qry = DB::getConnection();
        $stmt = $qry->prepare("UPDATE participation SET corporationID=?, allianceID=? WHERE (fleetID=".$this->fleetID." and characterID=?)");
        $stmt->bind_param('iii', $corp, $ally, $char);
        foreach($this->members as $id => $m) {
            $char = $id;
            $corp = $m['corporationID'];
            $ally = $m['allianceID'];
            $stmt->execute();
        }
        $stmt->close();
    }

    private function addAllNames() {
        $lookup = array();
        foreach ($this->members as $id => $m) {
            $lookup[] = $id;
            $lookup[] = $m['corporationID'];
            if ($m['allianceID']) {
                $lookup[] = $m['allianceID'];
            }
        }
        $names = EVEHELPERS::esiIdsToNames($lookup);
        foreach ($this->members as $id => $m) {
            $this->members[$id]['name'] = $names[$id];
            $this->members[$id]['corporationName'] = $names[$m['corporationID']];
            if ($m['allianceID']) {
                $this->members[$id]['allianceName'] = $names[$m['allianceID']];
            }
        }
    }

    private function addAffiliationNames() {
        $lookup = array();
        foreach ($this->members as $id => $m) {
            $lookup[] = $m['corporationID'];
            if ($m['allianceID']) {
                $lookup[] = $m['allianceID'];
            }
        }
        $affiliations = EVEHELPERS::esiIdsToNames($lookup);
        foreach ($this->members as $id => $m) {
            $this->members[$id]['corporationName'] = $affiliations[$m['corporationID']];
            if ($m['allianceID']) {
                $this->members[$id]['allianceName'] = $affiliations[$m['allianceID']];
            }
        }
    }

    public function fetchKills() {
        $todo = array('character' => array(), 'corporation' => array(), 'alliance' => array());
        if (count((array)$this->members) > 20) {
            foreach ($this->members as $id => $m) {
                if($m['allianceID']) {
                    if(!in_array($m['allianceID'], $todo['alliance']) ) {
                        $todo['alliance'][] = $m['allianceID'];
                    }
                } elseif ($m['corporationID'] >= 2000000) {
                    if(!in_array($m['corporationID'], $todo['corporation']) ) {
                        $todo['corporation'][] = $m['corporationID'];
                    }
                } else {
                    $todo['character'][] = $id;
                }
            }
        } else {
            $todo['character'] = array_keys($this->members);
        }
        $kills = array();
        $zkbapi = new ZKBAPI();
        $i = 0;
        $start = microtime(true);
        foreach ($todo['character'] as $id) {
            $result = $zkbapi->getCharacterKills($id, $this->created, $this->ended);
            $kills = array_merge($kills, $result);
            $i += 1;
            if ($i == 10) {
                $used = microtime(true);
                if (($used - $start) < 1 ) {
                    sleep(1.1 - ($used - $start));
                }
                $i = 0;
                $start = $used;
            }
        }
        foreach ($todo['corporation'] as $id) {
            $result = $zkbapi->getCorporationKills($id, $this->created, $this->ended);
            $kills = array_merge($kills, $result);
            $i += 1;
            if ($i == 10) {
                $used = microtime(true);
                if (($used - $start) < 1 ) {
                    sleep(1.1 - ($used - $start));
                }
                $i = 0;
                $start = $used;
            }
        }
        foreach ($todo['alliance'] as $id) {
            $result = $zkbapi->getAllianceKills($id, $this->created, $this->ended);
            $kills = array_merge($kills, $result);
            $i += 1;
            if ($i == 10) {
                $used = microtime(true);
                if (($used - $start) < 1 ) {
                    sleep(1.1 - ($used - $start));
                }
                $i = 0;
                $start = $used;
            }
        }
        $memberids=array_keys($this->members);
        $qry = DB::getConnection();
        $stmt = $qry->prepare("INSERT IGNORE into kills (fleetID, killID, killTime, shipID, systemID, type, value, killmail) VALUES (".$this->fleetID.", ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isiisds', $kllid, $klltime, $shipid, $sysid, $type, $value, $mail);
        foreach ($kills as $k) {
            $kill = $k['killmail'];
            if (isset($kill['victim']['character_id']) && in_array($kill['victim']['character_id'], $memberids) ) {
                $kllid = $kill['killmail_id'];
                $klltime = $kill['killmail_time'];
                $shipid = $kill['victim']['ship_type_id'];
                $sysid = $kill['solar_system_id'];
                $type = 'loss';
                $value = $k['zkb']['totalValue'];
                $mail = json_encode($kill);
                $stmt->execute();
                continue;
            }
            foreach ($kill['attackers'] as $a) {
                if (isset($a['character_id']) && in_array($a['character_id'], $memberids) )  {
                    $kllid = $kill['killmail_id'];
                    $klltime = $kill['killmail_time'];
                    $shipid = $kill['victim']['ship_type_id'];
                    $sysid = $kill['solar_system_id'];
                    $type = 'kill';
                    $value = $k['zkb']['totalValue'];
                    $mail = json_encode($kill);
                    $stmt->execute();
                    break;
                }
            }
        }
        $stmt->close();
    }
    
    function updateStats() {
        $kills = DBH::getFleetKills($this->fleetID);
        $memberids=array_keys($this->members);
        $this->kills = 0;
        $this->losses = 0;
        $this->iskDestroyed =0;
        $this->iskLost = 0;      
        $this->dmgDone = 0;
        foreach ($memberids as $id) {
            $this->members[$id]['finalBlows'] = 0;
            $this->members[$id]['kills'] = 0;
            $this->members[$id]['losses'] = 0;
            $this->members[$id]['iskDestroyed'] = 0;
            $this->members[$id]['iskLost'] = 0;
            if (!isset($this->members[$id]['ships']) || !is_array($this->members[$id]['ships']) ) {
                $this->members[$id]['ships'] = array();
            }
            $this->members[$id]['dmgDone'] = 0;
        }
        foreach ($kills as $k) {
            $kill = json_decode($k['killmail'], true);
            if (isset($kill['victim']['character_id']) && in_array($kill['victim']['character_id'], $memberids) ) {
                $this->losses += 1;
                $this->iskLost += $k['value'];
                $id = $kill['victim']['character_id'];
                $this->members[$id]['losses'] += 1;
                if (isset($k['shipID']) && !in_array($k['shipID'], $this->members[$id]['ships'])) {
                    $this->members[$id]['ships'][] = $k['shipID'];
                }
                $this->members[$id]['iskLost'] += $k['value'];
                $this->members[$id]['corporationID'] = $kill['victim']['corporation_id'];
                if (isset($kill['victim']['alliance_id']) && $kill['victim']['alliance_id']) {
                    $this->members[$id]['allianceID'] = $kill['victim']['alliance_id'];
                } else {
                    $this->members[$id]['allianceID'] = 0;
                }
                foreach ($kill['attackers'] as $a) {
                    if (isset($a['character_id']) && in_array($a['character_id'], $memberids) )  {
                        $id = $a['character_id'];
                        if (isset($a['ship_type_id']) && !in_array($a['ship_type_id'], $this->members[$id]['ships'])) {
                            $this->members[$id]['ships'][] = $a['ship_type_id'];
                        }
                        $this->members[$id]['corporationID'] = $a['corporation_id'];
                        if (isset($a['alliance_id']) && $a['alliance_id']) {
                            $this->members[$id]['allianceID'] = $a['alliance_id'];
                        } else {
                            $this->members[$id]['allianceID'] = 0;
                        }
                    }
                }
            } else {
                $this->kills += 1;
                $this->iskDestroyed += $k['value'];
                foreach ($kill['attackers'] as $a) {
                    if (isset($a['character_id']) && in_array($a['character_id'], $memberids) )  {
                        $id = $a['character_id'];
                        $this->members[$id]['kills'] += 1;
                        $this->members[$id]['iskDestroyed'] += $k['value'];
                        if (isset($a['ship_type_id']) && !in_array($a['ship_type_id'], $this->members[$id]['ships'])) {
                            $this->members[$id]['ships'][] = $a['ship_type_id'];
                        }
                        if (isset($a['final_blow']) && $a['final_blow']) {
                            $this->members[$id]['finalBlows'] += 1;
                        }
                        if (isset($a['damage_done']) && $a['damage_done']) {
                            $this->members[$id]['dmgDone'] += $a['damage_done'];
                            $this->dmgDone += $a['damage_done'];
                        }
                        $this->members[$id]['corporationID'] = $a['corporation_id'];
                        if (isset($a['alliance_id']) && $a['alliance_id']) {
                            $this->members[$id]['allianceID'] = $a['alliance_id'];
                        } else {
                            $this->members[$id]['allianceID'] = 0;
                        }
                    }
                }
            }
        }
        $qry = DB::getConnection();
        $stmt = $qry->prepare("UPDATE participation SET finalBlows=?, iskDestroyed=?, iskLost=?, kills=?, losses=?, ships=?, dmgDone=?, corporationID=?, allianceID=? WHERE (fleetID=".$this->fleetID." AND characterID=?)");
        $stmt->bind_param('iddiisdiii', $finalBlows, $iskDestroyed, $iskLost, $kills, $losses, $ships, $dmgDone, $corporationID, $allianceID, $characterID);
        foreach ($memberids as $id) {
            $finalBlows = $this->members[$id]['finalBlows'];
            $iskDestroyed = $this->members[$id]['iskDestroyed'];
            $iskLost = $this->members[$id]['iskLost'];
            $kills = $this->members[$id]['kills'];
            $losses = $this->members[$id]['losses'];
            $ships = json_encode($this->members[$id]['ships']);
            $dmgDone = $this->members[$id]['dmgDone'];
            $corporationID = $this->members[$id]['corporationID'];
            $allianceID = $this->members[$id]['allianceID'];
            $characterID = $id;
            $stmt->execute();
        }
        $stmt->close();
        $sql = "REPLACE INTO fleetMetrics (fleetID, kills, losses, iskDestroyed, iskLost, dmgDone) VALUES ({$this->fleetID}, {$this->kills}, {$this->losses}, {$this->iskDestroyed}, {$this->iskLost}, {$this->dmgDone})";
        $qry->query($sql);
    }

    public function getFleetID() {
        return $this->fleetID;
    }

    public function getKey() {
        return md5($this->fc.$this->fleetID.$this->created.STATS_SALT);
    }

    public function getVisibility() {
        return $this->stats;
    }

    public function setVisibility($stats) {
        $this->stats = $stats;
        $qry = DB::getConnection();
        $sql = "UPDATE fleets SET stats='".$qry->real_escape_string($stats)."' WHERE fleetID=".$this->fleetID;
        $qry->query($sql);
        $sql = "UPDATE statsFleets SET stats='".$qry->real_escape_string($stats)."' WHERE fleetID=".$this->fleetID;
        $qry->query($sql);
    }

    public function getFC() {
        return $this->fc;
    }

    public function getFCName() {
        if (!isset($this->members[$this->fc])) {
            return 'None';
        }
        if (!isset($this->members[$this->fc]['name'])) {
            $this->addAllNames();
        }
        return $this->members[$this->fc]['name'];
    }

    public function getFCCorporation() {
        $qry = DB::getConnection();
        $sql = "SELECT corporationID FROM participation WHERE fleetID=".$this->fleetID." AND characterID=".$this->fc;
        $result = $qry->query($sql);
        if($result->num_rows) {
            return $result->fetch_array()[0];
        }
        return 0;
    }

    public function getFCAlliance() {
        $qry = DB::getConnection();
        $sql = "SELECT allianceID FROM participation WHERE fleetID=".$this->fleetID." AND characterID=".$this->fc;
        $result = $qry->query($sql);
        if($result->num_rows) {
            return $result->fetch_array()[0];
        }
        return 0;
    }

    public function getEnded() {
        return $this->ended;
    }

    public function getCreated() {
        return $this->created;
    }

    public function getMembers() {
        return $this->members;
    }

    public function getStats() {
        return array('kills' => $this->kills, 
                     'losses' => $this->losses,
                     'iskDestroyed' => $this->iskDestroyed,
                     'iskLost' => $this->iskLost,
                     'dmgDone' => $this->dmgDone);
    }

    public function getKillmails($number = null) {
        $response = array();
        $ids = array();
        $qry = DB::getConnection();
        $sql = "SELECT k.*, ms.solarSystemName, mr.regionName, ms.security, it.typeName as shipName, ig.groupName as shipClass FROM `kills` as k 
                LEFT JOIN mapSolarSystems as ms ON k.systemID=ms.solarSystemID 
                LEFT JOIN mapRegions as mr ON ms.regionID=mr.regionID 
                LEFT JOIN invTypes as it ON k.shipID=it.typeID 
                LEFT JOIN invGroups as ig ON it.groupID = ig.groupID 
                WHERE fleetID=".$this->fleetID;
        if ($number) {
            $sql .= " ORDER BY value DESC LIMIT 5";
        }
        $result = $qry->query($sql);
        while ($row = $result->fetch_assoc()) {
            $km = json_decode($row['killmail'], true);
            $response[$row['killID']] = array('type' => $row['type'], 
                                              'killTime' => strtotime($row['killTime']),
                                              'shipID' => $row['shipID'],
                                              'shipName' => $row['shipName'],
                                              'shipClass' => $row['shipClass'],
                                              'value' => $row['value'],
                                              'involved' => count($km['attackers']),
                                              'systemID' => $row['systemID'],
                                              'systemName' => $row['solarSystemName'],
                                              'regionName' => $row['regionName'],
                                              'security' => $row['security'],
                                              'corporationID' => $km['victim']['corporation_id']);
            if (isset($km['victim']['character_id']) && $km['victim']['character_id']) {
                $response[$row['killID']]['victimID'] = $km['victim']['character_id'];
                $ids[] = $km['victim']['character_id'];
            }
            $ids[] = $km['victim']['corporation_id'];
            if (isset($km['victim']['alliance_id']) && $km['victim']['alliance_id']) {
                $response[$row['killID']]['allianceID'] = $km['victim']['alliance_id'];
                $ids[] = $km['victim']['alliance_id'];
            } else {
                $response[$row['killID']]['allianceID'] = 0;
            }
        }
        $names = EVEHELPERS::esiIdsToNames($ids);
        foreach ($response as $id => $r) {
            if (isset($r['victimID']) && isset($names[$r['victimID']])) {
                $response[$id]['name'] = $names[$r['victimID']];
            } else {
                $response[$id]['name'] = 'Unknown';
            }
            if (isset($names[$r['corporationID']])) {
                $response[$id]['corporationName'] = $names[$r['corporationID']];
            } else {
                $response[$id]['corporationName'] = 'Unknown Corp';
            }
            if ($r['allianceID'] && isset($names[$r['allianceID']])) {
                $response[$id]['allianceName'] = $names[$r['allianceID']];
            } elseif ($r['allianceID']) {
                $response[$id]['allianceName'] = 'Unknown Alliance';
            }

        }
        return $response;
    }

    public function getShipsUsed() {
        $response = array();
        $ships = array();
        foreach ($this->members as $member) {
            foreach($member['ships'] as $s) {
                if (!isset($ships[$s])) {
                    $ships[$s] = 1;
                } else {
                    $ships[$s] += 1;
                }
            }
        }
        $names = DBH::getInvNames(array_keys($ships));
        foreach ($ships as $id => $cnt) {
            $response[] = array('shipID' => $id, 'shipName' => $names[$id], 'count' => $cnt);
        }
        return $response;
    }

    public function getShipClassesUsed() {
        $response = array();
        $qry = DB::getConnection();
        $sql = "SELECT ig.groupName as shipClass, COUNT(ig.groupID) as `count` 
                FROM participation AS p JOIN (SELECT typeID, groupID FROM invTypes WHERE marketGroupID IS NOT NULL AND published = TRUE) AS i 
                ON JSON_CONTAINS(p.ships, CAST(i.typeID AS JSON)) 
                LEFT JOIN invGroups AS ig ON ig.groupID = i.groupID
                WHERE fleetID=".$this->fleetID." AND i.typeID IS NOT NULL GROUP BY ig.groupID";
        $result = $qry->query($sql);
        while ($row = $result->fetch_assoc()) {
            $response[] = $row;
        }
        return $response;
    }

    public function getShipStats() {
        $killmails = DBH::getFleetKills($this->fleetID);
        $memberids = array_keys($this->members);
        $shipcount = array();
        foreach ($this->members as $m) {
            foreach ($m['ships'] as $s) {
                if (isset($shipcount[$s])) {
                    $shipcount[$s] += 1;
                } else {
                    $shipcount[$s] = 1;
                }
            }
        }
        $vcount = array();
        $ships = array();
        $victims = array();
        foreach($killmails as $k) {
            $counted = array();
            $km = json_decode($k['killmail'], true);
            if ($k['type'] == 'kill') {
                if (!isset($victims[$km['victim']['ship_type_id']])) {
                    $victims[$km['victim']['ship_type_id']] = array('losses' => 1, 'dmgTaken' => $km['victim']['damage_taken'], 'valueLost' => $k['value']);
                    $victims[$km['victim']['ship_type_id']] = array('count' => 1, 'kills' => 1, 'losses' => 1, 'dmgDone' => 0, 'dmgTaken' => $km['victim']['damage_taken'], 'finalBlows' => 0, 'valueDestroyed' => 0, 'valueLost' => $k['value']);
                } else {
                    $victims[$km['victim']['ship_type_id']]['losses'] += 1;
                    $victims[$km['victim']['ship_type_id']]['count'] += 1;
                    $victims[$km['victim']['ship_type_id']]['dmgTaken'] += $km['victim']['damage_taken'];
                    $victims[$km['victim']['ship_type_id']]['valueLost'] += $k['value'];
                }
                foreach($km['attackers'] as $a) {
                    if(!isset($a['ship_type_id']) || !isset($a['character_id']) || !in_array($a['character_id'], $memberids)) {
                        continue;
                    }
                    if(!isset($ships[$a['ship_type_id']])) {
                        $ships[$a['ship_type_id']] = array('count' => $shipcount[$a['ship_type_id']], 'kills' => 1, 'losses' => 0, 'dmgDone' => $a['damage_done'], 'dmgTaken' => 0, 'finalBlows' => 0, 'valueDestroyed' => $k['value'], 'valueLost' => 0);
                        $counted[] = $a['ship_type_id'];
                    } else {
                        if (!in_array($a['ship_type_id'], $counted)) {
                            $ships[$a['ship_type_id']]['kills'] += 1;
                            $ships[$a['ship_type_id']]['valueDestroyed'] += $k['value'];
                            $counted[] = $a['ship_type_id'];
                        }
                        $ships[$a['ship_type_id']]['dmgDone'] += $a['damage_done'];
                    }
                    if(isset($a['final_blow']) && $a['final_blow']) {
                        $ships[$a['ship_type_id']]['finalBlows'] += 1;
                    }
                }
            } else {
                $max_counts = array();
                $v = $km['victim'];
                if (!isset($ships[$v['ship_type_id']])) {
                    $ships[$v['ship_type_id']] = array('count' => $shipcount[$v['ship_type_id']], 'kills' => 0, 'losses' => 1, 'dmgDone' => 0, 'dmgTaken' => $v['damage_taken'], 'finalBlows' => 0, 'valueDestroyed' => 0, 'valueLost' => $k['value']);
                } else {
                    $ships[$v['ship_type_id']]['losses'] += 1;
                    $ships[$v['ship_type_id']]['valueLost']  += $k['value'];
                    $ships[$v['ship_type_id']]['dmgTaken'] = $v['damage_taken'];
                }

                foreach($km['attackers'] as $a) {
                    if(!isset($a['ship_type_id']) || !isset($a['character_id']) || in_array($a['character_id'], $memberids)) {
                        continue;
                    }
                    if(!isset($max_counts[$a['ship_type_id']])) {
                        $max_counts[$a['ship_type_id']] = 1;
                    } else {
                        $max_counts[$a['ship_type_id']] += 1;
                    }
                    if(!isset($victims[$a['ship_type_id']])) {
                        $victims[$a['ship_type_id']] = array('count' => 0, 'kills' => 1, 'losses' => 0, 'dmgDone' => $a['damage_done'], 'dmgTaken' => 0, 'finalBlows' => 0, 'valueDestroyed' => $k['value'], 'valueLost' => 0);
                        $counted[] = $a['ship_type_id'];
                    } else {
                        if (!in_array($a['ship_type_id'], $counted)) {
                            $victims[$a['ship_type_id']]['kills'] += 1;
                            $victims[$a['ship_type_id']]['valueDestroyed'] += $k['value'];
                            $counted[] = $a['ship_type_id'];
                        }
                        $victims[$a['ship_type_id']]['dmgDone'] += $a['damage_done'];
                    }
                    if(isset($a['final_blow']) && $a['final_blow']) {
                        $victims[$a['ship_type_id']]['finalBlows'] += 1;
                    }
                }
                foreach ($max_counts as $id => $cnt) {
                    if(!isset($vcount[$id]) || $vcount[$id] < $cnt) {
                        $vcount[$id] = $cnt;
                    }
                }
            }
        }
        foreach ($shipcount as $id => $count) {
            if (!isset($ships[$id])) {
                $ships[$id] = array('count' => $count, 'kills' => 0, 'losses' => 0, 'dmgDone' => 0, 'dmgTaken' => 0, 'finalBlows' => 0, 'valueDestroyed' => 0, 'valueLost' => 0);
            }
        }
        foreach ($victims as $id => $stats) {
            if (isset($vcount[$id]) && $stats['count'] < $vcount[$id]) {
                $victims[$id]['count'] = $vcount[$id];
            }
        }
        return array('friendly' => $ships, 'victims' => $victims);
    }

    public function getEnemies() {
        $killmails = DBH::getFleetKills($this->fleetID);
        $memberids=array_keys($this->members);
        $corps = array();
        $allys = array();
        $pilots = array();
        $counted = array();
        $noally = 0;
        foreach($killmails as $k) {
            $km = json_decode($k['killmail'], true);
            if ($k['type'] == 'kill') {
                if (isset($km['victim']['character_id'] )) {
                    if (!in_array($km['victim']['character_id'], $counted)) {
                        $counted[] = $km['victim']['character_id'];
                        $pilots[$km['victim']['character_id']] = array();
                    } else {
                        continue;
                    }
                    if (!isset($corps[$km['victim']['corporation_id']] )) {
                        $corps[$km['victim']['corporation_id']]['count'] = 1;
                    } else {
                        $corps[$km['victim']['corporation_id']]['count'] += 1;
                    }
                    $pilots[$km['victim']['character_id']]['corporationID'] = $km['victim']['corporation_id'];
                    if (isset($km['victim']['alliance_id'])) {
                        if (!isset($allys[$km['victim']['alliance_id']] )) {
                            $allys[$km['victim']['alliance_id']]['count'] = 1;
                        } else {
                            $allys[$km['victim']['alliance_id']]['count'] += 1;
                        }
                        $pilots[$km['victim']['character_id']]['allianceID'] = $km['victim']['alliance_id'];
                    }
                }
            } else {
                foreach($km['attackers'] as $a) {
                    if (isset($a['character_id']) && !in_array($a['character_id'], $memberids) ) {
                        if (!in_array($a['character_id'], $counted)) {
                            $counted[] = $a['character_id'];
                            $pilots[$a['character_id']] = array();
                        } else {
                            continue;
                        }
                        if (!isset($corps[$a['corporation_id']] )) {
                            $corps[$a['corporation_id']]['count'] = 1;
                        } else {
                            $corps[$a['corporation_id']]['count'] += 1;
                        }
                        $pilots[$a['character_id']]['corporationID'] = $a['corporation_id'];
                        if (isset($a['alliance_id'])) {
                            if (!isset($allys[$a['alliance_id']] )) {
                                $allys[$a['alliance_id']]['count'] = 1;
                            } else {
                                $allys[$a['alliance_id']]['count'] += 1;
                            }
                            $pilots[$a['character_id']]['allianceID'] = $a['alliance_id'];
                        } else {
                            $noally += 1;
                        }
                    }               
                }
            }
        }
        $names = EVEHELPERS::esiIdsToNames(array_merge(array_keys($pilots), array_keys($corps), array_keys($allys)));
        foreach ($pilots as $p => $plt) {
            if (isset($names[$p])) {
                $pilots[$p]['name'] = $names[$p];
            } else {
                $pilots[$p]['name'] = 'Unknown pilot';
            }
        }
        foreach ($corps as $c => $corp) {
            if (isset($names[$c])) {
                $corps[$c]['name'] = $names[$c];
            } else {
                $corps[$c]['name'] = 'Unknown corporation';
            }
        }
        foreach ($allys as $a => $ally) {
            if (isset($names[$a])) {
                $allys[$a]['name'] = $names[$a];
            } else {
                $allys[$a]['name'] = 'Unknown alliance';
            }
        }
        if ($noally > 0) {
            $allys[0] = array('name' => 'No alliance', 'count' => $noally);
        }
        return array('pilots' => $pilots, 'corporations' => $corps, 'alliances' => $allys);
    }

    public function getTitle() {
        return $this->title;
    }

    public function setTitle($title) {
        $this->title = $title;
        $qry = DB::getConnection();
        $sql = "UPDATE fleets SET title='".$qry->real_escape_string($title)."' WHERE fleetID=".$this->fleetID;
        $qry->query($sql);
        $sql = "UPDATE statsFleets SET title='".$qry->real_escape_string($title)."' WHERE fleetID=".$this->fleetID;
        $qry->query($sql);
    }

    public static function getViewableStats($characterID, $isAdmin = false) {
        $sql = "SELECT f.fleetID, ANY_VALUE(f.title) as title, ANY_VALUE(f.fc) as fc, ANY_VALUE(f.stats) as stats, ANY_VALUE(f.created) as created,
                       ANY_VALUE(f.ended) as ended, ANY_VALUE(fc.corporationID) as fleetCorporation, ANY_VALUE(fc.allianceID) as fleetAlliance, 
                       ANY_VALUE(fm.dmgDone) as dmgDone, ANY_VALUE(fm.iskDestroyed) as iskDestroyed, ANY_VALUE(fm.iskLost) as iskLost, 
                       ANY_VALUE(fm.kills) as kills, ANY_VALUE(fm.losses) as losses, COUNT(p.characterID) as memberCount 
                FROM (SELECT fleetID, title, fc, stats, created, ended FROM `fleets` 
                      UNION SELECT fleetID, title, fc, stats, created, ended FROM statsFleets) as f
                INNER JOIN participation AS fc ON (f.fc = fc.characterID AND f.fleetID = fc.fleetID)
                LEFT JOIN participation AS p ON f.fleetID = p.fleetID
                LEFT JOIN fleetMetrics as fm ON f.fleetID = fm.fleetID";
        if (!$isAdmin) {
            $sql .= " WHERE stats='public' OR fc=".$characterID." OR (p.characterID=".$characterID." and stats = 'fleet')";
            $corp = ESIPILOT::getCorpForChar($characterID);
            $sql .= " OR (fc.corporationID=".$corp." and stats = 'corporation')";
            $ally = ESIPILOT::getAllyForChar($characterID);
            if (isset($ally) && $ally) {
                $sql .= " OR (fc.allianceID=".$ally." and stats = 'alliance')";
            }
        }
        $sql .= " GROUP BY fleetID ORDER BY created DESC";
        $qry = DB::getConnection();
        $result = $qry->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function clearKills() {
        $this->kills = 0;
        $this->losses = 0;
        $qry = DB::getConnection();
        $sql = "DELETE FROM kills WHERE fleetID=".$this->fleetID;
        $qry->query($sql);
    }

    public function removePilot($id) {
        $this->kills = 0;
        $this->losses = 0;
        $qry = DB::getConnection();
        $sql = "DELETE FROM participation WHERE fleetID=".$this->fleetID." AND characterID=".$id;
        $qry->query($sql);
        if (isset($this->members[$id])) {
            unset($this->members[$id]);
        }
    }

    public function addPilot($id) {
        $names = EVEHELPERS::esiIdsToNames(array($id));
        if (isset($names[$id])) {
            $name = $names[$id];
        } else {
            return false;
        }
        $this->members[$id] = array('name' => $name);
        $this->addAffiliations();
        $qry = DB::getConnection();
        $stmt = $qry->prepare("INSERT into participation (fleetID, characterID, corporationID, allianceID) VALUES (".$this->fleetID.", ?, ?, ?)");
        $stmt->bind_param('iii', $char, $corp, $ally);
        $char = $id;
        $corp = $this->members[$id]['corporationID'];
        $ally = $this->members[$id]['allianceID'];
        $stmt->execute();
        if ($stmt->errno) {
            $stmt->close();
            return false;
        }
        $stmt->close();
        return true;
    }


    public function getError() {
		return $this->error;
	}

    public function getMessage() {
        return $this->message;
    }

}
