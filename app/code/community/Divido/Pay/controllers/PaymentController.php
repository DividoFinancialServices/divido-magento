<?php
require_once(Mage::getBaseDir('lib') . '/Divido/Divido.php'); 
class Divido_Pay_PaymentController extends Mage_Core_Controller_Front_Action
{

        /**
     * Checkout types: Checkout as Guest, Register, Logged In Customer
     */
    const METHOD_GUEST    = 'guest';
    const METHOD_REGISTER = 'register';
    const METHOD_CUSTOMER = 'customer';

    const
        M_STATUS_PENDING     = 'pending',
        M_STATUS_DEFAULT     = 'processing',
        M_STATUS_HOLDED      = 'holded',
        M_STATE_NEW          = Mage_Sales_Model_Order::STATE_NEW,
        M_STATE_HOLDED       = Mage_Sales_Model_Order::STATE_HOLDED,
        M_STATE_PROCESSING   = Mage_Sales_Model_Order::STATE_PROCESSING,
        STATUS_ACCEPTED      = 'ACCEPTED',
        STATUS_ACTION_LENDER = 'ACTION-LENDER',
        STATUS_CANCELED      = 'CANCELED',
        STATUS_COMPLETED     = 'COMPLETED',
        STATUS_DEFERRED      = 'DEFERRED',
        STATUS_DECLINED      = 'DECLINED',
        STATUS_DEPOSIT_PAID  = 'DEPOSIT-PAID',
        STATUS_FULFILLED     = 'FULFILLED',
        STATUS_REFERRED      = 'REFERRED',
        STATUS_SIGNED        = 'SIGNED',
        LOG_FILE             = 'divido.log',
        EPSILON              = 0.000001,
        DIVIDO_WAIT_TIME     = 7;
        
    private $historyMessages = array(
        self::STATUS_ACCEPTED      => 'Credit request accepted',
        self::STATUS_ACTION_LENDER => 'Lender notified',
        self::STATUS_CANCELED      => 'Application canceled',
        self::STATUS_COMPLETED     => 'Application completed',
        self::STATUS_DEFERRED      => 'Application deferred by Underwriter, waiting for new status',
        self::STATUS_DECLINED      => 'Applicaiton declined by Underwriter',
        self::STATUS_DEPOSIT_PAID  => 'Deposit paid by customer',
        self::STATUS_FULFILLED     => 'Credit request fulfilled',
        self::STATUS_REFERRED      => 'Credit request referred by Underwriter, waiting for new status',
        self::STATUS_SIGNED        => 'Customer have signed all contracts',
    );

    private $noGo = array(
        self::STATUS_CANCELED, 
        self::STATUS_DECLINED,
    );

    private $orderId;

    private $quoteId;

    private $logId;

    public function getLookup($quote_id)
    {
        $lookup = Mage::getModel('callback/lookup')->loadActiveByQuoteId($quote_id);

        return $lookup;
    }

