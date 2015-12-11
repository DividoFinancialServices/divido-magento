<?php


class Divido_Pay_PaymentController extends Divido_Pay_Controller_Payment_Abstract
{
    // Redirect to instamojo 
    private $LOG_FILE_NAME = 'divido.log';
    protected $_configType = 'pay/config';

    /**
     * Config method type
     *
     * @var string
     */
    protected $_configMethod = 'pay';

    /**
     * Checkout mode type
     *
     * @var string
     */
    protected $_checkoutType = 'pay/standard_checkout';
    
    
    public function redirectAction()
    {
        die('asd');
        try {
            Mage::Log('Step 5 Process: Loading the redirect.html page', Zend_Log::DEBUG, $this->LOG_FILE_NAME);
            $this->loadLayout();
            // Get latest order data
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            

            // Set status to payment pending
            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true)->save();
            $amount = $order-> getBaseGrandTotal();
            $email = $order->getCustomerEmail();
            $name = $order->getCustomerName();          
            $phone = $order->getBillingAddress()->getTelephone();
            $rmTranid = time();

            $index = strpos($amount, '.');
            if ($index !== False){
                $amount = substr($amount, 0, $index+3);  
            }
            
//            $url = Mage::getStoreConfig('payment/imojo/payment_url');
            $api_key = Mage::getStoreConfig('payment/pay/api_key');
//            $auth_token = Mage::getStoreConfig('payment/imojo/auth_token');
//            $private_salt = Mage::getStoreConfig('payment/imojo/private_salt');
//            $custom_field = Mage::getStoreConfig('payment/imojo/custom_field');

            // Mage::helper('imojo')->myLog("Data from Backend: $url | $api_key | $auth_token | $private_salt | $custom_field"); 
            Mage::log("Data from Backend: $url | $api_key | $auth_token | $private_salt | $custom_field", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

            $data = Array();
            $data['data_email'] = substr($email, 0, 75);
            $data['data_name'] = substr($name, 0, 20);
            $data['data_phone'] = substr($phone, 0, 20);
            $data['data_amount'] = $amount;
            $data['data_' . $custom_field] = $rmTranid . "-". $orderId;

            // Mage::helper('imojo')->myLog("Transaction-order ID: " . ($rmTranid . "-". $orderId));

            Mage::log("Transaction-order ID: " . ($rmTranid . "-". $orderId), Zend_Log::DEBUG, $this->LOG_FILE_NAME);

            $ver = explode('.', phpversion());
            $major = (int) $ver[0];
            $minor = (int) $ver[1];
            if($major >= 5 and $minor >= 4){
                ksort($data, SORT_STRING | SORT_FLAG_CASE);
            }
            else{
                uksort($data, 'strcasecmp');
            }

            $message = implode('|', $data);
            $sign = hash_hmac("sha1", $message, $private_salt);
            $data['data_sign'] = $sign;
            
            // Mage::helper('imojo')->myLog("Signature is: $sign");
            Mage::log("Signature is: $sign", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

            $link= $url . "?embed=form&";
            $link .= "data_readonly=data_email&data_readonly=data_amount&data_readonly=data_phone&data_readonly=data_name&data_readonly=data_$custom_field&data_hidden=data_$custom_field";
            $link.="&data_amount=$amount&data_name=$name&data_email=$email&data_phone=$phone&data_$custom_field=$custom_field&data_sign=$sign";
            $payment = $order->getPayment();
            $payment->setTransactionId($rmTranid); // Make it unique.

            $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
                                                    null,
                                                    false,
                                                    'I am good');
            $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                                                   array('Context'=>'Token payment',
                                                         'Amount'=>$amount,
                                                         'Status'=>0,
                                                         'Url'=>$link));
            $transaction->setIsTransactionClosed(false); // Close the transaction on return?
            $transaction->save();
            $order->save();

			
			
			
            $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'imojo', array('template' => 'imojo/redirect.phtml'))
                          ->assign(array_merge($data, array('url'=>$url, 'custom_field_name'=>'data_' . $custom_field)));
            $this->getLayout()->getBlock('content')->append($block);
            $this->renderLayout();
        } catch (Exception $e){
            Mage::logException($e);
            Mage::log($e, Zend_Log::ERR, $this->LOG_FILE_NAME);
            parent::_redirect('checkout/cart');
        }
    }

    // Redirect from Instamojo
    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function responseAction() {
        
        Mage::log("Running response action", Zend_Log::DEBUG, $this->LOG_FILE_NAME); 

        $custom_field = Mage::getStoreConfig('payment/imojo/custom_field');
        $status = $this->getRequest()->getParam('status');
        $insta_id = $this->getRequest()->getParam('payment_id');
        $this->loadLayout();

        Mage::log("Status: $status| Payment ID: $insta_id", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
        // Do curl here to get order id and information from instamojo;
        $data = $this->_getcurlInfo($insta_id);
        $payment_status = $data['payment']['status'];
        
        // What if Id or status doesnt exit?
        if($payment_status === "Credit")
		{
            Mage::log("Payment was successfull for $insta_id", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
            $tr_ord_id = $data['payment']['custom_fields'][$custom_field]['value'];
            Mage::log("Value of Tran-order ID: $tr_ord_id", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
            $order_tran_id = explode('-',  $tr_ord_id);
            $transactionId = $order_tran_id[0];
            $orderId = $order_tran_id[1];
            $amount = $data['payment']['amount'];

            // Get order details
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($orderId);
            $this->_processInvoice($orderId);
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, false)->save();
            Mage::log("Pending payment is set to False", Zend_Log::DEBUG, 'imojo.log');
            $order->sendNewOrderEmail();
            $order->setEmailSent(true);

            // Close the transaction
            $payment = $order->getPayment();
            $transaction = $payment->getTransaction($transactionId);  
            $data = $transaction->getAdditionalInformation();
            $url = $data['raw_details_info']['Url'];
            $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                                array('InstmojoId'=> $insta_id,
                                      'Context'=>'Token payment',
                                      'Amount'=>$amount,
                                      'Status'=>1,
                                      'Url'=>$url))->save();
            $transaction->setParentTxnId($insta_id)->save();
            $payment->setIsTransactionClosed(1);
            // $this->_processInvoice($orderId);
            $block = $this->getLayout()->createBlock('Mage_Core_Block_Template',
                                                     'imojo',
                                                     array('template' => 'imojo/success.phtml'))->assign(array('instaId'=> $insta_id));   
            // Curl fetch status information to cross compare
        }
		else
		{
            $block = $this->getLayout()->createBlock('Mage_Core_Block_Template',
                                                     'imojo',
                                                     array('template' => 'imojo/failure.phtml'))->assign(array('instaId'=> $insta_id));      
        }
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
       
        // Trigger emails for success / failure - Sending desposit email
        // $this->depositEmail($orderId);

        // Trigger a order invoice after that?
        
    }

    private function _processInvoice($orderId){

        $order = Mage::getModel("sales/order")->load($orderId);

        try {
            if(!$order->canInvoice())
            {
            Mage::throwException(Mage::helper("core")->__("Cannot create an invoice"));
            }
            $invoice = Mage::getModel("sales/service_order", $order)->prepareInvoice();
            if (!$invoice->getTotalQty()) {
            Mage::throwException(Mage::helper("core")->__("Cannot create an invoice without products."));
            }
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $transactionSave = Mage::getModel("core/resource_transaction")
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
            $transactionSave->save();
            $invoice->sendEmail();

            /* SET Order Status Here */
            $orderModel = Mage::getModel("sales/order");
            $orderModel->load($orderId);
            $orderModel->setStatus("complete")
            ->save();

        }
        catch (Mage_Core_Exception $e) {
            echo $e->getMessage();
        }

    }

    // Get the order id from Instamojo based the transaction id
    private function _getcurlInfo($iTransactionId){
         try {

            $cUrl = 'https://www.instamojo.com/api/1.1/payments/' . $iTransactionId;
            $api_key = Mage::getStoreConfig('payment/imojo/api_key');
            $auth_token = Mage::getStoreConfig('payment/imojo/auth_token');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $cUrl);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Api-Key:$api_key",
                                                       "X-Auth-Token:$auth_token"));
            $response = curl_exec($ch);			
            $res = json_decode($response, true);
            curl_close($ch);   
        } catch (Exception $e)
		{
            Mage::logException($e);
            Mage::log($e, Zend_Log::ERR, $this->LOG_FILE_NAME);
            throw $e;
        }
        return $res;
    }

} 
