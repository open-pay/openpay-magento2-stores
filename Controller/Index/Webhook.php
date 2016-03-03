<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Openpay\Stores\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
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
     * Load the page defined in view/frontend/layout/storeswebhook_index_webhook.xml
     * URL /storeswebhook/index/webhook
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute() {
        $objeto = file_get_contents('php://input');
        $json = json_decode($objeto);

        if ($json->type == 'verification') {
            header('HTTP/1.1 200 OK');
            exit;
        }

        if ($json->type == 'charge.succeeded' && ($json->transaction->method == 'store' || $json->transaction->method == 'bank_account')) {
            $order = $this->_objectManager->create('Magento\Sales\Model\Order');            
            $order->loadByAttribute('ext_order_id', $json->transaction->id);

            $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
            $order->setState($status)->setStatus($status);
            $order->addStatusHistoryComment("Pago recibido exitosamente")->setIsCustomerNotified(true);            
            $order->save();

            header('HTTP/1.1 200 OK');
            exit;
        }
//        $resultPage = $this->resultPageFactory->create();
//        $resultPage->getConfig()->getTitle()->prepend(__($json));
//        return $resultPage;                
    }

}
