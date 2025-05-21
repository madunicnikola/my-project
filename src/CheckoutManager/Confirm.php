<?php

namespace App\Ecommerce\Checkout;

use Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\AbstractStep;
use Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\CheckoutStepInterface;

class Confirm extends AbstractStep implements CheckoutStepInterface
{
    const PRIVATE_NAMESPACE = 'confirm';

    public function getName(): string
    {
        return 'confirm';
    }

    public function commit(mixed $data): bool
    {
        $this->cart->setCheckoutData(self::PRIVATE_NAMESPACE, json_encode($data));
        return true;
    }

    public function getData(): mixed
    {
        $data = json_decode($this->cart->getCheckoutData(self::PRIVATE_NAMESPACE));
        return $data;
    }
}