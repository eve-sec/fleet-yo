<?php

require_once('config.php');

class AUTHTOKEN {

    private $id = null;
    private $selector = null;
    private $cookietoken = null;
    private $token = null;
    private $characterID = null;
    private $expires = null;

    public function __construct($selector = null, $characterID = null)
    {
        if ($selector == null && $characterID != null) {
            $this->characterID = $characterID;
            $this->selector = md5(uniqid($characterID, true));
            $this->cookietoken = self::random_str(32);
        } elseif ($selector != null) {
            $this->selector = $selector;
            $sql="SELECT * FROM authTokens WHERE selector='".$this->selector."'";
            $qry = DB::getConnection();
            $result = $qry->query($sql);
            if($result->num_rows) {
                $row = $result->fetch_assoc();
                $this->characterID = $row['characterID'];
                $this->id = $row['id'];
                $this->token = $row['token'];
                $this->expires = strtotime($row['expires']);
            }    
        }
    }

    public function addToDB() {
        $token = hash('sha256', $this->characterID . $this->cookietoken . ESI_SALT);
        $this->expires = strtotime("now")+3600*24*7;
        $expires = date('Y-m-d H:i:s', $this->expires);
        $sql="REPLACE INTO authTokens (characterID,selector,token,expires) VALUES ({$this->characterID},'{$this->selector}','{$token}','{$expires}')";
        $qry = DB::getConnection();
        $result = $qry->query($sql);
    }

    public function verify() {
        $sql="SELECT * FROM authTokens WHERE selector='".$this->selector."'";
        $qry = DB::getConnection();
        $result = $qry->query($sql);
        if($result->num_rows) {
            $row = $result->fetch_assoc();
            $this->characterID = $row['characterID'];
            $this->id = $row['id'];
            $this->token = $row['token'];
            $this->expires = strtotime($row['expires']);
            if ($this->hasExpired()) {
                return false;
            } else {
                return $this->isValid();
            }
            return true;
        } else {
            return false;
        }
    }

    public static function getFromCookie() {
        if (isset($_COOKIE[COOKIE_PREFIX])) {
            try {
                $cookie = json_decode($_COOKIE[COOKIE_PREFIX]);
                $authtoken = new AUTHTOKEN($cookie->selector);
                $authtoken->setCookietoken($cookie->token);
            } catch (Exception $e) {
                return false;
            }
            return $authtoken;
        } else {
            return false;
        }
    }

    private static function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

    public function hasExpired() {
        if ($this->expires < strtotime("now")) {
            return true;
        } else {
            return false;
        }
    }

    public function isValid() {
        if ($this->token == hash('sha256', $this->characterID . $this->cookietoken . ESI_SALT)) {
            return true;
        } else {
            return false;
        }
    }

    public function storeCookie() {
        $data = json_encode(array('selector' => $this->selector, 'token' => $this->cookietoken));
        $path = URL::path_only();
        $server = URL::server();
        setcookie(COOKIE_PREFIX, $data, $this->expires, $path, $server, 1);
    }

    public function setCharacterID($characterID) {
        $this->characterID = $characterID;
    }

    public function getCharacterID() {
        return $this->characterID;
    }

    public function getSelector() {
        return $this->characterID;
    }

    public function getCookietoken() {
        return $this->cookietoken;
    }

    public function setCookietoken($cookietoken) {
        $this->cookietoken = $cookietoken;
    }

}
?>
