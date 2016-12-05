<?php

class Divido_Pay_Model_Observer
{
    const CACHE_KEY_PLANS      = 'divido_plans';
    const FULFILMENT_STATUS    = 'complete';

    public function clearCache ($observer)
    {
        $cache = Mage::app()->getCache();
        $cache->remove(self::CACHE_KEY_PLANS);
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

    public function submitFulfilment ($observer)
    {
        $helper = Mage::helper('pay');

        $order = $observer->getOrder();
        $currentStatus = $order->getData('status');
        $previousStatus = $order->getOrigData('status');

        $lookup = Mage::getModel('callback/lookup');
        $lookup->load($order->quote_id, 'quote_id');

        // If it's not a Divido order
        if (! $lookup->getId()) {
            return false;
        }

        // If fulfilment is not enabled
        if (! Mage::getStoreConfig('payment/pay/fulfilment_update')) {
            return false;
        }

        $fulfilmentStatus = self::FULFILMENT_STATUS;
        if ($fulfilmentStatusOverride = Mage::getStoreConfig('payment/pay/fulfilment_order_status')) {
            $fulfilmentStatus = $fulfilmentStatusOverride;
        }

        if ($currentStatus == $fulfilmentStatus && $currentStatus != $previousStatus) {
            $shippingMethod = $order->getShippingDescription();

            $shipmentCollection = Mage::getResourceModel('sales/order_shipment_collection')
                ->setOrderFilter($order)->load();
            $trackingNumbers = array();
            foreach ($shipmentCollection as $shipment){
                foreach($shipment->getAllTracks() as $tracknum) {
                    $trackingNumbers[] = $tracknum->getNumber();
                }
            }
            $trackingNumbers = implode(',', $trackingNumbers);
            $applicationId = $lookup->getData('credit_application_id');
            $helper->setFulfilled($applicationId, $shippingMethod, $trackingNumbers);
        }
    }
}
