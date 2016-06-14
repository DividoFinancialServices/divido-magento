<?php
require_once("app/Mage.php");
umask(0);

Mage::app('admin');

define('STORE',                1);

define('NEW_STATUS',           'processing');

define('STATUS_ACCEPTED',      'ACCEPTED');
define('STATUS_ACTION_LENDER', 'ACTION-LENDER');
define('STATUS_CANCELED',      'CANCELED');
define('STATUS_COMPLETED',     'COMPLETED');
define('STATUS_DEFERRED',      'DEFERRED');
define('STATUS_DECLINED',      'DECLINED');
define('STATUS_DEPOSIT_PAID',  'DEPOSIT-PAID');
define('STATUS_FULFILLED',     'FULFILLED');
define('STATUS_REFERRED',      'REFERRED');
define('STATUS_SIGNED',        'SIGNED');

$historyMessages = array(
    STATUS_ACCEPTED      => 'Credit request accepted',
    STATUS_ACTION_LENDER => 'Lender notified',
    STATUS_CANCELED      => 'Application canceled',
    STATUS_COMPLETED     => 'Application completed',
    STATUS_DEFERRED      => 'Application deferred by Underwriter, waiting for new status',
    STATUS_DECLINED      => 'Applicaiton declined by Underwriter',
    STATUS_DEPOSIT_PAID  => 'Deposit paid by customer',
    STATUS_FULFILLED     => 'Credit request fulfilled',
    STATUS_REFERRED      => 'Credit request referred by Underwriter, waiting for new status',
    STATUS_SIGNED        => 'Customer have signed all contracts',
);

function exitWithVersion() {
    $version = Mage::getConfig()->getModuleConfig("Divido_Pay")->version;
    exit("M1-{$version}");
}

$noGo = array(STATUS_CANCELED, STATUS_DECLINED);

$payload = file_get_contents('php://input');
if (Mage::getStoreConfig('payment/pay/debug')) {
    Mage::log('Update: ' . $payload, Zend_Log::DEBUG, 'divido.log', true);
}
$data = json_decode($payload);
$quoteId = $data->metadata->quote_id;

if ($data->event == 'proposal-new-session') {
    if (Mage::getStoreConfig('payment/pay/debug')) {
        Mage::log("[Quote: {$quoteId}] Proposal new session", Zend_Log::DEBUG, 'divido.log', true);
    }
    exitWithVersion();
}
$store = Mage::getSingleton('core/store')->load(STORE);

$lookup = Mage::getModel('callback/lookup');
$lookup->load($data->metadata->quote_id, 'quote_id');
if (! $lookup->getId()) {
    Mage::log('Bad request, could not find lookup. Req: ' . $payload, Zend_Log::WARN, 'divido.log');
    exit('Can not verify request');
}

$salt = $lookup->getSalt();
$hash = Mage::helper('pay')->hashQuote($salt, $data->metadata->quote_id);
if ($hash !== $data->metadata->quote_hash) {
    Mage::log('Bad request, mismatch in hash. Req: ' . $payload, Zend_Log::WARN, 'divido.log');
    exit('Can not verify request');
}

if (isset($data->application)) {
    $lookup->setCreditApplicationId($data->application);
    $lookup->save();
    if (Mage::getStoreConfig('payment/pay/debug')) {
        Mage::log("[Quote: {$quoteId}] Lookup: " . json_encode($lookup->getData()), Zend_Log::DEBUG, 'divido.log', true);
    }
}

$order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $data->metadata->quote_id);

if (! $order->getId() && in_array($data->status, $noGo)) {
    if (Mage::getStoreConfig('payment/pay/debug')) {
        Mage::log("[Quote: {$quoteId}] Direct {$data->status}", Zend_Log::DEBUG, 'divido.log', true);
    }

    if ($data->status == STATUS_DECLINED) {
        $lookup->setDeclined(1);
    } elseif ($data->status == STATUS_CANCELED) {
        $lookup->setCanceled(1);
    }

    $lookup->save();
    exitWithVersion();
}

if ($order->getId() && $data->status === STATUS_DECLINED) {
    if (Mage::getStoreConfig('payment/pay/debug')) {
        Mage::log("[Quote: {$quoteId}] DECLINED after REFERRED", Zend_Log::DEBUG, 'divido.log', true);
    }

    $history = $order->addStatusHistoryComment("Divido: {$historyMessages[$data->status]}.", false);

    $order->cancel();
    $order->save();

    $lookup->setDeclined(1);
    $lookup->save();

    exitWithVersion();
}

if (Mage::getStoreConfig('payment/pay/order_create_signed') && $data->status != STATUS_SIGNED) {
    exitWithVersion();
}

if (! $order->getId()) {
    if (Mage::getStoreConfig('payment/pay/debug')) {
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
if (Mage::getStoreConfig('payment/pay/debug')) {
    Mage::log("[Quote: {$quoteId}] Lookup: " . json_encode($lookup->getData()), Zend_Log::DEBUG, 'divido.log', true);
}

/*
if ($data->status === STATUS_DEPOSIT_PAID) {
    $order->setTotalPaid($lookup->getDepositAmount());
}
*/

if ($data->status === STATUS_SIGNED) {
    if (Mage::getStoreConfig('payment/pay/debug')) {
        Mage::log("[Quote: {$quoteId}] Signed", Zend_Log::DEBUG, 'divido.log', true);
    }

    $newStatus = NEW_STATUS;
    if ($statusOverride = $apiKey = Mage::getStoreConfig('payment/pay/order_status')) {
        $newStatus = $statusOverride;
    }
    $order->setData('status', $newStatus);
    $order->queueNewOrderEmail();
}

if (isset($historyMessages[$data->status])) {
    $history = $order->addStatusHistoryComment("Divido: {$historyMessages[$data->status]}.", false);
    $history->setIsCustomerNotified(false);
}

$order->save();

exitWithVersion();
