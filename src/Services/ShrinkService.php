<?php declare(strict_types=1);

namespace NadeosData\Services;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{
    RangeFilter,
    EqualsAnyFilter
};
use League\Flysystem\Filesystem;

use NadeosData\Extractors\{
    ExtractorInterface,
    OrderExtractor    
};
use Shopware\Core\Defaults;
use Doctrine\DBAL\Connection;
use DateTime;

class ShrinkService
{
    const QUERY = <<<SQL
        WITH
            ordersNewest AS (
                SELECT
                    MAX(auto_increment)			AS autoIncrement,
                    orders.order_number         AS orderNumber
                FROM
                    `order` AS orders
                WHERE
                    YEAR(orders.order_date)      = :year
                    AND MONTH(orders.order_date) = :month
                    -- AND DAY(orders.order_date)   = 1
                GROUP BY
                    orders.order_number
            ),
            ordersFiltered as (
                SELECT
                    orders.order_number                             AS orderNumber,
                    YEAR(orders.order_date)                         AS orderYear,
                    MONTH(orders.order_date)                        AS orderMonth,
                    order_date,
                    JSON_UNQUOTE(JSON_EXTRACT(item.payload, '$.productNumber'))   AS productNumber,
                    LEFT(
                        JSON_UNQUOTE(JSON_EXTRACT(item.payload, '$.productNumber')),
                        LENGTH(JSON_UNQUOTE(JSON_EXTRACT(item.payload, '$.productNumber'))) - 3
                    )                                               AS productNumberSuffix,
                    REPLACE (
                        REPLACE (
                            REPLACE (item.label, 'Tester', '')
                            , 'Austausch'
                            , ''
                        ),
                        '-',
                        ''
                    )                                               AS label,
                    item.quantity                                   AS amount
                FROM
                    ordersNewest
                    INNER JOIN `order`         AS orders  ON ordersNewest.autoIncrement = orders.auto_increment
                    INNER JOIN order_line_item AS item    ON item.order_id = orders.id
                                                                and item.order_version_id = orders.version_id
            )

SQL;

    public function __construct(
        private readonly Connection $databaseConnection
    ) {}

    public function listByProduct(DateTime $date): array
    {
        $query = self::QUERY;

        $query .= <<<SQL
            SELECT
                orderYear     AS `year`,
                orderMonth    AS `month`,
                GROUP_CONCAT(DISTINCT orderNumber) 		AS orderNumbers,
                GROUP_CONCAT(DISTInCT order_date)	    AS orderDates,
                productNumber 					AS productNumber,
                productNumberSuffix,
                label							AS `name`,
                SUM(amount)   					AS amount,
                RIGHT(productNumber, 2) IN ('TE', 'AU')	AS isRelevant
            FROM
                ordersFiltered
            GROUP BY
                productNumber,
                RIGHT(productNumber, 2) IN ('TE', 'AU')
            ORDER BY
                -- order_date,
                SUM(amount) DESC
SQL;

        $data = $this->databaseConnection->fetchAllAssociative($query, [
            'year'  => $date->format('Y'),
            'month' => $date->format('m')
        ]);

        return $data;
    }

    public function listRelevantProducts(DateTime $date): array
    {
        $query = self::QUERY;
        $query .= <<<SQL
            SELECT
                -- orderYear     AS `year`,
                -- orderMonth    AS `month`,
                -- GROUP_CONCAT(DISTINCT orderNumber) 		AS orderNumbers,
                -- GROUP_CONCAT(DISTINCT order_date)	    AS orderDates,
                -- productNumber 					        AS productNumber,
                productNumberSuffix,
                label							        AS `name`,
                SUM(amount)   					        AS amount
            FROM
                ordersFiltered
            WHERE
                RIGHT(productNumber, 2) IN ('TE', 'AU')
            GROUP BY
                productNumberSuffix
            ORDER BY
                SUM(amount)
SQL;

        $data = $this->databaseConnection->fetchAllAssociative($query, [
            'year'  => $date->format('Y'),
            'month' => $date->format('m')
        ]);

        return $data;
    }

    public function list(DateTime $date): array
    {
        $query = self::QUERY;
        $query .= <<<SQL
            SELECT
                orderYear     AS `year`,
                orderMonth    AS `month`,
                productNumberSuffix                     AS product_number,
                label							        AS label,
                SUM(amount)   					        AS amount
            FROM
                ordersFiltered
            WHERE
                RIGHT(productNumber, 2) IN ('TE', 'AU')
            GROUP BY
                productNumberSuffix
            ORDER BY
                SUM(amount)
SQL;

        $data = $this->databaseConnection->fetchAllAssociative($query, [
            'year'  => $date->format('Y'),
            'month' => $date->format('m'),
        ]);

        return $data;
    }
}