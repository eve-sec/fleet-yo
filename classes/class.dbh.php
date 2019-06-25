<?php
include_once('config.php');
include_once('classes/class.db.php');

class DBH extends DB
{

    public static function configGet($key) {
        $qry = new parent;
        $sql="SELECT `value` FROM `config` WHERE `cfg_key`='".$qry->real_escape_string($key)."'";
        $result = $qry->query($sql);
        if ($result->num_rows) {
            return $result->fetch_row()[0];
        } else {
            return null;
        }
    }
    
    public static function configSet($key, $value = null) {
        $qry = new parent;
        $sql="REPLACE INTO `config`(`cfg_key`,`value`) VALUES('{$qry->real_escape_string($key)}','{$qry->real_escape_string($value)}')";
        $result = $qry->query($sql);
    }

    public static function fleetExists($fcid, $start, $end) {
        $response = false;
        $qry = new parent;
        $sql="SELECT created, ended, fleetID, stats, fc FROM fleets WHERE created<='".$end."' and created>='".$start."' and fc = ".$fcid."
              UNION SELECT created, ended, fleetID, stats, fc FROM statsFleets WHERE created<='".$end."' and created>='".$start."' and fc = ".$fcid;
        $result = $qry->query($sql);
        while ($row = $result->fetch_assoc()) {
            $response = true;
        }
        return $response;
    }

    public static function getStatsFleet($fleetID) {
        $response = null;
        $qry = new parent;
        $sql="SELECT created, ended, fleetID, stats, fc, title FROM fleets WHERE fleetID=".$fleetID."
              UNION SELECT created, ended, fleetID, stats, fc, title FROM statsFleets WHERE fleetID=".$fleetID;
        $result = $qry->query($sql);
        while ($row = $result->fetch_assoc()) {
            $response = $row;
        }
        return $response;
    }

    public static function getParticipants($fleetID) {
        $response = array();
        $qry = new parent;
        $sql="SELECT characterID, corporationID, allianceID, ships, kills, losses, iskDestroyed, iskLost, finalBlows FROM participation WHERE fleetID=".$fleetID;
        $result = $qry->query($sql);
        while ($row = $result->fetch_assoc()) {
            $response[$row['characterID']] = $row;
            $response[$row['characterID']]['ships'] = json_decode($row['ships'], true);
            unset($response[$row['characterID']]['characterID']);
        }
        return $response;
    }

    public static function getFleetKills($fleetID) {
        $response = array();
        $qry = new parent;
        $sql="SELECT * FROM kills WHERE fleetID=".$fleetID;
        $result = $qry->query($sql);
        while ($row = $result->fetch_assoc()) {
            $response[] = $row;
        }
        return $response;
    }

    public static function getInvNames($ids) {
        $response = array();
        if (!count((array)$ids)) {
            return $response;
        }
        $qry = new parent;
        $sql="SELECT typeID, typeName FROM invTypes WHERE (typeID=".implode(" or typeID=" ,array_unique($ids)).")";
        $result = $qry->query($sql);
        while ($row = $result->fetch_assoc()) {
            $response[$row['typeID']] = $row['typeName'];
        }
        foreach($ids as $id) {
            if (!isset($response[$id])) {
                $response[$id] = 'Unknown';
            }
        }
        return $response;
    }

    public static function getInvGroups($ids) {
        $response = array();
        if (!count((array)$ids)) {
            return $response;
        }
        $qry = new parent;
        $sql="SELECT typeID, groupName FROM invTypes AS i LEFT JOIN invGroups as ig ON i.groupID = ig.groupID WHERE (typeID=".implode(" or typeID=" ,array_unique($ids)).")";
        $result = $qry->query($sql);
        while ($row = $result->fetch_assoc()) {
            $response[$row['typeID']] = $row['groupName'];
        }
        foreach($ids as $id) {
            if (!isset($response[$id])) {
                $response[$id] = 'Unknown';
            }
        }
        return $response;
    }


    public static function addShipClasses($ships) {
        if (!count((array)$ships)) {
            return $ships;
        }
        $qry = new parent;
        $sql = "SELECT typeID AS shipID, groupName AS shipClass FROM invTypes AS it LEFT JOIN invGroups AS ig ON it.groupID = ig.groupID WHERE typeID=".implode(" OR typeID=", array_column($ships, 'shipID'));
        $result = $qry->query($sql);
        $dict = array();
        while ($row = $result->fetch_assoc()) {
            $dict[$row['shipID']] = $row['shipClass'];
        }
        foreach ($ships as $i => $ship) {
            $ships[$i]['shipClass'] = $dict[$ship['shipID']]; 
        }
        return $ships;
    }

}
