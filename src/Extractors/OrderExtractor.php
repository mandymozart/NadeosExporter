<?php declare(strict_types=1);

namespace NadeosData\Extractors;

use NadeosData\Extractors\AbstractExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/*
4000	Erlöse Naturkosmetik    20%	         20,00
4001	Erlöse Deutschland      19% EU-OSS	 19,00
4002	Erlöse Frankreich       20% EU-OSS	 20,00
4003	Erlöse Spanien          21% EU-OSS	 21,00
4004	Erlöse Kroatien         25% EU-OSS	 25,00
4005	Erlöse Ungarn           27% EU-OSS	 27,00
4006	Erlöse Schweden         25% EU-OSS	 25,00
4007	Erlöse Belgien          21% EU-OSS	 21,00
4008	Erlöse Irland           23% EU-OSS	 23,00
4009	Erlöse Italien          22% EU-OSS	 22,00
4010	Erlöse Luxemburg        17% EU-OSS	 17,00
4011	Erlöse Niederlande      21% EU-OSS	 21,00
4012	Erlöse Polen            23% EU-OSS	 23,00
4013	Erlöse Slowenien        22% EU-OSS	 22,00
4031	Erlöse Deutschland          EU-OSS	  7,00
4050	Erlöse (Ausfuhrlief.)    0% 	      0,00
4100	Erlöse ig. Lieferungen (steuerfrei)	  0,00
*/

class OrderExtractor extends AbstractExtractor
{
    const TAX_DETAILS_FALLBACK = [4000, 20];    // [account, tax]
    const TAX_DETAILS = [
        // ISO2 => [account, tax]
        'AT'  => ['4000', 20], // Austria
        'DE'  => ['4001', 19], // Germany
        'FR'  => ['4002', 20], // France
        'ES'  => ['4003', 21], // Spain
        'HR'  => ['4004', 25], // Croatia
        'HU'  => ['4005', 27], // Hungary
        'SE'  => ['4006', 25], // Sweden
        'BE'  => ['4007', 21], // Belgium
        'IE'  => ['4008', 23], // Ireland
        'IT'  => ['4009', 22], // Italy
        'LU'  => ['4010', 17], // Luxembourg
        'NL'  => ['4011', 21], // Netherlands
        'PL'  => ['4012', 23], // Poland
        'SI'  => ['4013', 22], // Slovenia
    ];
    const TAX_DETAILS_TAXCODE                       = '1';

                                                   // [ accountCounterpart, taxPercentage, taxCode ]
    const TAX_DETAILS_FOR_COMPANIES_IN_EU           = [ '4100', 0, '7' ];
    const TAX_DETAILS_FOR_COMPANIES_NOT_EU          = [ '4050', 0, '5' ];

    const CUSTOMER_NUMBER_PREFIXES = [
        4 => '20',
        5 => '2'
    ];

    private array $euCountryIds = [];

    public function __construct(private readonly SystemConfigService $config)
    {
        $this->euCountryIds = $config->get('NadeosExporter.config.euCountries') ?? [];
    }

    protected function isValidEntity(Entity $entity): bool
    {
        return $entity instanceof DocumentEntity; // OrderEntity;
    }

