<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\FilterHandler;

use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;

final class PriceAbstractFilterHandler extends AbstractFilterHandler
{
    public function getDecorated(): AbstractFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function getFilter(Request $request): Filter
    {
        $min = $request->get('min-price');
        $max = $request->get('max-price');

        $range = [];
        if ($min !== null && $min >= 0) {
            $range[RangeFilter::GTE] = $min;
        }
        if ($max !== null && $max >= 0) {
            $range[RangeFilter::LTE] = $max;
        }

        return new Filter(
            'price',
            !empty($range),
            [new StatsAggregation('price', 'product.cheapestPrice', true, true, false, false)],
            new RangeFilter('product.cheapestPrice', $range),
            [
                'min' => (float) $request->get('min-price'),
                'max' => (float) $request->get('max-price'),
            ]
        );
    }
}
