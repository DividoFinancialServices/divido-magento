<?php
require_once(Mage::getBaseDir('lib') . '/Divido/Divido.php'); 
class Divido_Pay_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Start Standard Checkout and dispatching customer to divido
     */
    public function startAction()
    {
        $resource       = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $table          = $resource->getTableName('core_config_data');
        $query          = "select value from $table where path = 'payment/pay/api_key'";
        $api_encode     = $readConnection->fetchOne($query);
        $apiKey         = Mage::helper('core')->decrypt($api_encode);

        Divido::setMerchant($apiKey);

        $quote_cart         = Mage::getModel('checkout/cart')->getQuote();

        $checkout_session   = Mage::getSingleton('checkout/session');
        $quote_id           = $checkout_session->getQuoteId();
        $quote_session      = $checkout_session->getQuote();
        $quote_session_data = $quote_session->getData();

        $deposit_percentage  = $this->getRequest()->getParam('divido_deposit') / 100;
        $finance  = $this->getRequest()->getParam('divido_finance');
        $language = strtoupper(substr(Mage::getStoreConfig('general/locale/code', Mage::app()->getStore()->getId()),0,2));
        $currency = Mage::app()->getStore()->getCurrentCurrencyCode();

        $billAddress = $quote_session->getBillingAddress();
        $billing     = $billAddress->getData();
        $postcode    = $billing['postcode'];
        $telephone   = $billing['telephone'];
        $firstname   = $billing['firstname'];
        $lastname    = $billing['lastname'];
        $country     = $billing['country_id'];
        $email       = $quote_session_data['customer_email'];
        $middlename  = $quote_session_data['customer_middlename'];

        $item_quote     = Mage::getModel('checkout/cart')->getQuote();
        $items_in_cart  = $item_quote->getAllItems();
        $products       = array();

        foreach ($items_in_cart as $item) {
            $item_qty   = $item->getQty();
            $item_value = $item->getPriceInclTax();

            $product = array(
                "type"     => "product",
                "text"     => $item->getName(),
                "quantity" => $item_qty,
                "value"    => $item_value,
            );

            array_push($products, $product);
        }

        $totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();

        $total_discount = null;
        if (isset($totals['discount']) && $_discount = $totals['discount']) {
            $total_discount = $_discount->getValue();
        }

        $shipping_handling = null;
        if (isset($totals['shipping']) && $_shipping = $totals['shipping']) {
            $shipping_handling = $_shipping->getAddress()->getShippingInclTax();
        }

        $grand_total = $totals['grand_total']->getValue();

        if (! empty($shipping_handling)) {
            $products[] = array(
                'type'     => 'product',
                'text'     => 'Shipping & Handling',
                'quantity' => 1,
                'value'    => $shipping_handling,
            );
        }

        if (! empty($total_discount)) {
            $products[] = array(
                'type'     => 'product',
                'text'     => 'Discounts',
                'quantity' => 1,
                'value'    => $total_discount,
            );
        }

        $deposit = round($deposit_percentage * $grand_total, 2);

        $salt = uniqid('', true);
        $quote_hash = Mage::helper('pay')->hashQuote($salt, $quote_id);

        $customer = array(
            'title'         => '',
            'first_name'    => $firstname,
            'middle_name'   => $middlename,
            'last_name'     => $lastname,
            'country'       => $country,
            'postcode'      => $postcode,
            'email'         => $email,
            'mobile_number' => '',
            'phone_number'  => $telephone,
        );

        $metadata = array(
            'quote_id'   => $quote_id,
            'quote_hash' => $quote_hash,
        );

        $request_data = array(
            'merchant'     => $apiKey,
            'deposit'      => $deposit,
            'finance'      => $finance,
            'country'      => $country,
            'language'     => $language,
            'currency'     => $currency,
            'metadata'     => $metadata,
            'customer'     => $customer,
            'products'     => $products,
            'response_url' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'divido_callback.php',
            'checkout_url' => Mage::helper('checkout/url')->getCheckoutUrl(),
            'redirect_url' => Mage::getUrl('pay/payment/return', array('quote_id' => $quote_id)),
        );

        $response = Divido_CreditRequest::create($request_data);

        if ($response->status == 'ok') {
            $lookup = Mage::getModel('callback/lookup');
            $lookup->setQuoteId($quote_id);
            $lookup->setSalt($salt);
            $lookup->setCreditRequestId($response->id);

            $existing_lookup = Mage::getModel('callback/lookup')->load($quote_id, 'quote_id');
            if ($existing_lookup->getId()) {
                $lookup->setId($existing_lookup->getId());
            }

            $lookup->save();

            $this->getResponse()->setRedirect($response->url);
            return;
        } else {
            if ($response->status === 'error') {
                Mage::getSingleton('checkout/session')->addError($response->error);
                $this->_redirect('checkout/cart');
            }
        }
    }

    public function returnAction ()
    {
        $session = Mage::getSingleton('checkout/session');
        $quoteId = $this->getRequest()->getParam('quote_id');
        $quote   = Mage::getModel('sales/quote')->load($quoteId);

        $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

        $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $quoteId);
        if ($orderId = $order->getId()) {
            $session->setLastOrderId($orderId)
                ->setLastRealOrderId($order->getIncrementId());
        }

        $this->_redirect('checkout/onepage/success');
    }
}
