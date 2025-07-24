<?php declare(strict_types=1);

namespace NadeosData\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\{
    EntityRepository,
    Dbal\Common\RepositoryIterator
};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{
    RangeFilter,
    NotFilter,
    EqualsFilter,
};
use Shopware\Core\Defaults;
use League\Flysystem\Filesystem;
use Mpdf;
use Doctrine\DBAL\Connection;
use NadeosData\Storefront\Controller\{
    DateRequestTrait,
    HttpAuthTrait
};
use NadeosData\Services\{
    CommissionService,
    CommissionPdfGenerationService
};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use NadeosData\Services\CommissionMailService;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class CommissionsController extends StorefrontController
{
    use DateRequestTrait;
    use HttpAuthTrait;

    const BULK = 500;

    public function __construct(
        private readonly CommissionMailService          $commissionMailService,
        private readonly CommissionPdfGenerationService $commissionPdfGenerationService,
        private readonly CommissionService  $commissionService,
        private readonly Connection         $databaseConnection,
        private readonly EntityRepository   $orderRepository,
        private readonly Filesystem         $filesystem,
    ) {}

    private function prepareDirectory(): void
    {
        if (false === $this->filesystem->directoryExists(self::DIRECTORY_NAME_EXPORTS)) {
            $this->filesystem->createDirectory(self::DIRECTORY_NAME_EXPORTS);
        }
    }

    #[Route(
        path: '/commissions/mail',
        name: 'frontend.nadeos.commissions-mail',
        methods: ['GET']
    )]
    public function mail(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;

        $testEmail = $request->query->get('test');
        if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $this->commissionMailService->sendMails(
                $this->getDateFromRequests($request),
                null,
                $request->getSchemeAndHttpHost(),
                $testEmail
            );
        }
        else {
            $this->commissionMailService->sendMails(
                $this->getDateFromRequests($request),
                null,
                $request->getSchemeAndHttpHost()
            );
        }

        return $this->redirectToRoute('frontend.nadeos.commissions');
    }

    #[Route(
        path: '/commissions',
        name: 'frontend.nadeos.commissions',
        methods: ['GET']
    )]
    public function list(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;

        $date = $this->getDateFromRequests($request);
        $group = $request->query->get('group');

        $commissionsCollection = $this->commissionService->list($date, $group);
        
        return $this->renderStorefront('@NadeosExporter/storefront/page/commissions.html.twig', [
            'title' => sprintf(
                '%s für %d-%d',
                'Provisionen',
                $date->format('Y'),
                $date->format('m')
            ),
            'collection' => $commissionsCollection->getElements(),
            'token'      => $this->getToken(),
        ]);
    }

    #[Route(
        path: '/commission/pdf',
        name: 'frontend.nadeos.commsions-pdf',
        methods: ['GET']
    )]
    public function pdf(Request $request, SalesChannelContext $context): Response
    {
        $date = $this->getDateFromRequests($request);

        $group = $request->query->get('group');
        if (true === empty($group)) {
            throw new BadRequestHttpException('Die Gruppe muss gesetzt sein');
        }

        $commissionCollection = $this->commissionService->list(
            $date,
            $group
        );

        if (0 === count($commissionCollection)) {
            throw new BadRequestHttpException('Keine Daten gefunden!');
        }

        $pdf = $this->commissionPdfGenerationService->getPdf($commissionCollection->getElements()[0]);

        return new Response(
            $pdf->Output('', 'S'),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="provision.pdf"',
            ]
        );
    }

    #[Route(
        path: '/commission/overview',
        name: 'frontend.nadeos.commssions-overview',
        methods: ['GET']
    )]
    public function overview(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;
        
        $date  = $this->getDateFromRequests($request);
        $group = base64_encode($request->query->get('group'));

        $list = $this->commissionService->list($date, $group);
        if (0 === count($list)) {
            return $this->redirectToRoute('frontend.nadeos.commissions');
        }

        $commission = $list->first();

        return $this->renderStorefront('@NadeosExporter/storefront/page/commissions.overview.html.twig', [
            'title' => sprintf(
                '%s - %d.%d',
                'Provisionen',
                $date->format('m'),
                $date->format('Y')
            ),
            'date'   => $date,
            'datas'  => $commission,
            'orders' => $commission->getOrders()
        ]);
    }
}