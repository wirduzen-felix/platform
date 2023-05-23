<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\FilterHandler;

use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;

use function array_filter;
use function explode;

final class ManufacturerFilterHandler extends AbstractFilterHandler
{
    public function getDecorated(): AbstractFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function getFilter(Request $request): Filter
    {
        $ids = $this->getManufacturerIds($request);

        return new Filter(
            'manufacturer',
            !empty($ids),
            [new EntityAggregation('manufacturer', 'product.manufacturerId', 'product_manufacturer')],
            new EqualsAnyFilter('product.manufacturerId', $ids),
            $ids
        );
    }

    /**
     * @return list<string>
     */
    private function getManufacturerIds(Request $request): array
    {
        $ids = $request->query->get('manufacturer', '');
        if ($request->isMethod(Request::METHOD_POST)) {
            $ids = $request->request->get('manufacturer', '');
        }

        if (\is_string($ids)) {
            $ids = explode('|', $ids);
        }

        /** @var list<string> $ids */
        $ids = array_filter((array) $ids);

        return $ids;
    }
}
