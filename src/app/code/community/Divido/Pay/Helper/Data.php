<?php

require_once(Mage::getBaseDir('lib') . '/Divido/Divido.php');

class Divido_Pay_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CACHE_KEY_PLANS      = 'divido_plans';
    const CACHE_LIFETIME_PLANS = 3600;

    public function getAllPlans ()
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

    public function isActiveGlobal ()
    {
        $globalActive = Mage::getStoreConfig('payment/pay/active');

        return (bool) $globalActive;
    }

    public function isActiveLocal ($product)
    {
        $globalActive = $this->isActiveGlobal();

        if (! $globalActive) {
            return false;
        }

        $productOptions        = Mage::getStoreConfig('payment/pay/product_options');
        $productPriceThreshold = Mage::getStoreConfig('payment/pay/product_price_treshold');

        switch ($productOptions) {
        case 'products_price_treshold':
            if ($product['price'] < $productPriceThreshold) {
                return false;
            }
            break;

        case 'products_selected':
            $productPlans = $this->getLocalPlans($product);
            if (! $productPlans) {
                return false;
            }
        }

        return true;
    }

    public function getLocalPlans ($product)
    {
        $globalPlans = $this->getGlobalSelectedPlans();

        // Get local settings for Divido
        $productPlans    = $product['divido_plan_option'];
        $productPlanList = $product['divido_plan_selection'];
        $productPlanList = ! empty($productPlanList) ? explode(',', $productPlanList) : array();

        if ($productPlans == 'default_plans') {
            return $globalPlans;
        }

        $plans = array();
        foreach ($globalPlans as $plan) {
            if (in_array($plan->id, $productPlanList)) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    public function getGlobalSelectedPlans ()
    {
        // Get all finance plans
        $allPlans = $this->getAllPlans();

        // Get system settings for Divido
        $globalPlansDisplayed = Mage::getStoreConfig('payment/pay/finances_displayed');
        $globalPlanList       = Mage::getStoreConfig('payment/pay/finances_list');
        $globalPlanList       = ! empty($globalPlanList) ? explode(',', $globalPlanList) : null;

        if ($globalPlansDisplayed == 'all_fincances') {
            return $allPlans;
        }

        // We're only showing selected finance plans
        $plans = array();
        foreach ($allPlans as $plan) {
            if (in_array($plan->id, $globalPlanList)) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    public function plans2list ($plans)
    {
        $plansBare = array_map(function ($plan) {
            return $plan->id;
        }, $plans);

        return implode(',', $plansBare);
    }
}
