<?php

class Divido_Pay_Model_Observer
{
    public function __construct ()
    {
    }

    public function updateDefaultProductPlans ($observer)
    {
        $helper = Mage::helper('pay');
        try {
            $plans = $helper->getGlobalSelectedPlans();
        } catch (Exception $e) {
            return false;
        }

        $plan_ids = array();
        foreach ($plans as $plan) {
            $plan_ids[] = $plan->id;
        }
        $plan_list = implode(',', $plan_ids);

        $data = array(
                'default_value' =>  $plan_list,
        );

        $attributeModel = Mage::getModel('eav/entity_attribute');
        $attributeModel->loadByCode('catalog_product', 'divido_plan_selection');
        $attributeModel->addData($data);

        $session = Mage::getSingleton('adminhtml/session');

        try {
            $attributeModel->save();
            $session->addSuccess(Mage::helper('catalog')->__('Default product plans have been updated.'));
        } catch (Exception $e) {
            $session->addError(Mage::helper('catalog')->__('Default product plans could not be updated. Message: ' . $e->getMessage()));
        }


    }

    public function toggleDividoAvailability (Varien_Event_Observer $observer)
    {
        $event         = $observer->getEvent();
        $method        = $event->getMethodInstance();
        $result        = $event->getResult();
        $currencyCode  = Mage::app()->getStore()->getCurrentCurrencyCode();
        $rightCurrency = $currencyCode == 'GBP';
        $quote         = $observer->quote;
        $plans         = Mage::helper('pay')->getQuotePlans($quote);

        if ($method->getCode() == 'pay' && (!$rightCurrency || empty($plans))) {
            $result->isAvailable = false;
        }
    }
}
