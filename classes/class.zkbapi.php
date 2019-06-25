<?php
require_once('config.php');

use Swagger\Client\Configuration;
use Swagger\Client\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Doctrine\Common\Cache\FilesystemCache;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

require_once('vendor/autoload.php');
require_once('classes/esi/autoload.php');

class ZKBAPI
{
    protected $esiConfig;
    protected $error = false;
    protected $message = null;
    protected $esilog;
    protected $zkblog;
    private $timeout = 3;
    private $connect_timeout = 2;
    private $retries = 2;
    private $retryDelay = 2;
    private $client = null;
    private $baseUrl = 'https://zkillboard.com/api/';
    private $redisqUrl = 'https://redisq.zkillboard.com/listen.php';
    private $redisqNullcount = 3;
    private $redisqTime = 45;
    private $redisqTtw = 5;

    public function __construct() 
    {
        $this->esilog = new ESILOG('log/esi.log');
        $this->zkblog = new ESILOG('log/zkb.log');
        $this->initClient();
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setConnectTimeout($timeout)
    {
        $this->connect_timeout = $timeout;
    }

    public function setRetries($retries)
    {
        $this->retries = $retries;
    }

    public function setRetryDelay($delay)
    {
        $this->retryDelay = $delay;
    }

    public function getRetryDelayMs()
    {
        return function( $retries ) {
            return 1000 * $this->retryDelay;
        };
    }

    private function initClient() {
        $stack = HandlerStack::create();
        $stack->push( Middleware::retry( $this->retryDecider(), $this->getRetryDelayMs() ) );
        $stack->push(
        new CacheMiddleware(
            new PrivateCacheStrategy(
                new DoctrineCacheStorage(
                    new FilesystemCache('cache/zkb/')
                )
            )
        ), 
        'private-cache'
        );
        $this->client = new Client(['handler' => $stack, 
                           'defaults' => ['connect_timeout' => $this->connect_timeout, 
                                          'timeout' => $this->timeout, 
                                          'headers' => ['User-Agent' => 'fleet-yo_Guzzle_client',
                                                        'Accept-Encoding' => 'gzip'],
                                      ]
                          ]);
    }

    public function get($path) {
        $url = $this->baseUrl.$path;
        $res = $this->client->request('GET', $url);
        if ($res->getStatusCode() >= 200 && $res->getStatusCode() <= 299) {
            return json_decode($res->getBody(), true);
        } else {
            return null;
        }
    }

    public function getKillsByType($type, $id, $start, $end = null) {
        $path = $type.'ID/'.$id.'/';
        $startdate = strtotime(str_replace(' ', 'T', $start).'+00:00');
        $path .= 'startTime/'.gmdate('YmdH', $startdate).'00/';
        if ($end) {
            $enddate = strtotime(str_replace(' ', 'T', $end).'+00:00');
            $path .= 'endTime/'.gmdate('YmdH', $enddate + 3599).'00/';
        }
        (DEBUG?$this->zkblog->debug('ZKB Api fetch: '.$path):'');
        $killmails = $this->get($path);
        if (count($killmails) >= 200) {
            $i = 2;
            do {
                $newpath = $path . 'page/'.$i.'/';
                $toadd = $this->get($newpath);
                if ($toadd) {
                    $killmails = array_merge($killmails, $toadd);
                }
                $i += 1;
            } while ($toadd && count($toadd) >= 200);
        }
        (DEBUG?$this->zkblog->debug(count((array)$killmails).' kills fetched.'):'');
        $promise = array();
        $esiapi = new ESIAPI();
        $killapi = $esiapi->getApi('Killmails');
        foreach ($killmails as $i => $km) {
            $promise[] = $killapi->getKillmailsKillmailIdKillmailHashAsync($km['zkb']['hash'], $km['killmail_id']);
        }
        $responses = GuzzleHttp\Promise\settle($promise)->wait();
        $result = array();
        foreach ($responses as $i => $response) {
            if ($response['state'] == 'fulfilled') {
                $kill = json_decode($response['value'], true);
                if (strtotime($kill['killmail_time']) >= $startdate && (!$end || strtotime($kill['killmail_time']) <= $enddate)) {
                    $killmails[$i]['killmail'] = $kill;
                    $result[] = $killmails[$i];
                }
            } elseif ($response['state'] == 'rejected') {
                $this->zkblog->error($response['reason']);
            }
        }
        (DEBUG?$this->zkblog->debug(count((array)$result).' left after filtering fleet time.'):'');
        return $result;
    }

    public function getCharacterKills($id, $start, $end) {
        return $this->getKillsByType('character', $id, $start, $end);
    }

    public function getCorporationKills($id, $start, $end) {
        return $this->getKillsByType('corporation', $id, $start, $end);
    }

    public function getAllianceKills($id, $start, $end) {
        return $this->getKillsByType('alliance', $id, $start, $end);
    }

    public function getKillmail($killId, $hash) {
        $esiapi = new ESIAPI();
        $killapi = $esiapi->getApi('Killmails');
        try {
            $result = json_decode($killapi->getKillmailsKillmailIdKillmailHash($hash, $killId), true);
        } catch (Exception $e) {
            $esilog->exception($e);
            return null;
        }
        return $result;
    }

    private function retryDecider() {
       return function (
          $retries,
          Request $request,
          Response $response = null,
          RequestException $exception = null
       ) {
          // Limit the number of retries
          if ( $retries >= $this->retries ) {
             return false;
          }
     
          // Retry connection exceptions
          if( $exception instanceof ConnectException ) {
             return true;
          }
     
          if( $response ) {
             // Retry on server errors
             if( $response->getStatusCode() >= 500 ) {
                return true;
             }
          }
     
          return false;
       };
    }

    public function listenRedisq($queueID = null) {
        $start = microtime(true);
        $nullcount = 0;
        $kills = array();
        $url = $this->redisqUrl.'?ttw='.$this->redisqTtw;
        if ($queueID) {
            $url .= '&queueID='.$queueID;
        }
        $stack = HandlerStack::create();
        $stack->push( Middleware::retry( $this->retryDecider(), $this->getRetryDelayMs() ) );
        $client = new Client(['handler' => $stack,
                           'defaults' => ['connect_timeout' => $this->connect_timeout,
                                          'timeout' => $this->timeout,
                                          'headers' => ['User-Agent' => 'fleet-yo_Guzzle_client',
                                                        'Accept-Encoding' => 'gzip'],
                                      ]
                          ]);
        while( ( (microtime(true)-$start) < $this->redisqTime ) && ( $nullcount < $this->redisqNullcount) ) {
            try {
                $res = $this->client->request('GET', $url);
            } catch (Exception $e) {
                $this->zkblog->exception($e);
                break;
            }
            if ($res->getStatusCode() >= 200 && $res->getStatusCode() <= 299) {
                $temp = json_decode($res->getBody(), true);
                if (isset($temp['package']) && $temp['package']) {
                    $kills[] = $temp['package'];
                } else {
                    $nullcount += 1;
                }
            } else {
                $nullcount += 1;
            }
        }
        return $kills;
    }
    
}
?>
