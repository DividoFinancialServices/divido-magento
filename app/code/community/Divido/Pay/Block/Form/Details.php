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
 * @package     Mage_Payment
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
require_once(Mage::getBaseDir('lib') . '/Divido/Divido.php');

class Divido_Pay_Block_Form_Details extends Divido_Pay_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('pay/form/details.phtml');
	}

    /**
     * Retrieve payment configuration object
     *
     * @return Mage_Payment_Model_Config
     */
    protected function _getConfig()
    {
        return Mage::getSingleton('payment/pay');
    }
    
    /*
     * Set Merchant for divido using api key
     */
    protected function _setMerchant()
    {
        Divido::setMerchant($this->getApiKey());
        if ($this->getMethod()->getConfigData('sandbox')) {
                Divido::setSandboxMode(true);
        }
    }
    
    protected function getCampaign()
    {
        $this->_setMerchant();
        $response = Divido_Finances::all(array(
                'merchant'=>$this->getApiKey(),
        ));

        return $response;
    }
    
    protected function getCurrencySymbol()
    {
        $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode(); 
        $currency_symbol = Mage::app()->getLocale()->currency( $currency_code )->getSymbol();
        
        return $currency_symbol;
    }
    
    protected function getLogo()
    {
        return $this->getMethod()->getConfigData('logo');
    }
    
}
