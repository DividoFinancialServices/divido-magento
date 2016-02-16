<?php
 
class Divido_Pay_Model_Resource_Lookup extends Mage_Core_Model_Resource_Db_Abstract
{
	protected function __construct()
    {
        $this->_init('divido_pay/lookup', 'lookup_id');
    }
}
