<?php
 
$installer = $this;
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$installer->startSetup();
/**
 * Adding Different Attributes
 */

$groupName        = 'Divido';
$attrCode         =  'divido_calculator';
$entityTypeId     = $setup->getEntityTypeId('catalog_product');
$defaultAttrSetId = $setup->getDefaultAttributeSetId($entityTypeId);

 
// adding attribute group
$setup->addAttributeGroup($entityTypeId, $defaultAttrSetId, $groupName, 1000);
$groupId = $setup->getAttributeGroupId($entityTypeId, $defaultAttrSetId, $groupName);

// Add attribute
$setup->addAttribute($entityTypeId, $attrCode, array(
    'label'        => 'Finance by Divido',
    'type'         => 'varchar',
    'input'        => 'select',
    'backend'      => 'eav/entity_attribute_backend_array',
    'frontend'     => '',
    'source'       => 'pay/source_option',
    'global'       => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'      => true,
    'required'     => true,
    'user_defined' => true,
    'searchable'   => true,
    'filterable'   => false,
    'comparable'   => false,
    'option'            => array (
        'value' => array(
            'optionone' => array('Yes'),
            'optiontwo' => array('No'),
        )
    ),
    'visible_on_front'           => true,
    'visible_in_advanced_search' => true,
    'unique'                     => false
));
$attrId = $setup->getAttributeId($entityTypeId, $attrCode);

// Connect attribute to group
$setup->addAttributeToGroup($entityTypeId, $defaultAttrSetId, $groupId, $attrId, null);
 

$installer->endSetup();
