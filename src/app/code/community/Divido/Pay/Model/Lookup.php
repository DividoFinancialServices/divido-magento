<?php

class Divido_Pay_Model_Lookup extends Mage_Core_Model_Abstract
{
    protected function _construct ()
    {
        $this->_init('callback/lookup');
    }
}
