<?php declare(strict_types=1);

namespace NadeosData\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use NadeosData\Services\ShrinkService;
use NadeosData\Storefront\Controller\{
    DateRequestTrait,
    HttpAuthTrait
};

#[Route(defaults: ['_routeScope' => ['storefront']])]
class ShrinkController extends StorefrontController
{
    use DateRequestTrait;
    use HttpAuthTrait;

    public function __construct(
        private readonly ShrinkService $service
    ) {}

    #[Route(
        path: '/shrink-list',
        name: 'frontend.shrink.list',
        methods: ['GET']
    )]
    public function list(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;

        $date = $this->getDateFromRequests($request);

        return $this->renderStorefront('@NadeosExporter/storefront/page/shrink.html.twig', [
            'title' => sprintf(
                '%s für %d-%d',
                'Schwundliste',
                $date->format('Y'),
                $date->format('m')
            ),
            'articles' => $this->service->list($date),
            'token'     => $this->getToken()
        ]);
    }

    #[Route(
        path: '/shrink-list/overview',
        name: 'frontend.shrink.list-overview',
        methods: ['GET']
    )]
    public function overview(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;

        $date = $this->getDateFromRequests($request);

        return $this->renderStorefront('@NadeosExporter/storefront/page/shrink.overview.html.twig', [
            'title' => sprintf(
                '%s für %d-%d',
                'Schwundliste',
                $date->format('Y'),
                $date->format('m')
            ),
            'articlesRelevant' => $this->service->listRelevantProducts($date),
            'articlesOverview' => $this->service->listByProduct($date),
            'token'     => $this->getToken()
        ]);
    }
}
