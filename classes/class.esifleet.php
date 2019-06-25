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
class ESIFLEET extends ESISSO
{
    protected $fleetID = null;
    protected $title = '';
    protected $fc = null;
    protected $boss = null;
    protected $members = array();
    protected $backupfcs = array();
    protected $public = false;
    protected $created = null;
    protected $lastFetch = null;
    protected $freemove = false;
    protected $motd = null;
    protected $stats = 'fleet';
    protected $tracking = false;
    private $fleetfailcount;

    public function __construct($fleetID, $characterID, $dbonly=false) {
        $this->fleetID = $fleetID;        
        parent::__construct(null, $characterID);
        $qry = DB::getConnection();
        $sql = "SELECT * FROM fleets WHERE fleetID=".$fleetID." AND fleets.created >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)";
        $result = $qry->query($sql);
        if($result->num_rows) {
            $row=$result->fetch_assoc();
            $this->title = $row['title'];
            $this->fc = $row['fc'];
            $this->boss = $row['boss'];
            $this->public = $row['public'];
            $this->created = strtotime($row['created']);
            $this->lastFetch = strtotime($row['lastFetch']);
            $this->fleetfailcount = $row['fleetfail'];
            $this->stats = $row['stats'];
            $this->tracking = $row['tracking'];
            $sql = "SELECT fm.characterID, p.characterName, fm.backupfc, p.shipTypeID, p.fitting, p.locationID, p.stationID, p.structureID, fm.wingID, fm.squadID, fm.role, fm.fleetWarp, fm.joined FROM fleetmembers as fm 
                LEFT JOIN pilots as p ON p.characterID=fm.characterID WHERE fleetID=".$fleetID;
            if ($stmt = $qry->prepare($sql)) {
                $stmt->execute();
                $stmt->bind_result($id, $name, $backup, $ship, $fit, $system, $station, $structure, $wing, $squad, $role, $fleetwarp, $joined);
                while ($stmt->fetch()) {
                    $this->members[] = array('id' => $id,
                                         'name' => $name,
                                         'backupfc' => $backup,
                                         'joined' => strtotime($joined),
                                         'role' => $role,
                                         'ship' => $ship,
                                         'fit' => $fit,
                                         'system' => $system,
                                         'station' => $station,
                                         'station' => $structure,
                                         'wing' => $wing,
                                         'squad' => $squad,
                                         'fleetwarp' => $fleetwarp );
                }
                $stmt->close();
            }        

        } elseif (!$dbonly) {
            $esiapi = new ESIAPI();
            if ($this->hasExpired()) {
                $this->refresh(false);
            }
            $esiapi->setAccessToken($this->accessToken);
            $fleetapi = $esiapi->getApi('Fleets');
            try {
                $fleetinfo = $fleetapi->getFleetsFleetId($fleetID, 'tranquility');
                $fleetmembers = $fleetapi->getFleetsFleetIdMembers($fleetID);
            } catch (Exception $e) {
                $this->error = true;
                $this->message = 'Could not find Fleet: '.$e->getMessage().PHP_EOL;
            }
            if (!$this->error) {
                $this->resetFleetFailCount();
                $this->boss = $characterID;
                $this->fc = null;
                $this->freemove = $fleetinfo->getIsFreeMove();
                $this->motd = $fleetinfo->getMotd();
                $sql = "DELETE FROM fleetmembers WHERE fleetID=".$this->fleetID;
                $qry->query($sql);
                foreach ($fleetmembers as $member) {
                    $this->members[] = array('id' => $member->getCharacterId(),
                                             'backupfc' => false, 
                                             'joined' => $member->getJoinTime(),
                                             'role' => $member->getRole(),
                                             'ship' => $member->getShipTypeId(),
                                             'fit' => null,
                                             'system' => $member->getSolarSystemId(),
                                             'station' => $member->getStationId(),
                                             'structure' => null,
                                             'wing' => $member->getWingId(),
                                             'squad' => $member->getSquadId(),
                                             'fleetwarp' => ($member->getTakesFleetWarp()?1:0) );
                    if ($member->getRole() == 'fleet_commander') {
                        $this->fc = $member->getCharacterId();
                    }
                }
                if (!$this->fc) {
                    $this->fc = $this->boss;
                }
                $sql = "REPLACE INTO fleets (fleetID,title,boss,fc,created,lastFetch) VALUES ({$this->fleetID},'{$this->title}',{$this->boss},'{$this->fc}',UTC_TIMESTAMP(),NOW())";
                $qry->query($sql);
                foreach($this->members as $m) {
                    $sql = "REPLACE INTO fleetmembers (characterID, fleetID, backupfc, wingID, squadID, role, fleetWarp, joined)
                           VALUES ({$m['id']},{$this->fleetID},FALSE,{$m['wing']},{$m['squad']},'{$m['role']}', '{$m['fleetwarp']}','".$m['joined']->format('Y-m-d H:i:s')."')";
                    $qry->query($sql);
                }
                $this->update();
            }
        } else {
            $this->fleetID = null;
        }
    }

