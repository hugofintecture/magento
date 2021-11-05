<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model\ResourceModel\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'fin_order_collection';
    protected $_eventObject = 'fin_order_collection';

    protected function _construct()
    {
        $this->_init('Fintecture\Payment\Model\Order', 'Fintecture\Payment\Model\ResourceModel\Order');
    }

    public function getOpenOrders($quote)
    {
        $curr_code = $quote->getQuoteCurrencyCode();
        $email     = $quote->getCustomerEmail();
        $date      = $quote->getCreatedAt();
        $this->getSelect()
             ->where('main_table.state =?', 'new')
             ->where('main_table.status =?', 'pending')
             ->where('main_table.order_currency_code =?', $curr_code)
             ->where('main_table.customer_email =?', $email)
             ->order('main_table.updated_at DESC');

        return $this->addFieldToFilter('created_at', ['gt' => $date]);
    }
}
