<?php
namespace App\Controller;

use App\Service\CartService;
use Pimcore\Bundle\EcommerceFrameworkBundle\Factory;
use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Payment\PaymentInformation;

class CheckoutController extends FrontendController
{
    /**
     * @Route("/checkout", name="checkout")
     */
    public function checkoutAction(CartService $cartService, Request $request): Response
    {
        $cart = $cartService->getOrCreateCart();
        $checkoutManager = Factory::getInstance()->getCheckoutManager($cart);
        
        return $this->render('checkout/index.html.twig', [
            'cart' => $cart,
            'checkoutManager' => $checkoutManager
        ]);
    }
    
    /**
     * @Route("/checkout/address", name="checkout_address")
     */
    public function addressAction(CartService $cartService, Request $request): Response
    {
        $cart = $cartService->getOrCreateCart();
        $manager = Factory::getInstance()->getCheckoutManager($cart);
        $step = $manager->getCheckoutStep("deliveryaddress");
        
        if ($step === null) {
            $address = new \stdClass();
            
            if ($request->isMethod('POST')) {
                $address = (object)[
                    'firstname' => $request->get('firstname'),
                    'lastname' => $request->get('lastname'),
                    'street' => $request->get('street'),
                    'zip' => $request->get('zip'),
                    'city' => $request->get('city'),
                    'country' => $request->get('country')
                ];
                
                $cart->setCheckoutData('delivery_address', json_encode($address));
                $cart->save();
                
                return $this->redirectToRoute('checkout_confirm');
            }
            
            return $this->render('checkout/address.html.twig', [
                'cart' => $cart,
                'address' => $address
            ]);
        }
        
        if ($request->isMethod('POST')) {
            $address = [
                'firstname' => $request->get('firstname'),
                'lastname' => $request->get('lastname'),
                'street' => $request->get('street'),
                'zip' => $request->get('zip'),
                'city' => $request->get('city'),
                'country' => $request->get('country')
            ];
            
            $manager->commitStep($step, (object)$address);
            $cart->save();
            
            return $this->redirectToRoute('checkout_confirm');
        }
        
        $address = $step->getData() ?: new \stdClass();
        
        return $this->render('checkout/address.html.twig', [
            'cart' => $cart,
            'address' => $address
        ]);
    }
    
    /**
     * @Route("/checkout/confirm", name="checkout_confirm")
     */
    public function confirmAction(CartService $cartService, Request $request): Response
    {
        $cart = $cartService->getOrCreateCart();
        $checkoutManager = Factory::getInstance()->getCheckoutManager($cart);
        
        $addressStep = $checkoutManager->getCheckoutStep('deliveryaddress');
        $address = $addressStep ? $addressStep->getData() : json_decode($cart->getCheckoutData('delivery_address'));
        
        if (!$address) {
            $address = new \stdClass();
        }
        
        $confirmStep = $checkoutManager->getCheckoutStep('confirm');
        
        if ($request->isMethod('POST')) {
            $paymentMethod = $request->get('payment_method', 'datatrans');
            
            $cart->setCheckoutData('payment_method', $paymentMethod);
            
            if ($confirmStep) {
                $confirmData = ['confirmed' => true];
                $checkoutManager->commitStep($confirmStep, $confirmData);
            } else {
                $cart->setCheckoutData('confirm', json_encode(['confirmed' => true]));
            }
            
            $cart->save();
            
            try {
                $paymentManager = Factory::getInstance()->getPaymentManager();
                $paymentProvider = $paymentManager->getProvider($paymentMethod);
                
                $paymentInformation = $checkoutManager->initOrderPayment();
                
                $startPaymentResponse = $checkoutManager->startOrderPaymentWithPaymentProvider($paymentProvider);
                
                if ($startPaymentResponse instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
                    return $startPaymentResponse;
                }
                
                $success = $checkoutManager->commitOrder();
                
                if ($success) {
                    $cartService->clearCart();
                    return $this->redirectToRoute('checkout_success');
                }
            } catch (\Exception $e) {
                error_log('Payment error: ' . $e->getMessage());
                
                return $this->render('checkout/error.html.twig', [
                    'cart' => $cart,
                    'errorMessage' => 'There was an error processing your payment. Please try again.'
                ]);
            }
        }
        
        $paymentProviders = [
            'datatrans' => Factory::getInstance()->getPaymentManager()->getProvider('datatrans'),
            'ogone' => Factory::getInstance()->getPaymentManager()->getProvider('ogone'),
            'mpay24' => Factory::getInstance()->getPaymentManager()->getProvider('mpay24'),
            'hobex' => Factory::getInstance()->getPaymentManager()->getProvider('hobex')
        ];
        
        $paymentProviderNames = ['datatrans', 'ogone', 'mpay24', 'hobex'];
        
        return $this->render('checkout/confirm.html.twig', [
            'cart' => $cart,
            'address' => $address,
            'paymentProviderNames' => $paymentProviderNames
        ]);
    }
    
    /**
     * @Route("/checkout/success", name="checkout_success")
     */
    public function successAction(): Response
    {
        return $this->render('checkout/success.html.twig');
    }
}