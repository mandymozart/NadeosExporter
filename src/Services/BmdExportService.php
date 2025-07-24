<?php declare(strict_types=1);

namespace NadeosData\Services;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\{
    EntityRepository,
    Dbal\Common\RepositoryIterator
};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{
    RangeFilter,
    EqualsAnyFilter,
    NotFilter,
    EqualsFilter
};
use League\Flysystem\Filesystem;

use NadeosData\Extractors\{
    ExtractorInterface,
    OrderExtractor    
};
use Shopware\Core\Defaults;

use DateTime;
use DateTimeZone;
use SplFileObject;

class BmdExportService
{
    const VALID_DOCUMENT_TYPES = [
        'invoice',
        'credit_note',
        'cancellation_invoice'
    ];

    const CANCELLATION_DOCUMENT_TYPES = [
        'credit_note',
        'cancellation_invoice',
        'storno'
    ];

    private $type = null;

    CONST BULK = 500;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $orderRepository,
        private readonly Filesystem $filesystem,
        private readonly ExtractorInterface $customerExtractor,
        private readonly ExtractorInterface $orderExtractor
    ) {}

    private function getOrdersForDateframe(DateTime $date): RepositoryIterator
    {
        $year   = $date->format('Y');
        $month  = $date->format('m');

        $dateFrom = new DateTime("$year-$month-01 00:00:00", new DateTimeZone('UTC'));
        $dateTo   = new DateTime("$year-$month-01 23:59:59", new DateTimeZone('UTC'));
        $dateTo->modify('last day of this month');

        $context = Context::createDefaultContext();

        $documentTypes = (is_null($this->type))
                            ? self::VALID_DOCUMENT_TYPES
                            : [$this->type];

        # cancellation_invoice = storno (irgendwer hat den typ auf deutsch gestellt!)
        if (in_array('cancellation_invoice', $documentTypes)) {
            $documentTypes = array_map(function($value) {
                return ($value === "cancellation_invoice") ? "storno" : $value;
            }, $documentTypes);
        }

        return new RepositoryIterator(
            $this->orderRepository,
            $context,
            (new Criteria)
                ->addAssociations([
                    'documentType.technicalName',
                    'order',
                    'order.billingAddress',
                    'order.billingAddress.country',
                    'order.deliveries',
                    'order.deliveries.shippingOrderAddress',
                    'order.deliveries.shippingOrderAddress.country',
                    // 'referencedDocument.order'
                    'referencedDocument',
                    'referencedDocument.documentType.technicalName',
                ])
                ->addFilter(new EqualsAnyFilter('documentType.technicalName', $documentTypes))
                ->addFilter(new RangeFilter('createdAt', [
                    RangeFilter::GTE => $dateFrom->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    RangeFilter::LTE => $dateTo->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                ]))
                ->setLimit(self::BULK)
        );
    }

    private function getFileObject(): SplFileObject
    {
        $file = new SplFileObject('php://temp', 'w+');
        $file->setCsvControl(';', "\"");

        return $file;
    }

    private function getExtractedDatas(ExtractorInterface $extractor, DateTime $date): \Generator
    {
        $iterator = $this->getOrdersForDateframe($date);
        while(($result = $iterator->fetch()) !== null) {
            $orders = $result->getEntities();
            foreach($orders as $order) {
                $datas = $extractor->extract($order);

                if ($extractor instanceof OrderExtractor) {
                    $datasOrdersForDocument = $datas;
                    if (in_array($datasOrdersForDocument['document.type'], self::CANCELLATION_DOCUMENT_TYPES)) {
                        $datas['betrag'] *= -1;
                        $datas['steuer'] *= -1;
                    }

                    $datasOrdersForDocument['belegnr']      = $datasOrdersForDocument['document.number'];   // $document->getDocumentNumber();
                    $datasOrdersForDocument['belegdatum']   = $datasOrdersForDocument['document.date'];     // $document->getCreatedAt()->format('d.m.Y');
                    $datasOrdersForDocument['betrag']       = number_format( $datas['betrag'], 2, ',', '');
                    $datasOrdersForDocument['steuer']       = number_format( $datas['steuer'], 2, ',', '');

                    yield $datasOrdersForDocument;
                }
                else {
                    yield $datas;
                }
            }
        }
    }

    public function getOrderDatas(DateTime $date): \Generator
    {
        return $this->getExtractedDatas(
            $this->orderExtractor,
            $date
        );
    }

    public function getOrderDatasCsv(DateTime $date): SplFileObject
    {
        $file = $this->getFileObject();

        $headers = [
            'order.date'        => 'Bestell Datum',
            'order.number'      => 'Bestell Nr.',
            'document.date'     => 'Dokument Datum',
            'document.number'   => 'Dokument Nr.',
            'document.name'     => 'Dokumentart',
            'konto'             => 'Kunden Nr.',
            'text'              => 'Kunde',
            'betrag'            => 'Betrag',
            'referencedDocument.name'   => 'Ref. Dokumentart',
            'referencedDocument.number' => 'Ref. Dokument Nr.',
            'referencedDocument.date'   => 'Ref. Dokument Datum',
        ];

        $dataKeys = array_keys($headers);

        $file->fputcsv(array_values($headers));

        $generator = $this->getExtractedDatas(
            $this->orderExtractor,
            $date
        );
        foreach ($generator as $datas) {
            # only header keys (and keep header key order)
            $datas = array_intersect_key($datas, array_flip($dataKeys));
            $datas = array_replace(array_flip($dataKeys), $datas);

            $file->fputcsv($datas);
        }

        $file->rewind();

        return $file;
    }

    private function getExtractedFile(ExtractorInterface $extractor, DateTime $date): SplFileObject
    {
        $file = $this->getFileObject();
        $hasHeaderWritten = false;

        $documentKeys = [
            'document.type',
            'document.name',
            'document.number',
            'document.date',
            'document.dateUpdated',
            'order.number',
            'order.date',
            'referencedDocument.type',
            'referencedDocument.name',
            'referencedDocument.number',
            'referencedDocument.date',
        ];

        $datasUniqueHashes = [];

        $generator = $this->getExtractedDatas($extractor, $date);
        foreach ($generator as $datas) {
            $datas = array_diff_key(
                $datas,
                array_flip($documentKeys)
            );

            if (false === $hasHeaderWritten) {
                $headers = array_keys($datas);

                $file->fputcsv($headers);

                $hasHeaderWritten = true;
            }

            $uniqueHash = md5(json_encode($datas));
            if (true === in_array($uniqueHash, $datasUniqueHashes)) continue;

            $datasUniqueHashes[] = $uniqueHash;

            $file->fputcsv($datas);
        }

        $file->rewind();

        return $file;
    }

    public function exportCustomers(DateTime $date): SplFileObject
    {
        return $this->getExtractedFile(
            $this->customerExtractor,
            $date
        );
    }

    public function exportOrders(DateTime $date): SplFileObject
    {
        return $this->getExtractedFile(
            $this->orderExtractor,
            $date
        );
    }

    public function exportInvoicesOnly(DateTime $date): SplFileObject
    {
        $this->type = 'invoice';

        return $this->getExtractedFile(
            $this->orderExtractor,
            $date
        );
    }

    public function exportCancellationsOnly(DateTime $date): SplFileObject
    {
        $this->type = 'cancellation_invoice';

        return $this->getExtractedFile(
            $this->orderExtractor,
            $date
        );
    }

    public function exportCreditsOnly(DateTime $date): SplFileObject
    {
        $this->type = 'credit_note';

        return $this->getExtractedFile(
            $this->orderExtractor,
            $date
        );
    }

    public function exportCustomers_(DateTime $date): SplFileObject
    {
        $fileCustomers = new SplFileObject('php://temp', 'w+');
        $fileCustomers->setCsvControl(";", "\"");

        $hasHeaderWritten = false;

        $iterator = $this->getOrdersForDateframe($date);
        while(($result = $iterator->fetch()) !== null) {
            $orders = $result->getEntities();
            foreach($orders as $order) {
                $datas = $this->customerExtractor->extract($order);
        
                $name = ($datas['customer.isCompany'] && false === empty($datas['customer.companyName']))
                    ? $datas['customer.companyName']
                    : $datas['customer.lastName'];

                $datasCustomer = [
                    'Kto-Nr' 	    => $datas['customer.customerNumber'],
                    'Nachname' 	    => $datas['customer.lastName'],
                    'Kurzname'	    => $name,
                    'Vorname'	    => $datas['customer.firstName'],
                    'Strasse' 	    => $datas['orderCustomerAddress.street'],
                    'PLZ'		    => $datas['orderCustomerAddress.zipCode'],
                    'Land'		    => $datas['orderCustomerAddress.country'],
                    'UID-Nummer'    => str_replace(' ', '', (string) $datas['customer.companyVatId']),
                ];
            
                if (false === $hasHeaderWritten) {
                    $fileCustomers->fputcsv(array_keys($datasCustomer));

                    $hasHeaderWritten = true;
                }

                $fileCustomers->fputcsv($datasCustomer);
            }
        }

        $fileCustomers->rewind();

        return $fileCustomers;
    }
}