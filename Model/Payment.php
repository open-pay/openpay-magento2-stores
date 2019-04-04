<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Openpay\Stores\Model;

use Magento\Store\Model\ScopeInterface;

/**
 * Class Payment
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 */
class Payment extends \Magento\Payment\Model\Method\AbstractMethod
{

    const CODE = 'openpay_stores';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canOrder = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_isOffline = true;
    protected $scope_config;
    protected $openpay = false;
    protected $is_sandbox;
    protected $merchant_id = null;
    protected $sk = null;
    protected $deadline = 72;
    protected $sandbox_merchant_id;
    protected $sandbox_sk;
    protected $live_merchant_id;
    protected $live_sk;
    protected $pdf_url_base;
    protected $show_map;
    protected $supported_currency_codes = array('MXN');
    protected $_transportBuilder;
    protected $logger;
    protected $_storeManager;
    protected $_inlineTranslation;
    protected $_directoryList;
    protected $_file;
    
    /**
     * 
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param TransportBuilder $transportBuilder
     * @param array $data
     */
    public function __construct(
            \Magento\Framework\Model\Context $context,
            \Magento\Framework\Registry $registry, 
            \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
            \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory, 
            \Magento\Payment\Helper\Data $paymentData, 
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, 
            \Magento\Payment\Model\Method\Logger $logger,             
            \Openpay\Stores\Mail\Template\TransportBuilder $transportBuilder,
            \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
            \Magento\Store\Model\StoreManagerInterface $storeManager,
            \Psr\Log\LoggerInterface $logger_interface,
            \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
            \Magento\Framework\Filesystem\Io\File $file,
            array $data = []            
    ) {
        parent::__construct(
            $context,
            $registry, 
            $extensionFactory,
            $customAttributeFactory,
            $paymentData, 
            $scopeConfig,
            $logger,
            null,
            null,            
            $data            
        );

        $this->_file = $file;
        $this->_directoryList = $directoryList;
        $this->logger = $logger_interface;
        $this->_inlineTranslation = $inlineTranslation;        
        $this->_storeManager = $storeManager;
        $this->_transportBuilder = $transportBuilder;
        $this->scope_config = $scopeConfig;

        $this->is_active = $this->getConfigData('active');
        $this->is_sandbox = $this->getConfigData('is_sandbox');
        $this->sandbox_merchant_id = $this->getConfigData('sandbox_merchant_id');
        $this->sandbox_sk = $this->getConfigData('sandbox_sk');
        $this->live_merchant_id = $this->getConfigData('live_merchant_id');
        $this->live_sk = $this->getConfigData('live_sk');
        $this->show_map = $this->getConfigData('show_map');

        $this->merchant_id = $this->is_sandbox ? $this->sandbox_merchant_id : $this->live_merchant_id;
        $this->sk = $this->is_sandbox ? $this->sandbox_sk : $this->live_sk;
        $this->pdf_url_base = $this->is_sandbox ? 'https://sandbox-dashboard.openpay.mx/paynet-pdf' : 'https://dashboard.openpay.mx/paynet-pdf';
    }

    /**
     * 
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return \Openpay\Stores\Model\Payment
     * @throws \Magento\Framework\Validator\Exception
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount) {

        /**
         * Magento utiliza el timezone UTC, por lo tanto sobreescribimos este 
         * por la configuración que se define en el administrador         
         */
        $store_tz = $this->scope_config->getValue('general/locale/timezone');
        date_default_timezone_set($store_tz);

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();

        try {

            $customer_data = array(
                'name' => $billing->getFirstname(),
                'last_name' => $billing->getLastname(),
                'phone_number' => $billing->getTelephone(),
                'email' => $order->getCustomerEmail()
            );

            if ($this->validateAddress($billing)) {
                $customer_data['address'] = array(
                    'line1' => $billing->getStreetLine(1),
                    'line2' => $billing->getStreetLine(2),
                    'postal_code' => $billing->getPostcode(),
                    'city' => $billing->getCity(),
                    'state' => $billing->getRegion(),
                    'country_code' => $billing->getCountryId()
                );
            }

            $due_date = date('Y-m-d\TH:i:s', strtotime('+ '.$this->deadline.' hours'));

            $charge_request = array(
                'method' => 'store',
                'amount' => $amount,
                'description' => sprintf('ORDER #%s, %s', $order->getIncrementId(), $order->getCustomerEmail()),
                'order_id' => $order->getIncrementId(),
                'due_date' => $due_date,
                'customer' => $customer_data
            );

//            $openpay = \Openpay::getInstance($this->merchant_id, $this->sk);
//            \Openpay::setSandboxMode($this->is_sandbox);
            $openpay = $this->getOpenpayInstance();
            
            $charge = $openpay->charges->create($charge_request);
            $payment->setTransactionId($charge->id);

            $state = \Magento\Sales\Model\Order::STATE_NEW;
            $order->setState($state)->setStatus($state);
            $order->setExtOrderId($charge->id);

            $pdf_url = $this->pdf_url_base.'/'.$this->merchant_id.'/'.$charge->payment_method->reference;
            $_SESSION['pdf_url'] = $pdf_url;            
            $_SESSION['show_map'] = $this->show_map;
                        
//            $pdf_file = $this->handlePdf($pdf_url, $order->getIncrementId());
//            $this->sendEmail($pdf_file, $order);
            
        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            $this->_logger->error(__( $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__($this->error($e)));
        }

        $payment->setSkipOrderProcessing(true);
        return $this;
    }
    
