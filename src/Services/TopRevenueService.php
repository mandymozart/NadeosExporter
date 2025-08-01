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
            'customerId' => hex2bin($customerId)
        ]);

        if (!$result) {
            return null;
        }

        return [
            'customer_number' => $result['customer_number'],
            'email' => $result['email'],
            'first_name' => $result['first_name'] ?? '',
            'last_name' => $result['last_name'] ?? '',
            'phone_number' => $result['phone_number'] ?? $result['phone'] ?? '',
            'company' => $result['company']
        ];
    }
}
