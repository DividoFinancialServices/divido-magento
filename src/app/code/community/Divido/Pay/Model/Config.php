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

 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * 
 * 
 */
class Divido_Pay_Model_Config
{
    /**
     * 
     * @var string
     */
    const METHOD_WPS         = 'pay_standard';

    /**
     * 
     * @var string
     */
    const METHOD_WPP_EXPRESS = 'pay';

    /**
     * Payflow Pro Gateway
     * @var string
     */
    const METHOD_PAYFLOWPRO         = 'verisign';

    const METHOD_PAYFLOWLINK        = 'payflow_link';
    const METHOD_PAYFLOWADVANCED    = 'payflow_advanced';

    const METHOD_HOSTEDPRO          = 'hosted_pro';




    const DEFAULT_LOGO_TYPE = 'wePrefer_150x60';

    /**
     * Payment actions
     * @var string
     */
    const PAYMENT_ACTION_SALE  = 'Sale';
    const PAYMENT_ACTION_ORDER = 'Order';
    const PAYMENT_ACTION_AUTH  = 'Authorization';

    /**
     * Authorization amounts for Account Verification
     *
     * @deprecated since 1.6.2.0
     * @var int
     */
    const AUTHORIZATION_AMOUNT_ZERO = 0;
    const AUTHORIZATION_AMOUNT_ONE = 1;
    const AUTHORIZATION_AMOUNT_FULL = 2;

    /**
     * Require Billing Address
     * @var int
     */
    const REQUIRE_BILLING_ADDRESS_NO = 0;
    const REQUIRE_BILLING_ADDRESS_ALL = 1;
    const REQUIRE_BILLING_ADDRESS_VIRTUAL = 2;

    /**
     * Fraud management actions
     * @var string
     */
    const FRAUD_ACTION_ACCEPT = 'Acept';
    const FRAUD_ACTION_DENY   = 'Deny';

    /**
     * Refund types
     * @var string
     */
    const REFUND_TYPE_FULL = 'Full';
    const REFUND_TYPE_PARTIAL = 'Partial';

    /**
     * Express Checkout flows
     * @var string
     */
    const EC_SOLUTION_TYPE_SOLE = 'Sole';
    const EC_SOLUTION_TYPE_MARK = 'Mark';

    /**
     * Payment data transfer methods (Standard)
     *
     * @var string
     */
    const WPS_TRANSPORT_IPN      = 'ipn';
    const WPS_TRANSPORT_PDT      = 'pdt';
    const WPS_TRANSPORT_IPN_PDT  = 'ipn_n_pdt';

    /**
     * Billing Agreement Signup
     *
     * @var string
     */
    const EC_BA_SIGNUP_AUTO     = 'auto';
    const EC_BA_SIGNUP_ASK      = 'ask';
    const EC_BA_SIGNUP_NEVER    = 'never';
     /**
     * Current payment method code
     * @var string
     */
    protected $_methodCode = null;

    /**
     * Current store id
     *
     * @var int
     */
    protected $_storeId = null;

    /**
     * Currency codes supported by divido methods
     *
     * @var array
     */
    protected $_supportedCurrencyCodes = array('GBP');

    /**
     * Merchant country supported by divido
     *
     * @var array
     */
    protected $_supportedCountryCodes = array('GB');

    /**
     * Buyer country supported by divido
     *
     * @var array
     */
    protected $_supportedBuyerCountryCodes = array( 'GB '  );

    /**
     * Locale codes supported by misc images (marks, shortcuts etc)
     *
     * @var array
     * 
     */
    protected $_supportedImageLocales = array('en_GB');

    /**
     * Set method and store id, if specified
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        if ($params) {
            $method = array_shift($params);
            $this->setMethod($method);
            if ($params) {
                $storeId = array_shift($params);
                $this->setStoreId($storeId);
            }
        }
    }

    /**
     * Method code setter
     *
     * @param string|Mage_Payment_Model_Method_Abstract $method
     * 
     */
    public function setMethod($method)
    {
        if ($method instanceof Mage_Payment_Model_Method_Abstract) {
            $this->_methodCode = $method->getCode();
        } elseif (is_string($method)) {
            $this->_methodCode = $method;
        }
        return $this;
    }

