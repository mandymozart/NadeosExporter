<?php declare(strict_types=1);

namespace NadeosData\Core\Content\Example\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
class ExampleRoute extends AbstractExampleRoute
{
    public function __construct(private readonly EntityRepository $productRepository)
    {
    }

    public function getDecorated(): AbstractExampleRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/example',
        name: 'store-api.example.search',
        methods: ['GET', 'POST']
    )]
    public function load(Criteria $criteria, SalesChannelContext $context): ExampleRouteResponse
    {
        return new ExampleRouteResponse($this->productRepository->search($criteria, $context->getContext()));
    }
}
