<?php
require_once('config.php');

use Swagger\Client\ApiException;
use Swagger\Client\Api\CharacterApi;
use Swagger\Client\Api\LocationApi;
use Swagger\Client\Api\UniverseApi;
use Swagger\Client\Api\CorporationApi;

require_once('classes/esi/autoload.php');
require_once('classes/class.esisso.php');

if (session_status() != PHP_SESSION_ACTIVE) {
  session_start();
}

// Credit to FuzzySteve https://github.com/fuzzysteve/eve-sso-auth/
class ESIPILOT extends ESISSO
{
    protected $locationID = 0;
    protected $locationName = null;
    protected $shipTypeName = null;
    protected $shipTypeID = null;
    protected $shipName = null;
    protected $stationID = 0;
    protected $stationName = 'in space';
    protected $structureID = 0;
    protected $fitting = null;
    protected $lastFetch = null;
    protected $corpID = 0;
    protected $allyID = 0;

    public function __construct($characterID, $forcerefresh=false) {
        parent::__construct(null, $characterID);
        $sql="SELECT * FROM pilots WHERE characterID=".$this->characterID;
        $qry = DB::getConnection();
        $result = $qry->query($sql);
        $refresh = false;
        if($result->num_rows) {
            $row = $result->fetch_assoc();
            if ($row['characterName'] == null || $row['characterName'] == '') {
                $esiapi = new ESIAPI();
                $charapi = $esiapi->getApi('Character');
                try {
                    $charinfo = json_decode($charapi->getCharactersCharacterId($this->characterID, 'tranquility'));
                    $this->characterName = $charinfo->name;
                } catch (Exception $e) {
                    $this->error = true;
                    $this->message = 'Could not relove character name: '.$e->getMessage().PHP_EOL;
                }
            } else {
                $this->characterName = $row['characterName'];
            }
            $this->locationID = $row['locationID'];
            $this->shipTypeID = $row['shipTypeID'];
            $this->stationID = $row['stationID'];
            $this->structureID = $row['structureID'];
            $this->fitting = $row['fitting'];
            $this->corpID = $row['corporationID'];
            $this->allyID = $row['allianceID'];
            $this->lastfetch = strtotime($row['lastFetch']);
            if (strtotime("now")-$this->lastfetch > 60 ) {
                $refresh = true;
            } else {
                if (isset($this->stationID) && $this->stationID != 0) {
                    $sql="SELECT mapSolarSystems.solarSystemName as systemName, mapDenormalize.itemName as stationName FROM `mapSolarSystems` INNER JOIN mapDenormalize on mapSolarSystems.solarSystemID = mapDenormalize.solarSystemID WHERE mapSolarSystems.solarSystemID = ".$this->locationID." AND mapDenormalize.itemID =".$this->stationID;
                    $qry = DB::getConnection();
                    $result = $qry->query($sql);
                    if($result->num_rows) {
                        $row=$result->fetch_assoc();
                        $this->locationName = $row['systemName'];
                        $this->stationName = $row['stationName'];
                    }
                } else {
                    $this->stationID = 0;
                    $qry = DB::getConnection();
                    $sql="SELECT solarSystemName as systemName FROM mapSolarSystems WHERE solarSystemID = ".$this->locationID;
                    $result = $qry->query($sql);
                    if($result->num_rows) {
                        $row=$result->fetch_assoc();
                        $this->locationName = $row['systemName'];
                    }

                    if (isset($this->structureID) && $this->structureID != 0) {
                        $qry = DB::getConnection();
                        $sql="SELECT structureName FROM structures WHERE structureID = ".$this->structureID;
                        $result = $qry->query($sql);
                        if($result->num_rows) {
                            $row=$result->fetch_assoc();
                            $this->stationName = $row['structureName'];
                        }
                    }
                }
                $qry = DB::getConnection();
                $sql="SELECT typeName FROM invTypes WHERE typeID = ".$this->shipTypeID;
                $result = $qry->query($sql);
                if($result->num_rows) {
                    $row=$result->fetch_assoc();
                    $this->shipTypeName = $row['typeName'];
                }
            }
        } else {
            $refresh = true;
        }
        if ($refresh || $forcerefresh) {
            $this->corpID = $this->getCorpForChar($characterID);
            $this->allyID = $this->getAllyForCorp($this->corpID);
            if (!isset($esiapi)) {
                $esiapi = new ESIAPI();
            }
            $esiapi->setAccessToken($this->accessToken);
            $locationapi = $esiapi->getApi('Location');
            try {
                $locationinfo = json_decode($locationapi->getCharactersCharacterIdLocation($this->characterID, 'tranquility'));
                $this->locationID = $locationinfo->solar_system_id;
                if (isset($locationinfo->station_id)) {
                    $this->stationID = $locationinfo->station_id;
                    $sql="SELECT mapSolarSystems.solarSystemName as systemName, mapDenormalize.itemName as stationName FROM `mapSolarSystems` INNER JOIN mapDenormalize on mapSolarSystems.solarSystemID = mapDenormalize.solarSystemID WHERE mapSolarSystems.solarSystemID = ".$this->locationID." AND mapDenormalize.itemID =".$this->stationID;
                    $qry = DB::getConnection();
                    $result = $qry->query($sql);
                    if($result->num_rows) {
                        $row=$result->fetch_assoc();
                        $this->locationName = $row['systemName'];
                        $this->stationName = $row['stationName'];
                    } 
                } else {
                    $this->stationID = 0;
                    $qry = DB::getConnection();
                    $sql="SELECT solarSystemName as systemName FROM mapSolarSystems WHERE solarSystemID = ".$this->locationID;
                    $result = $qry->query($sql);
                    if($result->num_rows) {
                        $row=$result->fetch_assoc();
                        $this->locationName = $row['systemName'];
                    }

                }
                if (isset($locationinfo->structure_id)) {
                    $this->structureID = $locationinfo->structure_id;
                    $qry = DB::getConnection();
                    $sql="SELECT structureName FROM structures WHERE structureID = ".$this->structureID;
                    $result = $qry->query($sql);
                    if($result->num_rows) {
                        $row=$result->fetch_assoc();
                        $this->stationName = $row['structureName'];
                    } else {
                        if (!isset($esiapi)) {
                            $esiapi = new ESIAPI();
                        }
                        $esiapi->setAccessToken($this->accessToken);
                        $universeapi = $esiapi->getApi('Universe');
                        $structureinfo = json_decode($universeapi->getUniverseStructuresStructureId($this->structureID, 'tranquility'));
                        $this->stationName = $structureinfo->name;
                        $sql="INSERT INTO structures (solarSystemID,structureID,structureName,lastUpdate) VALUES ({$structureinfo->solar_system_id},{$this->structureID},'{$qry->real_escape_string($this->stationName)}',NOW())";
                        $result = $qry->query($sql);
                    }
                } else {
                    $this->structureID = 0;
                }
            } catch (Exception $e) {
                $this->error = true;
                $this->message = 'Could not get location Info: '.$e->getMessage().PHP_EOL;
                return false;
            }

            try {
                $shipinfo = json_decode($locationapi->getCharactersCharacterIdShip($this->characterID, 'tranquility'));
                $previousship = $this->shipTypeID;
                $this->shipTypeID = $shipinfo->ship_type_id;
                $shipUniqueID = $shipinfo->ship_item_id;
                $this->shipName = $shipinfo->ship_name;
            } catch (Exception $e) {
                $this->error = true;
                $this->message = 'Could not get location Info: '.$e->getMessage().PHP_EOL;
                return false;
            }
            $qry = DB::getConnection();
            $sql="SELECT typeName FROM invTypes WHERE typeID = ".$this->shipTypeID;
            $result = $qry->query($sql);
            if($result->num_rows) {
                $row=$result->fetch_assoc();
                $this->shipTypeName = $row['typeName'];
            }
            $qry = DB::getConnection();
            if ($this->shipTypeID == $previousship) {
                $sql="UPDATE pilots SET characterName='".$qry->real_escape_string($this->characterName)."',locationID={$this->locationID},shipTypeID={$this->shipTypeID},stationID={$this->stationID},
                      structureID={$this->structureID},corporationID={$this->corpID},allianceID={$this->allyID},lastFetch=NOW() WHERE characterID={$this->characterID}";
            } else {
                $sql="UPDATE pilots SET characterName='".$qry->real_escape_string($this->characterName)."',locationID={$this->locationID},shipTypeID={$this->shipTypeID},stationID={$this->stationID},
                      structureID={$this->structureID},fitting=NULL,corporationID={$this->corpID},allianceID={$this->allyID},lastFetch=NOW() WHERE characterID={$this->characterID}";
	    }
            $result = $qry->query($sql);
            if ($qry->affected_rows == 0) {
                $sql="REPLACE INTO pilots (characterID,characterName,locationID,shipTypeID,stationID,structureID,fitting,corporationID,allianceID,lastFetch)
                      VALUES ({$this->characterID},'".$qry->real_escape_string($this->characterName)."','{$this->locationID}',{$this->shipTypeID},'{$this->stationID}','{$this->structureID}',NULL,{$this->corpID},{$this->allyID},NOW())";
                $qry->query($sql);
            }
        }
    }

