<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace LiqpayMagento\LiqPay\Model;

use Exception;
use LiqpayMagento\LiqPay\Api\LiqPayCallbackInterface;
use LiqpayMagento\LiqPay\Helper\Data as Helper;
use LiqpayMagento\LiqPay\Sdk\LiqPay;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;

/**
 * Class LiqPayCallback
 * @package LiqpayMagento\LiqPay\Model
 */
class LiqPayCallback implements LiqPayCallbackInterface
{
    /**
     * @var Order
     */
    protected $_order;

    /**
     * @var LiqPay
     */
    protected $_liqPay;

    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var Transaction
     */
    protected $_transaction;

    /**
     * @var Helper
     */
    protected $_helper;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * LiqPayCallback constructor.
     *
     * @param Order $order
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     * @param Helper $helper
     * @param LiqPay $liqPay
     * @param RequestInterface $request
     */
    public function __construct(
        Order                    $order,
        OrderRepositoryInterface $orderRepository,
        InvoiceService           $invoiceService,
        Transaction              $transaction,
        Helper                   $helper,
        LiqPay                   $liqPay,
        RequestInterface         $request
    ) {
        $this->_order = $order;
        $this->_liqPay = $liqPay;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_helper = $helper;
        $this->_request = $request;
    }

    /**
     * @return ResultInterface
     */
    public function callback()
    {
        $post = $this->_request->getParams();
        if (!(isset($post['data']) && isset($post['signature']))) {
            $this->_helper->getLogger()->error(__('In the response from LiqPay server there are no POST parameters "data" and "signature"'));
            return null;
        }

        $data = $post['data'];
        $receivedSignature = $post['signature'];

        $decodedData = $this->_liqPay->getDecodedData($data);
        $orderIncrementId = $decodedData['order_id'] ?? null;
        $receivedPublicKey = $decodedData['public_key'] ?? null;
        $status = $decodedData['status'] ?? null;
        $amount = $decodedData['amount'] ?? null;
        $currency = $decodedData['currency'] ?? null;
        $transactionId = $decodedData['transaction_id'] ?? null;
        $senderPhone = $decodedData['sender_phone'] ?? null;

        try {
            /** @var Order $order */
            $order = $this->getRealOrder($status, $orderIncrementId);

            if (!($order && $order->getId() && $this->_helper->checkOrderIsLiqPayPayment($order))) {
                $this->_helper->getLogger()->error(__(
                    'Order does not exist or was created without Liqpay payment'
                ));

                return null;
            }

            // ALWAYS CHECK signature field from Liqpay server!!!!
            // DON'T delete this block, be careful of fraud!!!
            if (!$this->_helper->securityOrderCheck($data, $receivedPublicKey, $receivedSignature)) {
                $order->addStatusHistoryComment(__('LiqPay security check failed!'));
                $this->_orderRepository->save($order);
                return null;
            }

            $historyMessage = [];
            $state = null;
            switch ($status) {
                case LiqPay::STATUS_SANDBOX:
                case LiqPay::STATUS_WAIT_COMPENSATION:
                    // case LiqPay::STATUS_SUBSCRIBED:
                case LiqPay::STATUS_SUCCESS:
                    if ($order->canInvoice()) {
                        $invoice = $this->_invoiceService->prepareInvoice($order);
                        $invoice->register()->pay();
                        $transactionSave = $this->_transaction->addObject(
                            $invoice
                        )->addObject(
                            $invoice->getOrder()
                        );
                        $transactionSave->save();
                        if ($status == LiqPay::STATUS_SANDBOX) {
                            $historyMessage[] = __('Invoice #%1 created (sandbox).', $invoice->getIncrementId());
                        } else {
                            $historyMessage[] = __('Invoice #%1 created.', $invoice->getIncrementId());
                        }
                        $state = Order::STATE_PROCESSING;
                    } else {
                        $historyMessage[] = __('Error during creation of invoice.');
                    }
                    if ($senderPhone) {
                        $historyMessage[] = __('Sender phone: %1.', $senderPhone);
                    }
                    if ($amount) {
                        $historyMessage[] = __('Amount: %1.', $amount);
                    }
                    if ($currency) {
                        $historyMessage[] = __('Currency: %1.', $currency);
                    }
                    break;
                case LiqPay::STATUS_FAILURE:
                case LiqPay::STATUS_ERROR:
                    $state = Order::STATE_CANCELED;
                    $historyMessage[] = __('Liqpay error.');
                    break;
                case LiqPay::STATUS_WAIT_SECURE:
                    $state = Order::STATE_PROCESSING;
                    $historyMessage[] = __('Waiting for verification from the Liqpay side.');
                    break;
                case LiqPay::STATUS_WAIT_ACCEPT:
                    $state = Order::STATE_PROCESSING;
                    $historyMessage[] = __('Waiting for accepting from the buyer side.');
                    break;
                case LiqPay::STATUS_WAIT_CARD:
                    $state = Order::STATE_PROCESSING;
                    $historyMessage[] = __('Waiting for setting refund card number into your Liqpay shop.');
                    break;
                default:
                    $historyMessage[] = __('Unexpected status from LiqPay server: %1', $status);
                    break;
            }
            if ($transactionId) {
                $historyMessage[] = __('LiqPay transaction id %1.', $transactionId);
            }
            if (count($historyMessage)) {
                $order->addStatusHistoryComment(implode(' ', $historyMessage))
                      ->setIsCustomerNotified(true);
            }
            if ($state) {
                $order->setState($state);
                $order->setStatus($state);
                $order->save();
            }
            $this->_orderRepository->save($order);
        } catch (Exception $e) {
            $this->_helper->getLogger()->critical($e);
        }

        return null;
    }

    /**
     * @param $status
     * @param $orderIncrementId
     *
     * @return mixed
     */
    protected function getRealOrder($status, $orderIncrementId)
    {
        if ($status == LiqPay::STATUS_SANDBOX) {
            $testOrderSurfix = $this->_helper->getTestOrderSurfix();
            if (!empty($testOrderSurfix)) {
                $testOrderSurfix = LiqPay::TEST_MODE_SURFIX_DELIM . $testOrderSurfix;
                if (strlen($testOrderSurfix) < strlen($orderIncrementId)
                    && substr($orderIncrementId, -strlen($testOrderSurfix)) == $testOrderSurfix
                ) {
                    $orderIncrementId = substr($orderIncrementId, 0, strlen($orderIncrementId) - strlen($testOrderSurfix));
                }
            }
        }

        return $this->_order->loadByIncrementId($orderIncrementId);
    }
}
