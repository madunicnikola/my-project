<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace App;

use Pimcore\Bundle\AdminBundle\PimcoreAdminBundle;
use Pimcore\Bundle\QuillBundle\PimcoreQuillBundle;
use Pimcore\HttpKernel\BundleCollection\BundleCollection;
use Pimcore\Kernel as PimcoreKernel;

class Kernel extends PimcoreKernel
{
    /**
     * Adds bundles to register to the bundle collection. The collection is able
     * to handle priorities and environment specific bundles.
     *
     */
    public function registerBundlesToCollection(BundleCollection $collection): void
    {
        if (class_exists(PimcoreAdminBundle::class)) {
            $collection->addBundle(new PimcoreAdminBundle(), 60);
        }
        if (class_exists(PimcoreQuillBundle::class)) {
            $collection->addBundle(new PimcoreQuillBundle());
        }
    }
}
