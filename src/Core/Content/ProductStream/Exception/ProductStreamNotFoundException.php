<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('business-ops')]
class ProductStreamNotFoundException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct('Product stream with id {{ id }} was not found.', ['id' => $id]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_STREAM_PRODUCTSTREAM_NOT_FOUND';
    }
}
