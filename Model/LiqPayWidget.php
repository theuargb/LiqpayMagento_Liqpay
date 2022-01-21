<?php

namespace LiqpayMagento\LiqPay\Model;

use LiqpayMagento\LiqPay\Sdk\LiqPay;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Serialize\Serializer\Json;

class LiqPayWidget implements \LiqpayMagento\LiqPay\Api\LiqPayWidgetInterface
{

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var LiqPay
     */
    private $liqPay;

    /**
     * @var Json
     */
    private $json;

    public function __construct(CheckoutSession $checkoutSession, LiqPay $liqPay, Json $json)
    {
        $this->checkoutSession = $checkoutSession;
        $this->liqPay = $liqPay;
        $this->json = $json;
    }

    public function getHydrateData(string $orderId)
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if ((string)$order->getId() != $orderId) {
            return (string)$this->json->serialize(['error' => 'requested order was not found with current session']);
        }

        $liqPayData = $this->liqPay->cnb_form_raw([
            'public_key' => $this->liqPay->getHelper()->getPublicKey(),
            'version' => '3',
            'action' => 'pay',
            'amount' => "{$order->getGrandTotal()}",
            'currency' => 'UAH',
            'description' => "Mammy Club Order #{$order->getId()}",
            'order_id' => "{$order->getId()}",
        ]);

        return (string)$this->json->serialize($liqPayData);
    }
}