    /**
     * Payment method instance code getter
     *
     * @return string
     */
    public function getMethodCode()
    {
        return $this->_methodCode;
    }

    /**
     * Store ID setter
     *
     * @param int $storeId
     * 
     */
    public function setStoreId($storeId)
    {
        $this->_storeId = (int)$storeId;
        return $this;
    }

    /**
     * Check whether method active in configuration and supported for merchant country or not
     *
     * @param string $method Method code
     * @return bool
     */
    public function isMethodActive($method)
    {
        if ($this->isMethodSupportedForCountry($method)
            && Mage::getStoreConfigFlag("payment/{$method}/active", $this->_storeId)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Check whether method available for checkout or not
     * Logic based on merchant country, methods dependence
     *
     * @param string $method Method code
     * @return bool
     */
    public function isMethodAvailable($methodCode = null)
    {
        if ($methodCode === null) {
            $methodCode = $this->getMethodCode();
        }

        $result = true;

        if (!$this->isMethodActive($methodCode)) {
            $result = false;
        }

        switch ($methodCode) {
            case self::METHOD_WPS:
                if (!$this->businessAccount) {
                    $result = false;
                    break;
                }
                // check for direct payments dependence
                if ($this->isMethodActive(self::METHOD_WPP_DIRECT)
                    || $this->isMethodActive(self::METHOD_WPP_PE_DIRECT)) {
                    $result = false;
                }
                break;
            case self::METHOD_WPP_EXPRESS:
                // check for direct payments dependence
                if ($this->isMethodActive(self::METHOD_WPP_PE_DIRECT)) {
                    $result = false;
                } elseif ($this->isMethodActive(self::METHOD_WPP_DIRECT)) {
                    $result = true;
                }
                break;
//            case self::METHOD_BML:
//                // check for express payments dependence
//                if (!$this->isMethodActive(self::METHOD_WPP_EXPRESS)) {
//                    $result = false;
//                }
//                break;
            case self::METHOD_WPP_PE_EXPRESS:
                // check for direct payments dependence
                if ($this->isMethodActive(self::METHOD_WPP_PE_DIRECT)) {
                    $result = true;
                } elseif (!$this->isMethodActive(self::METHOD_WPP_PE_DIRECT)
                          && !$this->isMethodActive(self::METHOD_PAYFLOWPRO)) {
                    $result = false;
                }
                break;
//            case self::METHOD_WPP_PE_BML:
                // check for express payments dependence
//                if (!$this->isMethodActive(self::METHOD_WPP_PE_EXPRESS)) {
//                    $result = false;
//                }
//                break;
            case self::METHOD_BILLING_AGREEMENT:
                $result = $this->isWppApiAvailabe();
                break;
            case self::METHOD_WPP_DIRECT:
            case self::METHOD_WPP_PE_DIRECT:
                break;
        }
        return $result;
    }

    /**
     * Config field magic getter
     * The specified key can be either in camelCase or under_score format
     * Tries to map specified value according to set payment method code, into the configuration value
     * Sets the values into public class parameters, to avoid redundant calls of this method
     *
     * @param string $key
     * @return string|null
     */
    public function __get($key)
    {
        $underscored = strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $key));
        $value = Mage::getStoreConfig($this->_getSpecificConfigPath($underscored), $this->_storeId);
        $value = $this->_prepareValue($underscored, $value);
        $this->$key = $value;
        $this->$underscored = $value;
        return $value;
    }

    /**
     * Perform additional config value preparation and return new value if needed
     *
     * @param string $key Underscored key
     * @param string $value Old value
     * @return string Modified value or old value
     */
    protected function _prepareValue($key, $value)
    {
        // Always set payment action as "Sale" for Unilateral payments in EC
        if ($key == 'payment_action'
            && $value != self::PAYMENT_ACTION_SALE
            && $this->_methodCode == self::METHOD_WPP_EXPRESS
            && $this->shouldUseUnilateralPayments())
        {
            return self::PAYMENT_ACTION_SALE;
        }
        return $value;
    }

    /**
     * 
     *
     * @return array
     */
    public function getSupportedMerchantCountryCodes()
    {
        return $this->_supportedCountryCodes;
    }

    /**
     * 
     *
     * @return array
     */
    public function getSupportedBuyerCountryCodes()
    {
        return $this->_supportedBuyerCountryCodes;
    }