    public function sendEmail($pdf_file, $order) {
        $email = $this->scope_config->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE);
        $name  = $this->scope_config->getValue('trans_email/ident_general/name', ScopeInterface::SCOPE_STORE);
        $pdf = file_get_contents($pdf_file);        
        $from = array('email' => $email, 'name' => $name);
        $to = $order->getCustomerEmail();
        $template_vars = array(
            'title' => 'Tu recibo de pago | Orden #'.$order->getIncrementId()
        );
        
        $this->logger->debug('#sendEmail', array('$pdf_path' => $pdf_file, '$from' => $from, '$to' => $to));                    
        
        try {            
            $this->_transportBuilder
                ->setTemplateIdentifier('openpay_pdf_template')
                ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $this->_storeManager->getStore()->getId()])
                ->setTemplateVars($template_vars)
                ->addAttachment($pdf, 'recibo_pago.pdf', 'application/octet-stream')
                ->setFrom($from)
                ->addTo($to)
                ->getTransport()
                ->sendMessage();
            return;
        } catch (MailException $me) {            
            $this->logger->error('#MailException', array('msg' => $me->getMessage()));                    
            throw new \Magento\Framework\Exception\LocalizedException(__($me->getMessage()));
        } catch (\Exception $e) {            
            $this->logger->error('#Exception', array('msg' => $e->getMessage()));                    
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }    
    
    private function handlePdf($url, $order_id) {
        $pdfContent = file_get_contents($url);
        $filePath = "/openpay/attachments/";
        $pdfPath = $this->_directoryList->getPath('media') . $filePath;
        $ioAdapter = $this->_file;
        
        if (!is_dir($pdfPath)) {            
            $ioAdapter->mkdir($pdfPath, 0775);
        }

        $fileName = "payment_receipt_".$order_id.".pdf";
        $ioAdapter->open(array('path' => $pdfPath));
        $ioAdapter->write($fileName, $pdfContent, 0666);

        return $pdfPath.$fileName;
    }
    
    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode) {
        if (!in_array($currencyCode, $this->supported_currency_codes)) {
            return false;
        }
        return true;
    }

    /**
     * Get $sk property
     * 
     * @return string
     */
    public function getSecretKey() {
        return $this->sk;
    }

    /**
     * Get $merchant_id property
     * 
     * @return string
     */
    public function getMerchantId() {
        return $this->merchat_id;
    }

    /**
     * Get $is_sandbox property
     * 
     * @return boolean
     */
    public function isSandbox() {
        return $this->is_sandbox;
    }

    /**
     * @param Exception $e
     * @return string
     */
    public function error($e) {

        /* 6001 el webhook ya existe */
        switch ($e->getErrorCode()) {
            case '1000':
            case '1004':
            case '1005':
                $msg = 'Servicio no disponible.';
                break;
            case '6001':
                $msg = 'El webhook ya existe, has caso omiso de este mensaje.';
            case '6002':
                $msg = 'El webhook no pudo ser verificado, revisa la URL.';
            default: /* Demás errores 400 */
                $msg = 'La petición no pudo ser procesada.';
                break;
        }

        return 'ERROR '.$e->getErrorCode().'. '.$msg;
    }

    /**
     * @param Address $billing
     * @return boolean
     */
    public function validateAddress($billing) {
        if ($billing->getStreetLine(1) && $billing->getCity() && $billing->getPostcode() && $billing->getRegion() && $billing->getCountryId()) {
            return true;
        }
        return false;
    }

    /**
     * Create webhook
     * @return mixed
     */
    public function createWebhook() {
        $protocol = $this->hostSecure() === true ? 'https://' : 'http://';
        $uri = $_SERVER['HTTP_HOST']."/openpay/index/webhook";
        $webhook_data = array(
            'url' => $protocol.$uri,
            'event_types' => array(
                'verification',
                'charge.succeeded',
                'charge.created',
                'charge.cancelled',
                'charge.failed',
                'payout.created',
                'payout.succeeded',
                'payout.failed',
                'spei.received',
                'chargeback.created',
                'chargeback.rejected',
                'chargeback.accepted'
            )
        );

        $openpay = \Openpay::getInstance($this->merchant_id, $this->sk);
        \Openpay::setSandboxMode($this->is_sandbox);

        try {
            $webhook = $openpay->webhooks->add($webhook_data);
            return $webhook;
        } catch (Exception $e) {
            return $this->error($e);
        }
    }
    
    /*
     * Validate if host is secure (SSL)
     */
    public function hostSecure() {
        $is_secure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $is_secure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $is_secure = true;
        }
        
        return $is_secure;
    }

    public function getOpenpayInstance() {
        $openpay = \Openpay::getInstance($this->merchant_id, $this->sk);
        \Openpay::setSandboxMode($this->is_sandbox);        
        return $openpay;
    }

}
