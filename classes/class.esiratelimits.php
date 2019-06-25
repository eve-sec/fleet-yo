<?php
require_once('config.php');

use Doctrine\Common\Cache\FilesystemCache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Concat\Http\Middleware\RateLimitProvider;

class ESIRateLimits implements RateLimitProvider
{
    private $cache;
    private $max_errors = 100;

    public function __construct()
    {
        $this->cache = new FilesystemCache('cache/rate/');
    }

    public function getLastRequestTime(RequestInterface $request)
    {
        // This is just an example, it's up to you to store the time of the
        // most recent request, whether it's in a database or cache driver.
        return $this->cache->fetch('last_request_time');
    }

    /**
     * Used to set the current time as the last request time to be queried when
     * the next request is attempted.
     */
    public function setLastRequestTime(RequestInterface $request)
    {
        // This is just an example, it's up to you to store the time of the
        // most recent request, whether it's in a database or cache driver.
        return $this->cache->save('last_request_time', microtime(true));
    }

    /**
     * Returns what is considered the time when a given request is being made.
     *
     * @param RequestInterface $request The request being made.
     *
     * @return float Time when the given request is being made.
     */
    public function getRequestTime(RequestInterface $request)
    {
        return microtime(true);
    }

    /**
     * Returns the minimum amount of time that is required to have passed since
     * the last request was made. This value is used to determine if the current
     * request should be delayed, based on when the last request was made.
     *
     * Returns the allowed time between the last request and the next, which
     * is used to determine if a request should be delayed and by how much.
     *
     * @param RequestInterface $request The pending request.
     *
     * @return float The minimum amount of time that is required to have passed
     *               since the last request was made (in microseconds).
     */
    public function getRequestAllowance(RequestInterface $request)
    {
        // This is just an example, it's up to you to store the request 
        // allowance, whether it's in a database or cache driver.
        return $this->cache->fetch('request_allowance');
    }

    /**
     * Used to set the minimum amount of time that is required to pass between
     * this request and the next request.
     *
     * @param ResponseInterface $response The resolved response.
     */
    public function setRequestAllowance(ResponseInterface $response)
    {
        // Let's also assume that the response contains two headers:
        //     - ratelimit-remaining
        //     - ratelimit-window
        //
        // The first header tells us how many requests we have left in the 
        // current window, the second tells us how many seconds are left in the
        // window before it expires.
        $requests = $response->getHeader('X-Esi-Error-Limit-Remain')[0];
        $seconds  = $response->getHeader('X-Esi-Error-Limit-Reset')[0];

        // The allowance is therefore how much time is remaining in our window
        // divided by the number of requests we can still make. This is the 
        // value we need to store to determine if a future request should be 
        // delayed or not.
        if ($requests == 0) {
            $allowance = (float) $seconds;
        } elseif ($requests >= $this->max_errors*0.75) {
            $allowance = (float) 0;
        } else {
            $allowance = (float) $seconds / (int) $requests;
        }
    
        // This is just an example, it's up to you to store the request 
        // allowance, whether it's in a database or cache driver.
        $this->cache->save('request_allowance', $allowance);
    }

}
