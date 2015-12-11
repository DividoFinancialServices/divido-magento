<?php
 
$installer = $this;
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$installer->startSetup();
/**
 * Adding Different Attributes
 */

$groupName        = 'Divido';
$entityTypeId     = $setup->getEntityTypeId('catalog_product');
$defaultAttrSetId = $setup->getDefaultAttributeSetId($entityTypeId);

// adding attribute group
$setup->addAttributeGroup($entityTypeId, $defaultAttrSetId, $groupName, 1000);
$groupId = $setup->getAttributeGroupId($entityTypeId, $defaultAttrSetId, $groupName);

// Add attributes
$planOptionAttrCode =  'divido_plan_option';
$setup->addAttribute($entityTypeId, $planOptionAttrCode, array(
    'label'            => 'Available on finance',
    'type'             => 'varchar',
    'input'            => 'select',
    'backend'          => 'eav/entity_attribute_backend_array',
    'frontend'         => '',
    'source'           => 'pay/source_option',
    'global'           => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'          => true,
    'required'         => true,
    'user_defined'     => true,
    'searchable'       => true,
    'filterable'       => false,
    'comparable'       => false,
    'visible_on_front' => true,
    'unique'           => false,
));
$planOptionAttrId = $setup->getAttributeId($entityTypeId, $planOptionAttrCode);
$setup->addAttributeToGroup($entityTypeId, $defaultAttrSetId, $groupId, $planOptionAttrId, null);
  
$planSelectionAttrCode = 'divido_plan_selection';
$setup->addAttribute($entityTypeId, $planSelectionAttrCode, array(
    'label'            => 'Selected plans',
    'type'             => 'varchar',
    'input'            => 'multiselect',
    'backend'          => 'eav/entity_attribute_backend_array',
    'frontend'         => '',
    'source'           => 'pay/source_defaultprodplans',
    'global'           => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'          => true,
    'required'         => true,
    'user_defined'     => true,
    'searchable'       => true,
    'filterable'       => false,
    'comparable'       => false,
    'visible_on_front' => true,
    'unique'           => false,
));
$planSelectionAttrId = $setup->getAttributeId($entityTypeId, $planSelectionAttrCode);
$setup->addAttributeToGroup($entityTypeId, $defaultAttrSetId, $groupId, $planSelectionAttrId, null);
 

$installer->endSetup();
