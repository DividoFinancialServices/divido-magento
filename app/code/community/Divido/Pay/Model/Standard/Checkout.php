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
 * 
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * 
 */
class Divido_Pay_Model_Standard_Checkout
{
    /**
     * 
     * @var string
     */
    const PAL_CACHE_ID = 'pay_express_checkout_pal';

    /**
     * Keys for passthrough variables in sales/quote_payment and sales/order_payment
     * Uses additional_information as storage
     * @var string
     */
    const PAYMENT_INFO_TRANSPORT_SHIPPING_OVERRIDEN = 'pay_express_checkout_shipping_overriden';
    const PAYMENT_INFO_TRANSPORT_SHIPPING_METHOD = 'pay_express_checkout_shipping_method';
    const PAYMENT_INFO_TRANSPORT_PAYER_ID = 'pay_express_checkout_payer_id';
    const PAYMENT_INFO_TRANSPORT_REDIRECT = 'pay_express_checkout_redirect_required';
    const PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT = 'pay_ec_create_ba';

    /**
     * 
     * Uses additional_information as storage
     * @var string
     */
    const PAYMENT_INFO_BUTTON = 'button';

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * Config instance
     * 
     */
    protected $_config = null;

    /**
     * API instance
     * 
     */
    protected $_api = null;

    /**
     * Api Model Type
     *
     * @var string
     */
    protected $_apiType = 'pay/api_key';

    /**
     * Payment method type
     *
     * @var unknown_type
     */
    protected $_methodType = 'pay';

    /**
     * State helper variables
     * @var string
     */
    protected $_redirectUrl = '';
    protected $_pendingPaymentMessage = '';
    protected $_checkoutRedirectUrl = '';

    /**
     * @var Mage_Customer_Model_Session
     */
    protected $_customerSession;

    /**
     * Create Billing Agreement flag
     *
     * @var bool
     */
    protected $_isBARequested = false;

    /**
     * Flag for Bill Me Later mode
     *
     * @var bool
     */
    protected $_isBml = false;

    /**
     * Customer ID
     *
     * @var int
     */
    protected $_customerId = null;

    /**
     * Recurring payment profiles
     *
     * @var array
     */
    protected $_recurringPaymentProfiles = array();

    /**
     * Billing agreement that might be created during order placing
     *
     * @var Mage_Sales_Model_Billing_Agreement
     */
    protected $_billingAgreement = null;

    /**
     * Order
     *
     * @var Mage_Sales_Model_QuoteMage_Sales_Model_Quote
     */
    protected $_order = null;

    /**
     * Set quote and config instances
     * @param array $params
     */
    public function __construct($params = array())
    {
        if (isset($params['quote']) && $params['quote'] instanceof Mage_Sales_Model_Quote) {
            $this->_quote = $params['quote'];
        } else {
            throw new Exception('Quote instance is required.');
        }
        if (isset($params['config']) && $params['config'] instanceof Divido_Pay_Model_Config) {
            $this->_config = $params['config'];
        } else {
            throw new Exception('Config instance is required.');
        }

        $this->_customerSession = isset($params['session']) && $params['session'] instanceof Mage_Customer_Model_Session
            ? $params['session'] : Mage::getSingleton('customer/session');
    }

    /**
     * 
     * Spares API calls of getting "pal" variable, by putting it into cache per store view
     * @return string
     */
    public function getCheckoutShortcutImageUrl()
    {
        // get "pal" thing from cache or lookup it via API
        $pal = null;
        if ($this->_config->areButtonsDynamic()) {
            $cacheId = self::PAL_CACHE_ID . Mage::app()->getStore()->getId();
            $pal = Mage::app()->loadCache($cacheId);
            if (-1 == $pal) {
                $pal = null;
            } elseif (!$pal) {
                $pal = null;
                $this->_getApi();
                try {
                    $this->_api->callGetPalDetails();
                    $pal = $this->_api->getPal();
                    Mage::app()->saveCache($pal, $cacheId, array(Mage_Core_Model_Config::CACHE_TAG));
                } catch (Exception $e) {
                    Mage::app()->saveCache(-1, $cacheId, array(Mage_Core_Model_Config::CACHE_TAG));
                    Mage::logException($e);
                }
            }
        }

        return $this->_config->getExpressCheckoutShortcutImageUrl(
            Mage::app()->getLocale()->getLocaleCode(),
            $this->_quote->getBaseGrandTotal(),
            $pal
        );
    }

