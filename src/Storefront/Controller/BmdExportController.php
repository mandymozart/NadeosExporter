<?php declare(strict_types=1);

namespace NadeosData\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\{
    Response,
    StreamedResponse
};
use Symfony\Component\Routing\Attribute\Route;

use NadeosData\Services\BmdExportService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use DateTime;
use NadeosData\Storefront\Controller\{
    DateRequestTrait,
    HttpAuthTrait
};

#[Route(defaults: ['_routeScope' => ['storefront']])]
class BmdExportController extends StorefrontController
{
    use DateRequestTrait;
    use HttpAuthTrait;

    public function __construct(private readonly BmdExportService $exportService) {}

    private function getFilename(string $suffix, DateTime $date): string
    {
        return sprintf(
            '%s_%s_%s.csv',
            $date->format('Y'),
            $date->format('m'),
            $suffix
        );
    }

    
    #[Route(
        path: '/bmd-export',
        name: 'frontend.bmd-export.index',
        methods: ['GET']
    )]
    public function bmd(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;

        $date = $this->getDateFromRequests($request);

        return $this->renderStorefront('@NadeosExporter/storefront/page/bmd.html.twig', [
            'title' => 'BMD Export V2',
            'date'  => $date,
            'token' => $this->getToken(),
        ]);
    }

    #[Route(
        path: '/bmd-export/datas',
        name: 'frontend.bmd-export.datas',
        methods: ['GET']
    )]
    public function ordersWithDocuments(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;
        
        $date = $this->getDateFromRequests($request);

        return $this->renderStorefront('@NadeosExporter/storefront/page/bmd.overview.html.twig', [
            'title' => sprintf(
                '%s - %d.%d',
                'BMD Export (Bestellungen)',
                $date->format('m'),
                $date->format('Y')
            ),
            'date'  => $date,
            'datas' => $this->exportService->getOrderDatas($date),
        ]);
    }


    #[Route(
        path: '/bmd-export/datas-csv',
        name: 'frontend.bmd-export.datas-csv',
        methods: ['GET']
    )]
    public function ordersWithDocumentsCsv(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;
        
        $date = $this->getDateFromRequests($request);

        $file = $this->exportService->getOrderDatasCsv($date);
        $file->rewind();

        $filenameSuffix = 'overview';

        $filename = $this->getFilename($filenameSuffix, $date);

        $response = new StreamedResponse(function () use ($file) {
            while (!$file->eof()) {
                echo $file->fgets();
            }
        });
        $response->headers->set('Content-Type', 'text/csv'); // Passe den Content-Type an
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route(
        path: '/bmd-export/orders',
        name: 'frontend.bmd-export.orders',
        methods: ['GET']
    )]
    public function exportOrders(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;

        $date = $this->getDateFromRequests($request);
        $documentType = $request->query->get('type');

        $paramsToMethod = [
            'invoices'      => 'exportInvoicesOnly',
            'credits'       => 'exportCreditsOnly',
            'cancellations' => 'exportCancellationsOnly'
        ];

        $method = isset($paramsToMethod[$documentType])
                    ? $paramsToMethod[$documentType]
                    : 'exportOrders';

        $file = $this->exportService->{$method}($date);
        $file->rewind();

        $filenameSuffix = isset($paramsToMethod[$documentType]) ? $documentType . '-only' : 'orders';

        $filename = $this->getFilename($filenameSuffix, $date);

        $response = new StreamedResponse(function () use ($file) {
            while (!$file->eof()) {
                echo $file->fgets();
            }
        });
        $response->headers->set('Content-Type', 'text/csv'); // Passe den Content-Type an
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route(
        path: '/bmd-export/customers',
        name: 'frontend.bmd-export.customers',
        methods: ['GET']
    )]
    public function exportCustomers(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;

        $date = $this->getDateFromRequests($request);

        $file = $this->exportService->exportCustomers($date);
        $file->rewind();

        $filename = $this->getFilename('customers', $date);

        $response = new StreamedResponse(function () use ($file) {
            while (!$file->eof()) {
                echo $file->fgets();
            }
        });
        $response->headers->set('Content-Type', 'text/csv'); // Passe den Content-Type an
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
