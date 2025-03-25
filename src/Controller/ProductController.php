<?php

namespace App\Controller;

use Pimcore\Bundle\EcommerceFrameworkBundle\Factory;
use Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\ListHelper;
use Pimcore\Model\DataObject\FilterDefinition;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject\Product;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends AbstractController
{
    /**
     * @Route("/products", name="product_list")
     */
    public function listAction(Request $request, PaginatorInterface $paginator)
    {
        $filterDefinition = FilterDefinition::getByPath('/Filters/productspage');
        if (!$filterDefinition) {
            throw new \RuntimeException('No FilterDefinition found at path /Filters/productsPage');
        }

        $params = array_merge($request->query->all(), $request->attributes->all());

        if (isset($params['categoryNames'])) {
            if (!is_array($params['categoryNames'])) {
                $params['categoryNames'] = [$params['categoryNames']];
            }
            
            $params['categoryNames'] = array_map(function($val) {
                return trim(str_replace('#;#', '', $val));
            }, $params['categoryNames']);
        }

        $factory = Factory::getInstance();

        $indexService = $factory->getIndexService('OpenSearch');
        $productListing = $indexService->getProductListForCurrentTenant();

        $productArray = $productListing->getProducts(0, 10);

        $totalCount = $productListing->count();

        $filterService = $factory->getFilterService('OpenSearch');

        $listHelper = new ListHelper();
        $listHelper->setupProductList(
            $filterDefinition,
            $productListing,
            $params,
            $filterService,
            true,
            true
        );

        $currentFilter = [];
        $currentFilter['categoryNames'] = isset($params['categoryNames']) ? $params['categoryNames'] : [];
        $currentFilter['status']        = isset($params['status']) ? $params['status'] : '';
        $currentFilter['id']            = isset($params['id']) ? $params['id'] : '';
        $currentFilter['brandName']     = isset($params['brandName']) ? $params['brandName'] : '';

        $page = $request->query->getInt('page', 1);
        $limit = $filterDefinition->getPageLimit() ?? 12;
        $products = $paginator->paginate($productListing, $page, $limit);

        return $this->render('product/list.html.twig', [
            'products'         => $products,
            'totalCount'       => $productListing->count(),
            'filterService'    => $filterService,
            'filterDefinition' => $filterDefinition,
            'currentFilter'    => $currentFilter,
            'productListing'   => $productListing,
        ]);
    }

    /**
    *   @Route("/product/{sku}", name="product_detail")
    */
    public function detailAction(string $sku): Response
    {
        $product = Product::getBySku($sku, 1);

        if (!$product instanceof Product) {
            throw $this->createNotFoundException('Product not found');
        }
        
        $factory = \Pimcore\Bundle\EcommerceFrameworkBundle\Factory::getInstance();
        $indexService = $factory->getIndexService('OpenSearch');
        $productListing = $indexService->getProductListForCurrentTenant();

        $categoryNames = [];
        if (method_exists($product, 'getCategories') && $product->getCategories()) {
            foreach ($product->getCategories() as $category) {
                $categoryNames[] = $category->getKey();
            }
        }
                
        if (!empty($categoryNames)) {
            $productListing->addCondition([
                'terms' => ['attributes.categoryNames' => $categoryNames]
            ], 'attributes.categoryNames');
        }

        $productListing->addCondition([
            'bool' => [
                'must_not' => [
                    ['term' => ['system.id' => $product->getId()]]
                ]
            ]
        ], 'system.id');
        
        $productListing->setLimit(4);

        $relatedProducts = $productListing->getProducts();
        
        return $this->render('product/detail.html.twig', [
            'product' => $product,
            'relatedProducts' => $relatedProducts
        ]);
    }
}
