<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Openpay\Stores\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Openpay\Stores\Model\Payment as Config;

/**
 * Class CreateWebhook
 */
class CreateWebhook implements ObserverInterface
{

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @param Config $config
     * @param ManagerInterface $messageManager
     */
    public function __construct(
    Config $config, ManagerInterface $messageManager
    ) {
        $this->config = $config;
        $this->messageManager = $messageManager;
    }

    /**
     * Create Webhook
     *
     * @param Observer $observer          
     */
    public function execute(Observer $observer) {
        return $this->config->createWebhook();
    }

}
