<?php
 
$installer = $this;
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$installer->startSetup();
/**
 * Adding Different Attributes
 */
 
// adding attribute group
$setup->addAttributeGroup('catalog_product', 'Default', 'Divido', 1000);
 
// the attribute added will be displayed under the group/tab Special Attributes in product edit page
						
$setup->addAttribute('catalog_product', 'divido', array(
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
 
$installer->endSetup();
