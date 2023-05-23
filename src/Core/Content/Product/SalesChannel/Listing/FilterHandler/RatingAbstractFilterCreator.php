<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\FilterHandler;

use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;

final class RatingAbstractFilterCreator extends AbstractFilterHandler
{
    public function getDecorated(): AbstractFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function getFilter(Request $request): Filter
    {
        $filtered = $request->get('rating');

        return new Filter(
            'rating',
            $filtered !== null,
            [
                new FilterAggregation(
                    'rating-exists',
                    new MaxAggregation('rating', 'product.ratingAverage'),
                    [new RangeFilter('product.ratingAverage', [RangeFilter::GTE => 0])]
                ),
            ],
            new RangeFilter('product.ratingAverage', [
                RangeFilter::GTE => (int) $filtered,
            ]),
            $filtered
        );
    }
}
