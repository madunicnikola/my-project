<?php

namespace App\EventSubscriber;

use App\Service\OpenSearchService;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Model\DataObject\Product;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductChangeSubscriber implements EventSubscriberInterface
{
    private OpenSearchService $openSearchService;
    
    public function __construct(OpenSearchService $openSearchService)
    {
        $this->openSearchService = $openSearchService;
    }
    
    public static function getSubscribedEvents()
    {
        return [
            DataObjectEvents::POST_UPDATE => 'onProductUpdate',
            DataObjectEvents::POST_DELETE => 'onProductDelete',
        ];
    }
    
    public function onProductUpdate(DataObjectEvent $event)
    {
        $object = $event->getObject();
        
        if ($object instanceof Product) {
            $this->openSearchService->indexProduct($object);
        }
    }
    
    public function onProductDelete(DataObjectEvent $event)
    {
        $object = $event->getObject();
        
        if ($object instanceof Product) {
            $this->openSearchService->deleteProduct($object->getId());
        }
    }
}