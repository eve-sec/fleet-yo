<?php
include_once('config.php');
include_once('classes/class.esipilot.php');

class FITTING
{
    protected $fitting = array();
    protected $shipID = null;
    protected $highs = array();
    protected $meds = array();
    protected $lows = array();
    protected $rigs = array();
    protected $subsys = array();
    protected $drones = array();
    protected $charges = array();
    protected $error = false;
    protected $message = '';

    public function __construct($fit = null) {
      if ($fit != null) {
        $temp = array();
        $tempmods = array();
        $named = false;
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $fit) as $line){
            if (0 === strpos($line, '[') && !$named) {
                $temp[] = array(0 => preg_split('/[\[,]/', $line)[1]);
                $named = true;
            } else {
                if (trim($line) == '') {
                    $temp[] = $tempmods;
                    $tempmods = array();
                } else {
                    $tempmods[] = (preg_split('/[,]/', $line)[0]);
                }
            }
        }
        $i = 0;
        foreach ($temp as $values) {
            if (count($values)) {
                $blank = true;
                $qry = DB::getConnection();
                $escapednames = array();
                foreach ($values as $value) {
                    $escapednames[] = $qry->real_escape_string(preg_replace('/\sx[0-9]*$/', '', $value));
                }
                $typenames = implode("' OR typeName='",$escapednames);
                $sql="SELECT typeID, typeName FROM invTypes WHERE typeName='".$typenames."'";
                $result = $qry->query($sql);
                while ($row = $result->fetch_row()) {
                    foreach ($values as $name) {
                        $name = preg_replace('/\sx[0-9]*$/', '', $name);
                        if ($row[1] == $name) {
                            switch ($i) {
                               case 0:
                                   $this->shipID = $row[0];
                                   break;
                               case 1:
                                   $this->lows[] = $row[0];
                                   break;
                               case 2:
                                   $this->meds[] = $row[0];
                                   break;
                               case 3:
                                   $this->highs[] = $row[0];
                                   break;
                               case 4:
                                   $this->rigs[] = $row[0];
                                   break;
                               case 5:
                                   $this->subsys[] = $row[0];
                                   break;
                               case 6:
                                   $this->drones[] = $row[0];
                                   break;
                               case 7:
                                   $this->charges[] = $row[0];
                                   break;
                            }
                        }
                    }
                }
                $blank = false;
                $i += 1;
            } else {
                if (!$blank) {
                    $i += 1;
                }
                $blank = true;
            }   
        }
        if ($this->shipID == null) {
            $this->error = true;
            $this->message = "Fitting could not be parsed";
        }
        $this->fitting['ship'] = $this->shipID;
        $this->fitting['lows'] = $this->lows;
        $this->fitting['meds'] = $this->meds;
        $this->fitting['highs'] = $this->highs;
        $this->fitting['rigs'] = $this->rigs;
        $this->fitting['subsys'] = $this->subsys;
        $this->fitting['drones'] = $this->drones;
        $this->fitting['charges'] = $this->charges;
      }
    }

    public function addToChar($characterID) {
        if ($this->error) {
            return false;
        }
        $fit = json_encode($this->fitting, JSON_NUMERIC_CHECK);
        $qry = DB::getConnection();
        $sql="SELECT shipTypeID FROM pilots WHERE characterID = ".$characterID;
        $result = $qry->query($sql);
        if($result->num_rows) {
            $row=$result->fetch_assoc();
            $shiptypeid = $row['shipTypeID'];
            if ($shiptypeid == $this->shipID) {
                $sql="UPDATE pilots SET fitting='{$fit}' WHERE characterID = ".$characterID;
                $qry->query($sql);
                $this->message = "Fitting updated.";
                return true;
            } else {
                $pilot = new ESIPILOT($characterID, true);
                if ($pilot->getShipTypeID() == $this->shipID) {
                    $sql="UPDATE pilots SET fitting='{$fit}' WHERE characterID = ".$characterID;
                    $qry->query($sql);
                    $this->message = "Fitting updated.";
                    return true;
                } else {
                    $this->error = true;
                    $this->message = "Fitting does not match your current ship.";
                    return false;
                }
            }
        } else {
            $this->error = true;
            $this->message = "Pilot not found in the database";
            return false;
        }
    }
    
    public static function getCharFit($characterID, $dbonly=false) {
        $qry = DB::getConnection();
        $sql="SELECT fitting FROM pilots WHERE characterID = ".$characterID;
        $result = $qry->query($sql);
        if($result->num_rows) {
            if (!$dbonly) {
                $esipilot = new ESIPILOT($_SESSION['characterID']);
            }
            $row = $result->fetch_row();
            $fitting = json_decode($row[0], True);
            return $fitting;
        } else {
            return null;
        } 
    }

    public static function getModGroups($fitting=null, $getNames = false) {
        $gids = array();
        $f['ab'] = 0;
        $f['mwd'] = 0;
        $f['scram'] = 0;
        $f['disrupt'] = 0;
        $f['dis_field'] = 0;
        $f['web'] = 0;
        $f['grap'] = 0;
        $f['rsebo'] = 0;
        $f['td'] = 0;
        $f['damp'] = 0;
        $f['paint'] = 0;
        $f['sbomb'] = 0;
        $f['y_jam'] = 0;
        $f['r_jam'] = 0;
        $f['b_jam'] = 0;
        $f['g_jam'] = 0;
        $f['m_jam'] = 0;
        if ($fitting) {
            $qry = DB::getConnection();
            $mods = self::flatten($fitting);
            $sql="SELECT typeID, marketGroupID FROM invTypes WHERE typeID=".implode(" OR typeID=", $mods);
            $result = $qry->query($sql);
            if($result->num_rows) {
                while($row = $result->fetch_row()) {
                    $gid[$row[0]] = $row[1];
                }
                foreach ($mods as $mod) {
                    if (isset($gid[$mod])) {
                        switch($gid[$mod]) {
                            case 542:
                                $f['ab'] += 1;
                                break;
                            case 131:
                                $f['mwd'] += 1;
                                break;
                            case 1936:
                                $f['scram'] += 1;
                                break;
                            case 1935:
                                $f['disrupt'] += 1;
                                break;
                            case 1085:
                                $f['dis_field'] += 1;
                                break;
                            case 683:
                                $f['web'] += 1;
                                break;
                            case 2154:
                                $f['grap'] += 1;
                                break;
                            case 673:
                                $f['rsebo'] += 1;
                                break;
                            case 680:
                                $f['td'] += 1;
                                break;
                            case 679:
                                $f['damp'] += 1;
                                break;
                            case 757:
                                $f['paint'] += 1;
                                break;
                            case 380:
                            case 381:
                            case 382:
                            case 383:
                                $f['sbomb'] += 1;
                                break;
                            case 718:
                                $f['y_jam'] += 1;
                                break;
                            case 716:
                                $f['r_jam'] += 1;
                                break;
                            case 717:
                                $f['b_yam'] += 1;
                                break;
                            case 715:
                                $f['g_jam'] += 1;
                                break;
                            case 719:
                                $f['m_jam'] += 1;
                                break;
                        }
                    }
                }
            }
        } elseif ($getNames) {
            $qry = DB::getConnection();
            $sql="SELECT marketGroupID, marketGroupName FROM invMarketGroups";
            $result = $qry->query($sql);
            if($result->num_rows) {
                while($row = $result->fetch_row()) {
                    switch($row[0]) {
                        case 542:
                            $f['ab'] = $row[1];
                            break;
                        case 131:
                            $f['mwd'] = $row[1];
                            break;
                        case 1936:
                            $f['scram'] = $row[1];
                            break;
                        case 1935:
                            $f['disrupt'] = $row[1];
                            break;
                        case 1085:
                            $f['dis_field'] = $row[1];
                            break;
                        case 683:
                            $f['web'] = $row[1];
                            break;
                        case 2154:
                            $f['grap'] = $row[1];
                            break;
                        case 673:
                            $f['rsebo'] = $row[1];
                            break;
                        case 680:
                            $f['td'] = $row[1];
                            break;
                        case 679:
                            $f['damp'] = $row[1];
                            break;
                        case 757:
                            $f['paint'] = $row[1];
                            break;
                        case 380:
                            $f['sbomb'] = 'Smartbombs';
                            break;
                        case 718:
                            $f['y_jam'] = $row[1];
                            break;
                        case 716:
                            $f['r_jam'] = $row[1];
                            break;
                        case 717:
                            $f['b_jam'] = $row[1];
                            break;
                        case 715:
                            $f['g_jam'] = $row[1];
                            break;
                        case 719:
                            $f['m_jam'] = $row[1];
                            break;
                    }
                }
            }
        }
        return $f;
    }

    public static function getNames($fitting) {
        $qry = DB::getConnection();
        $sql="SELECT typeID, typeName FROM invTypes WHERE typeID=".implode(" OR typeID=", self::flatten($fitting));
        $result = $qry->query($sql);
        $return = array();
        if($result->num_rows) {
            while ($row = $result->fetch_assoc()) {
                $return[$row['typeID']] = $row['typeName'];
            }
            return $return;
        } else {
            return null;
        }
    }

    private static function flatten(array $array) {
        $return = array();
        array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
        return $return;
    }

    public function getShipTypeID() {
        return $this->shipID;
    }

    public function getError() {
        return $this->error;
    }

    public function getMessage() {
        return $this->message;
    }

}
?>
