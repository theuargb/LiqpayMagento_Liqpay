<?php

namespace LiqpayMagento\LiqPay\Api;

interface LiqPayWidgetInterface
{

    /**
     * @param string $orderId
     *
     * @return string
     * @api
     */
    public function getHydrateData(string $orderId);
}
