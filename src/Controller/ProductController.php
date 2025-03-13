<?php

namespace App\Controller;

use App\Service\OpenSearchService;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Product;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends FrontendController
{
    /**
     * @Route("/products", name="product_list")
     */
    public function listAction(Request $request, OpenSearchService $openSearchService): Response
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
        
        $searchResults = $openSearchService->searchProducts($filters, $page, $limit);
        
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
    public function searchAction(Request $request, OpenSearchService $openSearchService): Response
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
                $searchData = $openSearchService->searchFullText($searchTerm, $filters, $page, $limit);
                $searchResults = $searchData['products'] ?? [];
                $totalCount = $searchData['totalCount'] ?? 0;
            } else {
                $searchData = $openSearchService->searchProducts($filters, $page, $limit);
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
            $this->get('logger')->error('Error in search controller: ' . $e->getMessage(), [
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
}