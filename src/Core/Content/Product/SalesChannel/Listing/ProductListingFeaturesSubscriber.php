<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 * @deprecated tag:v6.6.0 - will be removed and replaced by ListingFeatures
 */
#[Package('inventory')]
class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    final public const DEFAULT_SEARCH_SORT = 'score';

    final public const PROPERTY_GROUP_IDS_REQUEST_PARAM = 'property-whitelist';

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
            ProductListingResultEvent::class => [
                ['handleResult', 100],
                ['removeScoreSorting', -100],
            ],
            ProductSearchResultEvent::class => 'handleResult',
        ];
    }

    public function handleFlags(ProductListingCriteriaEvent $event): void
    {
        if (Feature::isActive("v6.6.0.0")) {
            return;
        }

        $request = $event->getRequest();
        $criteria = $event->getCriteria();

        $this->listingFeatures->handleFlags($request, $criteria);
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        if (Feature::isActive("v6.6.0.0")) {
            return;
        }

        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $context = $event->getSalesChannelContext();

        $this->listingFeatures->handleListingRequest($request, $criteria, $context);
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        if (Feature::isActive("v6.6.0.0")) {
            return;
        }

        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $context = $event->getSalesChannelContext();

        $this->listingFeatures->handleSearchRequest($request, $criteria, $context);
    }

    public function handleResult(ProductListingResultEvent $event): void
    {
        if (Feature::isActive("v6.6.0.0")) {
            return;
        }

        $request = $event->getRequest();
        $result = $event->getResult();
        $context = $event->getSalesChannelContext();

        $this->listingFeatures->handleResult($request, $result, $context);
    }

    public function removeScoreSorting(ProductListingResultEvent $event): void
    {
        if (Feature::isActive("v6.6.0.0")) {
            return;
        }

        $sortings = $event->getResult()->getAvailableSortings();

        $sortings = $this->listingFeatures->removeScoreSorting($sortings);

        $event->getResult()->setAvailableSortings($sortings);
    }
}
