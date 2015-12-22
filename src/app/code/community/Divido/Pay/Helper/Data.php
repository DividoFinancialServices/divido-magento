<?php

require_once(Mage::getBaseDir('lib') . '/Divido/Divido.php');

class Divido_Pay_Helper_Data extends Mage_Core_Helper_Abstract
{
    const
        CACHE_KEY_PLANS      = 'divido_plans',
        CACHE_LIFETIME_PLANS = 60*60;

    public function getPlans ()
    {
        $cache = Mage::app()->getCache();

        if ($plans = $cache->load(self::CACHE_KEY_PLANS)) {
            $plans = unserialize($plans);
            return $plans;
        }

        $apiKey = Mage::getStoreConfig('payment/pay/api_key');
        if (empty($apiKey)) {
            throw new Exception('API Key must be set');
        }        

        $apiKey = Mage::helper('core')->decrypt($apiKey);

        Divido::setMerchant($apiKey);        
        $parameters = array('merchant' => $apiKey);
        $response   = Divido_Finances::all($parameters);

        if ($response->status !== 'ok') {
            throw new Exception("Could not fetch plans. Error code: {$response->error_code}");
        }

        $plans = $response->finances;

        $cache->save(serialize($plans), self::CACHE_KEY_PLANS, array('divido_cache'), self::CACHE_LIFETIME_PLANS);

        return $plans;
    }

    public function getScriptUrl ()
    {
        $apiKey = Mage::getStoreConfig('payment/pay/api_key');
        if (empty($apiKey)) {
            return '';
        }

        $apiKey = Mage::helper('core')->decrypt($apiKey);
        $jsKey = strtolower(array_shift(explode('.', $apiKey)));
        return "<script src=\"//content.divido.com.s3-eu-west-1.amazonaws.com/calculator/{$jsKey}.js\"></script>";
    }
}
