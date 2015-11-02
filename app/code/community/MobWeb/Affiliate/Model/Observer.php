<?php

class MobWeb_Affiliate_Model_Observer
{
    /*
     *
     * Observe every page load in the frontend and check if a referrer parameter has
     * been set. If yes, save the parameters value in a cookie
     *
     */
    public function controllerFrontInitBefore(Varien_Event_Observer $observer)
    {
        $controller = $observer->getEvent()->getFront();
        if($referrer = $controller->getRequest()->getParam('aref', false)) {
            
            // If a referrer has been detected, save it in a cookie
            Mage::getSingleton('core/cookie')->set(
                Mage::helper('affiliate')->customerReferrerAttributeCode,
                $referrer,
                60*60*24*365*10, // 10 years
                '/'
            );

            Mage::helper('affiliate')->log('Referral cookie set for referrer: ' . $referrer);
        }
    }

    /*
     *
     * When a customer registers their account, check if a "referrer" cookie is present.
     * If yes, store the referrer in the customer's account
     *
     */
    public function customerRegisterSuccess(Varien_Event_Observer $observer)
    {
        $customer = $observer->getCustomer();

        Mage::helper('affiliate')->log(sprintf('Customer %s: has registered', $customer->getId()));

        // Check if a referrer cookie exists
        if($customer && $referrer = Mage::getModel('core/cookie')->get(Mage::helper('affiliate')->customerReferrerAttributeCode)) {

            // Update the newly registered customer's account the name of the referral
            $customer->setData(Mage::helper('affiliate')->customerReferrerAttributeCode, $referrer)->save();

            // And remove the cookie
            Mage::getModel('core/cookie')->delete(Mage::helper('affiliate')->customerReferrerAttributeCode);

            // Create a log entry
            Mage::helper('affiliate')->log(sprintf('Customer %s: assigned to referrer %s', $customer->getId(), $referrer));
        } else {
            Mage::helper('affiliate')->log(sprintf('Customer %s: no referrer cookie present', $customer->getId()));
        }
    }

    /*
     *
     * If an order is captured, check if we can assign a referrer to the order either from an existing referrer on the account
     * or from a cookie, if the customer checked out as a guest.
     * Also, check if the account was created during the checkout and if yes, if a cookie is present so that we have
     * to set the referrer for the newly created account (the 'customer_register_success'-event is NOT fired if the
     * registration happens during checkout).
     *
     */
    public function captureOrder(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $orderId = $order->getId();

        Mage::helper('affiliate')->log(sprintf('Order %s: placed', $orderId));

        // Check if the order was placed by a registered account
        if($customerId = $order->getCustomerId()) {

            // Load the customer
            $customer = Mage::getModel('customer/customer')->load($customerId);

            Mage::helper('affiliate')->log(sprintf('Order %s: placed by registered customer %s', $orderId, $customerId));

            // Check if the account is assigned to a referrer
            if($referrer = $customer->getData(Mage::helper('affiliate')->customerReferrerAttributeCode, $referrer)) {
                Mage::helper('affiliate')->log(sprintf('Order %s: existing registered customer %s is assigned to referrer %s', $orderId, $customerId, $referrer));
            } else {
                Mage::helper('affiliate')->log(sprintf('Order %s: existing registered customer %s is not assigned to a referrer yet', $orderId, $customerId));

                // Get the customer's registration date to determine if it was recent
                $customerCreatedAt = $customer->getData('created_at');

                // Convert the timestamp into the server's timezone
                $customerCreatedAt = Mage::getModel('core/date')->timestamp(strtotime($customerCreatedAt));
                $current = Mage::getModel('core/date')->timestamp(time());

                // If the account was created during the last minute, this means
                // the customer just signed up
                if(($current-$customerCreatedAt) < 60*1) {

                    Mage::helper('affiliate')->log(sprintf('Order %s: customer %s has signed up during order submission', $orderId, $customerId));

                    // If a "referrer" cookie exists save the referrer in the customer's account
                    if($referrer = Mage::getModel('core/cookie')->get(Mage::helper('affiliate')->customerReferrerAttributeCode)) {

                        // Destroy the "refferer" cookie
                        Mage::getModel('core/cookie')->delete(Mage::helper('affiliate')->customerReferrerAttributeCode);

                        // Save the referrer in the customer's account
                        $customer->setData(Mage::helper('affiliate')->customerReferrerAttributeCode, $referrer)->save();

                        Mage::helper('affiliate')->log(sprintf('Order %s: newly registered customer %s assigned to referrer %s', $orderId, $customerId, $referrer));
                    } else {
                        Mage::helper('affiliate')->log(sprintf('Order %s: newly registered customer %s does not have a referrer cookie, not assigning to a referrer', $orderId, $customerId));
                    }
                } else {
                    Mage::helper('affiliate')->log(sprintf('Order %s: existing registered customer %s is not assigned to a referrer yet, but the account was not newly created, so not assigning', $orderId, $customerId));
                }
            }
        } else {
            Mage::helper('affiliate')->log(sprintf('Order %s: placed by guest account', $orderId));

            // If the order was placed by a guest account, check if the "referrer" cookie is present
             if($referrer = Mage::getModel('core/cookie')->get(Mage::helper('affiliate')->customerReferrerAttributeCode)) {
                Mage::helper('affiliate')->log(sprintf('Order %s: placed by guest account with referrer cookie %s', $orderId, $referrer));
             } else {
                Mage::helper('affiliate')->log(sprintf('Order %s: placed by guest account with NO referrer cookie', $orderId));
            }
        }

        // Finally check if a referrer has been identified for this order, either from a cookie
        // or from the customer attribute
        if(isset($referrer) && $referrer) {
            Mage::helper('affiliate')->log(sprintf('Order %s: assigning order to referrer %s', $orderId, $referrer));

            // Assign the order that was just placed to that referrer
            $order->setData(Mage::helper('affiliate')->orderReferrerAttributeCode, $referrer)->save();
        } else {
            Mage::helper('affiliate')->log(sprintf('Order %s: NOT assigning order to any referrer', $orderId));
        }
    }

