<?php
class Divido_Pay_Block_Widget extends Mage_Core_Block_Template
{
    private
        $active,
        $plans,
        $price,
        $product;

    public function showWidget ()
    {
        if (!$this->isActive() || !$this->getPrice() || !$this->getPlans()) {
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

    public function isActive ()
    {
        if ($this->active === null) {
            $product = $this->getProduct();
            $active  = Mage::helper('pay')->isActiveLocal($product);

            $this->active = $active;
        }

        return $this->active;
    }

    public function getPrice ()
    {
        if ($this->price === null) {
            $product = $this->getProduct();
            $price   = $product->getFinalPrice();
            $incTax  = Mage::helper('tax')->getPrice($product, $price, true);

            $this->price = $incTax;
        }

        return $this->price;
    }

    public function getPlans ()
    {
        if ($this->plans === null) {
            $product = $this->getProduct();
            $plans   = Mage::helper('pay')->getLocalPlans($product);
            $plans   = Mage::helper('pay')->plans2list($plans);

            $this->plans = $plans;
        }

        return $this->plans;
    }
}
