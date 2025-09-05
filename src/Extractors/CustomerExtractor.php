<?php declare(strict_types=1);

namespace NadeosData\Extractors;

use NadeosData\Extractors\{
    AbstractExtractor,
    OrderExtractor
};
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Document\DocumentEntity;

class CustomerExtractor extends AbstractExtractor
{
    protected function isValidEntity(Entity $entity): bool
    {
        return $entity instanceof DocumentEntity;
    }

    private function extractEntityBeauty(Entity $entity): array
    {
        $document  = $entity;
        $order     = $document->getOrder();
        $address   = $order->getBillingAddress();
        $customer  = $order->getOrderCustomer();

        $customerNumber = (string) $customer->getCustomerNumber();
        $customerNumber = ( OrderExtractor::CUSTOMER_NUMBER_PREFIXES[ strlen($customerNumber) ] ?? '' ) . $customerNumber;

        $isCompany = $order->getAmountTotal() === $order->getAmountNet();

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
        ];
    }

    protected function extractEntity(Entity $entity): array
    {
        $datas = $this->extractEntityBeauty($entity);

        return [
            'Kto-Nr' 	    => $datas['customer.customerNumber'],
            'Nachname' 	    => $datas['customer.lastName'],
            'Kurzname'	    => $datas['customer.name'],
            'Vorname'	    => $datas['customer.firstName'],
            'Strasse' 	    => $datas['orderCustomerAddress.street'],
            'PLZ'		    => "'" . $datas['orderCustomerAddress.zipCode'],
            'Land'		    => $datas['orderCustomerAddress.country'],
            'UID-Nummer'    => str_replace(' ', '', (string) $datas['customer.companyVatId']),
        ];
    }
}