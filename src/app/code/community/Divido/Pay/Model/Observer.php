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
        $quote        = $observer->quote;

        // Only interesting if run in the context of a quote;
        if (! $quote) {
            return;
        }

        $event        = $observer->getEvent();
        $method       = $event->getMethodInstance();
        $result       = $event->getResult();

        $plans        = Mage::helper('pay')->getQuotePlans($quote);

        $billingAddr  = $quote->getBillingAddress()->getData();
        $rightCountry = $billingAddr['country_id'] == 'GB';


        if ($method->getCode() == 'pay' && (!$rightCountry || empty($plans))) {
            $result->isAvailable = false;
        }
    }
}
