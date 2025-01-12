<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace LiqpayMagento\LiqPay\Sdk;

use LiqpayMagento\LiqPay\Helper\Data;

/** extends official LiqPay Sdk */
class LiqPay extends \LiqPay
{
    const VERSION = '3';
    const TEST_MODE_SURFIX_DELIM = '-';

    // success
    const STATUS_SUCCESS = 'success';
    const STATUS_WAIT_COMPENSATION = 'wait_compensation';
    // const STATUS_SUBSCRIBED        = 'subscribed';

    // processing
    const STATUS_PROCESSING = 'processing';

    // failure
    const STATUS_FAILURE = 'failure';
    const STATUS_ERROR = 'error';

    // wait
    const STATUS_WAIT_SECURE = 'wait_secure';
    const STATUS_WAIT_ACCEPT = 'wait_accept';
    const STATUS_WAIT_CARD = 'wait_card';

    // sandbox
    const STATUS_SANDBOX = 'sandbox';

    protected $_helper;

    /**
     * LiqPay constructor.
     *
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->_helper = $helper;
        if ($helper->isEnabled()) {
            $publicKey = $helper->getPublicKey();
            $privateKey = $helper->getPrivateKey();
            parent::__construct($publicKey, $privateKey);
        }
    }

    /**
     * @param $params
     *
     * @return mixed
     */
    protected function prepareParams($params)
    {
        if (!isset($params['sandbox'])) {
            $params['sandbox'] = (int)$this->_helper->isTestMode();
        }
        if (!isset($params['version'])) {
            $params['version'] = static::VERSION;
        }
        if (isset($params['order_id']) && $this->_helper->isTestMode()) {
            $surfix = $this->_helper->getTestOrderSurfix();
            if (!empty($surfix)) {
                $params['order_id'] .= self::TEST_MODE_SURFIX_DELIM . $surfix;
            }
        }
        return $params;
    }

    /**
     * @return Data
     */
    public function getHelper()
    {
        return $this->_helper;
    }

    /**
     * @return mixed
     */
    public function getSupportedCurrencies()
    {
        return $this->_supportedCurrencies;
    }

    /**
     * @param $path
     * @param array $params
     * @param int $timeout
     *
     * @return mixed
     */
    public function api($path, $params = [], $timeout = 5)
    {
        $params = $this->prepareParams($params);
        return parent::api($path, $params, $timeout);
    }

    /**
     * @param $params
     *
     * @return mixed
     */
    public function cnb_form($params)
    {
        $params = $this->prepareParams($params);
        return parent::cnb_form($params);
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    public function getDecodedData($data)
    {
        return json_decode(base64_decode($data), true, 1024);
    }

    /**
     * @param $signature
     * @param $data
     *
     * @return bool
     */
    public function checkSignature($signature, $data)
    {
        $privateKey = $this->_helper->getPrivateKey();
        $generatedSignature = base64_encode(sha1($privateKey . $data . $privateKey, 1));
        return $signature == $generatedSignature;
    }
}
