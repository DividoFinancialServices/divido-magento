<?php

require_once(Mage::getBaseDir('lib') . '/Divido/Divido.php');

class Divido_Pay_Model_System_Config_Finances {

    public function toOptionArray () {
        $finances = [];

        $apiKey = Mage::getStoreConfig('payment/pay/api_key');
        if (empty($apiKey)) {
            return $finances;
        }        

        $apiKey = Mage::helper('core')->decrypt($apiKey);

        Divido::setMerchant($apiKey);        
        $financeOptions = array('merchant' => $apiKey);
        $response       = Divido_Finances::all($financeOptions);

        if ($response->status !== 'ok') {
            return $finances;
        }
        
        foreach($response->finances as $finance) {
            $finances[] = array(
                'value' => $finance->id,
                'label' => $finance->text,
            );
        }

        return $finances;
    }
}
