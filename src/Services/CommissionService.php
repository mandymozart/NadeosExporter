<?php declare(strict_types=1);

namespace NadeosData\Services;

use Shopware\Core\Defaults;
use Doctrine\DBAL\Connection;
use DateTime;
use NadeosData\Dto\{
    Commission,
    CommissionItem,
    CommissionCollection
};
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\{
    EntityRepository,
    Dbal\Common\RepositoryIterator
};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{
    RangeFilter,
    PrefixFilter,
    MultiFilter
};
use Shopware\Core\Framework\Context;
use DateTimeZone;

class CommissionService
{
    const BULK = 5000;

    public function __construct(
        private readonly Connection $databaseConnection,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityRepository      $orderRepository
    ) {}

    private function getCustomerGroups(string $group)
    {

    }

    public function getOrders(DateTime $date, string $group): \Generator
    {   
        $year   = $date->format('Y');
        $month  = $date->format('m');

        $dateFrom = new DateTime("$year-$month-01 00:00:00", new DateTimeZone('UTC'));
        $dateTo   = new DateTime("$year-$month-01 23:59:59", new DateTimeZone('UTC'));
        $dateTo->modify('last day of this month');

        $iterator = new RepositoryIterator(
            $this->orderRepository,
            Context::createDefaultContext(),
            (new Criteria)
                ->addAssociations([
                    'order',
                    'orderCustomer.customer',
                    'orderCustomer.customer.customerGroup',
                    'orderCustomer.customer.customer_group_id',
                    'orderCustomer.customer.group'
                ])
                ->addAssociation('orderCustomer.customerGroup')
                ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new RangeFilter('order.orderDateTime', [
                        RangeFilter::GTE => $dateFrom->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        RangeFilter::LTE => $dateTo->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                    ]),
                    new PrefixFilter('orderCustomer.customer.group.name', $group)
                ]))
                ->setLimit(self::BULK)
        );

        while(($result = $iterator->fetch()) !== null) {
            foreach($result->getEntities() as $order) {
                yield $order;
            };
        }
    }

    public function list(DateTime $date, string $group = null): CommissionCollection
    {
        $sqlGroup = '';
        if (false === is_null($group)) {
            $group = base64_decode($group);

            $sqlGroup = ' AND LEFT(customerGroupTrans.name, 2) = :group ';
            $sqlGroup = ' AND LOWER(LEFT(customerGroupTrans.name, 2)) = LOWER(:group) ';
        }

        $query = <<<QUERY
            WITH
                customerGroups AS (
                    SELECT
                        customerGroup.id,
                        LEFT(customerGroupTrans.name, 2)			AS groupName,
                        COALESCE(
                            JSON_EXTRACT(customerGroupTrans.custom_fields, '$.migration_nadeoscomsw5_customer_group_provisiontype'),
                            'default'
                        ) AS provisionType,
                        JSON_EXTRACT(customerGroupTrans.custom_fields, '$.migration_nadeoscomSW5_customer_group_anrede')    AS salutation,
                        JSON_EXTRACT(customerGroupTrans.custom_fields, '$.migration_nadeoscomSW5_customer_group_email')     AS email,
                        JSON_EXTRACT(customerGroupTrans.custom_fields, '$.migration_nadeoscomSW5_customer_group_plz_ort')   AS cityZip,
                        JSON_EXTRACT(customerGroupTrans.custom_fields, '$.migration_nadeoscomSW5_customer_group_vorname')   AS firstname,
                        JSON_EXTRACT(customerGroupTrans.custom_fields, '$.migration_nadeoscomSW5_customer_group_nachname')  AS lastname,
                        JSON_EXTRACT(customerGroupTrans.custom_fields, '$.migration_nadeoscomSW5_customer_group_anschrift') AS street,
                        CAST(
                            JSON_EXTRACT(customerGroupTrans.custom_fields, '$.migration_nadeoscomSW5_customer_group_provision')
                            AS UNSIGNED
                        ) AS provision
                    FROM
                        customer_group AS customerGroup
                        INNER JOIN customer_group_translation	AS customerGroupTrans 	ON customerGroup.id = customerGroupTrans.customer_group_id
                    WHERE
                        CAST( JSON_EXTRACT(customerGroupTrans.custom_fields, '$.migration_nadeoscomSW5_customer_group_provision') AS UNSIGNED) > 0
                        $sqlGroup
                        AND customerGroupTrans.language_id          = :language
                ),
                ordersNewest AS (
                    SELECT
                        MAX(auto_increment)			AS autoIncrement,
                        orders.order_number         AS orderNumber,
                        orders.order_date           AS orderDate,
                        YEAR(orders.order_date)     AS orderYear,
                        MONTH(orders.order_date)    AS orderMonth
                    FROM
                        `order` AS orders
                    WHERE
                        YEAR(orders.order_date)      = :year
                        AND MONTH(orders.order_date) = :month
                    GROUP BY
                        orders.order_number
                )
            
            SELECT
                ordersNewest.orderNumber,
                ordersNewest.orderDate,
                ordersNewest.orderYear,
                ordersNewest.orderMonth,
                ROUND(SUM(orders.amount_net), 2)	AS orderAmountSum,
            
                customerGroup.provision    AS provisionPercentage,
                ROUND(
                    SUM(orders.amount_net) * ( customerGroup.provision / 100 ),
                    2
                ) AS commisionSum,
            
                customerGroup.groupName,
                customerGroup.provisionType,
                customerGroup.salutation,
                customerGroup.email,
                customerGroup.cityZip,
                customerGroup.firstname,
                customerGroup.lastname,
                customerGroup.street
            FROM
                ordersNewest
                INNER JOIN `order`          AS orders           ON orders.order_number = ordersNewest.orderNumber AND orders.auto_increment = ordersNewest.autoIncrement
                INNER JOIN order_customer   AS ordersCustomer   ON orders.id           = ordersCustomer.order_id  AND orders.version_id = ordersCustomer.order_version_id
                INNER JOIN customer         AS customer         ON customer.id         = ordersCustomer.customer_id
                INNER JOIN customerGroups   AS customerGroup    ON customerGroup.id    = customer.customer_group_id
            GROUP BY
                customerGroup.groupName,
                customerGroup.provisionType
QUERY;

        $params = [
            'year'      => $date->format('Y'),
            'month'     => $date->format('m'),
            'language'  => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)
        ];

        if (false === is_null($group)) {
            $params['group'] = $group;
        }

        $data = $this->databaseConnection->fetchAllAssociative($query, $params);

        $lastGroup  = null;
        $commission = null;
        $collection = new CommissionCollection();
        foreach ($data as $row) {
            $currentGroup = $row['groupName'];

            if ($currentGroup !== $lastGroup) {
                if (false === is_null($commission)) {
                    $collection->add($commission);
                }

                $orderDate = new DateTime($row['orderDate']);

                $commission = new Commission(
                    $this,      // TODO: refactor
                    $row['orderNumber'],
                    $orderDate,
                    (float) $row['orderAmountSum'],
                    (float) $row['provisionPercentage'],
                    TRIM($row['provisionType'], '"'),
                    $row['groupName'],
                    TRIM($row['salutation'], '"'),
                    $row['email'],
                    TRIM($row['firstname'], '"'),
                    TRIM($row['lastname'], '"'),
                    TRIM($row['street'], '"'),
                    TRIM($row['cityZip'], '"'),
                    $this->urlGenerator->generate(
                        'frontend.nadeos.commsions-pdf',
                        [
                            'year' => $orderDate->format('Y'),
                            'month' => $orderDate->format('m'),
                            'group' => base64_encode($row['groupName'])
                        ],
                        UrlGeneratorInterface::RELATIVE_PATH
                    )
                );

                $commission->addItem(
                    // note: this is only for future purposes, when there will be multiple items
                    new CommissionItem(
                        $commission->orderAmountNetTotal,
                        $commission->commissionNetTotal,
                        $commission->orderDate
                    )
                );
            }
            else {
                // note: this is only for future purposes, when there will be multiple items
                $commission->addItem(
                    // note: this is only for future purposes, when there will be multiple items
                    new CommissionItem(
                        $commission->orderAmountNetTotal,
                        $commission->commissionNetTotal,
                        $commission->orderDate
                    )
                );
            }
        }

        if (false === is_null($commission)) {
            $collection->add($commission);
        }

        // @todo: implement hydrator
        return $collection;
    }
}