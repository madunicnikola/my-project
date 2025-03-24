<?php

namespace App\Controller;

use OpenSearch\Client;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Product;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class ProductController extends FrontendController
{
    private Client $openSearchClient;
    private LoggerInterface $logger;
    private string $indexName;

    public function __construct(Client $openSearchClient, LoggerInterface $logger)
    {
        $this->openSearchClient = $openSearchClient;
        $this->logger = $logger;
        $this->indexName = $_ENV['OPENSEARCH_INDEX'] ?? 'products';
    }

    /**
     * @Route("/products", name="product_list")
     */
    public function listAction(Request $request): Response
    {
        $category = $request->query->get('category');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 12;
        
        $filters = [
            'category' => $category,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice
        ];
        
        $searchResults = $this->searchProducts($filters, $page, $limit);
        
        $productIds = array_map(function($product) {
            return $product['id'];
        }, $searchResults['products']);
        
        $products = [];
        if (!empty($productIds)) {
            $productListing = new Product\Listing();
            $productListing->setCondition('id IN (' . implode(',', $productIds) . ')');
            
            $allProducts = $productListing->load();
            
            $productMap = [];
            foreach ($allProducts as $product) {
                $productMap[$product->getId()] = $product;
            }
            
            foreach ($productIds as $id) {
                if (isset($productMap[$id])) {
                    $products[] = $productMap[$id];
                }
            }
        }
        
        $categoryList = new Category\Listing();
        $categoryList->setOrderKey('key');
        $categoryList->setOrder('asc');
        $categories = array_map(function($category) {
            return $category->getKey();
        }, $categoryList->load());
        
        return $this->render('product/list.html.twig', [
            'products' => $products,
            'totalCount' => $searchResults['totalCount'],
            'page' => $page,
            'limit' => $limit,
            'categories' => $categories,
            'filters' => $filters
        ]);
    }
    
    /**
     * @Route("/search", name="product_search")
     */
    public function searchAction(Request $request): Response
    {
        try {
            $searchTerm = $request->query->get('q', '');
            $category = $request->query->get('category');
            $minPrice = $request->query->get('minPrice');
            $maxPrice = $request->query->get('maxPrice');
            $page = max(1, (int)$request->query->get('page', 1));
            $limit = 12;
            
            $filters = [
                'category' => $category,
                'minPrice' => $minPrice,
                'maxPrice' => $maxPrice
            ];
            
            $searchResults = [];
            $totalCount = 0;
            
            if (!empty($searchTerm)) {
                $searchData = $this->searchFullText($searchTerm, $filters, $page, $limit);
                $searchResults = $searchData['products'] ?? [];
                $totalCount = $searchData['totalCount'] ?? 0;
            } else {
                $searchData = $this->searchProducts($filters, $page, $limit);
                $searchResults = $searchData['products'] ?? [];
                $totalCount = $searchData['totalCount'] ?? 0;
            }
            
            $products = [];
            $productIds = array_map(function($product) {
                return $product['id'];
            }, $searchResults);
            
            if (!empty($productIds)) {
                $productListing = new Product\Listing();
                $productListing->setCondition('id IN (' . implode(',', $productIds) . ')');
                
                $allProducts = $productListing->load();
                
                $productMap = [];
                foreach ($allProducts as $product) {
                    $productMap[$product->getId()] = $product;
                }
                
                foreach ($productIds as $id) {
                    if (isset($productMap[$id])) {
                        $products[] = $productMap[$id];
                    }
                }
            }
            
            $categoryList = new Category\Listing();
            $categoryList->setOrderKey('key');
            $categoryList->setOrder('asc');
            $categories = array_map(function($category) {
                return $category->getKey();
            }, $categoryList->load());
            
            return $this->render('product/search.html.twig', [
                'products' => $products,
                'totalCount' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'categories' => $categories,
                'filters' => $filters,
                'searchTerm' => $searchTerm
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in search controller: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->redirect($this->generateUrl('product_list'));
        }
    }
    
    /**
     * @Route("/product/{sku}", name="product_detail")
     */
    public function detailAction(string $sku): Response
    {
        $product = Product::getBySku($sku, 1);
        
        if (!$product instanceof Product) {
            throw $this->createNotFoundException('Product not found');
        }
        
        return $this->render('product/detail.html.twig', [
            'product' => $product
        ]);
    }

    /**
     * Search products with filters
     *
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    private function searchProducts(array $filters, int $page = 1, int $limit = 12): array
    {
        try {
            $from = ($page - 1) * $limit;
            
            $query = [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match_all' => (object)[]]
                        ],
                        'filter' => []
                    ]
                ],
                'from' => $from,
                'size' => $limit,
                'sort' => [
                    ['name.keyword' => ['order' => 'asc']]
                ]
            ];
            
            // Add filters
            if (!empty($filters['category'])) {
                $query['query']['bool']['filter'][] = [
                    'nested' => [
                        'path' => 'categories',
                        'query' => [
                            'match' => [
                                'categories.name' => $filters['category']
                            ]
                        ]
                    ]
                ];
            }
            
            if (!empty($filters['minPrice'])) {
                $query['query']['bool']['filter'][] = [
                    'range' => [
                        'price' => [
                            'gte' => (float)$filters['minPrice']
                        ]
                    ]
                ];
            }
            
            if (!empty($filters['maxPrice'])) {
                $query['query']['bool']['filter'][] = [
                    'range' => [
                        'price' => [
                            'lte' => (float)$filters['maxPrice']
                        ]
                    ]
                ];
            }
            
            $params = [
                'index' => $this->indexName,
                'body' => $query
            ];
            
            $response = $this->openSearchClient->search($params);
            
            $products = [];
            $totalCount = $response['hits']['total']['value'] ?? 0;
            
            foreach ($response['hits']['hits'] as $hit) {
                $products[] = $hit['_source'];
            }
            
            return [
                'products' => $products,
                'totalCount' => $totalCount
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error searching products: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'products' => [],
                'totalCount' => 0
            ];
        }
    }

    /**
     * Search products with full text
     *
     * @param string $searchTerm
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    private function searchFullText(string $searchTerm, array $filters = [], int $page = 1, int $limit = 12): array
    {
        try {
            $from = ($page - 1) * $limit;
            
            $query = [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $searchTerm,
                                'fields' => ['name^3', 'description', 'sku', 'categories.name'],
                                'fuzziness' => 'AUTO'
                            ]
                        ],
                        'filter' => []
                    ]
                ],
                'from' => $from,
                'size' => $limit,
                'sort' => [
                    '_score',
                    ['name.keyword' => ['order' => 'asc']]
                ]
            ];
            
            // Add filters
            if (!empty($filters['category'])) {
                $query['query']['bool']['filter'][] = [
                    'nested' => [
                        'path' => 'categories',
                        'query' => [
                            'match' => [
                                'categories.name' => $filters['category']
                            ]
                        ]
                    ]
                ];
            }
            
            if (!empty($filters['minPrice'])) {
                $query['query']['bool']['filter'][] = [
                    'range' => [
                        'price' => [
                            'gte' => (float)$filters['minPrice']
                        ]
                    ]
                ];
            }
            
            if (!empty($filters['maxPrice'])) {
                $query['query']['bool']['filter'][] = [
                    'range' => [
                        'price' => [
                            'lte' => (float)$filters['maxPrice']
                        ]
                    ]
                ];
            }
            
            $params = [
                'index' => $this->indexName,
                'body' => $query
            ];
            
            $response = $this->openSearchClient->search($params);
            
            $products = [];
            $totalCount = $response['hits']['total']['value'] ?? 0;
            
            foreach ($response['hits']['hits'] as $hit) {
                $products[] = $hit['_source'];
            }
            
            return [
                'products' => $products,
                'totalCount' => $totalCount
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error searching with full text: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'products' => [],
                'totalCount' => 0
            ];
        }
    }
}