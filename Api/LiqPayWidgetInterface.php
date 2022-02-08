<?php

namespace LiqpayMagento\LiqPay\Api;

use LiqpayMagento\LiqPay\Model\WidgetData;

interface LiqPayWidgetInterface
{

    /**
     * @param string $orderId
     *
     * @return WidgetData
     */
    public function getHydrateData(string $orderId);

    /**
     * @return string
     */
    public function getRedirectUrl();
}
