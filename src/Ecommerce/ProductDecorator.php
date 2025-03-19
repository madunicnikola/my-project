<?php

namespace App\Ecommerce;

use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractProduct;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\PriceInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\Price;
use Pimcore\Model\DataObject\Product;

class ProductDecorator extends AbstractProduct
{
    private Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function getId(): ?int
    {
        return $this->product->getId();
    }

    public function getParentId(): ?int
    {
        return $this->product->getParentId();
    }

    public function getOSProductNumber(): string
    {
        return $this->product->getSku();
    }

    public function getOSName(): string
    {
        return $this->product->getName();
    }

    public function getOSDescription(): ?string
    {
        return $this->product->getDescription();
    }

    public function getOSPrice(int $quantityScale = 1): PriceInterface
    {
        return new Price((float)$this->product->getPrice() * $quantityScale, null, []);
    }

    public function getOSStock(): int
    {
        return (int)$this->product->getStock();
    }

    public function getOSStatus(): string
    {
        return $this->product->getStatus();
    }

    public function getOSCategoryNames(): array
    {
        return $this->product->getCategoryNames() ?? [];
    }

    public function getOSBrandName(): string
    {
        $brand = $this->product->getBrand();
        return $brand ? $brand->getName() : '';
    }
    
    public function isActive(bool $inProductList = false): bool
    {
        return $this->product->isPublished() && $this->product->getStock() > 0;
    }
    
    public function getPriceSystemName(): string
    {
        return 'default';
    }
    
    public function getClassId(): ?string
    {
        $classId = $this->product->getClassId();
        return $classId !== null ? (string)$classId : null;
    }
    
    public function getTenantConfig(): string
    {
        return 'default';
    }
    
    public function getElementType(): string
    {
        return 'object';
    }
    
    public function getCategories(): array
    {
        return $this->product->getCategories() ?? [];
    }
    
    public function getCategoryIds(): array
    {
        $categoryIds = [];
        foreach ($this->getCategories() as $category) {
            $categoryIds[] = $category->getId();
        }
        return $categoryIds;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }
    
    public function getWidth(): ?float
    {
        if ($this->product->getTechnicalAttributes()) {
            $width = $this->product->getTechnicalAttributes()->getValue('width');
            if ($width) {
                return (float)$width->getValue();
            }
        }
        return null;
    }
    
    public function getHeight(): ?float
    {
        if ($this->product->getTechnicalAttributes()) {
            $height = $this->product->getTechnicalAttributes()->getValue('height');
            if ($height) {
                return (float)$height->getValue();
            }
        }
        return null;
    }
}