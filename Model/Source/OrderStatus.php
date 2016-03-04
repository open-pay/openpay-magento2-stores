<?php
/** 
 * @category    Payments
 * @package     Openpay_Stores
 * @author      Federico Balderas
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Stores\Model\Source;

use \Magento\Sales\Model\Order;

/**
 * Class OrderStatus 
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
