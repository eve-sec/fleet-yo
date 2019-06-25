<?php
include_once('config.php');

class DB extends mysqli
{
    protected static $instance;
    protected static $options = array();
    protected $mem = null;

    public function __construct() {
        $o = self::$options;

        // turn of error reporting
        mysqli_report(MYSQLI_REPORT_OFF);

        // connect to database
        @parent::__construct(isset($o['host'])   ? $o['host']   : DB_HOST,
                             isset($o['user'])   ? $o['user']   : DB_USER,
                             isset($o['pass'])   ? $o['pass']   : DB_PASS,
                             isset($o['dbname']) ? $o['dbname'] : DB_NAME,
                             isset($o['port'])   ? $o['port']   : 3306,
                             isset($o['sock'])   ? $o['sock']   : false );

        // check if a connection established
        if( mysqli_connect_errno() ) {
            throw new exception(mysqli_connect_error(), mysqli_connect_errno()); 
        }
        $this->autocommit(TRUE);
    }

    public function __destruct() {
         $this->close();
    }

    public static function getConnection() {
        if( !self::$instance ) {
            self::$instance = new self(); 
        }
        return self::$instance;
    }

    public static function setOptions( array $opt ) {
        self::$options = array_merge(self::$options, $opt);
    }

    public function query($query, $resultmode = NULL) {
        if( !$this->real_query($query) ) {
            throw new exception( $this->error, $this->errno );
        }

        $result = new mysqli_result($this);
        return $result;
    }

    public function prepare($query) {
        $stmt = new mysqli_stmt($this, $query);
        return $stmt;
    }    
}



?>
