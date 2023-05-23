<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\FilterHandler;

use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractFilterHandler
{
    abstract public function getDecorated(): self;

    public function getFilter(Request $request): Filter
    {
        return $this->getDecorated()->getFilter($request);
    }

    public function processFilter(ProductListingResult $result, SalesChannelContext $context): void
    {
    }
}
