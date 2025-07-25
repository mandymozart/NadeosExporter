<?php declare(strict_types=1);

namespace NadeosData\Core\Content\Example\SalesChannel;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @property EntitySearchResult $object
 */
class ExampleRouteResponse extends StoreApiResponse
{
    public function getExamples(): ProductCollection
    {
        /** @var ProductCollection $collection */
        $collection = $this->object->getEntities();

        return $collection;
    }
}
