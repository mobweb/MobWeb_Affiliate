<?php

class MobWeb_Affiliate_Helper_Data extends Mage_Core_Helper_Abstract {
	public $customerReferrerAttributeCode = 'mobweb_affiliate_ref_customer';
	public $orderReferrerAttributeCode = 'mobweb_affiliate_ref_order';

	public function log($msg, $level = NULL)
	{
	    Mage::log($msg, $level, $this->_getModuleName() . '.log');
	}

	public function getCustomerGridColumnOptionsArray()
	{
		// Get all values for the "mobweb_affiliate_referrer" customer attribute
		$collection = Mage::getModel('customer/customer')->getCollection()
		        ->addAttributeToFilter($this->customerReferrerAttributeCode, array('notnull' => true))
		        ->addAttributeToFilter($this->customerReferrerAttributeCode, array('neq' => ''))
		        ->addAttributeToSelect($this->customerReferrerAttributeCode);
		$attributeValues = array_unique($collection->getColumnValues($this->customerReferrerAttributeCode));

		sort($attributeValues, SORT_STRING);

		return $attributeValues;
	}

	public function getOrderGridColumnOptionsArray()
	{
		// Get all values for the "mobweb_affiliate_referrer" customer attribute
		$collection = Mage::getModel('sales/order')->getCollection()
		        ->addAttributeToFilter($this->orderReferrerAttributeCode, array('notnull' => true))
		        ->addAttributeToFilter($this->orderReferrerAttributeCode, array('neq' => ''))
		        ->addAttributeToSelect($this->orderReferrerAttributeCode);
		$attributeValues = array_unique($collection->getColumnValues($this->orderReferrerAttributeCode));

		sort($attributeValues, SORT_STRING);

		return $attributeValues;
	}
}