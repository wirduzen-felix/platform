<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\FilterHandler;

use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;

final class ShippingFreeAbstractFilterCreator extends AbstractFilterHandler
{
    public function getDecorated(): AbstractFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function getFilter(Request $request): Filter
    {
        $filtered = (bool)$request->get('shipping-free', false);

        return new Filter(
            'shipping-free',
            $filtered === true,
            [
                new FilterAggregation(
                    'shipping-free-filter',
                    new MaxAggregation('shipping-free', 'product.shippingFree'),
                    [new EqualsFilter('product.shippingFree', true)]
                ),
            ],
            new EqualsFilter('product.shippingFree', true),
            $filtered
        );
    }
}
