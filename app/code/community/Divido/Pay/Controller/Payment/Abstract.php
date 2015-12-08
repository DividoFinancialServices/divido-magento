<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Divido_Pay
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract Standard Checkout Controller
 */
require_once(Mage::getBaseDir('lib') . '/Divido/Divido.php'); 
abstract class Divido_Pay_Controller_Payment_Abstract extends Mage_Core_Controller_Front_Action
{

    /**
     * @var Divido_Pay_Model_Standard_Checkout
     */
    protected $_checkout = null;

    /**
     * @var Divido_Pay_Model_Config
     */
    protected $_config = null;

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = false;

    protected $_session = false;

    /**
     * Instantiate config
     */
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
        $sandbox_query  = "Select value from $table where path         = 'payment/pay/sandbox'";
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
            'response_url' => 'http://cjgk.me/response.php',
            'redirect_url' => Mage::getUrl('customer/account/'),
        );
        $response = Divido_CreditRequest::create($request_data);

        if ($response->status == 'ok') {
            //$this->_redirectUrl($response->url);
        } else {
            if ($response->status === 'error') {
                Mage::getSingleton('checkout/session')->addError($response->error);
                $this->_redirect('checkout/cart');
            }
        }
    }

    /**
     * Cancel Standard Checkout
     */
    public function cancelAction()
    {
        try {

            // TODO verify if this logic of order cancelation is deprecated
            // if there is an order - cancel it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();
            $order = ($orderId) ? Mage::getModel('sales/order')->load($orderId) : false;
            if ($order && $order->getId() && $order->getQuoteId() == $this->_getCheckoutSession()->getQuoteId()) {
                $order->cancel()->save();
                $this->_getCheckoutSession()
                    ->unsLastQuoteId()
                    ->unsLastSuccessQuoteId()
                    ->unsLastOrderId()
                    ->unsLastRealOrderId()
                    ->addSuccess($this->__('Standard Checkout and Order have been canceled.'))
                    ;
            }
            else
            {
                $this->_getCheckoutSession()->addSuccess($this->__('Standard Checkout has been canceled.'));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getCheckoutSession()->addError($this->__('Unable to cancel Standard Checkout.'));
            Mage::logException($e);
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * Review order after returning from divido
     */
    public function reviewAction()
    {
        try {
            $this->_initCheckout();
            $this->_checkout->prepareOrderReview();
            $this->loadLayout();
            $this->_initLayoutMessages('pay/session');
            $reviewBlock = $this->getLayout()->getBlock('pay.express.review');
            $reviewBlock->setQuote($this->_getQuote());

            $reviewBlock->getChild('details')->setQuote($this->_getQuote());
            if ($reviewBlock->getChild('shipping_method')) {
                $reviewBlock->getChild('shipping_method')->setQuote($this->_getQuote());
            }
            $this->renderLayout();
            return;
        }
        catch (Mage_Core_Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
        }
        catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError(
                $this->__('Unable to initialize Standard Checkout review.')
            );
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Dispatch customer back to divido for editing payment information
     */
    public function editAction()
    {
        try {
            $this->getResponse()->setRedirect($this->_config->getStandardCheckoutEditUrl());
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/review');
        }
    }

    /**
     * Update shipping method (combined action for ajax and regular request)
     */
    public function saveShippingMethodAction()
    {
        try {
            $isAjax = $this->getRequest()->getParam('isAjax');
            $this->_initCheckout();
            $this->_checkout->updateShippingMethod($this->getRequest()->getParam('shipping_method'));
            if ($isAjax) {
                $this->loadLayout('pay_express_review_details');
                $this->getResponse()->setBody($this->getLayout()->getBlock('root')
                    ->setQuote($this->_getQuote())
                    ->toHtml());
                return;
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Unable to update shipping method.'));
            Mage::logException($e);
        }
        if ($isAjax) {
            $this->getResponse()->setBody('<script type="text/javascript">window.location.href = '
                . Mage::getUrl('*/*/review') . ';</script>');
        } else {
            $this->_redirect('*/*/review');
        }
    }

    /**
     * Update Order (combined action for ajax and regular request)
     */
    public function updateShippingMethodsAction()
    {
        try {
            $this->_initCheckout();
            $this->_checkout->prepareOrderReview();
            $this->loadLayout('pay_express_review');

            $this->getResponse()->setBody($this->getLayout()->getBlock('express.review.shipping.method')
                ->setQuote($this->_getQuote())
                ->toHtml());
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Unable to update shipping method.'));
            Mage::logException($e);
        }
        $this->getResponse()->setBody('<script type="text/javascript">window.location.href = '
            . Mage::getUrl('*/*/review') . ';</script>');
    }

    /**
     * Submit the order
     */
    public function placeOrderAction()
    {
        //        echo '<pre>'; print_r($_REQUEST);echo '</pre>';
        //        die('response');
        //        $data = json_decode(@file_get_contents('php://input'));
        //        ob_start();
        //        print_r($data);
        //        $content = ob_get_contents();
        //        ob_end_clean();
        //
        //        mail("anders.hallsten@divido.com","webhook content",$content);
        //        die('hjghjg');

        print_r(json_decode(file_get_contents('php://input')));
        $content = ob_get_contents();
        echo "<pre>"; print_r($content); echo "</pre>";
        echo $content['status'];
        die('this');

        try {
            $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
            if ($requiredAgreements) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                if (array_diff($requiredAgreements, $postedAgreements)) {
                    Mage::throwException(Mage::helper('pay')->__('Please agree to all the terms and conditions before placing the order.'));
                }
            }
            $this->_initCheckout();

            $this->_checkout->place();

            // prepare session to success or cancellation page
            $session = $this->_getCheckoutSession();
            $session->clearHelperData();

            // "last successful quote"
            $quoteId = $this->_getQuote()->getId();
            $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            // an order may be created
            $order = $this->_checkout->getOrder();
            if ($order) {
                $session->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId());
                // as well a billing agreement can be created
                $agreement = $this->_checkout->getBillingAgreement();
                if ($agreement) {
                    $session->setLastBillingAgreementId($agreement->getId());
                }
            }

            // recurring profiles may be created along with the order or without it
            $profiles = $this->_checkout->getRecurringPaymentProfiles();
            if ($profiles) {
                $ids = array();
                foreach($profiles as $profile) {
                    $ids[] = $profile->getId();
                }
                $session->setLastRecurringProfileIds($ids);
            }

            // redirect if divido specified some URL 
            $url = $this->_checkout->getRedirectUrl();
            if ($url) {
                $this->getResponse()->setRedirect($url);
                return;
            }

            $this->_redirect('checkout/onepage/success');
            return;
        } catch (Divido_Pay_Model_Api_ProcessableException $e) {
            $this->_processDividiApiError($e);
        } catch (Mage_Core_Exception $e) {
            Mage::helper('checkout')->sendPaymentFailedEmail($this->_getQuote(), $e->getMessage());
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/review');
        } catch (Exception $e) {
            Mage::helper('checkout')->sendPaymentFailedEmail(
                $this->_getQuote(),
                $this->__('Unable to place the order.')
            );
            $this->_getSession()->addError($this->__('Unable to place the order.'));
            Mage::logException($e);
            $this->_redirect('*/*/review');
        }
    }




    /**
     * Redirect customer to shopping cart and show error message
     *
     * @param string $errorMessage
     */
    protected function _redirectToCartAndShowError($errorMessage)
    {

        $cart = Mage::getSingleton('checkout/cart');
        $cart->getCheckoutSession()->addError($errorMessage);
        $this->_redirect('checkout/cart');
    }

    /**
     * Instantiate quote and checkout
     *
     * @return Divido_Pay_Model_Standard_Checkout
     * @throws Mage_Core_Exception
     */
    protected function _initCheckout()
    {   

        $quote = $this->_getQuote();

        echo '<pre>'; print_r($quote->hasItems()); echo '</pre>';
        //die('qoute');
        if (!$quote->hasItems()){
            // die('notfound');
            $this->getResponse()->setHeader('HTTP/1.1','403 Forbidden');
            Mage::throwException(Mage::helper('pay')->__('Unable to initialize Standard Checkout.'));
        }   
        else {
            //die('found');

        }
        $this->_checkout = Mage::getSingleton($this->_checkoutType, array(
            'config' => $this->_config,
            'quote'  => $quote,
        ));
        return $this->_checkout;
    }

    /**
     * Divido session instance getter
     *
     * 
     */
    private function _getSession()
    {
        return Mage::getSingleton('pay/session');
    }

    /**
     * Return checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession()
    {
        if(Mage::getSingleton('checkout/session'))
        {
            return Mage::getSingleton('checkout/session');
        }
        else
        {   
            return Mage::getSingleton('core/session')->getPay();
        }
    }

    /**
     * Return checkout quote object
     *
     * @return Mage_Sales_Model_Quote
     */
    private function _getQuote()
    {
        $this->_quote = Mage::getSingleton('checkout/session')->getQuote();
        echo '<pre>';
        print_r($this->_quote->hasItems());
        echo '</pre>';
        //die('get');
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }

    /**
     * Redirect to login page
     *
     */
    public function redirectLogin()
    {
        $this->setFlag('', 'no-dispatch', true);
        $this->getResponse()->setRedirect(
            Mage::helper('core/url')->addRequestParam(
                Mage::helper('customer')->getLoginUrl(),
                array('context' => 'checkout')
            )
        );
    }

}