    /**
     * Start Standard Checkout and dispatching customer to divido
     */
    public function startAction()
    {
        $apiKeyEnc      = Mage::getStoreConfig('payment/pay/api_key');
        $apiKey         = Mage::helper('core')->decrypt($apiKeyEnc);
        $secretEnc      = Mage::getStoreConfig('payment/pay/secret');
        $secret         = Mage::helper('core')->decrypt($secretEnc);

        Divido::setMerchant($apiKey);
        if (! empty($sharedSecret)) {
            Divido::setSharedSecret($sharedSecret);
        }

        $quote_cart         = Mage::getModel('checkout/cart')->getQuote();

        $checkout_session   = Mage::getSingleton('checkout/session');
        $quote_id           = $checkout_session->getQuoteId();
        $quote_session      = $checkout_session->getQuote();
        $quote_session_data = $quote_session->getData();
        $checkout_type      = $quote_session->getCheckoutMethod();

        $totals = $quote_session->getTotals();
        $grand_total = $totals['grand_total']->getValue();


        $existing_lookup =  $this->getLookup($quote_id);
        $existing_lookup_id = $existing_lookup->getId();
        $existingCRId = $existing_lookup->getData('credit_request_id');
        if ($existing_lookup_id && $existingCRId) {

            $lookupTotalAmount = $existing_lookup->getData('total_order_amount');
            $is_cancelled = $existing_lookup->getCanceled();
            $is_declined = $existing_lookup->getDeclined();
            if ($grand_total == $lookupTotalAmount && !$is_cancelled && !$is_declined) {
                $dividoApi = new Divido_ApiRequestor();

                try {
                    $result = $dividoApi->request('GET', '/v1/applications', 'id=' . $existingCRId);
                    $result = $result[0];
                    if ($result['status'] == 'ok' && !empty($result['record'])) {
                        $record = $result['record'];
                        if (!empty($record['url']) && !in_array($record['status'], array('CANCELED', 'DECLINED'))) {
                            $this->getResponse()->setRedirect($record['url']);
                            return;
                        }
                    }
                } catch (Exception $e) {
                    Mage::log($e->getMessage() , Zend_Log::ERROR, 'divido.log', true);
                }
            }

            $existing_lookup->setInvalidatedAt(date(DATE_ATOM));
            $existing_lookup->save();
            $existing_lookup_id = null;
            
        }

        $deposit_percentage  = $this->getRequest()->getParam('divido_deposit') / 100;
        $finance  = $this->getRequest()->getParam('divido_finance');
        $language = strtoupper(substr(Mage::getStoreConfig('general/locale/code', Mage::app()->getStore()->getId()),0,2));
        $currency = Mage::app()->getStore()->getCurrentCurrencyCode();

        $shipAddr   = $quote_session->getShippingAddress();
        $shipping   = $shipAddr->getData();
        
        $billAddr   = $quote_session->getBillingAddress();
        $billing    = $billAddr->getData();

        $addressStreet = str_replace("\n"," ",$billing['street']);
        $addressPostcode = $billing['postcode'];
        $addressCity     = $billing['city'];
        $addressText     = implode(' ' , array($addressStreet,$addressCity,$addressPostcode));

        $shippingAddressStreet   = str_replace("\n"," ", $shipping['street']);
        $shippingAddressPostcode = $shipping['postcode'];
        $shippingAddressCity     = $shipping['city'];
        
        
        $postcode   = $shipping['postcode'];
        $telephone  = $shipping['telephone'];
        $firstname  = $shipping['firstname'];
        $lastname   = $shipping['lastname'];
        $country    = $shipping['country_id'];
        $email      = $quote_session_data['customer_email'];
        $middlename = $quote_session_data['customer_middlename'];

        $item_quote     = Mage::getModel('checkout/cart')->getQuote();
        $items_in_cart  = $item_quote->getAllItems();
        $products       = array();

        foreach ($items_in_cart as $item) {
            if ($item->getRealProductType() == 'bundle') {
                continue;
            }

            $item_qty   = $item->getQty();
            $item_value = $item->getPrice();

            $products[] = array(
                "type"     => "product",
                "text"     => $item->getName(),
                "quantity" => $item_qty,
                "value"    => $item_value,
            );
        }

        foreach ($totals as $total) {
            if (in_array($total->getCode(), array('subtotal', 'grand_total'))) {
                continue;
            }

            $products[] = array(
                'type' => 'product',
                'text' => $total->getTitle(),
                'quantity' => 1,
                'value' => $total->getValue(),
            );
        }

        $deposit = round($deposit_percentage * $grand_total, 2);

        $salt = uniqid('', true);
        $quote_hash = Mage::helper('divido_pay')->hashQuote($salt, $quote_id);

        $shippingAddress = array(
            'postcode'          => $shippingAddressPostcode,
            'street'            => $shippingAddressStreet,
            'flat'              => '',
            'buildingNumber'    => '',
            'buildingName'      => '',
            'town'              => $shippingAddressCity,
            'flat'              => '',
        );


        $address = array(
            'postcode'          => $addressPostcode,
            'street'            => $addressStreet,
            'flat'              => '',
            'buildingNumber'    => '',
            'buildingName'      => '',
            'town'              => $addressCity,
            'flat'              => '',
            'text'              => $addressText,            
        );

        $customer = array(
            'title'             => '',
            'first_name'        => $firstname,
            'middle_name'       => $middlename,
            'last_name'         => $lastname,
            'country'           => $country,
            'postcode'          => $postcode,
            'email'             => $email,
            'mobile_number'     => '',
            'phone_number'      => $telephone,
            'shippingAddress'   => $shippingAddress,
            'address'           => $address
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
            'response_url' => Mage::getUrl('pay/payment/webhook',array('_secure'=>true)),
            'checkout_url' => Mage::helper('checkout/url')->getCheckoutUrl(),
            'redirect_url' => Mage::getUrl('pay/payment/return', array('quote_id' => $quote_id)),
        );

        if (Mage::getStoreConfig('payment/pay/debug')) {
            Mage::log('Request: ' . json_encode($request_data), Zend_Log::DEBUG, 'divido.log', true);
        }

        $response = Divido_CreditRequest::create($request_data);

        if (Mage::getStoreConfig('payment/pay/debug')) {
            Mage::log('Response: ' . $response->__toJSON(), Zend_Log::DEBUG, 'divido.log', true);
        }

        if ($response->status == 'ok') {
            $lookup = Mage::getModel('callback/lookup');
            $lookup->setQuoteId($quote_id);
            $lookup->setSalt($salt);
            $lookup->setCreditRequestId($response->id);
            $lookup->setDepositAmount($deposit);
            $lookup->setTotalOrderAmount($grand_total);

            if ($existing_lookup_id) {
                $lookup->setId($existing_lookup_id);
            }
            $lookup->setCustomerCheckout($checkout_type);


            if (Mage::getStoreConfig('payment/pay/debug')) {
                Mage::log('Lookup: ' . json_encode($lookup->getData()), Zend_Log::DEBUG, 'divido.log', true);
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

        if (Mage::getStoreConfig('payment/pay/debug')) {
            Mage::log('Return, quote ID: ' . $quoteId, Zend_Log::DEBUG, 'divido.log', true);
        }

        $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

        $i=0;
        while($i < self::DIVIDO_WAIT_TIME) {
            $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $quoteId);
            if ($order->getId()) {
                if (Mage::getStoreConfig('payment/pay/debug')) {                    
                    Mage::log('already have order with id ' . $quoteId, Zend_Log::DEBUG, 'divido.log', true);
                }
                break;
            }else { 
                if (Mage::getStoreConfig('payment/pay/debug')) {                    
                    Mage::log('order not created waiting: ' . $quoteId, Zend_Log::DEBUG, 'divido.log', true);
                }        
                $i++;
                sleep(1);
            }
    
        }

        if ($orderId = $order->getId()) {
            $session->setLastOrderId($orderId)
                ->setLastRealOrderId($order->getIncrementId());
        }

        $this->_redirect('checkout/onepage/success');
    }

    public function webhookAction ()
    {

        $createStatus = self::STATUS_ACCEPTED;
        if (Mage::getStoreConfig('payment/pay/order_create_signed')) {
            $createStatus = self::STATUS_SIGNED;
        }

        $payload = file_get_contents('php://input');
        $this->debug('Update: ' . $payload);

        $secretEnc = Mage::getStoreConfig('payment/pay/secret');
        if (!empty($secretEnc)) {
            $reqSign = $this->getRequest()->getHeader('X-DIVIDO-HMAC-SHA256');
            $signature = Mage::helper('divido_pay')->createSignature($payload);
            if ($reqSign !== $signature) {
                $this->log("Invalid signature. Sent: {$reqSign}, Calculated: {$reqSign}");
                return $this->respond(false, 'invalid signature', true);
            }
        }

        $data = json_decode($payload);
        $this->quoteId = $data->metadata->quote_id;

        if ($data->event == 'proposal-new-session') {
            $this->debug("Proposal new session");

            return $this->respond(true, '');
        }

        $lookup = $this->getLookup($this->quoteId);
        if (! $lookup->getId()) {
            $this->log('Bad request, could not find lookup.');
            return $this->respond(false, 'no lookup', true);
        }

        $salt = $lookup->getSalt();
        $hash = Mage::helper('divido_pay')->hashQuote($salt, $this->quoteId);
        if ($hash !== $data->metadata->quote_hash) {
            $this->log('Bad request, mismatch in hash. Req: ' . $payload);
            return $this->respond(false, 'invalid hash', true);
        }

        // If we're cancelled or declined, log it and quit
        if (in_array($data->status, $this->noGo)) {
            $this->debug("Direct {$data->status}");

            if ($data->status == self::STATUS_DECLINED) {
                $lookup->setDeclined(1);
            } elseif ($data->status == self::STATUS_CANCELED) {
                $lookup->setCanceled(1);
            }

            $lookup->save();
            return $this->respond();
        }

        // Try to get quote
        $quote = Mage::getModel('sales/quote')->load($this->quoteId);
        if (! $quote->getId()) {
            $this->log("Could not find quote");
            return $this->respond(false, 'could not find quote');
        }

        // Try to get order
        $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $data->metadata->quote_id);

        // If no order exists and we're not at the order creation level, exit
        if (!$order->getId() && $data->status != $createStatus) {
            return $this->respond();
        }

        // If no order exists and we're AT the order creation level, create order
        if (!$order->getId() && $data->status == $createStatus) {
            $this->debug("Create order");

            // Convert quote to order
            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->collectTotals();
            $quote_service = Mage::getModel('sales/service_quote', $quote);

            $checkout_type=$lookup->getData('customer_checkout');
            
            //Customer type
            switch ($checkout_type) {
                case self::METHOD_GUEST:
                $this->debug("Prepare Guest Quote");
                    $this->_prepareGuestQuote($quote);
                    break;
                case self::METHOD_REGISTER:
                $this->debug("Prepare New Customer Quote");
                    $this->_prepareNewCustomerQuote($quote);
                    $isNewCustomer = true;
                    break;
                case self::METHOD_CUSTOMER:
                    $this->debug("Prepare  Customer Quote 1");
                        $this->_prepareCustomerQuote($quote);
                        break;
                case 'login_in':
                        $this->debug("Prepare Customer Quote 2");
                            $this->_prepareCustomerQuote($quote);
                            break;          
                default:
                $this->debug("Prepare guest Quote defualt");
                    $this->_prepareGuestQuote($quote);
                    break;
            }

            try {

                $quote_service->submitAll();
                $quote->save();

                $order = $quote_service->getOrder();
                if (! $order) {
                    throw new Exception("Order could not be created");
                }
            } catch (Exception $e) {
                Mage::logException($e);
                $this->log($e->getMessage());
                return $this->respond(false, $e->getMessage());
            }

            $order->setData('state', self::M_STATE_NEW);
            $order->setData('status', self::M_STATUS_PENDING);

            $this->debug('data application:'.$data->application);
            $lookup->setCreditApplicationId($data->application);
            $lookup->setOrderId($order->getId());
            $lookup->save();

            $this->debug("Created order.");
        }

        $orderId = $order->getId();


        $lookupTotalAmount = (float) $lookup->getData('total_order_amount');
        $orderGrandTotal = (float) $order->getGrandTotal();
        $amountsMatch = abs($lookupTotalAmount - $orderGrandTotal) < self::EPSILON;
        if (!$amountsMatch) {
            $this->log("Amount mismatch: Lookup: {$lookupTotalAmount}, Order: {$orderGrandTotal}");
        }

        if ($data->status === self::STATUS_SIGNED) {
            $this->debug("Signed");

            if ($amountsMatch) {
                $newStatus = self::M_STATUS_DEFAULT;
                if ($statusOverride = Mage::getStoreConfig('payment/pay/order_status')) {
                    $newStatus = $statusOverride;
                }
                $order->setData('status', $newStatus);
                $order->sendNewOrderEmail();
            } else {
                $order->setData('state', self::M_STATE_HOLDED);
                $order->setData('status', self::M_STATUS_HOLDED);

                $history = $order->addStatusHistoryComment("Divido: Credit amount does not match order amount.", false);
                $history->setIsCustomerNotified(false);
                $this->log("Holded order due to mismatch of order grand total and lookup grand total");
            }
        }

        if (isset($historyMessages[$data->status])) {
            $history = $order->addStatusHistoryComment("Divido: {$historyMessages[$data->status]}.", false);
            $history->setIsCustomerNotified(false);
        }

        $order->save();

        return $this->respond();

    }

