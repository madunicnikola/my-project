<?php

namespace App\Service;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Pimcore\Model\DataObject\Product;
use Psr\Log\LoggerInterface;
use App\Ecommerce\ProductDecorator;
use Pimcore\Bundle\EcommerceFrameworkBundle\Factory;
use Pimcore\Model\DataObject\Product\Listing;

class OpenSearchService
{
    private Client $client;
    private string $indexName;
    private LoggerInterface $logger;

    public function __construct(
        string $openSearchHost,
        string $openSearchPort,
        string $openSearchIndex,
        LoggerInterface $logger
    ) {
        $this->indexName = $openSearchIndex;
        $this->logger = $logger;
        
        try {
            $host = "$openSearchHost:$openSearchPort";
            $this->logger->info("Connecting to OpenSearch at: $host");
            
            $this->client = ClientBuilder::create()
                ->setHosts([$host])
                ->setSSLVerification(false)
                ->setBasicAuthentication('admin', 'YouongPrStrassword123!')
                ->setSSLVerification(false)
                ->build();
                
            $info = $this->client->info();
            $this->logger->info("OpenSearch connection successful", ['version' => $info['version']['number'] ?? 'unknown']);
            
            $this->createIndexIfNotExists();
        } catch (\Exception $e) {
            $this->logger->error("OpenSearch connection failed: " . $e->getMessage(), [
                'host' => $openSearchHost,
                'port' => $openSearchPort,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    private function createIndexIfNotExists(): void
    {
        try {
            $exists = $this->client->indices()->exists(['index' => $this->indexName]);
            
            if (!$exists) {
                $response = $this->client->indices()->create([
                    'index' => $this->indexName,
                    'body' => [
                        'mappings' => [
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'sku' => ['type' => 'keyword'],
                                'name' => ['type' => 'text', 'analyzer' => 'standard'],
                                'description' => ['type' => 'text', 'analyzer' => 'standard'],
                                'price' => ['type' => 'float'],
                                'stock' => ['type' => 'integer'],
                                'status' => ['type' => 'keyword'],
                                'brand' => ['type' => 'keyword'],
                                'categoryNames' => ['type' => 'keyword'],
                                'width' => ['type' => 'float'],
                                'height' => ['type' => 'float'],
                                'imagePath' => ['type' => 'keyword'],
                                'programTitle' => ['type' => 'text'],
                                'programDescription' => ['type' => 'text']
                            ]
                        ],
                        'settings' => [
                            'number_of_shards' => 1,
                            'number_of_replicas' => 1
                        ]
                    ]
                ]);
                
                $this->logger->info("Created index {$this->indexName}", [
                    'response' => $response
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error("Error creating index: " . $e->getMessage(), [
                'index' => $this->indexName,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    public function indexProduct(Product $product): bool
    {
        try {
            // First try to use the ecommerce framework for indexing
            try {
                $indexService = Factory::getInstance()->getIndexService();
                $decorator = new ProductDecorator($product);
                $indexService->updateIndex($decorator);
                
                $this->logger->info("Indexed product via Ecommerce Framework", [
                    'sku' => $product->getSku(),
                    'id' => $product->getId()
                ]);
                
                return true;
            } catch (\Exception $e) {
                // Fall back to direct OpenSearch indexing if ecommerce framework fails
                $this->logger->warning("Ecommerce Framework indexing failed, falling back to direct indexing: " . $e->getMessage());
            }
            
            // Original direct indexing code
            $width = null;
            $height = null;
            
            try {
                if ($product->getTechnicalAttributes()) {
                    $store = $product->getTechnicalAttributes();
                    $widthValue = $store->getValue('width');
                    $heightValue = $store->getValue('height');
                    
                    if ($widthValue) {
                        $width = $widthValue->getValue();
                    }
                    
                    if ($heightValue) {
                        $height = $heightValue->getValue();
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error processing technical attributes: ' . $e->getMessage());
            }
            
            $programTitle = '';
            $programDescription = '';
            $imagePath = '';
            
            try {
                if ($product->getProgramItem() && !empty($product->getProgramItem())) {
                    $programItems = $product->getProgramItem();
                    
                    if (count($programItems) > 0) {
                        $programItem = $programItems[0];
                        
                        if (isset($programItem['localizedfields'])) {
                            $localizedFields = $programItem['localizedfields'];
                            
                            if (is_object($localizedFields) && method_exists($localizedFields, 'getData')) {
                                $localizedData = $localizedFields->getData();
                                
                                if (is_array($localizedData) && isset($localizedData['en'])) {
                                    $programData = $localizedData['en'];
                                    $programTitle = $programData['programTitle'] ?? '';
                                    $programDescription = $programData['programDescription'] ?? '';
                                }
                            }
                        }
                        
                        if (isset($programItem['programImages'])) {
                            $programImages = $programItem['programImages'];
                            
                            if (is_object($programImages) && method_exists($programImages, 'getData')) {
                                $programImage = $programImages->getData();
                                
                                if ($programImage instanceof \Pimcore\Model\Asset\Image) {
                                    $imagePath = $programImage->getFullPath();
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error processing program items: ' . $e->getMessage(), [
                    'sku' => $product->getSku(),
                    'exception' => $e
                ]);
            }
            
            $brandName = '';
            if ($product->getBrand()) {
                $brandName = $product->getBrand()->getName();
            }
            
            $categoryNames = [];
            if (is_array($product->getCategoryNames())) {
                $categoryNames = $product->getCategoryNames();
            }
            
            $document = [
                'id' => (int)$product->getId(),
                'sku' => (string)$product->getSku(),
                'name' => (string)$product->getName(),
                'description' => (string)$product->getDescription(),
                'price' => (float)$product->getPrice(),
                'stock' => (int)$product->getStock(),
                'status' => (string)$product->getStatus(),
                'brand' => (string)$brandName,
                'categoryNames' => $categoryNames,
                'width' => $width !== null ? (float)$width : null,
                'height' => $height !== null ? (float)$height : null,
                'imagePath' => (string)$imagePath,
                'programTitle' => (string)$programTitle,
                'programDescription' => (string)$programDescription
            ];
            
            $response = $this->client->index([
                'index' => $this->indexName,
                'id' => (string)$product->getId(),
                'body' => $document
            ]);
            
            $this->logger->info("Indexed product directly", [
                'sku' => $product->getSku(),
                'id' => $product->getId()
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error indexing product: ' . $e->getMessage(), [
                'sku' => $product->getSku(),
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function deleteProduct(int $productId): bool
    {
        try {
            $this->client->delete([
                'index' => $this->indexName,
                'id' => (string)$productId
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting product from index: ' . $e->getMessage(), [
                'productId' => $productId,
                'exception' => $e
            ]);
            return false;
        }
    }
    
    public function searchProducts(array $filters = [], int $page = 1, int $limit = 10): array
    {
        try {
            $query = [
                'from' => ($page - 1) * $limit,
                'size' => $limit,
                'query' => [
                    'bool' => [
                        'must' => []
                    ]
                ],
                'sort' => [
                    ['_score' => ['order' => 'desc']]
                ]
            ];
            
            if (!empty($filters['category'])) {
                $query['query']['bool']['must'][] = [
                    'term' => ['categoryNames' => $filters['category']]
                ];
            }
            
            if (!empty($filters['minPrice']) || !empty($filters['maxPrice'])) {
                $range = [];
                
                if (!empty($filters['minPrice'])) {
                    $range['gte'] = (float)$filters['minPrice'];
                }
                
                if (!empty($filters['maxPrice'])) {
                    $range['lte'] = (float)$filters['maxPrice'];
                }
                
                $query['query']['bool']['must'][] = [
                    'range' => ['price' => $range]
                ];
            }
            
            if (empty($query['query']['bool']['must'])) {
                $query['query'] = ['match_all' => new \stdClass()];
            }
            
            $this->logger->debug('Executing search query', [
                'query' => $query,
                'index' => $this->indexName
            ]);
            
            $results = $this->client->search([
                'index' => $this->indexName,
                'body' => $query
            ]);
            
            $products = [];
            $totalCount = $results['hits']['total']['value'] ?? 0;
            
            foreach ($results['hits']['hits'] as $hit) {
                $products[] = $hit['_source'];
            }
            
            return [
                'products' => $products,
                'totalCount' => $totalCount
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error searching products: ' . $e->getMessage(), [
                'filters' => $filters,
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'products' => [],
                'totalCount' => 0
            ];
        }
    }
    
    public function searchFullText(string $searchTerm, array $filters = [], int $page = 1, int $limit = 10): array
    {
        try {
            $query = [
                'from' => ($page - 1) * $limit,
                'size' => $limit,
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $searchTerm,
                                    'fields' => ['name^3', 'description^2', 'programTitle', 'programDescription', 'brand']
                                ]
                            ]
                        ],
                        'filter' => []
                    ]
                ],
                'sort' => [
                    ['_score' => ['order' => 'desc']]
                ]
            ];
            
            if (!empty($filters['category'])) {
                $query['query']['bool']['filter'][] = [
                    'term' => ['categoryNames' => $filters['category']]
                ];
            }
            
            if (!empty($filters['minPrice']) || !empty($filters['maxPrice'])) {
                $range = [];
                
                if (!empty($filters['minPrice'])) {
                    $range['gte'] = (float)$filters['minPrice'];
                }
                
                if (!empty($filters['maxPrice'])) {
                    $range['lte'] = (float)$filters['maxPrice'];
                }
                
                $query['query']['bool']['filter'][] = [
                    'range' => ['price' => $range]
                ];
            }
            
            $this->logger->debug('Executing full text search query', [
                'query' => $query,
                'index' => $this->indexName,
                'searchTerm' => $searchTerm
            ]);
            
            $results = $this->client->search([
                'index' => $this->indexName,
                'body' => $query
            ]);
            
            $products = [];
            $totalCount = $results['hits']['total']['value'] ?? 0;
            
            foreach ($results['hits']['hits'] as $hit) {
                $products[] = $hit['_source'];
            }
            
            return [
                'products' => $products,
                'totalCount' => $totalCount
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error searching products by text: ' . $e->getMessage(), [
                'searchTerm' => $searchTerm,
                'filters' => $filters,
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'products' => [],
                'totalCount' => 0
            ];
        }
    }
    
    public function reindexAllProducts(): int
    {
        $count = 0;
        
        $listing = new Listing();
        $listing->setCondition("published = 1");
        $listing->setObjectTypes([Product::OBJECT_TYPE_OBJECT]);
        
        foreach ($listing as $product) {
            try {
                if ($this->indexProduct($product)) {
                    $count++;
                }
            } catch (\Exception $e) {
                $this->logger->error('Error reindexing product: ' . $e->getMessage(), [
                    'sku' => $product->getSku()
                ]);
            }
        }
        
        return $count;
    }
    
    public function getClient(): Client
    {
        return $this->client;
    }
    
    public function getIndexName(): string
    {
        return $this->indexName;
    }

    /**
     * Gets the product list from the ecommerce framework
     * 
     * @return mixed
     */
    public function getProductList()
    {
        return Factory::getInstance()->getIndexService()->getProductListForTenant('default');
    }
    
    /**
     * Gets the filter service from the ecommerce framework
     * 
     * @return mixed
     */
    public function getFilterService()
    {
        return Factory::getInstance()->getFilterService();
    }
}