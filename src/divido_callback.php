<?php
require_once("app/Mage.php");
umask(0);

define('STORE',               1);
define('STATUS_ACCEPTED',     'ACCEPTED');
define('STATUS_CANCELED',     'CANCELED');
define('STATUS_COMPLETED',    'COMPLETED');
define('STATUS_DEFERRED',     'DEFERRED');
define('STATUS_DECLINED',     'DECLINED');
define('STATUS_DEPOSIT_PAID', 'DEPOSIT-PAID');
define('STATUS_FULFILLED',    'FULFILLED');
define('STATUS_REFERRED',     'REFERRED');
define('STATUS_SIGNED',       'SIGNED');

Mage::app('admin');

$history_messages = array(
    STATUS_ACCEPTED     => 'Credit request accepted',
    STATUS_CANCELED     => 'Application canceled',
    STATUS_COMPLETED    => 'Application completed',
    STATUS_DEFERRED     => 'Application deferred by Underwriter, waiting for new status',
    STATUS_DECLINED     => 'Applicaiton declined by Underwriter',
    STATUS_DEPOSIT_PAID => 'Deposit paid by customer',
    STATUS_FULFILLED    => 'Credit request fulfilled',
    STATUS_REFERRED     => 'Credit request referred by Underwriter, waiting for new status',
    STATUS_SIGNED       => 'Customer have signed all contracts',
);

$data  = json_decode(file_get_contents('php://input'));
$store = Mage::getSingleton('core/store')->load(STORE);

Mage::log('Divido request: ' . serialize($data), null, 'divido.log');

$lookup = Mage::getModel('callback/lookup');
$lookup->load($data->metadata->quote_id, 'quote_id');
if (! $lookup->getId()) {
    Mage::log('Bad request, could not find lookup. Req: ' . serialize($data), null, 'divido.log');
    exit('Cannot verify request');
}

$salt = $lookup->getSalt();
$hash = Mage::helper('pay')->hashQuote($salt, $data->metadata->quote_id);
if ($hash !== $data->metadata->quote_hash) {
    Mage::log('Bad request, mismatch in hash. Req: ' . serialize($data), null, 'divido.log');
    exit('Cannot verify request');
}

$order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $data->metadata->quote_id);

if (! $order->getId()) {
    $quote = Mage::getModel('sales/quote')
        ->setStore($store)
        ->load($data->metadata->quote_id);

    // convert quote to order
    $quote->collectTotals();
    $quote_service = Mage::getModel('sales/service_quote', $quote);
    $quote_service->submitAll();
    $quote->save();

    $order = $quote_service->getOrder();
    $order->setData('state', 'new');
    $order->setStatus('pending_payment');
}

if ($data->status === STATUS_FULFILLED) {
    $order->setData('state', 'complete');
    $order->setStatus('complete');
    $order->queueNewOrderEmail();
}

if (isset($history_messages[$data->status])) {
    $history = $order->addStatusHistoryComment("Divido: {$history_messages[$data->status]}.", false);
    $history->setIsCustomerNotified(false);
}

$order->save();

print "ok";