    /**
     * Return merchant country code, use default country if it not specified in General settings
     *
     * @return string
     */
    public function getMerchantCountry()
    {
        $countryCode = Mage::getStoreConfig($this->_mapGeneralFieldset('merchant_country'), $this->_storeId);
        if (!$countryCode) {
            $countryCode = Mage::helper('core')->getDefaultCountry($this->_storeId);
        }
        return $countryCode;
    }

    /**
     * Check whether method supported for specified country or not
     * Use $_methodCode and merchant country by default
     *
     * @return bool
     */
    public function isMethodSupportedForCountry($method = null, $countryCode = null)
    {
        if ($method === null) {
            $method = $this->getMethodCode();
        }
        if ($countryCode === null) {
            $countryCode = $this->getMerchantCountry();
        }
        $countryMethods = $this->getCountryMethods($countryCode);
        if (in_array($method, $countryMethods)) {
            return true;
        }
        return false;
    }

    /**
     * Return list of allowed methods for specified country iso code
     *
     * @param string $countryCode 2-letters iso code
     * @return array
     */
    public function getCountryMethods($countryCode = null)
    {
        $countryMethods = array(
            'GB' => array(
                self::METHOD_WPP_DIRECT,
                self::METHOD_WPS,
                self::METHOD_WPP_PE_DIRECT,
                self::METHOD_HOSTEDPRO,
                self::METHOD_WPP_EXPRESS,
//                self::METHOD_BML,
                self::METHOD_BILLING_AGREEMENT,
                self::METHOD_WPP_PE_EXPRESS,
//                self::METHOD_WPP_PE_BML,
            ),
        );
        if ($countryCode === null) {
            return $countryMethods;
        }
        return isset($countryMethods[$countryCode]) ? $countryMethods[$countryCode] : $countryMethods['other'];
    }
    /**
     * Whether Express Checkout button should be rendered dynamically
     *
     * @return bool
     */
    public function areButtonsDynamic()
    {
        return $this->buttonFlavor === self::EC_FLAVOR_DYNAMIC;
    }
    /**
     * Mapper from divido-specific payment actions to Magento payment actions
     *
     * @return string|null
     */
    public function getPaymentAction()
    {
        switch ($this->paymentAction) {
            case self::PAYMENT_ACTION_AUTH:
                return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
            case self::PAYMENT_ACTION_SALE:
                return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
            case self::PAYMENT_ACTION_ORDER:
                return Mage_Payment_Model_Method_Abstract::ACTION_ORDER;
        }
    }

    /**
     * Returns array of possible Authorization Amounts for Account Verification
     *
     * @deprecated since 1.6.2.0
     * @return array
     */
    public function getAuthorizationAmounts()
    {
        return array();
    }

    /**
     * Whether to ask customer to create billing agreements
     * Unilateral payments are incompatible with the billing agreements
     *
     * @return bool
     */
    public function shouldAskToCreateBillingAgreement()
    {
        return ($this->allow_ba_signup === self::EC_BA_SIGNUP_ASK) && !$this->shouldUseUnilateralPayments();
    }

    /**
     * Check whether only Unilateral payments (Accelerated Boarding) possible for Express method or not
     *
     * @return bool
     */
    public function shouldUseUnilateralPayments()
    {
        return $this->business_account && !$this->isWppApiAvailabe();
    }

    /**
     * Check whether WPP API credentials are available for this method
     *
     * @return bool
     */
    public function isWppApiAvailabe()
    {
        return $this->api_username && $this->api_password && ($this->api_signature || $this->api_cert);
    }

    /**
     * Payment data delivery methods getter for divido Standard
     *
     * @return array
     */
    public function getWpsPaymentDeliveryMethods()
    {
        return array(
            self::WPS_TRANSPORT_IPN      => Mage::helper('adminhtml')->__('IPN (Instant Payment Notification) Only'),
            // not supported yet:
//            self::WPS_TRANSPORT_PDT      => Mage::helper('adminhtml')->__('PDT (Payment Data Transfer) Only'),
//            self::WPS_TRANSPORT_IPN_PDT  => Mage::helper('adminhtml')->__('Both IPN and PDT'),
        );
    }

