<?php

namespace LiqpayMagento\LiqPay\Model;

class WidgetData
{

    /**
     * @var string
     */
    public $data;

    /**
     * @var string
     */
    public $signature;

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @param string $data
     *
     * @return $this
     */
    public function setData(string $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return string
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     *
     * @return $this
     */
    public function setSignature(string $signature)
    {
        $this->signature = $signature;
        return $this;
    }
}
