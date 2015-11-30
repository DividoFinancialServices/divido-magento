<?php
require_once ("app/Mage.php");
ini_set("error_reporting",E_ALL);
ini_set("display_errors",true);
umask(0);
Mage::app('admin');
ob_start();
print_r(json_decode(file_get_contents('php://input')));
$obj = json_decode(file_get_contents('php://input'));
$status = $obj->status;
$content= ob_get_contents();
if($status == 'SIGNED'){
$order = Mage::getModel('sales/order')->loadByIncrementId(100000034);
        $order->setData('state', "complete");
        $order->setStatus("complete");       
        $history = $order->addStatusHistoryComment('Order was set to Complete by our automation tool.', false);
        $history->setIsCustomerNotified(false);
        $order->save();

}
ob_end_clean();
mail("brstdev@gmail.com","Divido Callback",$status,"From:info@divido.com");
print "ok";

?>