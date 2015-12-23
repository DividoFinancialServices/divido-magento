<?php
class Divido_Pay_Model_Source_Defaultprodplans extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    public function getAllOptions ()
    {
        if ($this->_options) {
            return $this->_options;
        }

        $plans          = Mage::helper('pay')->getGlobalSelectedPlans();
        
        $this->_options = array();
        foreach ($plans as $plan) {
            $this->_options[] = array(
                'value' => $plan->id,
                'label' => $plan->text,
            );
        }

        return $this->_options;
    }
}
