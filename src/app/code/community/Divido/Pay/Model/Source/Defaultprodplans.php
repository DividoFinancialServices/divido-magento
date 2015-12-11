<?php
class Divido_Pay_Model_Source_Defaultprodplans extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    public function getAllOptions ()
    {
        if (! $this->_options) {
            $this->_options = array(
                array(
                    'value' => 'baba',
                    'label' => Mage::helper('pay')->__('yaga'),
                ),
            );
        }

        return $this->_options;
    }
}
