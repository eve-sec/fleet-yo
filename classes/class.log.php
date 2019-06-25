<?php

class ESILOG {

    private $log_file, $fh;
    private $maxsize = 1024;
    private $log_file_default = 'log/logfile.txt';

    public function __construct($path = null) {
        if ($path != null) {
            $this->log_file = $path;
        }
    }

    public function setLogFile($path) {
        $this->log_file = $path;
    }

    public function put($message, $type = '') {
        if (!is_resource($this->fh)) {
            $this->open();
        }
        $script_name = pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME);
        $time = @date('Y/m/d H:i:s');
        $message = str_replace(array("\r", "\n"), '', $message);
        fwrite($this->fh, "$time ".($type == ''?'':$type.': ')."[$script_name] $message" . PHP_EOL);
    }

    public function exception($e) {
        $this->put($e->getMessage(), get_class($e));
    }

    public function error($message) {
        $this->put($message, 'Error');
        $this->close();
    }

    public function warning($message) {
        $this->put($message, 'Warning');
        $this->close();
    }

    public function debug($message) {
        $this->put($message, 'Debug');
        $this->close();
    }

    public function close() {
        if (is_resource($this->fh)) {
            fclose($this->fh);
        }
    }

    private function open() {
        $logfile = $this->log_file ? $this->log_file : $this->log_file_default;
        $this->rotate();
        $this->fh = fopen($logfile, 'a') or exit("Can't open $logfile!");
    }

    private function rotate() {
        $logfile = $this->log_file ? $this->log_file : $this->log_file_default;
        $threshold_bytes = $this->maxsize* 1024;
        if( file_exists($logfile) && filesize($logfile) >= $threshold_bytes ) {
            $path_info = pathinfo($logfile);
            $base_directory = $path_info['dirname'];
            $base_name = $path_info['basename'];
            $num_map = array();
            foreach( new DirectoryIterator($base_directory) as $fInfo) {
                if($fInfo->isDot() || ! $fInfo->isFile()) continue;
                if (preg_match('/^'.$base_name.'\.?([0-9]*)$/',$fInfo->getFilename(), $matches) ) {
                    $num = $matches[1];
                    $file2move = $fInfo->getFilename();
                    if ($num == '') $num = -1;
                    $num_map[$num] = $file2move;
                }
            }
            krsort($num_map);
            foreach($num_map as $num => $file2move) {
                $targetN = $num+1;
                rename($base_directory.DIRECTORY_SEPARATOR.$file2move,$logfile.'.'.$targetN);
            }
        }
    }
}
?>
