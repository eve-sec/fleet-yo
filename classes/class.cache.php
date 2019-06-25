<?php
require_once('config.php');

class CACHE {

    static $cacheTime = 600;
    
    public static function put($html) {
        if (CACHE_METHOD == 'file') {
            self::put_file($html);
        }
    }

    public static function get($cachetime = null) {
        if (CACHE_METHOD == 'file') {
            return self::get_file($cachetime);
        }
    }

    private static function put_file($html) {
        $cachefile = 'cache/pages/'.(URL::getQ('char_id')?URL::getQ('char_id').'_':'').md5(URL::full_url()).'.html';
        $html = preg_replace('/(?<=<p id="buildTime" class="text-right small"><em>Page )(built in [0-9.]* seconds)(?=\.<\/em><\/p>)/', 'cached', $html);
        file_put_contents($cachefile, $html, LOCK_EX);
        return;
    }

    public static function clear($prefix = null) {
        foreach (glob(realpath(dirname(__FILE__))."/../cache/pages/".($prefix?$prefix."_":"")."*.html") as $filename) {
            unlink($filename);
        }
    }

    private static function get_file($cachetime = null) {
        if (!$cachetime) {
            $cachetime = self::$cacheTime;
        }
        $cachefile = 'cache/pages/'.(URL::getQ('char_id')?URL::getQ('char_id').'_':'').md5(URL::full_url()).'.html';
        if (file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
            return file_get_contents($cachefile);
        } else {
            return false;
        }
    }
}
