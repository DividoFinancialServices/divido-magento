<?php
require_once('app/Mage.php');
Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));
 
$installer = $this;

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$installer->startSetup();

/**
 * Modify lookup table for Divido
 */
$installer->getConnection()
    ->addColumn($installer->getTable('callback/lookup'),
        'credit_application_id', 
        array(
            'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
            'nullable' => false,
            'comment' => 'Credit application ID',
        )
    );


$installer->endSetup();
