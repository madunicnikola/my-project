<?php

namespace App\EventListener;

use Pimcore\Model\DataObject\Product;
use SeoBundle\Manager\ElementMetaDataManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;

class SeoListener implements EventSubscriberInterface
{
    private ElementMetaDataManager $seoManager;

    public function __construct(ElementMetaDataManager $seoManager)
    {
        $this->seoManager = $seoManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController'
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Debug the actual methods available
        $this->debugAvailableMethods();
    }

    private function debugAvailableMethods(): void
    {
        // Get all methods of the seoManager
        $methods = get_class_methods($this->seoManager);
        
        // Log or dump available methods
        error_log('Available methods in ElementMetaDataManager:');
        foreach ($methods as $method) {
            error_log(" - $method");
        }

        // If you want to see the full class details
        try {
            $reflection = new \ReflectionClass($this->seoManager);
            $properties = $reflection->getProperties();
            
            error_log('Properties:');
            foreach ($properties as $property) {
                error_log(" - " . $property->getName());
            }
        } catch (\ReflectionException $e) {
            error_log('Reflection error: ' . $e->getMessage());
        }
    }
}