    private function extractEntityBeauty(Entity $entity): array
    {
        $document = $entity;
        $order    = $entity->getOrder();

        $shippingAddressCountry = $order->getDeliveries()?->getShippingAddress()?->getCountries()?->first() ?? null;

        $address  = $order->getBillingAddress();
        $customer = $order->getOrderCustomer();
        $referencedDocument = $document->getReferencedDocument();

        if ($shippingAddressCountry) {
            $customerCountry            = $shippingAddressCountry->getIso();
            $customerCountryIsEuCountry = in_array($shippingAddressCountry->getId(), $this->euCountryIds);
        }
        else {
            $customerCountry            = $address->getCountry()->getIso();
            $customerCountryIsEuCountry = in_array($address->getCountry()->getId(), $this->euCountryIds);
        }

        $customerNumber = (string) $customer->getCustomerNumber();
        $customerNumber = ( self::CUSTOMER_NUMBER_PREFIXES[ strlen($customerNumber) ] ?? '' ) . $customerNumber;

        $isCompany = $order->getAmountTotal() === $order->getAmountNet();
        if (true === $isCompany) {
            list (
                $accountCounterpart,
                $taxPercentage,
                $taxCode
            ) = $customerCountryIsEuCountry ? self::TAX_DETAILS_FOR_COMPANIES_IN_EU : self::TAX_DETAILS_FOR_COMPANIES_NOT_EU;
        }
        else {
            list (
                $accountCounterpart,
                $taxPercentage
            )        = self::TAX_DETAILS[$customerCountry] ?? self::TAX_DETAILS_FALLBACK;
            $taxCode = self::TAX_DETAILS_TAXCODE;
        }

        $taxAmount = round(
            $order->getAmountTotal() - $order->getAmountNet(),
            2
        ) * -1;

        $name = ($isCompany && false === empty($customer->getCompany()))
                    ? $customer->getCompany()
                    : $customer->getLastname();

        $name = trim(implode(" ", [
            $address->getFirstname(),
            $address->getLastname()
        ]));

        if (!empty($address->getCompany())) {
            $name = trim($address->getCompany() . ', ' . $name);
        }

        return [
            'order.number'                  => $order->getOrderNumber(),
            'order.date'                    => $order->getOrderDate(),
            'order.amountGross'             => $order->getAmountTotal(),
            'order.amountNet'               => $order->getAmountNet(),
            'order.amountTax'               => $taxAmount,
            'orderTax.accountCounterpart'   => $accountCounterpart,
            'orderTax.taxPercentage'        => $taxPercentage,
            'orderTax.taxCode'              => $taxCode,
            'customer.isCompany'            => $isCompany,
            'customer.companyVatId'         => $customer->getVatIds() ? $customer->getVatIds()[0] : null,
            'customer.customerNumber'       => $customerNumber,
            'customer.companyName'          => $customer->getCompany(),
            'customer.name'                 => $name,
            'customer.firstName'            => $customer->getFirstname(),
            'customer.lastName'             => $customer->getLastname(),
            'orderCustomerAddress.street'   => $address->getStreet(),
            'orderCustomerAddress.zipCode'  => $address->getZipCode(),
            'orderCustomerAddress.city'     => $address->getCity(),
            'orderCustomerAddress.country'  => $address->getCountry()->getIso(),
            'document.type'                 => $document->getDocumentType()->getTechnicalName(),
            'document.name'                 => $document->getDocumentType()->getName(),
            'document.number'               => $document->getDocumentNumber(),
            'document.date'                 => $document->getCreatedAt(),
            'document.dateUpdated'          => $document->getUpdatedAt(),
            'referencedDocument.type'       => $document->getReferencedDocument()?->getDocumentType()->getTechnicalName(),
            'referencedDocument.name'       => $document->getReferencedDocument()?->getDocumentType()->getName(),
            'referencedDocument.number'     => $document->getReferencedDocument()?->getDocumentNumber(),
            'referencedDocument.date'       => $document->getReferencedDocument()?->getCreatedAt(),
        ];
    }

    protected function extractEntity(Entity $entity): array
    {
        $datas = $this->extractEntityBeauty($entity);

        return [
            'satzart' 		=> '',
            'konto' 		=> $datas['customer.customerNumber'],
            'gkonto'		=> $datas['orderTax.accountCounterpart'],
            'belegnr'		=> $datas['order.number'],                      # eigl. dokument-nr: $zeile->docID,
            'belegdatum' 	=> $datas['order.date']->format('d.m.Y'),       # eigl. dokument-datum: date("d.m.Y", strtotime($zeile->datum)),
            'buchsymbol'	=> 'AR',
            'buchcode'		=> '1',
            'prozent'		=> $datas['orderTax.taxPercentage'],
            'steuercode'	=> $datas['orderTax.taxCode'],
            'betrag'		=> $datas['order.amountGross'],
            'steuer'		=> $datas['order.amountTax'],
            'text'			=> $datas['customer.name'],
            'kost'			=> '',
            'verbuchstatus'	=> '',

            'order.number'      => $datas['order.number'],
            'order.date'        => $datas['order.date']?->format('d.m.Y'),
            'document.type'     => $datas['document.type'],
            'document.name'     => $datas['document.name'],
            'document.number'   => $datas['document.number'],
            'document.date'     => $datas['document.date']?->format('d.m.Y'),
            'document.dateUpdated' => $datas['document.date']?->format('d.m.Y'),
            'referencedDocument.type'   => $datas['referencedDocument.type'],
            'referencedDocument.name'   => $datas['referencedDocument.name'],
            'referencedDocument.number' => $datas['referencedDocument.number'],
            'referencedDocument.date'   => $datas['referencedDocument.date']?->format('d.m.Y'),
        ];
    }
}