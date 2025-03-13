<?php

namespace App\Command;

use App\Service\OpenSearchService;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexProductsCommand extends AbstractCommand
{
    private OpenSearchService $openSearchService;
    
    public function __construct(OpenSearchService $openSearchService)
    {
        $this->openSearchService = $openSearchService;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:opensearch:reindex-products')
            ->setDescription('Reindexes all products in OpenSearch');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Starting product reindexing...');
        
        $count = $this->openSearchService->reindexAllProducts();
        
        $output->writeln("Successfully reindexed $count products.");
        return self::SUCCESS;
    }
}