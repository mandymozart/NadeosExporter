<?php declare(strict_types=1);

namespace NadeosData\Services;

use NadeosData\Dto\Commission;
use League\Flysystem\Filesystem;
use DateTime;
use Mpdf;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\{
    Criteria,
    Filter\EqualsFilter
};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;

class CommissionPdfGenerationService
{
    const DIRECTORY_NAME_EXPORTS = 'nadeos.exports.commissions';

    public function __construct(
        private readonly EntityRepository $commissionNumbersRepository,
        private readonly Connection       $databaseConnection,
        private readonly Filesystem       $filesystem,
    ) {}

    private function prepareDirectory(): void
    {
        if (false === $this->filesystem->directoryExists(self::DIRECTORY_NAME_EXPORTS)) {
            $this->filesystem->createDirectory(self::DIRECTORY_NAME_EXPORTS);
        }
    }

    public function savePdf(Commission $commission): void
    {
        $this->prepareDirectory();

        // $pdfContent = $pdf->Output('', 'S'); // 'S' returns the PDF as a string
        $this->filesystem->write(
            sprintf(
                '%s' .DIRECTORY_SEPARATOR . '%d_%d_%s.pdf',
                self::DIRECTORY_NAME_EXPORTS,
                $commission->orderYear,
                $commission->orderMonth,
                $commission->groupName
                // $orderNumber
            ),
            $this->getPdf($commission)->OutputBinaryData()
        );
    }

    private function getSequentialNumberFromDatabase(Commission $commission): bool|string
    {
        $params = [
            'year'   => $commission->orderYear,
            'month'  => $commission->orderMonth,
            'group'  => $commission->groupName
        ];

        $data = $this->databaseConnection->fetchOne(
            '
                SELECT
                    ida
                FROM
                    commission_numbers
                WHERE
                    `year` = :year
                    AND `month` = :month
                    AND `group` = :group
            ',
            $params
        );

        return $data;
    }

    private function getSequentialNumber(Commission $commission): int
    {
        $id = $this->getSequentialNumberFromDatabase($commission);

        if (false !== $id) return (int) $id;

        $newEntityData = [
            'id'    => Uuid::randomHex(),
            'group' => $commission->groupName,
            'year'  => $commission->orderYear,
            'month' => $commission->orderMonth,
        ];

        $this->commissionNumbersRepository->create([$newEntityData], Context::createDefaultContext());

        $id = $this->getSequentialNumberFromDatabase($commission);

        return (int) $id;
    }