    private function debug ($msg)
    {
        $debug = Mage::getStoreConfig('payment/pay/debug');
        if (! $debug) {
            return;
        }

        $this->log($msg, Zend_Log::DEBUG);
    }  

    private function log ($msg, $level = Zend_log::WARN)
    {
        if (empty($this->logId)) {
            $this->logId = (string) microtime(true);
        }

        $prefix = array($this->logId);

        if ($this->quoteId) {
            $prefix[] = "QuoteId: {$this->quoteId}";
        }

        if ($this->orderId) {
            $prefix[] = "OrderId: {$this->orderId}";
        }

        if ($prefix) {
            $msg = "[" . implode(', ', $prefix) . "] " . $msg;
        }

        Mage::log($msg, $level, self::LOG_FILE, true);
    }

    private function respond ($ok = true, $message = '', $bad_reqeust = false)
    {
        $response = $this->getResponse()
            ->clearHeaders()
            ->setHeader('Content-type','application/json', true);

        $pluginVersion = (string) Mage::getConfig()->getModuleConfig("Divido_Pay")->version;

        if ($ok) {
            $code = 200;
        } elseif ($bad_reqeust) {
            $code = 400;
        } else {
            $code = 500;
        }

        $status = $ok ? 'ok' : 'error';

        $response = array(
            'status'           => $status,
            'message'          => $message,
            'platform'         => 'Magento',
            'plugin_version'   => $pluginVersion,
        );

        $this->getResponse()
            ->setHttpResponseCode($code)
            ->setBody(json_encode($response));
    }

