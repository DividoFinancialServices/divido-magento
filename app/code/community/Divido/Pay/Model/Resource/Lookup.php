<?php
 
class Divido_Pay_Model_Resource_Lookup extends Mage_Core_Model_Resource_Db_Abstract
{
	protected function _construct()
    {
        $this->_init('callback/lookup', 'lookup_id');
    }

    public function loadActiveByQuoteId (Divido_Pay_Model_Lookup $object, $quote_id)
    {
        $adapter = $this->_getReadAdapter();
        $bind    = array('quote_id' => $quote_id);
        $select  = $adapter->select()
            ->from($this->getMainTable(), 'lookup_id')
            ->where('quote_id = :quote_id')
            ->where('invalidated_at is null');

        $modelId = $adapter->fetchOne($select, $bind);
        if ($modelId) {
            $this->load($object, $modelId );
        } else {
            $object->setData(array());
        }

        return $this;
    }
}
