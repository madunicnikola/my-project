<?php

namespace App\Command;

use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\Asset;

class ImportProductsCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('app:import:products')
            ->setDescription('Imports/Updates Products from an XLSX file, including Categories as Many-to-Many.');
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
                    $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $sku);
                    $product->setKey($safeKey);

                    $productsFolder = \Pimcore\Model\DataObject::getByPath('/Products');
                    if (!$productsFolder) {
                        $productsFolder = new Folder();
                        $productsFolder->setKey('Products');
                        $productsFolder->setParent(\Pimcore\Model\DataObject::getByPath('/'));
                        $productsFolder->save();
                    }
                    $product->setParent($productsFolder);
                }

                $store = $product->getTechnicalAttributes();
                if (!$store instanceof \Pimcore\Model\DataObject\Classificationstore) {
                    $store = new \Pimcore\Model\DataObject\Classificationstore();
                }

                $store->setValue('dimensions', $technicalAttributes);

                $product->setTechnicalAttributes($store);

                $product->setName($name);
                $product->setDescription($description);
                $product->setSku($sku);
                $product->setPrice($price);
                $product->setStock($stock);
                $product->setStatus($status);
                $product->setBrand($brand);

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

                        $catNameKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $catName);
                        $catObj = Category::getByPath('/Categories/' . $catNameKey);
                        if (!$catObj instanceof Category) {
                            $catFolder = \Pimcore\Model\DataObject::getByPath('/Categories');
                            if (!$catFolder instanceof Folder) {
                                $catFolder = new Folder();
                                $catFolder->setKey('Categories');
                                $catFolder->setParent(\Pimcore\Model\DataObject::getByPath('/'));
                                $catFolder->save();
                            }

                            $catObj = new Category();
                            $catObj->setKey($catNameKey);
                            $catObj->setParent($catFolder);
                            $catObj->save();
                        }
                        $catObjects[] = $catObj;
                    }
                }

                $product->setCategories($catObjects);

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
