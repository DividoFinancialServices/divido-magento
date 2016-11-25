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
    'order_id',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'nullable' => true,
        'comment' => 'Order ID',
    )
);

$installer->getConnection() ->addColumn($installer->getTable('callback/lookup'),
    'deposit_amount', 
    array(
        'type'      => Varien_Db_Ddl_Table::TYPE_NUMERIC,
        'nullable'  => false,
        'precision' => 10,
        'scale'     => 2,
        'comment'   => 'Credit application ID',
    )
);

$installer->getConnection()->addColumn($installer->getTable('callback/lookup'),
    'canceled',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_BOOLEAN,
        'nullable' => true,
        'comment' => 'The application has ben cancelled',
    )
);

$installer->getConnection()->addColumn($installer->getTable('callback/lookup'),
    'declined',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_BOOLEAN,
        'nullable' => true,
        'comment' => 'The application was denied'
    )
);

$installer->endSetup();
