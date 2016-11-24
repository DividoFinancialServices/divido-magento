<?php
require_once(Mage::getBaseDir('lib') . '/Divido/Divido.php'); 
class Divido_Pay_PaymentController extends Mage_Core_Controller_Front_Action
{

    const
        NEW_STATUS           = 'processing',
        STATUS_ACCEPTED      = 'ACCEPTED',
        STATUS_ACTION_LENDER = 'ACTION-LENDER',
        STATUS_CANCELED      = 'CANCELED',
        STATUS_COMPLETED     = 'COMPLETED',
        STATUS_DEFERRED      = 'DEFERRED',
        STATUS_DECLINED      = 'DECLINED',
        STATUS_DEPOSIT_PAID  = 'DEPOSIT-PAID',
        STATUS_FULFILLED     = 'FULFILLED',
        STATUS_REFERRED      = 'REFERRED',
        STATUS_SIGNED        = 'SIGNED';

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

        $shipAddr   = $quote_session->getShippingAddress();
        $shipping   = $shipAddr->getData();
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
            $item_qty   = $item->getQty();
            $item_value = $item->getPrice();

            $products[] = array(
                "type"     => "product",
                "text"     => $item->getName(),
                "quantity" => $item_qty,
                "value"    => $item_value,
            );
        }

        $totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();
        $grand_total = $totals['grand_total']->getValue();

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
            'response_url' => Mage::getUrl('pay/payment/webhook'),
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

            $existing_lookup = Mage::getModel('callback/lookup')->load($quote_id, 'quote_id');
            if ($existing_lookup->getId()) {
                $lookup->setId($existing_lookup->getId());
            }

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

        $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $quoteId);
        if ($orderId = $order->getId()) {
            $session->setLastOrderId($orderId)
                ->setLastRealOrderId($order->getIncrementId());
        }

        $this->_redirect('checkout/onepage/success');
    }

    public function webhookAction ()
    {
        $debug = Mage::getStoreConfig('payment/pay/debug');

        $createStatus = self::STATUS_ACCEPTED;
        if (Mage::getStoreConfig('payment/pay/order_create_signed')) {
            $createStatus = STATUS_SIGNED;
        }

        $payload = file_get_contents('php://input');
        if ($debug) {
            Mage::log('Update: ' . $payload, Zend_Log::DEBUG, 'divido.log', true);
        }
        $data = json_decode($payload);
        $quoteId = $data->metadata->quote_id;

        xdebug_break();

        if ($data->event == 'proposal-new-session') {
            if ($debug) {
                Mage::log("[Quote: {$quoteId}] Proposal new session", Zend_Log::DEBUG, 'divido.log', true);
            }

            return $this->respond(true, '', false);
        }

        $lookup = Mage::getModel('callback/lookup');
        $lookup->load($data->metadata->quote_id, 'quote_id');
        if (! $lookup->getId()) {
            Mage::log('Bad request, could not find lookup. Req: ' . $payload, Zend_Log::WARN, 'divido.log');
            return $this->respond(false, 'no lookup', false);
        }

        $salt = $lookup->getSalt();
        $hash = Mage::helper('pay')->hashQuote($salt, $data->metadata->quote_id);
        if ($hash !== $data->metadata->quote_hash) {
            Mage::log('Bad request, mismatch in hash. Req: ' . $payload, Zend_Log::WARN, 'divido.log');
            return $this->respond(false, 'invalid hash', false);
        }

        // Update Lookup with application ID
        if (isset($data->application)) {
            $lookup->setCreditApplicationId($data->application);
            $lookup->save();
            if ($debug) {
                Mage::log("[Quote: {$quoteId}] Lookup: " . json_encode($lookup->getData()), Zend_Log::DEBUG, 'divido.log', true);
            }
        }

        // If we're cancelled or declined, log it and quit
        if (in_array($data->status, $this->noGo)) {
            if ($debug) {
                Mage::log("[Quote: {$quoteId}] Direct {$data->status}", Zend_Log::DEBUG, 'divido.log', true);
            }

            if ($data->status == self::STATUS_DECLINED) {
                $lookup->setDeclined(1);
            } elseif ($data->status == self::STATUS_CANCELED) {
                $lookup->setCanceled(1);
            }

            $lookup->save();
            return $this->respond();
        }

        // Try to get order
        $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $data->metadata->quote_id);

        // If no order exists and we're not at the order creation level, exit
        if (!$order->getId() && $data->status != $createStatus) {
            return $this->respond();
        }

        // If no order exists and we're AT the order creation level, create order
        if (!$order->getId() && $data->status == $createStatus) {
            if ($debug) {
                Mage::log("[Quote: {$quoteId}] Create order", Zend_Log::DEBUG, 'divido.log', true);
            }

            $quote = Mage::getModel('sales/quote')
                ->setStore($store)
                ->load($data->metadata->quote_id);

            // Convert quote to order
            $quote->collectTotals();
            $quote_service = Mage::getModel('sales/service_quote', $quote);
            $quote_service->submitAll();
            $quote->save();

            $order = $quote_service->getOrder();
            $order->setData('state', 'new');
            $order->setData('status', 'pending');
        }

        $lookup->setOrderId($order->getId());
        $lookup->save();
        if ($debug) {
            Mage::log("[Quote: {$quoteId}] Lookup: " . json_encode($lookup->getData()), Zend_Log::DEBUG, 'divido.log', true);
        }

        if ($data->status === self::STATUS_SIGNED) {
            if ($debug) {
                Mage::log("[Quote: {$quoteId}] Signed", Zend_Log::DEBUG, 'divido.log', true);
            }

            $newStatus = self::NEW_STATUS;
            if ($statusOverride = Mage::getStoreConfig('payment/pay/order_status')) {
                $newStatus = $statusOverride;
            }
            $order->setData('status', $newStatus);
            $order->sendNewOrderEmail();
        }

        if (isset($historyMessages[$data->status])) {
            $history = $order->addStatusHistoryComment("Divido: {$historyMessages[$data->status]}.", false);
            $history->setIsCustomerNotified(false);
        }

        $order->save();

        return $this->respond();

    }

    private function respond ($ok = true, $message = '') {
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json', true);

        $pluginVersion = (string) Mage::getConfig()->getModuleConfig("Divido_Pay")->version;
        $status = $ok ? 'ok' : 'error';

        $response = array(
            'status'           => $status,
            'message'          => $message,
            'platform'         => 'Magento',
            'plugin_version'   => $pluginVersion,
        );

        $this->getResponse()->setBody(json_encode($response));
    }
}
