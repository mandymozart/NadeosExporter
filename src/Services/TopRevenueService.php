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
use Psr\Log\LoggerInterface;

class TopRevenueService
{
    const BULK = 1000;

    public function __construct(
        private readonly Connection $databaseConnection,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $customerRepository,
        private readonly LoggerInterface $logger
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
                    customerId: $this->sanitizeUtf8($data['customer_id']),
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

        // Debug logging using proper Shopware Monolog
        $this->logger->info('TopRevenue SQL Query Debug', [
            'sql' => $sql,
            'params' => [
                'dateFrom' => $dateFrom->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'dateTo' => $dateTo->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'limit' => $limit
            ]
        ]);

        $result = $this->databaseConnection->fetchAllAssociative($sql, $params, [
            'limit' => \PDO::PARAM_INT
        ]);

        $this->logger->info('TopRevenue Query Results', [
            'result_count' => count($result)
        ]);
        
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
            $this->logger->warning('Customer not found', ['customerId' => $customerId]);
            return null;
        }

        // Log raw customer data to identify UTF-8 issues
        $this->logger->info('Raw customer data retrieved', [
            'customerId' => $customerId,
            'raw_data' => array_map(function($value) {
                return $value === null ? 'NULL' : 'length:' . strlen((string)$value);
            }, $result)
        ]);

        // Sanitize each field and log any problematic values
        $sanitizedData = [];
        foreach (['customer_number', 'email', 'first_name', 'last_name', 'company'] as $field) {
            $rawValue = $result[$field] ?? '';
            $sanitizedValue = $this->sanitizeUtf8($rawValue);
            $sanitizedData[$field] = $sanitizedValue;
            
            // Log if sanitization changed the value significantly
            if (strlen($rawValue) > 0 && strlen($sanitizedValue) !== strlen($rawValue)) {
                $this->logger->warning('UTF-8 sanitization changed field', [
                    'field' => $field,
                    'original_length' => strlen($rawValue),
                    'sanitized_length' => strlen($sanitizedValue),
                    'customer_id' => $customerId
                ]);
            }
        }
        
        // Handle phone separately (has fallback logic)
        $phoneValue = $result['phone_number'] ?? $result['phone'] ?? '';
        $sanitizedData['phone_number'] = $this->sanitizeUtf8($phoneValue);

        // Final JSON test for this customer
        $testJson = json_encode($sanitizedData);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Customer data still not JSON-safe after sanitization', [
                'customer_id' => $customerId,
                'json_error' => json_last_error_msg(),
                'data_summary' => array_map('strlen', $sanitizedData)
            ]);
            // Return null to skip this problematic customer
            return null;
        }

        return $sanitizedData;
    }

    private function sanitizeUtf8(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        
        // More aggressive UTF-8 cleaning
        $value = (string) $value;
        
        // First, try to detect encoding and convert to UTF-8
        $encoding = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $value = mb_convert_encoding($value, 'UTF-8', $encoding);
        }
        
        // Clean invalid UTF-8 sequences
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        
        // Remove/replace problematic characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/', '', $value);
        
        // Replace common problematic characters
        $value = str_replace(['\\', '"', "\r", "\n", "\t"], ['', '', ' ', ' ', ' '], $value);
        
        // Final JSON-safe check
        $testJson = json_encode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If still problematic, use only ASCII
            $value = iconv('UTF-8', 'ASCII//IGNORE', $value);
        }
        
        return trim($value);
    }
}
