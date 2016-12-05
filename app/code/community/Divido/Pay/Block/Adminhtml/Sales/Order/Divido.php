<?php

class Divido_Pay_Block_Adminhtml_Sales_Order_Divido 
    extends Mage_Adminhtml_Block_Template
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected $_chat = null;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('divido/order-info.phtml');
    }

    public function getTabLabel ()
    {
        return $this->__('Divido Info');
    }

    public function getTabTitle ()
    {
        return $this->__('See relevant Divido information');
    }

    public function canShowTab () 
    {
        return true;
    }

    public function isHidden () 
    {
        return false;
    }

    public function getOrder () 
    {
        return Mage::registry('current_order');
    }

    public function getDividoInfo ()
    {
        $dividoInfo = array(
            'proposal_id'    => null,
            'application_id' => null,
            'deposit_amount' => null,
        );
        
        $order   = $this->getOrder();
        $quoteId = $order->getQuoteId();

        $lookup  = Mage::getModel('callback/lookup');
        $lookup->load($quoteId, 'quote_id');

        if ($lookup->getId()) {
            if ($proposalId = $lookup->getCreditRequestId()) {
                $dividoInfo['proposal_id'] = $proposalId;
            }

            if ($applicationId = $lookup->getCreditApplicationId()) {
                $dividoInfo['application_id'] = $applicationId;
            }

            if ($depositAmount = $lookup->getDepositAmount()) {
                $dividoInfo['deposit_amount'] = $depositAmount;
            }
        }

        return $dividoInfo;
    }
}
