<?php
use Swagger\Client\ApiClient;
use Swagger\Client\Configuration;
use Swagger\Client\ApiException;

require_once('classes/esi/autoload.php');

class ESIAPI extends ApiClient 
{
    public function __construct(\Swagger\Client\Configuration $esiConfig = null) 
    {    
        if($esiConfig == null)
        {
            $esiConfig = Configuration::getDefaultConfiguration();
            $esiConfig->setCurlTimeout(3);
            $esiConfig->setUserAgent(ESI_USER_AGENT);
            // disable the expect header, because the ESI server reacts with HTTP 502
            $esiConfig->addDefaultHeader('Expect', '');
        }
        
        parent::__construct($esiConfig);     
    }
    
    public function setAccessToken($accessToken)
    {
        $this->config->setAccessToken($accessToken);
    }
}
?>
