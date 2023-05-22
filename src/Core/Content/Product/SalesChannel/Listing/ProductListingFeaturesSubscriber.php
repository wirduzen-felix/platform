<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    final public const DEFAULT_SEARCH_SORT = 'score';

    final public const PROPERTY_GROUP_IDS_REQUEST_PARAM = 'property-whitelist';
    final public const ALREADY_HANDLED = 'already-handled';

    /**
     * @internal
     */
    public function __construct(
        private readonly ListingFeatures $listingFeatures
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => [
                ['handleListingRequest', 100],
                ['handleFlags', -100],
            ],
            ProductSuggestCriteriaEvent::class => [
                ['handleFlags', -100],
            ],
            ProductSearchCriteriaEvent::class => [
                ['handleSearchRequest', 100],
                ['handleFlags', -100],
            ],
            // todo Call new service inside this listeners
            // todo Call new service where event are dispatched
            // todo Implement new functions inside new service
            // todo add feature flag
            ProductListingResultEvent::class => [
                ['handleResult', 100],
                ['removeScoreSorting', -100],
            ],
            ProductSearchResultEvent::class => 'handleResult',
        ];
    }

    public function handleFlags(ProductListingCriteriaEvent $event): void
    {
        if(Feature::isActive("v6.6.0.0")){
            return;
        }

        $request = $event->getRequest();
        $criteria = $event->getCriteria();

        $this->listingFeatures->handleFlags($request, $criteria);
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        if(Feature::isActive("v6.6.0.0")){
            return;
        }

        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $context = $event->getSalesChannelContext();

        $this->listingFeatures->handleListingRequest($request, $criteria, $context);
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        if(Feature::isActive("v6.6.0.0")){
            return;
        }

        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $context = $event->getSalesChannelContext();

        $this->listingFeatures->handleSearchRequest($request, $criteria, $context);
    }

    // Fixme: move to ListingFeatures.php
    public function handleResult(ProductListingResultEvent $event): void
    {
        Profiler::trace('product-listing::feature-subscriber', function () use ($event): void {
            $this->groupOptionAggregations($event);

            $this->addCurrentFilters($event);

            $result = $event->getResult();

            /** @var ProductSortingCollection $sortings */
            $sortings = $result->getCriteria()->getExtension('sortings');
            $currentSortingKey = $this->getCurrentSorting($sortings, $event->getRequest())->getKey();

            $result->setSorting($currentSortingKey);

            $result->setAvailableSortings($sortings);

            $result->setPage($this->getPage($event->getRequest()));

            $result->setLimit($this->getLimit($event->getRequest(), $event->getSalesChannelContext()));
        });
    }

    // Fixme: Move to ListingFeatures.php
    public function removeScoreSorting(ProductListingResultEvent $event): void
    {
        $sortings = $event->getResult()->getAvailableSortings();

        $defaultSorting = $sortings->getByKey(self::DEFAULT_SEARCH_SORT);
        if ($defaultSorting !== null) {
            $sortings->remove($defaultSorting->getId());
        }

        $event->getResult()->setAvailableSortings($sortings);
    }
}