    /**
     * Set create billing agreement flag
     *
     * @param bool $flag
     * 
     */
    public function setIsBillingAgreementRequested($flag)
    {
        $this->_isBARequested = $flag;
        return $this;
    }

    /**
     * Setter for customer Id
     *
     * @param int $id
     * 
     * @deprecated please use self::setCustomer
     */
    public function setCustomerId($id)
    {
        $this->_customerId = $id;
        return $this;
    }

    /**
     * Set flag that forces to use BillMeLater
     *
     * @param bool $isBml
     */
    public function setIsBml($isBml)
    {
        $this->_isBml = $isBml;
    }

    /**
     * Setter for customer
     *
     * @param Mage_Customer_Model_Customer $customer
     * 
     */
    public function setCustomer($customer)
    {
        $this->_quote->assignCustomer($customer);
        $this->_customerId = $customer->getId();
        return $this;
    }

    /**
     * Setter for customer with billing and shipping address changing ability
     *
     * @param  Mage_Customer_Model_Customer   $customer
     * @param  Mage_Sales_Model_Quote_Address $billingAddress
     * @param  Mage_Sales_Model_Quote_Address $shippingAddress
     * 
     */
    public function setCustomerWithAddressChange($customer, $billingAddress = null, $shippingAddress = null)
    {
        $this->_quote->assignCustomerWithAddressChange($customer, $billingAddress, $shippingAddress);
        $this->_customerId = $customer->getId();
        return $this;
    }

