<?php
require_once('loadclasses.php');

use Swagger\Client\ApiException;
use Swagger\Client\Api\AllianceApi;
use Swagger\Client\Api\CorporationApi;
use Swagger\Client\Api\CharacterApi;
use Swagger\Client\Api\SearchApi;

$cachetime = 600;

if (isset($_GET['q'])) {
    if (strlen($_GET['q']) > 3) {
        $cachefile = 'cache/tt-'.$_GET['q'].'.json';
        if (file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
            $response = file_get_contents($cachefile);
            header('Content-type: application/json');
            echo $response;
            die();
        }
        $esiapi = new ESIAPI();
        $searchapi = $esiapi->getApi('Search');
        try {
            $tempids = json_decode($searchapi->getSearch(array('character'), $_GET['q'], 'en-us', 'tranquility', null, 'en-us', 0), true);
            if (count($tempids)) {
                $result_ary = array();
                foreach($tempids as $cat => $_ids) {
                    try {
                        $universeapi = $esiapi->getApi('Universe');
                        foreach (array_chunk(array_unique($_ids), 250) as $ids) {
                            $promise[] = $universeapi->postUniverseNamesAsync(json_encode($ids), 'tranquility');
                        }
                    } catch (Exception $e) {
                        $log = new ESILOG('log/esi.log');
                        $log->exception($e);
                        echo('{}');
                        die();
                    }
                }
                $responses = GuzzleHttp\Promise\settle($promise)->wait();
                foreach ($responses as $response) {
                    if ($response['state'] == 'fulfilled') {
                        foreach ($response['value'] as $r) {
                            $result_ary[] = array('category' => $r->getCategory(), 'id' => $r->getId(), 'name' => $r->getName());
                        }
                    } elseif ($response['state'] == 'rejected') {
                        if(!isset($log)) {
                            $log = new ESILOG('log/esi.log');
                        }
                        $log->exception($response['reason']);
                    }
                }
                if (!count($result_ary)) {
                    echo('{}');
                    die();
                }
                for($i=0; $i<count($result_ary); $i++) {
                    $temp_arr[levenshtein($_GET['q'], $result_ary[$i]['name'])] = $result_ary[$i];
                }
                ksort($temp_arr);
                $response = json_encode(array_values($temp_arr));
                header('Content-type: application/json');
                echo $response;
                if ($response != '{}') {
                    file_put_contents($cachefile, $response, LOCK_EX);
                }
            } else {
                echo('{}');
                die();
            }
        } catch (Exception $e) {
            echo('{}');
            die();
        }
    } else {
        echo('{}');
        die();
    }
} else {
    echo('{}');
    die();
}
?>
