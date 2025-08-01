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

    public function getTopRevenueData(DateTime $dateFrom, DateTime $dateTo, int $limit = 50, ?string $group = null): TopRevenueCollection
    {
        $collection = new TopRevenueCollection();
        
        // Get revenue data grouped by customer using direct SQL for better performance
        $revenueData = $this->getCustomerRevenueData($dateFrom, $dateTo, $limit, $group);
        
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
                    customerNumber: $customerDetails['customer_number'] ?? '',
                    groupName: $data['group_name'] ?? '',
                    groupFirstname: $data['group_firstname'] ?? '',
                    groupLastname: $data['group_lastname'] ?? ''
                );
                
                $collection->add($item);
                $rank++;
            }
        }
        
        return $collection;
    }

    private function getCustomerRevenueData(DateTime $dateFrom, DateTime $dateTo, int $limit, ?string $group = null): array
    {
        // Use a subquery approach to ensure each order is counted only once per customer
        // This avoids issues with multiple order_customer records per order
        // Filter based on Shopware transaction states - only include paid/authorized orders
        
        // Prepare group filtering (like CommissionService)
        $groupFilter = '';
        if ($group !== null) {
            $group = base64_decode($group);
            $groupFilter = ' AND LOWER(LEFT(cgt.name, 2)) = LOWER(:group) ';
        }
        
        $sql = "
            SELECT 
                customer_orders.customer_id,
                SUM(customer_orders.amount_net) as total_revenue,
                customer_orders.group_name,
                customer_orders.group_firstname,
                customer_orders.group_lastname
            FROM (
                SELECT DISTINCT
                    oc.customer_id,
                    o.id as order_id,
                    o.amount_net,
                    LEFT(cgt.name, 2) AS group_name,
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(cgt.custom_fields, '$.migration_nadeoscomSW5_customer_group_vorname')),
                        ''
                    ) AS group_firstname,
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(cgt.custom_fields, '$.migration_nadeoscomSW5_customer_group_nachname')),
                        ''
                    ) AS group_lastname
                FROM `order` o
                INNER JOIN order_customer oc ON o.id = oc.order_id AND oc.version_id = o.version_id
                INNER JOIN order_transaction ot ON o.id = ot.order_id AND ot.version_id = o.version_id
                INNER JOIN customer c ON c.id = oc.customer_id
                INNER JOIN customer_group cg ON cg.id = c.customer_group_id
                INNER JOIN customer_group_translation cgt ON cgt.customer_group_id = cg.id
                LEFT JOIN state_machine_state sms ON ot.state_id = sms.id
                WHERE o.order_date_time >= :dateFrom 
                    AND o.order_date_time <= :dateTo
                    AND o.version_id = :versionId
                    AND cgt.language_id = :languageId
                    AND (sms.technical_name IS NULL OR sms.technical_name IN ('paid', 'paid_partially', 'authorized'))
                    {$groupFilter}
            ) customer_orders
            GROUP BY customer_orders.customer_id, customer_orders.group_name, customer_orders.group_firstname, customer_orders.group_lastname
            ORDER BY total_revenue DESC
            LIMIT :limit
        ";

        $params = [
            'dateFrom' => $dateFrom->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'dateTo' => $dateTo->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'versionId' => hex2bin(Defaults::LIVE_VERSION),
            'languageId' => hex2bin(Defaults::LANGUAGE_SYSTEM),
            'limit' => $limit
        ];
        
        // Add group parameter if filtering by commission recipient
        if ($group !== null) {
            $params['group'] = $group;
        }

        $result = $this->databaseConnection->fetchAllAssociative($sql, $params, [
            'limit' => \PDO::PARAM_INT
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
            return null;
        }

        // Sanitize each field
        $sanitizedData = [];
        foreach (['customer_number', 'email', 'first_name', 'last_name', 'company'] as $field) {
            $rawValue = $result[$field] ?? '';
            $sanitizedData[$field] = $this->sanitizeUtf8($rawValue);
        }
        
        // Handle phone separately (has fallback logic)
        $phoneValue = $result['phone_number'] ?? $result['phone'] ?? '';
        $sanitizedData['phone_number'] = $this->sanitizeUtf8($phoneValue);

        // Final JSON test for this customer
        $testJson = json_encode($sanitizedData);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Customer data not JSON-safe after sanitization', [
                'customer_id' => bin2hex($customerId),
                'json_error' => json_last_error_msg()
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
            // If still problematic, use only ASCII (handle iconv failure)
            $asciiValue = iconv('UTF-8', 'ASCII//IGNORE', $value);
            $value = $asciiValue !== false ? $asciiValue : '';
        }
        
        return trim($value);
    }
}
