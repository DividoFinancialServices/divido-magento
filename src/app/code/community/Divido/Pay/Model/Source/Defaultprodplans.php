<?php
class Divido_Pay_Model_Source_Defaultprodplans extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    public function getAllOptions ()
    {
        if ($this->_options) {
            return $this->_options;
        }

        $plans          = Mage::helper('pay')->getPlans();
        $plansDisplayed = Mage::getStoreConfig('payment/pay/finances_displayed');
        $plansSelected  = explode(',', Mage::getStoreConfig('payment/pay/finances_list'));

        if ($plansDisplayed == 'selected_finances') {
            foreach ($plans as $key => $plan) {
                if (! in_array($plan->id, $plansSelected)) {
                    unset($plans[$key]);
                }
            }
        }
        
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
