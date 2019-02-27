<?php
class Divido_Pay_Model_Source_Option extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    public function getAllOptions($withEmpty = true, $defaultValues = false)
    {
        if (! $this->_options) {
            $this->_options = array(
                array(
                    'value' => 'default_plans',
                    'label' => Mage::helper('divido_pay')->__('Default settings'),
                ),
                array(
                    'value' => 'selected_plans',
                    'label' => Mage::helper('divido_pay')->__('Selected plans'),
                ),
            );
        }

        return $this->_options;
    }   
}
