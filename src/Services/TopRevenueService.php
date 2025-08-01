<?php declare(strict_types=1);

namespace NadeosData\Services;

use Shopware\Core\Defaults;
use Doctrine\DBAL\Connection;
use DateTime;
use NadeosData\Dto\{
    TopRevenueItem,
    TopRevenueCollection
};
use Shopware\Core\Framework\DataAbstractionLayer\{
    EntityRepository,
    Dbal\Common\RepositoryIterator
};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{
    RangeFilter,
    MultiFilter,
    EqualsFilter
};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\{
    TermsAggregation
};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\{
    SumAggregation
};
use Shopware\Core\Framework\Context;
use DateTimeZone;

class TopRevenueService
{
    const BULK = 1000;

    public function __construct(
        private readonly Connection $databaseConnection,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $customerRepository
    ) {}

    public function getTopRevenueData(DateTime $dateFrom, DateTime $dateTo, int $limit = 50): TopRevenueCollection
    {
        $collection = new TopRevenueCollection();
        
        // Get revenue data grouped by customer using direct SQL for better performance
        $revenueData = $this->getCustomerRevenueData($dateFrom, $dateTo, $limit);
        
        $rank = 1;
        foreach ($revenueData as $data) {
            $customerDetails = $this->getCustomerDetails($data['customer_id']);
            
            if ($customerDetails) {
                $item = new TopRevenueItem(
                    rank: $rank,
                    revenue: (float) $data['total_revenue'],
                    company: $customerDetails['company'] ?? $customerDetails['first_name'] . ' ' . $customerDetails['last_name'],
                    contactPerson: $customerDetails['first_name'] . ' ' . $customerDetails['last_name'],
                    phoneNumber: $customerDetails['phone_number'] ?? '',
                    email: $customerDetails['email'] ?? '',
                    customerId: $data['customer_id'],
                    customerNumber: $customerDetails['customer_number'] ?? ''
                );
                
                $collection->add($item);
                $rank++;
            }
        }
        
        return $collection;
    }

    private function getCustomerRevenueData(DateTime $dateFrom, DateTime $dateTo, int $limit): array
    {
        $sql = "
            SELECT 
                oc.customer_id,
                SUM(o.amount_total) as total_revenue
            FROM `order` o
            INNER JOIN order_customer oc ON o.id = oc.order_id
            WHERE o.order_date_time >= :dateFrom 
                AND o.order_date_time <= :dateTo
                AND o.version_id = :versionId
            GROUP BY oc.customer_id
            ORDER BY total_revenue DESC
            LIMIT :limit
        ";

        $params = [
            'dateFrom' => $dateFrom->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'dateTo' => $dateTo->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'versionId' => hex2bin(Defaults::LIVE_VERSION),
            'limit' => $limit
        ];

        // Debug logging
        error_log("TopRevenue Debug - SQL: " . $sql);
        error_log("TopRevenue Debug - Params: " . json_encode([
            'dateFrom' => $dateFrom->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'dateTo' => $dateTo->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'limit' => $limit
        ]));

        $result = $this->databaseConnection->fetchAllAssociative($sql, $params, [
            'limit' => \PDO::PARAM_INT
        ]);

        error_log("TopRevenue Debug - Result count: " . count($result));
        
        return $result;
    }

    private function getCustomerDetails(string $customerId): ?array
    {
        $sql = "
            SELECT 
                c.id,
                c.customer_number,
                c.email,
                c.first_name,
                c.last_name,
                c.company,
                ca.phone_number
            FROM customer c
            LEFT JOIN customer_address ca ON c.default_billing_address_id = ca.id
            WHERE c.id = :customerId
        ";

        $result = $this->databaseConnection->fetchAssociative($sql, [
            'customerId' => $customerId
        ]);

        if (!$result) {
            return null;
        }

        return [
            'customer_number' => $this->sanitizeUtf8($result['customer_number']),
            'email' => $this->sanitizeUtf8($result['email']),
            'first_name' => $this->sanitizeUtf8($result['first_name'] ?? ''),
            'last_name' => $this->sanitizeUtf8($result['last_name'] ?? ''),
            'phone_number' => $this->sanitizeUtf8($result['phone_number'] ?? $result['phone'] ?? ''),
            'company' => $this->sanitizeUtf8($result['company'])
        ];
    }

    private function sanitizeUtf8(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        
        // Remove or replace invalid UTF-8 characters
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        
        // Remove any remaining non-printable characters except common whitespace
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        return trim($value);
    }
}
