<?php declare(strict_types=1);

namespace NadeosData\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use NadeosData\Storefront\Controller\{
    DateRequestTrait,
    HttpAuthTrait
};
use NadeosData\Services\TopRevenueService;
use DateTime;
use DateTimeZone;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class TopRevenueController extends StorefrontController
{
    use DateRequestTrait;
    use HttpAuthTrait;

    public function __construct(
        private readonly TopRevenueService $topRevenueService
    ) {}

    #[Route(
        path: '/bmd-export/top-revenue',
        name: 'frontend.nadeos.top-revenue',
        methods: ['GET']
    )]
    public function topRevenue(Request $request, SalesChannelContext $context): Response
    {
        $authResponse = $this->checkAuth($request);
        if ($authResponse) return $authResponse;

        // Get date parameters (using existing trait)
        $dateFrom = $this->getDateFromRequests($request);
        $dateTo = $this->getDateToRequests($request);
        
        // Default to current month if no dates provided
        if (!$dateFrom || !$dateTo) {
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $dateFrom = new DateTime($now->format('Y-m-01 00:00:00'), new DateTimeZone('UTC'));
            $dateTo = new DateTime($now->format('Y-m-t 23:59:59'), new DateTimeZone('UTC'));
        }

        // Get limit parameter
        $limit = min((int) $request->query->get('limit', 50), 100); // Max 100 items

        try {
            $topRevenueData = $this->topRevenueService->getTopRevenueData($dateFrom, $dateTo, $limit);
            
            // Check if PDF output is requested
            if ($request->query->get('format') === 'pdf') {
                return $this->generatePdfResponse($topRevenueData, $dateFrom, $dateTo);
            }
            
            // Return JSON response by default
            return $this->json([
                'success' => true,
                'data' => $topRevenueData->toArray(),
                'meta' => [
                    'count' => $topRevenueData->count(),
                    'total_revenue' => $topRevenueData->getTotalRevenue(),
                    'date_from' => $dateFrom->format('Y-m-d'),
                    'date_to' => $dateTo->format('Y-m-d'),
                    'limit' => $limit
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to generate top revenue report: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generatePdfResponse($topRevenueData, DateTime $dateFrom, DateTime $dateTo): Response
    {
        // For now, return a simple HTML table that could be converted to PDF
        // This follows the pattern used in existing controllers but simplified
        $html = $this->generateReportHtml($topRevenueData, $dateFrom, $dateTo);
        
        return new Response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename="top-revenue-' . $dateFrom->format('Y-m') . '.html"'
        ]);
    }

    private function generateReportHtml($topRevenueData, DateTime $dateFrom, DateTime $dateTo): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Top Revenue Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .header { margin-bottom: 20px; }
        .revenue { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Top Revenue Report</h1>
        <p>Period: ' . $dateFrom->format('d.m.Y') . ' - ' . $dateTo->format('d.m.Y') . '</p>
        <p>Generated: ' . (new DateTime())->format('d.m.Y H:i:s') . '</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Rank/Position</th>
                <th>Revenue</th>
                <th>Company</th>
                <th>Contact Person</th>
                <th>Phone Number</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($topRevenueData->getItems() as $item) {
            $html .= '<tr>
                <td>' . $item->rank . '</td>
                <td class="revenue">' . $item->getFormattedRevenue() . '</td>
                <td>' . htmlspecialchars($item->company) . '</td>
                <td>' . htmlspecialchars($item->contactPerson) . '</td>
                <td>' . htmlspecialchars($item->phoneNumber) . '</td>
                <td>' . htmlspecialchars($item->email) . '</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>
    
    <div style="margin-top: 20px;">
        <strong>Total Revenue: ' . number_format($topRevenueData->getTotalRevenue(), 2, ',', '.') . ' €</strong>
    </div>
</body>
</html>';

        return $html;
    }
}
