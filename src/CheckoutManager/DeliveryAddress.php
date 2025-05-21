<?php

namespace App\CheckoutManager;

use Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\AbstractStep;
use Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\CheckoutStepInterface;

class DeliveryAddress extends AbstractStep implements CheckoutStepInterface
{
    const PRIVATE_NAMESPACE = 'delivery_address';

    public function getName(): string
    {
        return 'deliveryaddress';
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