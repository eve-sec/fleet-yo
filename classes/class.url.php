<?php

class URL {

    public static function url_origin( $s, $use_forwarded_host = false )
    {
        $ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
        $sp       = strtolower( $s['SERVER_PROTOCOL'] );
        $protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
        $port     = $s['SERVER_PORT'];
        $port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
        $host     = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
        $host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
        return $protocol . '://' . $host;
    }
    
    public static function full_url( $use_forwarded_host = false )
    {
        $s = $_SERVER;
        return self::url_origin( $s, $use_forwarded_host ) . $s['REQUEST_URI'];
    }
    
    public static function full_url_noq( $use_forwarded_host = false )
    {
        $s = $_SERVER;
        return self::url_origin( $s, $use_forwarded_host ) . strtok($s['REQUEST_URI'],'?');
    }

    public static function url_path( $use_forwarded_host = false )
    {
        $s = $_SERVER;
        return self::url_origin( $s, $use_forwarded_host ) . substr($s['REQUEST_URI'], 0, strrpos($s['REQUEST_URI'], '/') + 1);
    }

    public static function relative_url( $use_forwarded_host = false )
    {
        $s = $_SERVER;
        return substr($s['REQUEST_URI'], strrpos($s['REQUEST_URI'], '/') + 1);
    }

    public static function path_only()
    {
        $s = $_SERVER;
        return substr($s['REQUEST_URI'], 0, strrpos($s['REQUEST_URI'], '/') + 1);
    }

    public static function server()
    {
        $s = $_SERVER;
        return $s['SERVER_NAME'];
    }

    public static function getQ($str)
    {
        $s = $_SERVER;
        $querystring = parse_url(self::full_url(), PHP_URL_QUERY);
        parse_str($querystring, $var);
        if (isset($var[$str])) {
            return $var[$str];
        } else {
            return null;
        }
    }

    public static function getQueryString()
    {
        $s = $_SERVER;
        $querystring = parse_url(self::full_url(), PHP_URL_QUERY);
        return $querystring;
    }
}    
?>