    /**
     * Check whether system can skip order review page before placing order
     *
     * @return bool
     */
    public function canSkipOrderReviewStep()
    {
        $isOnepageCheckout = !$this->_quote->getPayment()
            ->getAdditionalInformation(Divido_Pay_Model_Standard_Checkout::PAYMENT_INFO_BUTTON);
        return $this->_config->isOrderReviewStepDisabled() && $isOnepageCheckout;
    }
    /**
     * Check whether order review has enough data to initialize
     *
     * 
     * @throws Mage_Core_Exception
     */
    public function prepareOrderReview()
    {
        $payment = $this->_quote->getPayment();
        if (!$payment || !$payment->getAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_PAYER_ID)) {
            Mage::throwException(Mage::helper('pay')->__('Payer is not identified.'));
        }
        $this->_quote->setMayEditShippingAddress(
            1 != $this->_quote->getPayment()->getAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_SHIPPING_OVERRIDEN)
        );
        $this->_quote->setMayEditShippingMethod(
            '' == $this->_quote->getPayment()->getAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_SHIPPING_METHOD)
        );
        $this->_ignoreAddressValidation();
        $this->_quote->collectTotals()->save();
    }

    /**
     * Return callback response with shipping options
     *
     * @param array $request
     * @return string
     */
    public function getShippingOptionsCallbackResponse(array $request)
    {
        // prepare debug data
        $logger = Mage::getModel('core/log_adapter', 'payment_' . $this->_methodType . '.log');
        $debugData = array('request' => $request, 'response' => array());

        try {
            // obtain addresses
            $this->_getApi();
            $address = $this->_api->prepareShippingOptionsCallbackAddress($request);
            $quoteAddress = $this->_quote->getShippingAddress();

            // compare addresses, calculate shipping rates and prepare response
            $options = array();
            if ($address && $quoteAddress && !$this->_quote->getIsVirtual()) {
                foreach ($address->getExportedKeys() as $key) {
                    $quoteAddress->setDataUsingMethod($key, $address->getData($key));
                }
                $quoteAddress->setCollectShippingRates(true)->collectTotals();
                $options = $this->_prepareShippingOptions($quoteAddress, false, true);
            }
            $response = $this->_api->setShippingOptions($options)->formatShippingOptionsCallback();

            // log request and response
            $debugData['response'] = $response;
            $logger->log($debugData);
            return $response;
        } catch (Exception $e) {
            $logger->log($debugData);
            throw $e;
        }
    }

    /**
     * Set shipping method to quote, if needed
     * @param string $methodCode
     */
    public function updateShippingMethod($methodCode)
    {
        if (!$this->_quote->getIsVirtual() && $shippingAddress = $this->_quote->getShippingAddress()) {
            if ($methodCode != $shippingAddress->getShippingMethod()) {
                $this->_ignoreAddressValidation();
                $shippingAddress->setShippingMethod($methodCode)->setCollectShippingRates(true);
                $this->_quote->collectTotals()->save();
            }
        }
    }

    /**
     * Place the order and recurring payment profiles when customer returned from pay
     * Until this moment all quote data must be valid
     *
     * 
     * @param string $shippingMethodCode
     */
    public function place($shippingMethodCode = null)
    {
        if ($shippingMethodCode) {
            $this->updateShippingMethod($shippingMethodCode);
        }

        $isNewCustomer = false;
        switch ($this->getCheckoutMethod()) {
            case Mage_Checkout_Model_Type_Onepage::METHOD_GUEST:
                $this->_prepareGuestQuote();
                break;
            case Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER:
                $this->_prepareNewCustomerQuote();
                $isNewCustomer = true;
                break;
            default:
                $this->_prepareCustomerQuote();
                break;
        }

        $this->_ignoreAddressValidation();
        $this->_quote->collectTotals();
        $service = Mage::getModel('sales/service_quote', $this->_quote);
        $service->submitAll();
        $this->_quote->save();

        if ($isNewCustomer) {
            try {
                $this->_involveNewCustomer();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        $this->_recurringPaymentProfiles = $service->getRecurringPaymentProfiles();
        // TODO: send recurring profile emails

        $order = $service->getOrder();
        if (!$order) {
            return;
        }
        $this->_billingAgreement = $order->getPayment()->getBillingAgreement();

        // commence redirecting to finish payment, if pay requires it
        if ($order->getPayment()->getAdditionalInformation(
                Divido_Pay_Model_Standard_Checkout::PAYMENT_INFO_TRANSPORT_REDIRECT
        )) {
            $this->_redirectUrl = $this->_config->getExpressCheckoutCompleteUrl();
        }

        switch ($order->getState()) {
            // even after placement pay can disallow to authorize/capture, but will wait until bank transfers money
            case Mage_Sales_Model_Order::STATE_PENDING_PAYMENT:
                // TODO
                break;
            // regular placement, when everything is ok
            case Mage_Sales_Model_Order::STATE_PROCESSING:
            case Mage_Sales_Model_Order::STATE_COMPLETE:
            case Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW:
                $order->sendNewOrderEmail();
                break;
        }
        $this->_order = $order;
    }

    /**
     * Make sure addresses will be saved without validation errors
     */
    private function _ignoreAddressValidation()
    {
        $this->_quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$this->_quote->getIsVirtual()) {
            $this->_quote->getShippingAddress()->setShouldIgnoreValidation(true);
            if (!$this->_config->requireBillingAddress && !$this->_quote->getBillingAddress()->getEmail()) {
                $this->_quote->getBillingAddress()->setSameAsBilling(1);
            }
        }
    }

    /**
     * Determine whether redirect somewhere specifically is required
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->_redirectUrl;
    }

    /**
     * Return recurring payment profiles
     *
     * @return array
     */
    public function getRecurringPaymentProfiles()
    {
        return $this->_recurringPaymentProfiles;
    }

    /**
     * Get created billing agreement
     *
     * @return Mage_Sales_Model_Billing_Agreement|null
     */
    public function getBillingAgreement()
    {
        return $this->_billingAgreement;
    }

    /**
     * Return order
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * Get checkout method
     *
     * @return string
     */
    public function getCheckoutMethod()
    {
        if ($this->getCustomerSession()->isLoggedIn()) {
            return Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER;
        }
        if (!$this->_quote->getCheckoutMethod()) {
            if (Mage::helper('checkout')->isAllowedGuestCheckout($this->_quote)) {
                $this->_quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
            } else {
                $this->_quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
            }
        }
        return $this->_quote->getCheckoutMethod();
    }

    /**
     * Sets address data from exported address
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param array $exportedAddress
     */
    protected function _setExportedAddressData($address, $exportedAddress)
    {
        // Exported data is more priority if we came from Express Checkout button
        $isButton  = (bool)$this->_quote->getPayment()->getAdditionalInformation(self::PAYMENT_INFO_BUTTON);
        if (!$isButton) {
            foreach ($exportedAddress->getExportedKeys() as $key) {
                $oldData = $address->getDataUsingMethod($key);
                $isEmpty = null;
                if (is_array($oldData)) {
                    foreach($oldData as $val) {
                        if(!empty($val)) {
                            $isEmpty = false;
                            break;
                        }
                        $isEmpty = true;
                    }
                }
                if (empty($oldData) || $isEmpty === true) {
                    $address->setDataUsingMethod($key, $exportedAddress->getData($key));
                }
            }
        } else {
            foreach ($exportedAddress->getExportedKeys() as $key) {
                $data = $exportedAddress->getData($key);
                if (!empty($data)) {
                    $address->setDataUsingMethod($key, $data);
                }
            }
        }
    }

    /**
     * Set create billing agreement flag to api call
     *
     * 
     */
    protected function _setBillingAgreementRequest()
    {
        if (!$this->_customerId || $this->_quote->hasNominalItems()) {
            return $this;
        }

        $isRequested = $this->_isBARequested || $this->_quote->getPayment()
            ->getAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT);

        if (!($this->_config->allow_ba_signup == Divido_Pay_Model_Config::EC_BA_SIGNUP_AUTO
            || $isRequested && $this->_config->shouldAskToCreateBillingAgreement())) {
            return $this;
        }

        if (!Mage::getModel('sales/billing_agreement')->needToCreateForCustomer($this->_customerId)) {
            return $this;
        }
        $this->_api->setBillingType($this->_api->getBillingAgreementType());
        return $this;
    }

    /**
     * 
     */
    protected function _getApi()
    {
        if (null === $this->_api) {
            $this->_api = Mage::getModel($this->_apiType)->setConfigObject($this->_config);
        }
        return $this->_api;
    }

    /**
     * Attempt to collect address shipping rates and return them for further usage in instant update API
     * Returns empty array if it was impossible to obtain any shipping rate
     * If there are shipping rates obtained, the method must return one of them as default.
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param bool $mayReturnEmpty
     * @return array|false
     */
    protected function _prepareShippingOptions(
        Mage_Sales_Model_Quote_Address $address,
        $mayReturnEmpty = false, $calculateTax = false
    ) {
        $options = array(); $i = 0; $iMin = false; $min = false;
        $userSelectedOption = null;

        foreach ($address->getGroupedAllShippingRates() as $group) {
            foreach ($group as $rate) {
                $amount = (float)$rate->getPrice();
                if ($rate->getErrorMessage()) {
                    continue;
                }
                $isDefault = $address->getShippingMethod() === $rate->getCode();
                $amountExclTax = Mage::helper('tax')->getShippingPrice($amount, false, $address);
                $amountInclTax = Mage::helper('tax')->getShippingPrice($amount, true, $address);

                $options[$i] = new Varien_Object(array(
                    'is_default' => $isDefault,
                    'name'       => trim("{$rate->getCarrier()} - {$rate->getMethodTitle()}", ' -'),
                    'code'       => $rate->getCode(),
                    'amount'     => $amountExclTax,
                ));
                if ($calculateTax) {
                    $options[$i]->setTaxAmount(
                        $amountInclTax - $amountExclTax
                            + $address->getTaxAmount() - $address->getShippingTaxAmount()
                    );
                }
                if ($isDefault) {
                    $userSelectedOption = $options[$i];
                }
                if (false === $min || $amountInclTax < $min) {
                    $min = $amountInclTax;
                    $iMin = $i;
                }
                $i++;
            }
        }

        if ($mayReturnEmpty && is_null($userSelectedOption)) {
            $options[] = new Varien_Object(array(
                'is_default' => true,
                'name'       => Mage::helper('pay')->__('N/A'),
                'code'       => 'no_rate',
                'amount'     => 0.00,
            ));
            if ($calculateTax) {
                $options[$i]->setTaxAmount($address->getTaxAmount());
            }
        } elseif (is_null($userSelectedOption) && isset($options[$iMin])) {
            $options[$iMin]->setIsDefault(true);
        }

        // Magento will transfer only first 10 cheapest shipping options if there are more than 10 available.
        if (count($options) > 10) {
            usort($options, array(get_class($this),'cmpShippingOptions'));
            array_splice($options, 10);
            // User selected option will be always included in options list
            if (!is_null($userSelectedOption) && !in_array($userSelectedOption, $options)) {
                $options[9] = $userSelectedOption;
            }
        }

        return $options;
    }

    /**
     * Compare two shipping options based on their amounts
     *
     * This function is used as a callback comparison function in shipping options sorting process
     * @see self::_prepareShippingOptions()
     *
     * @param Varien_Object $option1
     * @param Varien_Object $option2
     * @return integer
     */
    protected static function cmpShippingOptions(Varien_Object $option1, Varien_Object $option2)
    {
        if ($option1->getAmount() == $option2->getAmount()) {
            return 0;
        }
        return ($option1->getAmount() < $option2->getAmount()) ? -1 : 1;
    }

    /**
     * Try to find whether the code provided by divido corresponds to any of possible shipping rates
     * This method was created only because divido has issues with returning the selected code.
     * If in future the issue is fixed, we don't need to attempt to match it. It would be enough to set the method code
     * before collecting shipping rates
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param string $selectedCode
     * @return string
     */
    protected function _matchShippingMethodCode(Mage_Sales_Model_Quote_Address $address, $selectedCode)
    {
        $options = $this->_prepareShippingOptions($address, false);
        foreach ($options as $option) {
            if ($selectedCode === $option['code'] // the proper case as outlined in documentation
                || $selectedCode === $option['name'] // workaround: divido may return name instead of the code
                // workaround: divido may concatenate code and name, and return it instead of the code:
                || $selectedCode === "{$option['code']} {$option['name']}"
            ) {
                return $option['code'];
            }
        }
        return '';
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * 
     */
    protected function _prepareGuestQuote()
    {
        $quote = $this->_quote;
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        return $this;
    }

    /**
     * Checks if customer with email coming from Express checkout exists
     *
     * @return int
     */
    protected function _lookupCustomerId()
    {
        return Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($this->_quote->getCustomerEmail())
            ->getId();
    }

    /**
     * Prepare quote for customer registration and customer order submit
     * and restore magento customer data from quote
     *
     * 
     */
    protected function _prepareNewCustomerQuote()
    {
        $quote      = $this->_quote;
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customerId = $this->_lookupCustomerId();
        if ($customerId) {
            $this->getCustomerSession()->loginById($customerId);
            return $this->_prepareCustomerQuote();
        }

        $customer = $quote->getCustomer();
        /** @var $customer Mage_Customer_Model_Customer */
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } elseif ($shipping) {
            $customerBilling->setIsDefaultShipping(true);
        }
        /**
         * @todo integration with dynamica attributes customer_dob, customer_taxvat, customer_gender
         */
        if ($quote->getCustomerDob() && !$billing->getCustomerDob()) {
            $billing->setCustomerDob($quote->getCustomerDob());
        }

        if ($quote->getCustomerTaxvat() && !$billing->getCustomerTaxvat()) {
            $billing->setCustomerTaxvat($quote->getCustomerTaxvat());
        }

        if ($quote->getCustomerGender() && !$billing->getCustomerGender()) {
            $billing->setCustomerGender($quote->getCustomerGender());
        }

        Mage::helper('core')->copyFieldset('checkout_onepage_billing', 'to_customer', $billing, $customer);
        $customer->setEmail($quote->getCustomerEmail());
        $customer->setPrefix($quote->getCustomerPrefix());
        $customer->setFirstname($quote->getCustomerFirstname());
        $customer->setMiddlename($quote->getCustomerMiddlename());
        $customer->setLastname($quote->getCustomerLastname());
        $customer->setSuffix($quote->getCustomerSuffix());
        $customer->setPassword($customer->decryptPassword($quote->getPasswordHash()));
        $customer->setPasswordHash($customer->hashPassword($customer->getPassword()));
        $customer->save();
        $quote->setCustomer($customer);

        return $this;
    }

    /**
     * Prepare quote for customer order submit
     *
     * 
     */
    protected function _prepareCustomerQuote()
    {
        $quote      = $this->_quote;
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = $this->getCustomerSession()->getCustomer();
        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }
        if ($shipping && ((!$shipping->getCustomerId() && !$shipping->getSameAsBilling())
            || (!$shipping->getSameAsBilling() && $shipping->getSaveInAddressBook()))) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }
        if ($shipping && isset($customerBilling) && !$customer->getDefaultShipping() && $shipping->getSameAsBilling()) {
            $customerBilling->setIsDefaultShipping(true);
        } elseif ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        }
        $quote->setCustomer($customer);

        return $this;
    }

    /**
     * Involve new customer to system
     *
     * 
     */
    protected function _involveNewCustomer()
    {
        $customer = $this->_quote->getCustomer();
        if ($customer->isConfirmationRequired()) {
            $customer->sendNewAccountEmail('confirmation');
            $url = Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail());
            $this->getCustomerSession()->addSuccess(
                Mage::helper('customer')->__('Account confirmation is required. Please, check your e-mail for confirmation link. To resend confirmation email please <a href="%s">click here</a>.', $url)
            );
        } else {
            $customer->sendNewAccountEmail();
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
        return $this->_customerSession;
    }
}
