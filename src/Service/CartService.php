<?php
// src/Service/CartService.php

namespace App\Service;

use Pimcore\Bundle\EcommerceFrameworkBundle\Factory;
use Pimcore\Bundle\EcommerceFrameworkBundle\CartManager\CartInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\CheckoutableInterface;

class CartService
{
    public function getOrCreateCart(string $cartName = 'cart'): CartInterface
    {
        $cartManager = Factory::getInstance()->getCartManager();
        $cart = $cartManager->getCartByName($cartName);
        
        if (!$cart) {
            $cart = $cartManager->createCart(['name' => $cartName]);
        }
        
        return $cart;
    }

    public function addProductToCart(CheckoutableInterface $product, int $amount = 1, string $cartName = 'cart'): string
    {
        $cartManager = Factory::getInstance()->getCartManager();
        $cart = $this->getOrCreateCart($cartName);
        
        $itemId = $cartManager->addToCart($product, $amount, $cart->getId());
        
        return $itemId;
    }

    public function removeFromCart(string $itemId, string $cartName = 'cart'): void
    {
        $cartManager = Factory::getInstance()->getCartManager();
        $cart = $cartManager->getCartByName($cartName);
        
        if ($cart) {
            $cartManager->removeFromCart($itemId, $cart->getId());
            $cart->save();
        }
    }

    public function updateCartItemQuantity(string $itemId, int $amount, string $cartName = 'cart'): void
    {
        $cartManager = Factory::getInstance()->getCartManager();
        $cart = $cartManager->getCartByName($cartName);
        
        if ($cart) {
            foreach ($cart->getItems() as $item) {
                if ($item->getItemKey() === $itemId) {
                    $item->setCount($amount);
                    break;
                }
            }
            $cart->save();
        }
    }

    public function getCartTotals(string $cartName = 'cart'): array
    {
        $cart = $this->getOrCreateCart($cartName);
        $calculator = $cart->getPriceCalculator();
        
        return [
            'subTotal' => $calculator->getSubTotal(),
            'modifications' => $calculator->getPriceModifications(),
            'grandTotal' => $calculator->getGrandTotal()
        ];
    }
    
    public function clearCart(string $cartName = 'cart'): void
    {
        $cartManager = Factory::getInstance()->getCartManager();
        $cart = $cartManager->getCartByName($cartName);
        
        if ($cart) {
            $cart->clear();
            $cart->save();
        }
    }
    
    public function setCheckoutTenant(string $tenant = 'default'): void
    {
        $environment = Factory::getInstance()->getEnvironment();
        $environment->setCurrentCheckoutTenant($tenant);
        $environment->save();
    }
}