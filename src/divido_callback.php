<?php
require_once("app/Mage.php");
umask(0);

define('STORE',               1);
define('STATUS_ACCEPTED',     'ACCEPTED');
define('STATUS_DEPOSIT_PAID', 'DEPOSIT_PAID');
define('STATUS_DEFERRED',     'DEFERRED');
define('STATUS_SIGNED',       'SIGNED');
define('STATUS_FULLFILLED',   'FULLFILLED');

Mage::app('admin');

$history_messages = array(
    STATUS_ACCEPTED     => 'Credit request accepted',
    STATUS_DEPOSIT_PAID => 'Deposit paid',
    STATUS_DEFERRED     => 'Credit request deferred',
    STATUS_SIGNED       => 'Constract signed',
    STATUS_FULLFILLED   => 'Credit request fullfilled',
);

$data  = json_decode(file_get_contents('php://input'));
$store = Mage::getSingleton('core/store')->load(STORE);

if ($data->status === STATUS_ACCEPTED) {
    $quote = Mage::getModel('sales/quote')
        ->setStore($store)
        ->load($data->metadata->quote_id);

    // convert quote to order
    $quote->collectTotals()->save();
    $quote_service = Mage::getModel('sales/service_quote', $quote);
    $quote_service->submitAll();

    $order = $quote_service->getOrder();
    $order->setData('state', 'pending_payment');
    $order->setStatus("pending_payment");
} else {
    $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $data->metadata->quote_id);
}

if ($data->status === STATUS_FULLFILLED) {
    $order->setData('state', 'complete');
    $order->setStatus('complete');
}

$history = $order->addStatusHistoryComment("Divido: {$history_messages[$data->status]}.", false);
$history->setIsCustomerNotified(false);

$order->save();

print "ok";