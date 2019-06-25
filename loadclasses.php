<?php
$files = glob(__DIR__ . '/classes/*.php');

foreach ($files as $file) {
    require_once($file);   
}
?>