    public function getFleetID() {
        $esiapi = new ESIAPI();
        $esiapi->setAccessToken($this->accessToken);
        $fleetapi = $esiapi->getApi('Fleets');
        try {
            $fleetinfo = $fleetapi->getCharactersCharacterIdFleet($this->characterID, 'tranquility');
            $fleetID = $fleetinfo->getFleetId();
        } catch (Exception $e) {
            $fleetID = null;
        }
        return $fleetID;
    }

    public function getLocationID() {
            return $this->locationID;
    }

    public function getLocationName() {
            return $this->locationName;
    }

    public function getStationID() {
            return $this->stationID;
    }

    public function getStationName() {
            return $this->stationName;
    }

    public function getShipTypeID() {
            return $this->shipTypeID;
    }

    public function getShipTypeName() {
            return $this->shipTypeName;
    }

    public function getShipName() {
            return $this->shipName;
    }

    public static function getCorpForChar($characterID) {
        $esiapi = new ESIAPI();
        $charapi = $esiapi->getApi('Character');
        try {
            $charinfo = json_decode($charapi->getCharactersCharacterId($characterID, 'tranquility'));
            $corpID = $charinfo->corporation_id;
        } catch (Exception $e) {
            $corpID = 0;
        }
        return $corpID;
    }

    public static function getAllyForChar($characterID) {
        $esiapi = new ESIAPI();
        $charapi = $esiapi->getApi('Character');
        try {
            $charinfo = json_decode($charapi->getCharactersCharacterId($characterID, 'tranquility'));
            @$allyID = $charinfo->alliance_id;
        } catch (Exception $e) {
            $allyID = 0;
        }
        return $allyID;
    }

    public static function getAllyForCorp($corpID) {
        $esiapi = new ESIAPI();
        $corpapi = $esiapi->getApi('Corporation');
        try {
            $corpinfo = json_decode($corpapi->getCorporationsCorporationId($corpID, 'tranquility'));
            if (isset($corpinfo->alliance_id)) {
                $allyID = $corpinfo->alliance_id;
            } else {
                $allyID = 0;
            }
        } catch (Exception $e) {
            $allyID = 0;
        }
        return $allyID;
    }
}
?>
