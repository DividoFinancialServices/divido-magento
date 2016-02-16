<?php
 
class Divido_Pay_Model_Resource_Lookup extends Mage_Core_Model_Resource_Db_Abstract
{
	protected function _construct()
    {
        $this->_init('callback/lookup', 'lookup_id');
    }
}