        /**
     * Prepare quote for guest checkout order submit
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    protected function _prepareGuestQuote($quote)
    {
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        return $this;
    }

    /**
     * Prepare quote for customer registration and customer order submit
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    protected function _prepareNewCustomerQuote($quote)
    {
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        //$customer = Mage::getModel('customer/customer');
        $customer = $quote->getCustomer();
        /* @var $customer Mage_Customer_Model_Customer */
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } else {
            $customerBilling->setIsDefaultShipping(true);
        }

        Mage::helper('core')->copyFieldset('checkout_onepage_quote', 'to_customer', $quote, $customer);
        $customer->setPassword($customer->decryptPassword($quote->getPasswordHash()));
        $quote->setCustomer($customer)
            ->setCustomerId(true);
    }

    /**
     * Prepare quote for customer order submit
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    protected function _prepareCustomerQuote($quote)
    {
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        //$customer = $this->getCustomerSession()->getCustomer();
        $customer = $quote->getCustomer();
        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }
        if ($shipping && !$shipping->getSameAsBilling() &&
            (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }
        if ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        } else if (isset($customerBilling) && !$customer->getDefaultShipping()) {
            $customerBilling->setIsDefaultShipping(true);
        }
        $quote->setCustomer($customer);
        ;
    }

    /**
     * Involve new customer to system
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    protected function _involveNewCustomer()
    {
        $customer = $this->getQuote()->getCustomer();
        if ($customer->isConfirmationRequired()) {
            $customer->sendNewAccountEmail('confirmation', '', $this->getQuote()->getStoreId());
            $url = Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail());
            $this->getCustomerSession()->addSuccess(
                Mage::helper('customer')->__('Account confirmation is required. Please, check your e-mail for confirmation link. To resend confirmation email please <a href="%s">click here</a>.', $url)
            );
        } else {
            $customer->sendNewAccountEmail('registered', '', $this->getQuote()->getStoreId());
            $this->getCustomerSession()->loginById($customer->getId());
        }
        return $this;
    }


    /**
     * Get customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    public function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }


        /**
     * Get frontend checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    /*
    public function getCheckout()
    {
        return $this->_checkoutSession;
    }
    */

    /**
     * Quote object getter
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if ($this->_quote === null) {
            return $this->_checkoutSession->getQuote();
        }
        return $this->_quote;
    }
}
