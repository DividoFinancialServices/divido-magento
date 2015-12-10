<?php

class Divido_Pay_Model_System_Config_Financeoptions {
    public function toOptionArray ()
    {
        return array(
            array(
                'value' => 'all_fincances',
                'label' => Mage::helper('adminhtml')->__('Display all finance options'),
            ),
            array(
                'value' => 'selected_finances',
                'label' => Mage::helper('adminhtml')->__('Display selected finance options'),
            ),
        );
    }
}
