<?php
class Divido_Pay_Block_Widget extends Mage_Core_Block_Template
{
    const AUTO_WIDGET_NAME = 'divido_widget_auto';

    private
        $active,
        $plans,
        $price,
        $product;

    public function isAvailable ()
    {
        $nameInLayout      = $this->getNameInLayout();
        $widgetIsActivated = Mage::getStoreConfig('payment/pay/product_page_widget');

        if ($nameInLayout === self::AUTO_WIDGET_NAME && !$widgetIsActivated) {
            return false;
        }

        $price  = $this->getPrice(true);
        $active = $this->isActive($price);
        $plans  = $this->getPlans();

        if (! $active || ! $price  || ! $plans) {
            return false;
        }

        return true;
    }

    public function getProduct ()
    {
        if ($this->product === null) {
            $product = Mage::registry('current_product');
            $this->product = $product;
        }

        return $this->product;
    }

    public function isActive ($price = null)
    {
        if ($this->active === null) {
            $product = $this->getProduct();
            $active  = Mage::helper('divido_pay')->isActiveLocal($product, $price);

            $this->active = $active;
        }

        return $this->active;
    }

    public function getPrice ($max = false)
    {
        if ($this->price === null) {
            $product = $this->getProduct();
            $price   = $product->getFinalPrice();
            $incTax  = Mage::helper('tax')->getPrice($product, $price, true);

            $childIds = $product->getTypeInstance()->getChildrenIds($product->getId());
            if ($childIds) {
                $childPrices = array();
                foreach ($childIds as $ids) {
                    foreach ($ids as $id) {
                        $childProd = Mage::getModel('catalog/product')->load($id);
                        if ($childProd->getStatus() != Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
                            continue;
                        }

                        $childPrices[] = Mage::helper('tax')->getPrice($childProd, $childProd->getFinalPrice(), true);
                        $tierPrices = $childProd->getTierPrice();
                        if ($tierPrices) {
                            foreach ($tierPrices as $tierPrice) {
                                $childPrices[] = $tierPrice['price'];
                            }
                        }
                    }
                }

                if ($max) {
                    $incTax = max($childPrices);
                } else {
                    $incTax = min($childPrices);
                }
            }

            $this->price = $incTax;
        }

        return $this->price;
    }

    public function getPlans ()
    {
        if ($this->plans === null) {
            $product = $this->getProduct();
            $plans   = Mage::helper('divido_pay')->getLocalPlans($product);
            $plans   = Mage::helper('divido_pay')->plans2list($plans);

            $this->plans = $plans;
        }

        return $this->plans;
    }
}
