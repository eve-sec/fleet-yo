<?php

// autoload_psr4.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'Psr\\Log\\' => array($vendorDir . '/psr/log/Psr/Log'),
    'Psr\\Http\\Message\\' => array($vendorDir . '/psr/http-message/src'),
    'Kevinrob\\GuzzleCache\\' => array($vendorDir . '/kevinrob/guzzle-cache-middleware/src'),
    'Jose\\Component\\Signature\\Algorithm\\' => array($vendorDir . '/web-token/jwt-signature-algorithm-ecdsa', $vendorDir . '/web-token/jwt-signature-algorithm-eddsa', $vendorDir . '/web-token/jwt-signature-algorithm-hmac', $vendorDir . '/web-token/jwt-signature-algorithm-none', $vendorDir . '/web-token/jwt-signature-algorithm-rsa'),
    'Jose\\Component\\Signature\\' => array($vendorDir . '/web-token/jwt-signature'),
    'Jose\\Component\\Core\\' => array($vendorDir . '/web-token/jwt-core'),
    'Jose\\Component\\Checker\\' => array($vendorDir . '/web-token/jwt-checker'),
    'GuzzleHttp\\Psr7\\' => array($vendorDir . '/guzzlehttp/psr7/src'),
    'GuzzleHttp\\Promise\\' => array($vendorDir . '/guzzlehttp/promises/src'),
    'GuzzleHttp\\' => array($vendorDir . '/guzzlehttp/guzzle/src'),
    'FG\\' => array($vendorDir . '/fgrosse/phpasn1/lib'),
    'Doctrine\\Common\\Cache\\' => array($vendorDir . '/doctrine/cache/lib/Doctrine/Common/Cache'),
    'Concat\\Http\\Middleware\\' => array($vendorDir . '/rtheunissen/guzzle-rate-limiter/src'),
    'Base64Url\\' => array($vendorDir . '/spomky-labs/base64url/src'),
);