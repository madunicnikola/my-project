<?php
// src/Controller/CartController.php

namespace App\Controller;

use App\Service\CartService;
use Pimcore\Bundle\EcommerceFrameworkBundle\Factory;
use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends FrontendController
{
    /**
     * @Route("/cart", name="cart")
     */
    public function cartAction(CartService $cartService): Response
    {
        $cart = $cartService->getOrCreateCart();
        
        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'totals' => $cartService->getCartTotals()
        ]);
    }
    
    /**
     * @Route("/cart/add/{productId}", name="cart_add_product")
     */
    public function addProductAction(Request $request, CartService $cartService, int $productId): Response
    {
        $product = \Pimcore\Model\DataObject\Product::getById($productId);
        
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }
        
        $quantity = (int)$request->get('quantity', 1);
        
        $cartService->addProductToCart($product, $quantity);
        
        return $this->redirectToRoute('cart');
    }
    
    /**
    * @Route("/cart/update-item", name="cart-update-item", methods={"POST"})
    */
    public function updateItemAction(Request $request, CartService $cartService): Response
    {
        $itemId = $request->request->get('itemKey');
        $quantity = (int)$request->request->get('amount', 1);
        
        $cartService->updateCartItemQuantity($itemId, $quantity);
        
        return $this->redirectToRoute('cart');
    }

    /**
    * @Route("/cart/remove/{itemKey}", name="shop-cart-remove-item")
    */
    public function removeItemAction(CartService $cartService, string $itemKey): Response
    {
        $cartService->removeFromCart($itemKey);
        
        return $this->redirectToRoute('cart');
    }

    /**
    * @Route("/cart/clear", name="shop-cart-clear")
    */
    public function clearCartAction(CartService $cartService): Response
    {
        $cartService->clearCart();
        
        return $this->redirectToRoute('cart');
    }
    /**
    * @Route("/cart/set-tenant/{tenant}", name="cart_set_tenant")
    */
    public function setTenantAction(CartService $cartService, string $tenant): Response
    {
        $cartService->setCheckoutTenant($tenant);
        
        return $this->redirectToRoute('cart');
    }
}