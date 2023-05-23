<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\FilterHandler;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function array_column;
use function array_filter;
use function array_unique;
use function explode;

final class PropertyAbstractFilterHandler extends AbstractFilterHandler
{
    final public const PROPERTY_GROUP_IDS_REQUEST_PARAM = 'property-whitelist';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $optionRepository
    )
    {
    }

    public function getDecorated(): AbstractFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function getFilter(Request $request): Filter
    {
        $ids = $this->getPropertyIds($request);
        $groupIds = $request->request->all(self::PROPERTY_GROUP_IDS_REQUEST_PARAM);

        $propertyAggregation = new TermsAggregation('properties', 'product.properties.id');

        $optionAggregation = new TermsAggregation('options', 'product.options.id');

        if ($groupIds) {
            $propertyAggregation = new FilterAggregation(
                'properties-filter',
                $propertyAggregation,
                [new EqualsAnyFilter('product.properties.groupId', $groupIds)]
            );

            $optionAggregation = new FilterAggregation(
                'options-filter',
                $optionAggregation,
                [new EqualsAnyFilter('product.options.groupId', $groupIds)]
            );
        }

        if (empty($ids)) {
            return new Filter(
                'properties',
                false,
                [$propertyAggregation, $optionAggregation],
                new MultiFilter(MultiFilter::CONNECTION_OR, []),
                [],
                false
            );
        }

        $grouped = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(property_group_id)) as property_group_id, LOWER(HEX(id)) as id
             FROM property_group_option
             WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        $grouped = FetchModeHelper::group($grouped);

        $filters = [];
        foreach ($grouped as $options) {
            $options = array_column($options, 'id');

            $filters[] = new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new EqualsAnyFilter('product.optionIds', $options),
                    new EqualsAnyFilter('product.propertyIds', $options),
                ]
            );
        }

        return new Filter(
            'properties',
            true,
            [$propertyAggregation, $optionAggregation],
            new MultiFilter(MultiFilter::CONNECTION_AND, $filters),
            $ids,
            false
        );
    }

    /**
     * @return list<string>
     */
    private function getPropertyIds(Request $request): array
    {
        $ids = $request->query->get('properties', '');
        if ($request->isMethod(Request::METHOD_POST)) {
            $ids = $request->request->get('properties', '');
        }

        if (\is_string($ids)) {
            $ids = explode('|', $ids);
        }

        /** @var list<string> $ids */
        $ids = array_filter((array) $ids);

        return $ids;
    }

    public function processFilter(ProductListingResult $result, SalesChannelContext $context): void
    {
        $ids = $this->collectOptionIds($result);

        if (empty($ids)) {
            return;
        }

        $criteria = new Criteria($ids);
        $criteria->setLimit(500);
        $criteria->addAssociation('group');
        $criteria->addAssociation('media');
        $criteria->addFilter(new EqualsFilter('group.filterable', true));
        $criteria->setTitle('product-listing::property-filter');
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));

        $mergedOptions = new PropertyGroupOptionCollection();

        $repositoryIterator = new RepositoryIterator($this->optionRepository, $context->getContext(), $criteria);
        while (($optionResult = $repositoryIterator->fetch()) !== null) {
            /** @var PropertyGroupOptionCollection $entities */
            $entities = $optionResult->getEntities();

            $mergedOptions->merge($entities);
        }

        // group options by their property-group
        $grouped = $mergedOptions->groupByPropertyGroups();
        $grouped->sortByPositions();
        $grouped->sortByConfig();

        $aggregations = $result->getAggregations();

        // remove id results to prevent wrong usages
        $aggregations->remove('properties');
        $aggregations->remove('configurators');
        $aggregations->remove('options');
        /** @var EntityCollection<Entity> $grouped */
        $aggregations->add(new EntityResult('properties', $grouped));
    }

    /**
     * @return array<int, non-falsy-string>
     */
    private function collectOptionIds(ProductListingResult $result): array
    {
        $aggregations = $result->getAggregations();

        /** @var TermsResult|null $properties */
        $properties = $aggregations->get('properties');

        /** @var TermsResult|null $options */
        $options = $aggregations->get('options');

        $options = $options ? $options->getKeys() : [];
        $properties = $properties ? $properties->getKeys() : [];

        return array_unique(array_filter([...$options, ...$properties]));
    }
}
