<?php
/** 
 * @category    Payments
 * @package     Openpay_Stores
 * @author      Federico Balderas
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Stores\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Webhook class  
 */
class Webhook extends \Magento\Framework\App\Action\Action
{

    protected $resultPageFactory;
    protected $request;

    public function __construct(Context $context, PageFactory $resultPageFactory, \Magento\Framework\App\Request\Http $request) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->request = $request;
    }

    /**
     * Load the page defined in view/frontend/layout/openpay_index_webhook.xml
     * URL /openpay/index/webhook
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute() {
        $body = file_get_contents('php://input');        
        $json = json_decode($body);        
        if (isset($json->type)) {                     
            if ($json->type == 'charge.succeeded' && ($json->transaction->method == 'store' || $json->transaction->method == 'bank_account')) {
                $order = $this->_objectManager->create('Magento\Sales\Model\Order');            
                $order->loadByAttribute('ext_order_id', $json->transaction->id);

                $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
                $order->setState($status)->setStatus($status);
                $order->addStatusHistoryComment("Pago recibido exitosamente")->setIsCustomerNotified(true);            
                $order->save();
            }
        }        
        
        header('HTTP/1.1 200 OK');
        exit;        
    }

}
