<?php
require_once('app/Mage.php');
Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));

$installer = $this;

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$installer->startSetup();

/**
 * Modify lookup table for Divido
 */
$installer->getConnection()->addColumn($installer->getTable('callback/lookup'),
    'created_at',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_DATETIME,
        'nullable' => true,
        'comment' => 'Record created at',
    )
);

$installer->getConnection()->addColumn($installer->getTable('callback/lookup'),
    'invalidated_at',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_DATETIME,
        'nullable' => true,
        'comment' => 'Record updated at',
    )
);

$installer->getConnection()->addColumn($installer->getTable('callback/lookup'),
    'total_order_amount', 
    array(
        'type'      => Varien_Db_Ddl_Table::TYPE_NUMERIC,
        'nullable'  => false,
        'precision' => 10,
        'scale'     => 2,
        'comment'   => 'Credit application ID',
    )
);

$installer->getConnection()->dropIndex(
    $installer->getTable('callback/lookup'),
    $installer->getIdxName('callback/lookup', 
        array('quote_id'), 
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
);

$installer->getConnection()->addIndex(
    $installer->getTable('callback/lookup'),
    $installer->getIdxName('callback/lookup', 
        array('quote_id'), 
        Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX),
    array('quote_id'),
    Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
);

$installer->endSetup();