    public static function getFleetForChar($characterID) {
        $qry = DB::getConnection();
        $sql = "SELECT fleets.fleetID, fleets.boss FROM fleetmembers LEFT JOIN fleets ON fleets.fleetID=fleetmembers.fleetID WHERE characterID=".$characterID." AND fleets.created >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) AND fleets.ended IS NULL";
        $result = $qry->query($sql);
        if($result->num_rows) {
            $row=$result->fetch_assoc();
            $fleet = new ESIFLEET($row['fleetID'], $row['boss'], false);
            if ($fleet->getFleetID() == null) {
                return false;
            } else {
                return $fleet;
            }
        } else {
            return false;
        }
    }

    public function update() {
        $esiapi = new ESIAPI();
        if ($this->hasExpired()) {
            $this->refresh(false);
        }
        $esiapi->setAccessToken($this->accessToken);
        $fleetapi = $esiapi->getApi('Fleets');
        try {
            $fleetinfo = $fleetapi->getFleetsFleetId($this->fleetID, 'tranquility');
            $fleetmembers = $fleetapi->getFleetsFleetIdMembers($this->fleetID);
        } catch (Exception $e) {
            $this->error = true;
            $this->increaseFleetFailCount();
            if ($e->getCode() == 403) {
                $sql = "";
                $this->message = 'Could not fetch fleet: '.$e->getMessage();
                $this->log->error($this->message);
                $this->message = 'Looks like the fleet Boss dropped the fleet or has handed over fleet boss. If you\'re fleet boss register the fleet <a href="'.URL::url_path().'registerfleet.php">here</a>.';
            } elseif ($e->getCode() == 404) {
                $this->message = 'Could not fetch fleet: '.$e->getMessage();
                $this->log->error($this->message);
                $this->message = 'Looks like your last fleet ended.';
            } else {
                $this->message = 'Could not refresh your last Fleet: '.$e->getMessage().PHP_EOL;
            }
            if ($this->fleetfailcount >= 2 && !$this->hasEnded()) {
                $this->endFleet();
                $this->resetFleetFailCount();
            }
            return false;
        }
        $this->resetFleetFailCount();
        $this->freemove = $fleetinfo->getIsFreeMove();
        $this->motd = $fleetinfo->getMotd();
        $this->members = array();
        $dbmembers = array();
        $qry = DB::getConnection();
        $sql = "SELECT fm.fleetID as fleet, fm.characterID as id, p.shipTypeID as ship, p.fitting as fit, p.characterName as name, es.enabled as enabled,
                       p.locationID as location, p.stationID as station, p.structureID as structure, fm.backupfc as bfc, p.lastFetch as lastFetch
                       FROM fleetmembers as fm INNER JOIN pilots as p ON p.characterID=fm.characterID LEFT JOIN esisso as es ON p.characterID = es.characterID";
        $result = $qry->query($sql);
        while ($row = $result->fetch_assoc()) {
            $dbmembers[$row['id']] = array('fleet' => $row['fleet'], 'ship' => $row['ship'], 'fit'=> $row['fit'], 'name' => $row['name'], 'system' => $row['location'], 'station' => $row['station'], 'bfc' => $row['bfc'], 'lastFetch' => strtotime($row['lastFetch']), 'enabled' => $row['enabled']);
        }
        $this->fc = 0;
        foreach ($fleetmembers as $member) {
            if (!$member->getStationId()) {
                $stationID = 0;
            } else {
                $stationID = $member->getStationId();
            }
            $this->members[$member->getCharacterId()] = array('id' => $member->getCharacterId(),
                                     'backupfc' => false,
                                     'joined' => $member->getJoinTime(),
                                     'role' => $member->getRole(),
                                     'ship' => $member->getShipTypeId(),
                                     'fit' => null,
                                     'system' => $member->getSolarSystemId(),
                                     'station' => $stationID,
                                     'structure' => 0,
                                     'wing' => $member->getWingId(),
                                     'squad' => $member->getSquadId(),
                                     'fleetwarp' => ($member->getTakesFleetWarp()?1:0), 
                                     'sso' => False);
            if ($member->getRole() == 'fleet_commander') {
                $this->fc = $member->getCharacterId();
            }
        }
        if (!$this->fc) {
            $this->fc = $this->boss;
        }
        $promise = array();
        $esiapi = new ESIAPI();
        foreach($this->members as $m) {
            if(isset($dbmembers[$m['id']]) && $dbmembers[$m['id']]['enabled']) {
                $this->members[$m['id']]['sso'] = True;
                $esisso = new ESISSO(null, $m['id']);
                if (!$esisso->getError()) {
                    $locationapi = $esiapi->getApi('Location');
                    $promise[$m['id']] = $locationapi->getCharactersCharacterIdLocationAsync($m['id'], 'tranquility', null, $esisso->getAccessToken());
                }
            }
        }
        $responses = GuzzleHttp\Promise\settle($promise)->wait();
        foreach ($responses as $id => $response) {
            if ($response['state'] == 'fulfilled') {
                $this->members[$id]['system'] = $response['value']->getSolarSystemId();
                if ($response['value']->getStationId()) {
                    $this->members[$id]['station'] = $response['value']->getStationId();
                    $this->members[$id]['structure'] = 0;
                } elseif ($response['value']->getStructureId()) {
                    $this->members[$id]['structure'] = $response['value']->getStructureId();
                    $this->members[$id]['station'] = 0;
                } else {
                    $this->members[$id]['station'] = 0;
                    $this->members[$id]['structure'] = 0;
                }
            } elseif ($response['state'] == 'rejected') {
                $this->log->error($response['reason']);
            }
        }
        foreach($this->members as $i => $m) {
            if (isset($dbmembers[$m['id']])) {
                if ($dbmembers[$m['id']]['name'] != null && $dbmembers[$m['id']]['name'] != '') {
                    $this->members[$i]{'name'} = $dbmembers[$m['id']]['name'];
                }
                $m['backupfc'] = $this->members[$i]['backupfc'] = $dbmembers[$m['id']]['bfc'];
                $sql = "UPDATE fleetmembers SET fleetID={$this->fleetID}, wingID={$m['wing']}, squadID={$m['squad']},role='{$m['role']}',fleetWarp='{$m['fleetwarp']}' WHERE characterID={$m['id']}";
                $qry->query($sql);
                if ($m['system'] != $dbmembers[$m['id']]['system'] || ((int)$m['station'] != (int)$dbmembers[$m['id']]['station'])) {
                    if ($m['ship'] != $dbmembers[$m['id']]['ship']) {
                        if (strtotime("now") - $dbmembers[$m['id']]['lastFetch'] < 30) {
                            $sql="UPDATE pilots SET locationID='{$m['system']}',stationID={$m['station']},
                                structureID=0,lastFetch=NOW() WHERE characterID={$m['id']}";
                            $m['fit'] = $this->members[$i]['fit'] = $dbmembers[$m['id']]['fit'];
                            $m['ship'] = $this->members[$i]['ship'] = $dbmembers[$m['id']]['ship'];
                        } else {
                            $sql="UPDATE pilots SET locationID={$m['system']},shipTypeID={$m['ship']},stationID={$m['station']},
                                  structureID={$m['structure']},fitting=NULL,lastFetch=NOW() WHERE characterID={$m['id']}"; 
                        }
                    } else {
                        $sql="UPDATE pilots SET locationID={$m['system']},stationID={$m['station']},
                              structureID={$m['structure']},lastFetch=NOW() WHERE characterID={$m['id']}";
                        $m['fit'] = $this->members[$i]['fit'] = $dbmembers[$m['id']]['fit'];
                    }
                } elseif ($m['ship'] != $dbmembers[$m['id']]['ship']) {
                    if (strtotime("now") - $dbmembers[$m['id']]['lastFetch'] < 30) {
                        $m['fit'] = $this->members[$i]['fit'] = $dbmembers[$m['id']]['fit'];
                        $m['ship'] = $this->members[$i]['ship'] = $dbmembers[$m['id']]['ship'];
                    } else {
                        $sql="UPDATE pilots SET shipTypeID={$m['ship']},fitting=NULL,lastFetch=NOW() WHERE characterID={$m['id']}";
                    }
                } else {
                    $m['fit'] = $this->members[$i]['fit'] = $dbmembers[$m['id']]['fit'];
                }
                $qry->query($sql);
                unset($dbmembers[$m['id']]);
                if ($m['role'] == 'fleet_commander') {
                    $this->fc = $m['id'];
                }
            } else {
                    $esiapi = new ESIAPI();
                    $charapi = $esiapi->getApi('Character');
                    try {
                        $charinfo = json_decode($charapi->getCharactersCharacterId($m['id'], 'tranquility'));
                        $characterName = $charinfo->name;
                        $m['name'] = $this->members[$i]['name'] = $characterName;
                    } catch (Exception $e) {
                        $m['name'] = $this->members[$i]['name'] = null;
                    }
                    if(!isset($m['station'])) {
                        $m['station'] = 0;
                    }
                    if(!isset($m['structure'])) {
                        $m['structure'] = 0;
                    }
                    $sql = "REPLACE INTO fleetmembers (characterID, fleetID, backupfc, wingID, squadID, role, fleetWarp, joined)
                            VALUES ({$m['id']},{$this->fleetID},FALSE,{$m['wing']},{$m['squad']},'{$m['role']}', '{$m['fleetwarp']}','".$m['joined']->format('Y-m-d H:i:s')."')";
                    $qry->query($sql);
                    $sql = "REPLACE INTO pilots (characterID,characterName,locationID,shipTypeID,stationID,structureID,fitting,lastFetch) VALUES ({$m['id']},'".$qry->real_escape_string($m['name'])."',{$m['system']},{$m['ship']},{$m['station']},{$m['structure']},NULL,NOW())";
                    $qry->query($sql);
            }
        }
        if (count($dbmembers)) {
            $dbleft = array_keys($dbmembers);
            $sql="DELETE FROM fleetmembers WHERE (characterID=".implode(" OR characterID=", $dbleft).") AND fleetID =".$this->fleetID;
            $qry->query($sql);
        }
        if ($this->tracking) {
            $this->addAffiliations();
            $stmt = $qry->prepare("INSERT IGNORE into participation (fleetID, characterID, corporationID, allianceID) VALUES (".$this->fleetID.", ?, ?, ?)");
            $stmt->bind_param('iii', $char, $corp, $ally);
            foreach($this->members as $id => $m) {
                $char = $m['id'];
                $corp = $m['corporationID'];
                $ally = $m['allianceID'];
                $stmt->execute();
            }
            $stmt->close();
            foreach($this->members as $m) {
                 $sql = "SELECT ships FROM participation WHERE fleetID =".$this->fleetID." AND characterID=".$m['id'];
                 $result = $qry->query($sql);
                 while ($row = $result->fetch_assoc()) {
                     if ($row['ships']) {
                         $ships = json_decode($row['ships'], true);
                     } else {
                         $ships = array();
                     }
                 }
                 if (!in_array($m['ship'], array(670, 33328)) && !in_array($m['ship'], $ships)) {
                     $ships[] = $m['ship'];
                     $sql = "UPDATE participation SET ships='".json_encode($ships)."' WHERE fleetID =".$this->fleetID." AND characterID=".$m['id'];
                     $qry->query($sql);
                 }
            }
        }
        $sql = "UPDATE fleets SET fc='{$this->fc}', lastFetch=NOW() WHERE fleetID =".$this->fleetID;
        $qry->query($sql);
        return true;
    }

