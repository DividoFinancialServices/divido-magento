<?php

class Divido_Pay_Model_System_Config_Productoptions {
    public function toOptionArray ()
    {
        return array(
            array(
                'value' => 'products_all',
                'label' => Mage::helper('adminhtml')->__('All products'),
            ),
            array(
                'value' => 'products_selected',
                'label' => Mage::helper('adminhtml')->__('Selected products'),
            ),
            array(
                'value' => 'products_price_treshold',
                'label' => Mage::helper('adminhtml')->__('All products above a specified price'),
            ),
        );
    }
}
