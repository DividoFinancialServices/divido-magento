<?php
require_once("app/Mage.php");
umask(0);

define('STORE',               1);
define('STATUS_ACCEPTED',     'ACCEPTED');
define('STATUS_DEPOSIT_PAID', 'DEPOSIT-PAID');
define('STATUS_DEFERRED',     'DEFERRED');
define('STATUS_SIGNED',       'SIGNED');
define('STATUS_FULFILLED',    'FULFILLED');

Mage::app('admin');

$history_messages = array(
    STATUS_ACCEPTED     => 'Credit request accepted',
    STATUS_DEPOSIT_PAID => 'Deposit paid',
    STATUS_DEFERRED     => 'Credit request deferred',
    STATUS_SIGNED       => 'Constract signed',
    STATUS_FULFILLED    => 'Credit request fulfilled',
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
    $quote->collectTotals()->save();
    $quote_service = Mage::getModel('sales/service_quote', $quote);
    $quote_service->submitAll();

    $order = $quote_service->getOrder();
    $order->setData('state', 'pending_payment');
    $order->setStatus('pending_payment');
}

if ($data->status === STATUS_FULFILLED) {
    $order->setData('state', 'complete');
    $order->setStatus('complete');
}

$history = $order->addStatusHistoryComment("Divido: {$history_messages[$data->status]}.", false);
$history->setIsCustomerNotified(false);

$order->save();

print "ok";
