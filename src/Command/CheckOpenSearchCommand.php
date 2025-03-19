<?php

namespace App\Command;

use OpenSearch\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckOpenSearchCommand extends Command
{
    protected static $defaultName = 'app:check-opensearch-connection';
    protected static $defaultDescription = 'Checks connection to OpenSearch server';

    private Client $openSearchClient;

    public function __construct(Client $openSearchClient = null)
    {
        parent::__construct();
        $this->openSearchClient = $openSearchClient ?? \Pimcore::getContainer()->get('pimcore.open_search_client.default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('OpenSearch Connection Check');
        
        try {
            $io->section('Connection Info');
            $transportConfig = $this->openSearchClient->transport->getConnection()->getTransportSchema();
            $io->table(
                ['Property', 'Value'],
                [
                    ['Host', $transportConfig['hosts'][0]['host'] ?? 'unknown'],
                    ['Port', $transportConfig['hosts'][0]['port'] ?? 'unknown'],
                ]
            );
            
            $io->section('Testing Connection');
            $info = $this->openSearchClient->info();
            
            $io->success('Successfully connected to OpenSearch!');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Cluster Name', $info['cluster_name'] ?? 'unknown'],
                    ['OpenSearch Version', $info['version']['number'] ?? 'unknown'],
                    ['Node Name', $info['name'] ?? 'unknown'],
                ]
            );
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to connect to OpenSearch: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}