    /**
     * Return list of supported credit card types by divido Direct gateway
     *
     * @return array
     */
    public function getWppCcTypesAsOptionArray()
    {
        $model = Mage::getModel('payment/source_cctype')->setAllowedTypes(array('AE', 'VI', 'MC', 'SM', 'SO', 'DI'));
        return $model->toOptionArray();
    }

    /**
     * Return list of supported credit card types by divido Direct (Payflow Edition) gateway
     *
     * @return array
     */
    public function getWppPeCcTypesAsOptionArray()
    {
        $model = Mage::getModel('payment/source_cctype')->setAllowedTypes(array('VI', 'MC', 'SM', 'SO', 'OT', 'AE'));
        return $model->toOptionArray();
    }

    /**
     * Return list of supported credit card types by Payflow Pro gateway
     *
     * @return array
     */
    public function getPayflowproCcTypesAsOptionArray()
    {
        $model = Mage::getModel('payment/source_cctype')->setAllowedTypes(array('AE', 'VI', 'MC', 'JCB', 'DI'));
        return $model->toOptionArray();
    }

    /**
     * Check whether the specified payment method is a CC-based one
     *
     * @param string $code
     * @return bool
     */
    public static function getIsCreditCardMethod($code)
    {
        switch ($code) {
            case self::METHOD_WPP_DIRECT:
            case self::METHOD_WPP_PE_DIRECT:
            case self::METHOD_PAYFLOWPRO:
            case self::METHOD_PAYFLOWLINK:
            case self::METHOD_PAYFLOWADVANCED:
            case self::METHOD_HOSTEDPRO:
                return true;
        }
        return false;
    }

    /**
     * Check whether specified currency code is supported
     *
     * @param string $code
     * @return bool
     */
    public function isCurrencyCodeSupported($code)
    {
        if (in_array($code, $this->_supportedCurrencyCodes)) {
            return true;
        }
        if ($this->getMerchantCountry() == 'BR' && $code == 'BRL') {
            return true;
        }
        if ($this->getMerchantCountry() == 'MY' && $code == 'MYR') {
            return true;
        }
        return false;
    }

    /**
     * Export page style current settings to specified object
     *
     * @param Varien_Object $to
     */
    public function exportExpressCheckoutStyleSettings(Varien_Object $to)
    {
        foreach ($this->_ecStyleConfigMap as $key => $exportKey) {
            if ($this->$key) {
                $to->setData($exportKey, $this->$key);
            }
        }
    }

    /**
     * Check whether specified locale code is supported. Fallback to en_US
     *
     * @param string $localeCode
     * @return string
     */
    protected function _getSupportedLocaleCode($localeCode = null)
    {
        if (!$localeCode || !in_array($localeCode, $this->_supportedImageLocales)) {
            return 'en_US';
        }
        return $localeCode;
    }

    /**
     * Map any supported payment method into a config path by specified field name
     *
     * @param string $fieldName
     * @return string|null
     */
    protected function _getSpecificConfigPath($fieldName)
    {
        $path = null;
        switch ($this->_methodCode) {
            case self::METHOD_WPS:
                $path = $this->_mapStandardFieldset($fieldName);
                break;
//            case self::METHOD_BML:
//                $path = $this->_mapBmlFieldset($fieldName);
//                break;
//            case self::METHOD_WPP_PE_BML:
//                $path = $this->_mapBmlUkFieldset($fieldName);
//                break;
            case self::METHOD_WPP_EXPRESS:
            case self::METHOD_WPP_PE_EXPRESS:
                $path = $this->_mapExpressFieldset($fieldName);
                break;
            case self::METHOD_WPP_DIRECT:
            case self::METHOD_WPP_PE_DIRECT:
                $path = $this->_mapDirectFieldset($fieldName);
                break;
            case self::METHOD_BILLING_AGREEMENT:
            case self::METHOD_HOSTEDPRO:
                $path = $this->_mapMethodFieldset($fieldName);
                break;
        }

        if ($path === null) {
            switch ($this->_methodCode) {
                case self::METHOD_WPP_EXPRESS:
//                case self::METHOD_BML:
                case self::METHOD_WPP_DIRECT:
                case self::METHOD_BILLING_AGREEMENT:
                case self::METHOD_HOSTEDPRO:
                    $path = $this->_mapWppFieldset($fieldName);
                    break;
                case self::METHOD_WPP_PE_EXPRESS:
                case self::METHOD_WPP_PE_DIRECT:
                case self::METHOD_PAYFLOWADVANCED:
                case self::METHOD_PAYFLOWLINK:
                    $path = $this->_mapWpukFieldset($fieldName);
                    break;
            }
        }

        if ($path === null) {
            $path = $this->_mapGeneralFieldset($fieldName);
        }
        if ($path === null) {
            $path = $this->_mapGenericStyleFieldset($fieldName);
        }
        return $path;
    }

