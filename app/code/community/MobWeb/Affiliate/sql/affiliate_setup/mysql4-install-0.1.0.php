<?php

$installer = $this;
$installer->startSetup();


/*
 *
 * Add the custom attribute to the customer object
 *
 */
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$entityTypeId     = $setup->getEntityTypeId('customer');
$attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$setup->addAttribute('customer', Mage::helper('affiliate')->customerReferrerAttributeCode, array(
	'type' => 'varchar',
	'input' => 'text',
	'label' => 'Referrer',
	'global' => 1,
	'default' => '',
	'visible_on_front' => 0,
));

$setup->addAttributeToGroup(
	$entityTypeId,
	$attributeSetId,
	$attributeGroupId,
	Mage::helper('affiliate')->customerReferrerAttributeCode,
	'100'
);

// Add the attribute to the customer form in the Admin Panel
$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', Mage::helper('affiliate')->customerReferrerAttributeCode);
$oAttribute->setData('used_in_forms', array('adminhtml_customer')); 
$oAttribute->save();

/*
 *
 * Add the custom attribute to the order object
 *
 */
$setup = new Mage_Sales_Model_Resource_Setup('core_setup');

$setup->addAttribute('order', Mage::helper('affiliate')->orderReferrerAttributeCode, array(
	'type' => 'varchar',
	'label' => 'Referrer',
	'global' => 1,
	'required' => 0,
	'user_defined' => 0,
	'visible_on_front' => 0
));

$setup->endSetup();