<?php

namespace Openpay\Stores\Model\Mail;

class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder {

    /**
     * @param Api\AttachmentInterface $attachment
     */
    public function addAttachment($content, $name, $type) {
        $this->message->createAttachment(
                $content, $type, \Zend_Mime::DISPOSITION_ATTACHMENT, \Zend_Mime::ENCODING_BASE64, $name
        );
        return $this;
    }    
}
