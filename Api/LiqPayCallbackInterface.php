<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace LiqpayMagento\LiqPay\Api;

use Magento\Framework\Controller\ResultInterface;

/**
 * Interface LiqPayCallbackInterface
 * @package LiqpayMagento\LiqPay\Api
 */
interface LiqPayCallbackInterface
{

    /**
     * @return ResultInterface
     */
    public function callback();
}
