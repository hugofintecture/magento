<?php
namespace Fintecture\Payment\Model;

class Order extends \Magento\Sales\Model\Order
{
    protected function _construct()
    {
        $this->_init('Fintecture\Payment\Model\ResourceModel\Order');
    }

    public function getOrderForQuote($quote)
    {
        return ($this->getCollection()->getOpenOrders($quote))->getFirstItem();
    }
}
