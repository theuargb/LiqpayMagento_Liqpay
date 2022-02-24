<?php

namespace LiqpayMagento\LiqPay\Model;

use LiqpayMagento\LiqPay\Api\LiqPayWidgetInterface;
use LiqpayMagento\LiqPay\Sdk\LiqPay;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;

class LiqPayWidget implements LiqPayWidgetInterface
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
     * @var UrlInterface
     */
    private $urlBuilder;

    public function __construct(CheckoutSession $checkoutSession, LiqPay $liqPay, UrlInterface $urlBuilder)
    {
        $this->checkoutSession = $checkoutSession;
        $this->liqPay = $liqPay;
        $this->urlBuilder = $urlBuilder;
    }

    public function getHydrateData(string $orderId)
    {
        $order = $this->checkoutSession->getLastRealOrder()->loadByIncrementId($orderId);
        if ((string)$order->getId() != $orderId) {
            // return (string)$this->json->serialize(['error' => 'requested order was not found with current session']);
        }

        $cnbFormRawData = $this->liqPay->cnb_form_raw([
            'public_key' => $this->liqPay->getHelper()->getPublicKey(),
            'version' => '3',
            'action' => 'pay',
            'amount' => "{$order->getGrandTotal()}",
            'currency' => 'UAH',
            'description' => "Mammy Club Order #{$order->getIncrementId()}",
            'order_id' => "{$order->getId()}",
        ]);

        return (new WidgetData())->setData($cnbFormRawData['data'])->setSignature($cnbFormRawData['signature']);
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->urlBuilder->getUrl('hyva/reactcheckout/success');
    }
}