    public function getWings() {
        $esiapi = new ESIAPI();
        if ($this->hasExpired()) {
            $this->refresh(false);
        }
        $esiapi->setAccessToken($this->accessToken);
        $fleetapi = $esiapi->getApi('Fleets');
        try {
            $fleetwings = $fleetapi->getFleetsFleetIdWings($this->fleetID);
        } catch (Exception $e) {
            $this->error = true;
            $this->increaseFleetFailCount();
            $this->message = 'Could not fetch fleet wings: '.$e->getMessage();
            $this->log->error($this->message);
            return array();
        }
        $fleet = array('commander' => null, 'wings' => array(), 'count' => 0);
        foreach($fleetwings as $fw) {
            $w = json_decode($fw, true);
            $wing = array('name' => $w['name'], 'commander' => null, 'squads' => array(), 'count' => 0);
            foreach($w['squads'] as $s) {
                $squad = array('name' => $s['name'], 'commander' => null, 'members' => array(), 'count' => 0);
                $wing['squads'][$s['id']] = $squad;
            }
            $fleet['wings'][$w['id']] = $wing;
        }
        $qry = DB::getConnection();
        $sql = "SELECT fm.*, p.characterName, p.locationID, p.shipTypeID, p.stationID, p.structureID, e.enabled 
                FROM `fleetmembers` as fm LEFT JOIN pilots as p ON fm.characterID = p.characterID 
                LEFT JOIN esisso as e ON e.characterID = fm.characterID WHERE fm.fleetID=".$this->fleetID;
        $result = $qry->query($sql);
        $members = array();
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        foreach ($members as $m) {
            $fleet['count'] += 1;
            switch ($m['role']) {
                case 'fleet_commander':
                    $fleet['commander'] = $m;
                    break;
                case 'wing_commander':
                    $fleet['wings'][$m['wingID']]['commander'] = $m;
                    $fleet['wings'][$m['wingID']]['count'] += 1;
                    break;
                case 'squad_commander':
                    $fleet['wings'][$m['wingID']]['count'] += 1;
                    $fleet['wings'][$m['wingID']]['squads'][$m['squadID']]['count'] += 1;
                    $fleet['wings'][$m['wingID']]['squads'][$m['squadID']]['commander'] = $m;
                    break;
                default:
                case 'squad_member':
                    $fleet['wings'][$m['wingID']]['count'] += 1;
                    $fleet['wings'][$m['wingID']]['squads'][$m['squadID']]['count'] += 1;
                    $fleet['wings'][$m['wingID']]['squads'][$m['squadID']]['members'][] = $m;
                    break;
            }
        }
        return $fleet;
    }

