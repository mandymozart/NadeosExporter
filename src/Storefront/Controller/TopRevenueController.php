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
use NadeosData\Dto\TopRevenueCollection;
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
        
        // Get group parameter for commission recipient filtering
        $group = $request->query->get('group', null);

        try {
            $topRevenueData = $this->topRevenueService->getTopRevenueData($dateFrom, $dateTo, $limit, $group);
            
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

    private function generatePdfResponse(TopRevenueCollection $topRevenueData, DateTime $dateFrom, DateTime $dateTo): Response
    {
        // Initialize mPDF
        $pdf = new \Mpdf\Mpdf();
        
        // Load the PDF template (same as CommissionPdfGenerationService)
        $pagecount = $pdf->SetSourceFile(dirname(__DIR__, 3) . '/templates/template.pdf');
        $pdf->UseTemplate($pdf->ImportPage($pagecount));
        
        // Set margins and font
        $pdf->SetLeftMargin(20);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetDrawColor(140, 190, 34);
        
        // Add title and header information
        $pdf->Cell(190, 40, '', 0, 1); // Skip header area
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(190, 10, 'Top Revenue Report', 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(190, 5, 'Period: ' . $dateFrom->format('d.m.Y') . ' - ' . $dateTo->format('d.m.Y'), 0, 1, 'C');
        $pdf->Cell(190, 5, 'Generated: ' . (new DateTime())->format('d.m.Y H:i:s'), 0, 1, 'C');
        $pdf->Cell(190, 10, '', 0, 1); // Space
        
        // Create table using HTML (more flexible for complex tables)
        $tableHtml = $this->generateTableHtml($topRevenueData);
        $pdf->WriteHTML($tableHtml);
        
        // Add total revenue at the bottom
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(190, 8, 'Total Revenue: ' . number_format($topRevenueData->getTotalRevenue(), 2, ',', '.') . ' €', 0, 1, 'C');
        
        // Generate filename
        $filename = sprintf(
            'top_revenue_%s_to_%s.pdf',
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d')
        );
        
        // Return PDF response
        return new Response(
            $pdf->Output('', 'S'), // 'S' returns PDF as string
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('inline; filename="%s"', $filename)
            ]
        );
    }

    private function generateTableHtml(TopRevenueCollection $topRevenueData): string
    {
        $html = '<style>
            table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9pt; }
            th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .revenue { text-align: right; }
        </style>
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Revenue</th>
                    <th>Company</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
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
        </table>';

        return $html;
    }
}
