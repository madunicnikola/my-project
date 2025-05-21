<?php
namespace App\Payment;

class PaymentInformation 
{
    private $internalPaymentId;

    public function setInternalPaymentId($id)
    {
        $this->internalPaymentId = $id;
        return $this;
    }

    public function getInternalPaymentId()
    {
        return $this->internalPaymentId;
    }
}