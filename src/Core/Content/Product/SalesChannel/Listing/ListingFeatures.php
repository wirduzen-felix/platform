<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent;
use Shopware\Core\Content\Product\SalesChannel\Exception\ProductSortingNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Listing\FilterHandler\AbstractFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ListingFeatures
{
    final public const DEFAULT_SEARCH_SORT = 'score';

    final public const PROPERTY_GROUP_IDS_REQUEST_PARAM = 'property-whitelist';

    // todo implement abstract class

    public function __construct(
        private readonly EntityRepository $sortingRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $dispatcher,
        /** @var iterable<AbstractFilterHandler> $filterCreators */
        private readonly iterable $filterCreators
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    public function handleFlags(Request $request, Criteria $criteria): void
    {
        if ($request->get('no-aggregations')) {
            $criteria->resetAggregations();
        }

        if ($request->get('only-aggregations')) {
            // set limit to zero to fetch no products.
            $criteria->setLimit(0);

            // no total count required
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

            // sorting and association are only required for the product data
            $criteria->resetSorting();
            $criteria->resetAssociations();
        }
    }

    public function handleSearchRequest(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$request->get('order')) {
            $request->request->set('order', self::DEFAULT_SEARCH_SORT);
        }

        $this->handlePagination($request, $criteria, $context);

        $this->handleFilters($request, $criteria, $context);

        $this->handleSorting($request, $criteria, $context);
    }

    private function handlePagination(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $limit = $this->getLimit($request, $context);

        $page = $this->getPage($request);

        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }

    private function getLimit(Request $request, SalesChannelContext $context): int
    {
        $limit = $request->query->getInt('limit', 0);

        if ($request->isMethod(Request::METHOD_POST)) {
            $limit = $request->request->getInt('limit', $limit);
        }

        $limit = $limit > 0 ? $limit : $this->systemConfigService->getInt('core.listing.productsPerPage',
            $context->getSalesChannel()->getId());

        return $limit <= 0 ? 24 : $limit;
    }

    private function getPage(Request $request): int
    {
        $page = $request->query->getInt('p', 1);

        if ($request->isMethod(Request::METHOD_POST)) {
            $page = $request->request->getInt('p', $page);
        }

        return $page <= 0 ? 1 : $page;
    }

    public function removeScoreSorting(ProductSortingCollection $sortings): ProductSortingCollection
    {
        $defaultSorting = $sortings->getByKey(self::DEFAULT_SEARCH_SORT);
        if ($defaultSorting !== null) {
            $sortings->remove($defaultSorting->getId());
        }

        return $sortings;
    }

    private function handleFilters(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addAssociation('manufacturer');

        $filters = $this->getFilters($request, $context);

        $aggregations = $this->getAggregations($request, $filters);

        foreach ($aggregations as $aggregation) {
            $criteria->addAggregation($aggregation);
        }

        foreach ($filters as $filter) {
            if ($filter->isFiltered()) {
                $criteria->addPostFilter($filter->getFilter());
            }
        }

        $criteria->addExtension('filters', $filters);
    }

    private function getFilters(Request $request, SalesChannelContext $context): FilterCollection
    {
        $filters = new FilterCollection();
        foreach ($this->filterCreators as $filterCreator) {
            $filters->add($filterCreator->getFilter($request));
        }

        if (!$request->request->get('manufacturer-filter', true)) {
            $filters->remove('manufacturer');
        }

        if (!$request->request->get('price-filter', true)) {
            $filters->remove('price');
        }

        if (!$request->request->get('rating-filter', true)) {
            $filters->remove('rating');
        }

        if (!$request->request->get('shipping-free-filter', true)) {
            $filters->remove('shipping-free');
        }

        $event = new ProductListingCollectFilterEvent($request, $filters, $context);
        $this->dispatcher->dispatch($event);

        return $filters;
    }

    /**
     * @return array<Aggregation>
     */
    private function getAggregations(Request $request, FilterCollection $filters): array
    {
        $aggregations = [];

        if ($request->get('reduce-aggregations') === null) {
            foreach ($filters as $filter) {
                $aggregations = array_merge($aggregations, $filter->getAggregations());
            }

            return $aggregations;
        }

        foreach ($filters as $filter) {
            $excluded = $filters->filtered();

            if ($filter->exclude()) {
                $excluded = $excluded->blacklist($filter->getName());
            }

            foreach ($filter->getAggregations() as $aggregation) {
                if ($aggregation instanceof FilterAggregation) {
                    $aggregation->addFilters($excluded->getFilters());

                    $aggregations[] = $aggregation;

                    continue;
                }

                $aggregation = new FilterAggregation(
                    $aggregation->getName(),
                    $aggregation,
                    $excluded->getFilters()
                );

                $aggregations[] = $aggregation;
            }
        }

        return $aggregations;
    }

    private function handleSorting(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        /** @var ProductSortingCollection $sortings */
        $sortings = $criteria->getExtension('sortings') ?? new ProductSortingCollection();
        $sortings->merge($this->getAvailableSortings($request, $context->getContext()));

        $currentSorting = $this->getCurrentSorting($sortings, $request);

        $criteria->addSorting(
            ...$currentSorting->createDalSorting()
        );

        $criteria->addExtension('sortings', $sortings);
    }

    private function getAvailableSortings(Request $request, Context $context): ProductSortingCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-listing::load-sortings');
        $availableSortings = $request->get('availableSortings');
        $availableSortingsFilter = [];

        if ($availableSortings) {
            arsort($availableSortings, \SORT_DESC | \SORT_NUMERIC);
            $availableSortingsFilter = array_keys($availableSortings);

            $criteria->addFilter(new EqualsAnyFilter('key', $availableSortingsFilter));
        }

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('priority', 'DESC'))
        ;

        /** @var ProductSortingCollection $sortings */
        $sortings = $this->sortingRepository->search($criteria, $context)->getEntities();

        if ($availableSortings) {
            $sortings->sortByKeyArray($availableSortingsFilter);
        }

        return $sortings;
    }

    private function getCurrentSorting(ProductSortingCollection $sortings, Request $request): ProductSortingEntity
    {
        $key = $request->get('order');

        $sorting = $sortings->getByKey($key);
        if ($sorting !== null) {
            return $sorting;
        }

        throw new ProductSortingNotFoundException($key);
    }

    public function handleListingRequest(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$request->get('order')) {
            $request->request->set('order', $this->getSystemDefaultSorting($context));
        }

        $criteria->addAssociation('options');

        $this->handlePagination($request, $criteria, $context);

        $this->handleFilters($request, $criteria, $context);

        $this->handleSorting($request, $criteria, $context);
    }

    private function getSystemDefaultSorting(SalesChannelContext $context): string
    {
        return $this->systemConfigService->getString(
            'core.listing.defaultSorting',
            $context->getSalesChannel()->getId()
        );
    }

    public function handleResult(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        Profiler::trace('product-listing::feature-subscriber', function () use ($request, $result, $context): void {
            foreach ($this->filterCreators as $filterCreator) {
                $filterCreator->processFilter($result, $context);
            }

            $this->addCurrentFilters($result);

            /** @var ProductSortingCollection $sortings */
            $sortings = $result->getCriteria()->getExtension('sortings');
            $currentSortingKey = $this->getCurrentSorting($sortings, $request)->getKey();

            $result->setSorting($currentSortingKey);

            $result->setAvailableSortings($sortings);

            $result->setPage($this->getPage($request));

            $result->setLimit($this->getLimit($request, $context));
        });
    }

    private function addCurrentFilters(ProductListingResult $result): void
    {
        $filters = $result->getCriteria()->getExtension('filters');
        if (!$filters instanceof FilterCollection) {
            return;
        }

        foreach ($filters as $filter) {
            $result->addCurrentFilter($filter->getName(), $filter->getValues());
        }
    }
}
