<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace LiqpayMagento\LiqPay\Controller\Checkout;

use Exception;
use LiqpayMagento\LiqPay\Block\SubmitForm;
use LiqpayMagento\LiqPay\Helper\Data as Helper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;

/**
 * Class Form
 * @package LiqpayMagento\LiqPay\Controller\Checkout
 */
class Form extends Action
{
    /**
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    /**
     * @var Helper
     */
    protected $_helper;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param Helper $helper
     */
    public function __construct(
        Context         $context,
        CheckoutSession $checkoutSession,
        Helper          $helper
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_helper = $helper;
    }

    /**
     * Dispatch request
     *
     * @return ResultInterface|ResponseInterface
     * @throws NotFoundException
     */
    public function execute()
    {
        try {
            if (!$this->_helper->isEnabled()) {
                throw new Exception(__('Payment is not allow.'));
            }
            $order = $this->getCheckoutSession()->getLastRealOrder();
            if (!($order && $order->getId())) {
                throw new Exception(__('Order not found'));
            }
            if ($this->_helper->checkOrderIsLiqPayPayment($order)) {
                /* @var $formBlock SubmitForm */
                $formBlock = $this->_view->getLayout()->createBlock('LiqpayMagento\LiqPay\Block\SubmitForm');
                $formBlock->setOrder($order);
                $data = [
                    'status' => 'success',
                    'content' => $formBlock->getLiqpayForm(),
                ];
            } else {
                throw new Exception('Order payment method is not a LiqPay payment method');
            }
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong, please try again later'));
            $this->_helper->getLogger()->critical($e);
            $this->getCheckoutSession()->restoreQuote();
            $data = [
                'status' => 'error',
                'redirect' => $this->_url->getUrl('checkout/cart'),
            ];
        }
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData($data);
        return $result;
    }


    /**
     * Return checkout session object
     *
     * @return CheckoutSession
     */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }
}
