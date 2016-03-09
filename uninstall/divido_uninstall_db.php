<?php
include 'app/Mage.php';
Mage::app();
$setup = Mage::getResourceModel('catalog/setup','catalog_setup');
$setup->removeAttribute('catalog_product','divido_plan_option');
$setup->removeAttribute('catalog_product','divido_plan_selection');

$resource = Mage::getSingleton('core/resource');
$writeConn = $resource->getConnection('core_write');
$writeConn->query('drop table if exists divido_lookup');
$writeConn->query("delete from core_resource where code = 'divido_pay_setup'");
$writeConn->query("delete from core_config_data where core_config_data.path like 'payment/pay/%'");