    /**
     * Check wheter specified country code is supported by build notation codes for specific countries
     *
     * @param $code
     * @return string|null
     */
    private function _matchBnCountryCode($code)
    {
        switch ($code) {
            // GB == UK
            case 'GB':
                return 'UK';
            // Australia, Austria, Belgium, Canada, China, France, Germany, Hong Kong, Italy
            case 'AU': case 'AT': case 'BE': case 'CA': case 'CN': case 'FR': case 'DE': case 'HK': case 'IT':
            // Japan, Mexico, Netherlands, Poland, Singapore, Spain, Switzerland, United Kingdom, United States
            case 'JP': case 'MX': case 'NL': case 'PL': case 'SG': case 'ES': case 'CH': case 'UK': case 'US':
                return $code;
        }
    }

    /**
     * 
     *
     * @param string $fieldName
     * @return string|null
     */
    protected function _mapStandardFieldset($fieldName)
    {
        switch ($fieldName)
        {
            case 'line_items_summary':
            case 'sandbox_flag':
                return 'payment/' . self::METHOD_WPS . "/{$fieldName}";
            default:
                return $this->_mapMethodFieldset($fieldName);
        }
    }

    /**
     * 
     *
     * @param string $fieldName
     * @return string|null
     */
    protected function _mapExpressFieldset($fieldName)
    {
        switch ($fieldName)
        {
            case 'transfer_shipping_options':
            case 'solution_type':
            case 'visible_on_cart':
            case 'visible_on_product':
            case 'require_billing_address':
            case 'authorization_honor_period':
            case 'order_valid_period':
            case 'child_authorization_number':
            case 'allow_ba_signup':
                return "payment/{$this->_methodCode}/{$fieldName}";
            default:
                return $this->_mapMethodFieldset($fieldName);
        }
    }

    /**
     * 
     *
     * @param string $fieldName
     * @return string|null
     */
    protected function _mapBmlFieldset($fieldName)
    {
        switch ($fieldName)
        {
            case 'allow_ba_signup':
                return "payment/" . self::METHOD_WPP_EXPRESS . "/{$fieldName}";
            default:
                return $this->_mapExpressFieldset($fieldName);
        }
    }

    /**
     * 
     *
     * @param string $fieldName
     * @return string|null
     */
    protected function _mapBmlUkFieldset($fieldName)
    {
        switch ($fieldName)
        {
            case 'allow_ba_signup':
                return "payment/" . self::METHOD_WPP_PE_EXPRESS . "/{$fieldName}";
            default:
                return $this->_mapExpressFieldset($fieldName);
        }
    }

    /**
     * 
     *
     * @param string $fieldName
     * @return string|null
     */
    protected function _mapDirectFieldset($fieldName)
    {
        switch ($fieldName)
        {
            case 'useccv':
            case 'centinel':
            case 'centinel_is_mode_strict':
            case 'centinel_api_url':
                return "payment/{$this->_methodCode}/{$fieldName}";
            default:
                return $this->_mapMethodFieldset($fieldName);
        }
    }
    /**
     * 
     *
     * @param string $fieldName
     * @return string|null
     */
    protected function _mapMethodFieldset($fieldName)
    {
        if (!$this->_methodCode) {
            return null;
        }
        switch ($fieldName)
        {
            case 'active':
            case 'title':
            case 'payment_action':
            case 'allowspecific':
            case 'specificcountry':
            case 'line_items_enabled':
            case 'cctypes':
            case 'sort_order':
            case 'debug':
            case 'verify_peer':
                return "payment/{$this->_methodCode}/{$fieldName}";
            default:
                return null;
        }
    }
}

