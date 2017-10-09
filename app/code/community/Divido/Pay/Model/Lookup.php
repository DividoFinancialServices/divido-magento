<?php

class Divido_Pay_Model_Lookup extends Mage_Core_Model_Abstract
{
    protected function _construct ()
    {
        $this->_init('callback/lookup');
    }

    public function loadActiveByQuoteId($quote_id)
    {
        $this->_getResource()->loadActiveByQuoteId($this, $quote_id);

        return $this;
    }
}
