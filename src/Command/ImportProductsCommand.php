<?php

namespace App\Command;

use Elements\Bundle\ProcessManagerBundle\ExecutionTrait;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject\Service;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Fieldcollection\Data\Contact;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Data\QuantityValue;
use Pimcore\Model\DataObject\Fieldcollection;
use Pimcore\Model\DataObject\Data\Link;

class ImportProductsCommand extends AbstractCommand
{
    use ExecutionTrait;

    protected function configure()
    {
        $this
            ->setName('app:import:products')
            ->setDescription('Imports/Updates Products from an XLSX file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dump('Starting product import...');

        $xlsxFilePath = PIMCORE_PROJECT_ROOT . '/public/imports/products_import.xlsx';
        if (!file_exists($xlsxFilePath)) {
            $this->dump('File not found: ' . $xlsxFilePath);
            return self::FAILURE;
        }

        try {
            $spreadsheet = IOFactory::load($xlsxFilePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            for ($row = 2; $row <= $highestRow; $row++) {
                $name                = $sheet->getCell('A' . $row)->getValue();
                $description         = $sheet->getCell('B' . $row)->getValue();
                $imagePath           = $sheet->getCell('C' . $row)->getValue();
                $categoriesCsv       = $sheet->getCell('D' . $row)->getValue();
                $sku                 = $sheet->getCell('E' . $row)->getValue();
                $price               = (float) $sheet->getCell('F' . $row)->getValue();
                $stock               = (int) $sheet->getCell('G' . $row)->getValue();
                $status              = $sheet->getCell('H' . $row)->getValue();
                $brand               = $sheet->getCell('I' . $row)->getValue();
                $technicalAttributes = $sheet->getCell('J' . $row)->getValue();
                $contactString       = $sheet->getCell('K' . $row)->getValue();

                if (!$sku) {
                    $this->dump("Row $row: Missing SKU, skipping...");
                    continue;
                }
                if (!$name) {
                    $this->dump("Row $row: Missing name, skipping...");
                    continue;
                }

                $product = Product::getBySku($sku, 1);
                if (!$product instanceof Product) {
                    $product = new Product();
                    $safeKey = Service::getValidKey($sku, 'object');
                    $product->setKey($safeKey);

                    $productsFolder = Service::createFolderByPath('/Products');
                    $product->setParent($productsFolder);
                }

                $parts = array_map('trim', explode('x', $technicalAttributes));

                if (isset($parts[0]) && preg_match('/(\d+(?:\.\d+)?)/', $parts[0], $matches)) {
                    $widthNumeric = (float)$matches[1];
                } else {
                    $widthNumeric = 0;
                }

                if (isset($parts[1]) && preg_match('/(\d+(?:\.\d+)?)/', $parts[1], $matches)) {
                    $heightNumeric = (float)$matches[1];
                } else {
                    $heightNumeric = 0;
                }
                
                $unitId = 1;

                $widthValue  = new QuantityValue($widthNumeric,  $unitId);
                $heightValue = new QuantityValue($heightNumeric, $unitId);

                $store = $product->getTechnicalAttributes();
                if (!$store instanceof Classificationstore) {
                    $store = new Classificationstore();
                }

                $store->setValue('width',  $widthValue);
                $store->setValue('height', $heightValue);
                $store->setActiveGroups([6 => true]);

                $brandObj = Brand::getByPath('/Brands/' . $brand);
                if (!$brandObj instanceof Brand) {
                    $safeBrandKey = Service::getValidKey($brand, 'object');
                    $brandObj = new Brand();
                    $brandObj->setKey($safeBrandKey);

                    $brandsFolder = Service::createFolderByPath('/Brands');
                    $brandObj->setParent($brandsFolder);

                    $brandObj->setName($brand);

                    $brandObj->setPublished(true);
                    $brandObj->save();
                }

                $contactParts = array_map('trim', explode('|', $contactString));

                $contactsCollection = $product->getContacts();
                if (!$contactsCollection instanceof Fieldcollection) {
                    $contactsCollection = new Fieldcollection();
                } else {
                    $contactsCollection->setItems([]);
                }

                $linkObj = new Link();
                $linkObj->setPath($contactParts[3] ?? '');

                $contactItem = new Contact();
                $contactItem->setEmail($contactParts[0] ?? '');
                $contactItem->setPhone($contactParts[1] ?? '');
                $contactItem->setFax($contactParts[2] ?? '');
                $contactItem->setWebsite($linkObj);
                $contactItem->setContactType($contactParts[4] ?? '');

                $contactsCollection->add($contactItem);

                $product->setName($name);
                $product->setDescription($description);
                $product->setSku($sku);
                $product->setPrice($price);
                $product->setStock($stock);
                $product->setStatus($status);
                $product->setBrand($brandObj);
                $product->setTechnicalAttributes($store);
                $product->setContacts($contactsCollection);

                if ($imagePath) {
                    $asset = Asset::getByPath('/' . ltrim($imagePath, '/'));
                    if ($asset instanceof Asset\Image) {
                        $product->setImage($asset);
                    }
                }

                $catObjects = [];

                if (!empty($categoriesCsv)) {
                    $catNames = array_map('trim', explode(',', $categoriesCsv));
                    foreach ($catNames as $catName) {
                        if (!$catName) {
                            continue;
                        }

                        $catNameKey = Service::getValidKey($catName, 'object');
                        $catObj = Category::getByPath('/Categories/' . $catNameKey);
                        if (!$catObj instanceof Category) {
                            $catFolder = Service::createFolderByPath('/Categories');
                            $catObj = new Category();
                            $catObj->setKey($catNameKey);
                            $catObj->setParent($catFolder);
                            $catObj->setPublished(true);
                            $catObj->save();
                        }
                        $catObjects[] = $catObj;
                    }
                }

                $product->setCategories($catObjects);

                $product->setPublished(true);
                $product->save();
                $this->dump("Row $row: SKU=$sku => Imported/Updated with categories: " . $categoriesCsv);
            }
        } catch (\Exception $e) {
            $this->dump("Error: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->dump('Product import completed.');
        return self::SUCCESS;
    }
}