    public function getPdf(Commission $commission): Mpdf\Mpdf
    {
        $isCommissionTypeDefault = $commission->commissionType == 'default';

        $pnr = '';
        if (true === $isCommissionTypeDefault) {
            $pnr = '19877' . str_pad(
                (string) $this->getSequentialNumber($commission),
                4,
                '0',
                STR_PAD_LEFT
            );
        }

        $salutation     = $commission->salutation;
        $firstname      = $commission->firstname;
        $lastname       = $commission->lastname;

        $date = new DateTime("$commission->orderYear-$commission->orderMonth-01");
        $date->modify('first day of next month');


        $street  = $commission->street;
        $zipcode = $commission->cityZip;

        $pdf = new Mpdf\Mpdf();

        $pagecount = $pdf->SetSourceFile(dirname(__DIR__ ) . '/../templates/template.pdf');
        $pdf->UseTemplate( $pdf->ImportPage($pagecount) );

        $pdf->SetLeftMargin(20);

        $pdf->SetFont('Arial','',10);
        $pdf->SetDrawColor(140, 190, 34);


        // address
        $pdf->Cell(190,40,'',0,1);
        $pdf->Cell(190,5,''. $salutation .' '. $firstname .' '. $lastname .'',0,1);
        $pdf->Cell(190,5,''. $street .'',0,1);
        $pdf->Cell(190,5, $zipcode,0,1);
        
        // date
        $pdf->Cell(170,5,'Datum: '. $date->format('d.m.Y') .'',0,1,'R');

        // headline
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(190,20,'',0,1);
        $pdf->Cell(190,5, $isCommissionTypeDefault ? 'GUTSCHRIFTSRECHNUNG P'. $pnr .'' : 'PROVISIONSÜBERSICHT',0,1);

        if ($isCommissionTypeDefault) {
            $pdf->Cell(190,5,'Provisionsabrechnung',0,1);
        }
        $pdf->Cell(190,10,'',0,1);

        // description
        $pdf->SetFont('Arial','',10);
        $pdf->MultiCell(170,5,
            $isCommissionTypeDefault
                ? 'Aus den von Ihnen lukrierten Kundenumsätzen ergibt sich folgende Provisionsabrechnung:'
                : 'Aus den von Ihnen lukrierten Kundenumsätzen ergibt sich folgende Provision:'
        ,0,1);

        $pdf->Cell(190,10,'',0,1);

        // table
        $pdf->Cell(20,5,'Pos.',0,0);
        $pdf->Cell(110,5,'Bezeichnung',0,0);
        $pdf->Cell(40,5,'Netto',0,1,'R');
        $pdf->Cell(170,1,'','B',1);

        $position = 1;
        foreach ($commission->getItems() as $item) {
            $pdf->Cell(20,10, (string) $position ,0,0);
            $pdf->Cell(110,10,'Provision aus EUR '. $item->salesNet .' Nettoumsatz im Zeitraum '. $item->commissionPeriod->format('Y-m') .'',0,0);
            $pdf->Cell(40,10,''. $item->commissionNet .'',0,1,'R');

            $pdf->Cell(170,5,'',0,1);

            $position++;
        }

        $pdf->Cell(80,5,'',0,0);
        $pdf->Cell(50,5,'Betrag Netto',0,0);
        $pdf->Cell(40,5,''. $commission->commissionNetTotal .'',0,1,'R');

        $pdf->Cell(80,5,'',0,0);
        $pdf->Cell(50,5,'zzgl. 20% MwSt.',0,0);
        $pdf->Cell(40,5,''. ROUND($commission->commissionNetTotal * 0.2,2) .'',0,1,'R');
        
        $pdf->SetFont('Arial','B',10);

        $pdf->Cell(80,5,'',0,0);
        $pdf->Cell(90,1,'','B',1);			
        $pdf->Cell(80,5,'',0,0);
        $pdf->Cell(50,5,'Gesamtsumme',0,0);
        $pdf->Cell(40,5,''. ROUND($commission->commissionGrossTotal,2) .'',0,1,'R');

        $pdf->Cell(170,25,'',0,1);
        $pdf->SetFont('Arial','',10);

        if ($isCommissionTypeDefault) {
            $pdf->Cell(170,5, 'Die Gesamtsumme wird in den nächsten Tagen an Ihr hinterlegtes Konto überwiesen.',0,1);
        }
        else {
            $pdf->Cell(170,5, 'Diese Provision wird mit der nächsten Gehaltsabrechnung ausgezahlt.',0,1);
            $pdf->Cell(170,5, 'Bitte beachten Sie, dass der Betrag aufgrund des Gehalts von der hier angegebenen Provision abweichen kann.',0,1);
        }

        $pdf->AddPage();

        $tableHtml = '
        <style>
            #orders { font-family: arial; font-size: 10pt; }
            #orders td { text-align: center; }
            #orders .right { text-align: right; }
        </style>
        <table id="orders" style="width: 100%">';
        $tableHtml .= <<<'THEAD'
        <thead>
            <tr>
                <th>Bestell Nr.</th>
                <th>Datum</th><
                <th class="right">Netto €</th>
                <th class="right">Brutto €</th>
            </tr>
        </thead>
THEAD;
        $orders = $commission->getOrders();
        foreach($orders as $order) {
            $tableHtml .= sprintf(
                '<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td class="right">%s</td>
                    <td class="right">%s</td>
                </tr>',
                $order->getOrderNumber(),
                $order->getOrderDateTime()->format('d.m.Y H:i:s'),
                number_format($order->getAmountNet(), 2, ',', ''),
                number_format($order->getAmountTotal(), 2, ',', ''),
            );
        }
        $tableHtml .= '</table>';

        $pdf->writeHTML($tableHtml);

        return $pdf;
    }
}