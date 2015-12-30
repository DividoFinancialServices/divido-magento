<?php
require_once(Mage::getBaseDir('lib') . '/Divido/Divido.php'); 
class Divido_Pay_PaymentController extends Mage_Core_Controller_Front_Action
{
    protected $_config = null;
    protected $_configType = 'pay/config';
    protected $_configMethod = 'pay';

    protected function _construct()
    {
        parent::_construct();
        $this->_config = Mage::getModel($this->_configType, array($this->_configMethod));
    }

    /**
     * Start Standard Checkout and dispatching customer to divido
     */
    public function startAction()
    {
        $resource       = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $table          = $resource->getTableName('core_config_data');
        $query          = "Select value from $table where path = 'payment/pay/api_key'";
        $api_encode     = $readConnection->fetchOne($query);
        $apiKey         = Mage::helper('core')->decrypt($api_encode);
        $sandbox_query  = "Select value from $table where path = 'payment/pay/sandbox'";
        $sandbox_value  = $readConnection->fetchOne($sandbox_query);

        if ($sandbox_value === '1') {
            Divido::setSandboxMode(true);
        }

        Divido::setMerchant($apiKey);

        $quote_cart         = Mage::getModel('checkout/cart')->getQuote();

        $checkout_session   = Mage::getSingleton('checkout/session');
        $quote_id           = $checkout_session->getQuoteId();
        $quote_session      = $checkout_session->getQuote();
        $quote_session_data = $quote_session->getData();

        $deposit  = $this->getRequest()->getParam('divido_deposit');
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
        $products_value = 0;
        foreach ($items_in_cart as $item) {
            $item_qty   = $item->getQty();
            $item_value = $item->getPrice();

            $product = array(
                "type"     => "product",
                "text"     => $item->getName(),
                "quantity" => $item_qty,
                "value"    => $item_value,
            );

            $products_value += $item_value * $item_qty;
            array_push($products, $product);
        }

        $grandTotal        = $quote_cart->getGrandTotal();
        $shipping_handling = $grandTotal - $products_value;
        $products[] = array(
            'type'     => 'product',
            'text'     => 'Shipping & Handling',
            'quantity' => 1,
            'value'    => $shipping_handling,
        );

        $request_data = array(
            'merchant' => $apiKey,
            'deposit'  => $deposit,
            'finance'  => $finance,
            'country'  => $country,
            'language' => $language,
            'currency' => $currency,
            'metadata' => array(
                'quote_id' => $quote_id
            ),
            'customer' => array(
                'title'         => '',
                'first_name'    => $firstname,
                'middle_name'   => $middlename,
                'last_name'     => $lastname,
                'country'       => $country,
                'postcode'      => $postcode,
                'email'         => $email,
                'mobile_number' => '',
                'phone_number'  => $telephone,
            ),
            'products' => $products,
            'response_url' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'divido_callback.php',
            'redirect_url' => Mage::getUrl('customer/account/'),
        );

        $response = Divido_CreditRequest::create($request_data);

        if ($response->status == 'ok') {
           $this->_redirectUrl($response->url);
        } else {
            if ($response->status === 'error') {
                Mage::getSingleton('checkout/session')->addError($response->error);
                $this->_redirect('checkout/cart');
            }
        }
    }
} 