    private function addAffiliations() {
        if (count($this->members)) {
            $esiapi = new ESIAPI();
            $charapi = $esiapi->getApi('Character');
            try{
                $result = $charapi->postCharactersAffiliation(json_encode(array_column($this->members, 'id')));
            } catch (Exception $e) {
                $this->error = true;
                $this->message = 'Could not fetch character affiliations: '.$e->getMessage();
                $this->log->error($this->message);
                return false;
            }
            $i = 0;
            foreach ($this->members as $id => $m) {
                $this->members[$id]['corporationID'] = $result[$i]->getCorporationID();
                if ($result[$i]->getAllianceID()) {
                    $this->members[$id]['allianceID'] = $result[$i]->getAllianceID();
                } else {
                    $this->members[$id]['allianceID'] = 0;
                }
                $i += 1;
            }
        }
    }

    public function getFleetID() {
        return $this->fleetID;
    }

    public function getBoss() {
        return $this->boss;
    }

    public function getFC() {
        return $this->fc;
    }

    public function isPublic() {
        return $this->public;
    }

    public function getBackupFCs() {
        return $this->backupfcs;
    }

    public function getMembers() {
        return $this->members;
    }

    public function getMotd() {
        return $this->motd;
    }

    public function getFreemove() {
        return $this->freemove;
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

    public function getTracking() {
        return $this->tracking;
    }

    public function increaseFleetFailCount() {
        $this->fleetfailcount+=1;
        $qry = DB::getConnection();
        $sql="UPDATE fleets SET fleetfail={$this->fleetfailcount} WHERE fleetID={$this->fleetID};";
        $result = $qry->query($sql);
    }

    public function resetFleetFailCount() {
        if ($this->fleetfailcount != 0) {
            $this->fleetfailcount = 0;
            $qry = DB::getConnection();
                $sql="UPDATE fleets SET fleetfail=0 WHERE fleetID={$this->fleetID};";
            $result = $qry->query($sql);
        }
    }

    public function endFleet() {
        $qry = DB::getConnection();
        $sql = "UPDATE fleets SET ended=UTC_TIMESTAMP() WHERE fleetID=".$this->fleetID;
        $qry->query($sql);
    }

    public function hasEnded() {
        $qry = DB::getConnection();
        $sql = "SELECT ended FROM fleets WHERE fleetID=".$this->fleetID;
        $result = $qry->query($sql);
        if($result->num_rows) {
            $row = $result->fetch_assoc();
            if (!isset($row['ended']) || !$row['ended']) {
                return False;
            } else {
                return True;
            }
        }
        return False;
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

}
?>
