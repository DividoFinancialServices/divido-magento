<?php
require_once("app/Mage.php");
umask(0);

Mage::app('admin');

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

$historyMessages = array(
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

$noGo = array(STATUS_CANCELED, STATUS_DECLINED);

$data  = json_decode(file_get_contents('php://input'));
$store = Mage::getSingleton('core/store')->load(STORE);

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

$lookup->setCreditApplicationId($data->application);
$lookup->save();

$order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $data->metadata->quote_id);

if (! $order->getId() && in_array($data->status, $noGo)) {
    Mage::log("Quote: {$data->metadata->quote_id}, Status: {$data->status}", null, 'divido.log');
    exit('ok');
}

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
    //$order->setStatus('pending_payment');
}

/*
if ($data->status === STATUS_DEPOSIT_PAID) {
    $order->setTotalPaid($lookup->getDepositAmount());
}
*/

if ($data->status === STATUS_SIGNED) {
    $order->setData('state', 'processing');
    $order->queueNewOrderEmail();
}

if (isset($historyMessages[$data->status])) {
    $history = $order->addStatusHistoryComment("Divido: {$historyMessages[$data->status]}.", false);
    $history->setIsCustomerNotified(false);
}

$order->save();

echo "ok";
