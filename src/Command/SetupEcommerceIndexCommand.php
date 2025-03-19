<?php

namespace App\Command;

use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Factory;

class SetupEcommerceIndexCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('app:ecommerce:setup-index')
            ->setDescription('Sets up the index for the ecommerce framework');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Setting up index for ecommerce framework...');
        
        try {
            $indexService = Factory::getInstance()->getIndexService();
            
            $indexService->createOrUpdateIndexStructures();
            
            $output->writeln('Index structure created/updated successfully.');
            
            $output->writeln('Starting to update product indices...');
            
            $listing = new \Pimcore\Model\DataObject\Product\Listing();
            $listing->setCondition("published = 1");
            $count = 0;
            
            foreach ($listing as $product) {
                try {
                    $decorator = new \App\Ecommerce\ProductDecorator($product);
                    $indexService->updateIndex($decorator);
                    $count++;
                    
                    if ($count % 10 === 0) {
                        $output->writeln("Processed $count products...");
                    }
                } catch (\Exception $e) {
                    $output->writeln("<error>Error indexing product {$product->getId()}: {$e->getMessage()}</error>");
                }
            }
            
            $output->writeln("Index data updated successfully. Processed $count products.");
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error setting up index: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>' . $e->getTraceAsString() . '</error>');
            return self::FAILURE;
        }
    }
}