    /*
     *
     * This function adds our custom columns to the customer and order grids
     *
     */
    public function coreBlockAbstractToHtmlBefore(Varien_Event_Observer $observer)
    {
        $grid = $observer->getBlock();

        if($grid INSTANCEOF Mage_Adminhtml_Block_Customer_Grid) {

            // Add our custom "Referrer" column to the customer grid
            $grid->addColumnAfter(
                Mage::helper('affiliate')->customerReferrerAttributeCode,
                array(
                    'header' => Mage::helper('affiliate')->__('Referrer'),
                    'index'  => Mage::helper('affiliate')->customerReferrerAttributeCode,
                    'sortable' => true,
                    'type' => 'options',
                    'options' => Mage::helper('affiliate')->getCustomerGridColumnOptionsArray()
                ),
                'customer_since'
            );

            return;
        }

        if($grid INSTANCEOF Mage_Adminhtml_Block_Sales_Order_Grid) {

            // Add a "Referrer" column to the order grid
            $grid->addColumnAfter(
                Mage::helper('affiliate')->orderReferrerAttributeCode,
                array(
                    'header' => Mage::helper('affiliate')->__('Referrer'),
                    'index'  => Mage::helper('affiliate')->orderReferrerAttributeCode,
                    'filter_index'  => 'orders.' . Mage::helper('affiliate')->orderReferrerAttributeCode,
                    'sortable' => true,
                    'type' => 'options',
                    'options' => Mage::helper('affiliate')->getOrderGridColumnOptionsArray(),
                ),
                'grand_total'
            );

            // Add a "Referral Amount" column to the order grid, which displays
            // the amount paid subtracted by the amount refunded
            $grid->addColumnAfter(
                'referral_amount',
                array(
                    'header' => Mage::helper('affiliate')->__('Referral Amount'),
                    'index'  => 'referral_amount',
                    'type' => 'currency',
                    'sortable' => false,
                    'filter' => false,
                    'currency' => 'order_currency_code',
                ),
                Mage::helper('affiliate')->orderReferrerAttributeCode
            );

            return;
        }
    }

    /*
     *
     * This function modifies the customer and order collections to include
     * our custom attributes so we can display them in the grids
     *
     */
    public function eavCollectionAbstractLoadBefore(Varien_Event_Observer $observer)
    {
        $collection = $observer->getCollection();

        if($collection INSTANCEOF Mage_Customer_Model_Resource_Customer_Collection) {

            // Simply add the "Referrer" attribute to the customer collection
            $collection->addAttributeToSelect(Mage::helper('affiliate')->customerReferrerAttributeCode);

            return;
        }

        $collection = $observer->getOrderGridCollection();

        if($collection INSTANCEOF Mage_Sales_Model_Resource_Order_Collection) {

            // Join the default order collection with the sales_flat_order table to get the custom
            // order referrer attribute, and also calculate the referral amount (total paid - total refunded)
            $collection->getSelect()
                ->joinLeft(
                    array('orders' => Mage::getSingleton('core/resource')->getTableName('sales_flat_order')),
                    'orders.entity_id = `main_table`.entity_id',
                    array(

                        // Referrer
                        Mage::helper('affiliate')->orderReferrerAttributeCode => 'orders.' . Mage::helper('affiliate')->orderReferrerAttributeCode,

                        // Referral amount = Total paid - Total refunded
                        // COALESCE() means "$v = $v ? $v : 0;", use 0 for the calculation if the value is NULL or empty
                        'referral_amount' => '(COALESCE(orders.base_total_paid, 0) - COALESCE(orders.base_total_refunded, 0))' 
                    )
                );

            // Since we are inner joining the "orders" table, we have to
            // specify which table to run the search queries against,
            // otherwise the DB would throw an "ambigious" error
            // See: http://stackoverflow.com/q/18147525/278840
            $select = $collection->getSelect();
            if ($where = $select->getPart('where')) {
                foreach ($where as $key=> $condition) {
                    if (strpos($condition, 'increment_id')) {
                        $new_condition = str_replace("`increment_id`", "`main_table`.increment_id", $condition);
                        $where[$key] = $new_condition;
                    }
                    if (strpos($condition, 'created_at')) {
                        $new_condition = str_replace("`created_at`", "`main_table`.created_at", $condition);
                        $where[$key] = $new_condition;
                    }
                    if (strpos($condition, 'store_id')) {
                        $new_condition = str_replace("`store_id`", "`main_table`.store_id", $condition);
                        $where[$key] = $new_condition;
                    }
                    if (strpos($condition, 'grand_total')) {
                        $new_condition = str_replace("`grand_total`", "`main_table`.grand_total", $condition);
                        $where[$key] = $new_condition;
                    }
                    if (strpos($condition, 'base_grand_total')) {
                        $new_condition = str_replace("`base_grand_total`", "`main_table`.base_grand_total", $condition);
                        $where[$key] = $new_condition;
                    }
                    if (strpos($condition, 'status')) {
                        $new_condition = str_replace("`status`", "`main_table`.status", $condition);
                        $where[$key] = $new_condition;
                    }
                }
                $select->setPart('where', $where);
            }

            return;
        }
    }
}