<?php

use Pimcore\Bundle\OpenSearchClientBundle\PimcoreOpenSearchClientBundle;

return [
    //Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    \Pimcore\Bundle\ApplicationLoggerBundle\PimcoreApplicationLoggerBundle::class => ['all' => true],
    Elements\Bundle\ProcessManagerBundle\ElementsProcessManagerBundle::class => ['all' => true],
    PimcoreOpenSearchClientBundle::class => ['all' => true],
    Pimcore\Bundle\EcommerceFrameworkBundle\PimcoreEcommerceFrameworkBundle::class => ['all' => true],
];
