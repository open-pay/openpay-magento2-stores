<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Openpay\Stores\Model\Source;

use \Magento\Sales\Model\Order;

/**
 * Class PaymentAction
 * @codeCoverageIgnore
 */
class OrderStatus implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Possible actions on order place
     * 
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => Order::STATE_PENDING_PAYMENT,
                'label' => __('Pending payment'),
            ],
            [
                'value' => Order::STATE_PROCESSING,
                'label' => __('Processing'),
            ]
        ];
    }
}
