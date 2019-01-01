<?php
/*
 * @package    SussexDev_Cloudflare
 * @copyright  Copyright (c) 2019 Scott Parsons
 * @license    https://github.com/ScottParsons/module-cloudflare/blob/master/LICENSE.md
 * @version    1.0.3
 */
namespace SussexDev\Cloudflare\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

use Psr\Log\LoggerInterface;

/**
 * Class PurgeCloudflareCache
 *
 * @package SussexDev\Cloudflare\Observer
 */
class PurgeCloudflareCache implements ObserverInterface
{
    /**
     * System configuration paths
     */
    const XML_PATH_CFCACHE_ENABLE = 'dev/purge_cloudflare_cache/enable';
    const XML_PATH_CFCACHE_EMAIL  = 'dev/purge_cloudflare_cache/email_address';
    const XML_PATH_CFCACHE_API    = 'dev/purge_cloudflare_cache/api_key';
    const XML_PATH_CFCACHE_ZONE   = 'dev/purge_cloudflare_cache/site_zone_code';

    /**
     * Cloudflare API URL
     */
    const CFCACHE_API_URL = 'https://api.cloudflare.com/client/v4/zones/%s/purge_cache/';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var ManagerInterface
     */
    public $messageManager;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * PurgeCloudflareCache constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->scopeConfig    = $scopeConfig;
        $this->encryptor      = $encryptor;
        $this->messageManager = $messageManager;
        $this->logger         = $logger;
    }

    /**
     * Purge the Cloudflare cache
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $enabled = $this->scopeConfig->isSetFlag(self::XML_PATH_CFCACHE_ENABLE);
        if ($enabled) {
            $request = $this->getRequest();
            try {
                $response = $this->sendPurgeRequest($request);
                if ($response) {
                    $decodedResponse = json_decode($response, true);
                    if (isset($decodedResponse['success']) && $decodedResponse['success'] === true) {
                        $this->messageManager->addSuccessMessage(
                            'Cloudflare cache has been purged. 
                            Please allow up to 30 seconds for changes to take effect.'
                        );
                    } else {
                        $this->logger->error(
                            'Cloudflare error: ' .
                            $response
                        );
                        $this->messageManager->addErrorMessage(
                            'Cloudflare cache purge request failed. Please check Magento\'s log
                            files for more information.'
                        );
                    }
                }
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    'Magento encountered an unexpected problem. Please check Magento\'s log
                    files for more information.'
                );
                $this->logger->critical($e->getMessage());
            }
        }
    }

    /**
     * Return request to send to Cloudflare
     *
     * @return string
     */
    private function getRequest()
    {
        $zoneID = $this->scopeConfig->getValue(self::XML_PATH_CFCACHE_ZONE);
        return sprintf(self::CFCACHE_API_URL, $zoneID);
    }

    /**
     * Send purge request to Cloudflare
     *
     * @param $request
     * @return mixed
     */
    private function sendPurgeRequest($request)
    {
        $email = $this->scopeConfig->getValue(self::XML_PATH_CFCACHE_EMAIL);
        $apiKey = $this->encryptor->decrypt($this->scopeConfig->getValue(self::XML_PATH_CFCACHE_API));
        $headers = [];

        if ($email !== '' && $apiKey !== '') {
            $headers = [
                "X-Auth-Email: {$email}",
                "X-Auth-Key: {$apiKey}",
                "Content-Type: application/json"
            ];
        }

        $curl_opt_array = [
            CURLOPT_URL => $request,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_VERBOSE => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => '{"purge_everything":true}',
            CURLOPT_CUSTOMREQUEST => 'DELETE'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $curl_opt_array);

        $response = curl_exec($ch);
        return $response;
    }
}
